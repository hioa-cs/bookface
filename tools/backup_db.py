#!/usr/bin/env python3

# VERSION 0.4

import os
import json
import argparse
import shutil
import binascii
import base64
import re
from datetime import datetime, date, time
from decimal import Decimal
from pathlib import Path
from typing import Any, Dict, Optional, List, Iterable, Tuple

import psycopg2


DB_CONFIG: Dict[str, str] = {
    "dbname": "bf",
    "user": "bfuser",
    "password": "",
    "host": "localhost",
    "port": "5433",
}

_HEX_RE = re.compile(r"^[0-9a-fA-F]+$")


def create_backup_folder(output_dir: Optional[str] = None) -> str:
    if output_dir:
        backup_folder = output_dir
    else:
        backup_folder = f"backup_{datetime.now().strftime('%Y%m%d_%H%M%S')}"
    os.makedirs(backup_folder, exist_ok=True)
    os.makedirs(os.path.join(backup_folder, "images"), exist_ok=True)
    print(f"Backup folder created: {backup_folder}")
    return backup_folder


def copy_images(source_dir: str, backup_folder: str) -> None:
    src = Path(source_dir)
    dst = Path(backup_folder) / "images"
    dst.mkdir(parents=True, exist_ok=True)

    for filename in src.iterdir():
        if filename.is_file():
            shutil.copy2(str(filename), str(dst / filename.name))
            print(f"Copied: {filename} -> {dst / filename.name}")


def json_default(v: Any) -> Any:
    if isinstance(v, (datetime, date, time)):
        return v.isoformat()
    if isinstance(v, Decimal):
        return str(v)
    if isinstance(v, (bytes, bytearray, memoryview)):
        b = bytes(v)
        return {"__bytes_b64__": base64.b64encode(b).decode("ascii")}
    return str(v)


def qname_public(table_name: str) -> str:
    return table_name if "." in table_name else f"public.{table_name}"


def stream_table_to_json(conn, table_name: str, output_file: str, batch_size: int = 1000) -> None:
    cur = conn.cursor()
    cur.itersize = batch_size

    fq = qname_public(table_name)
    cur.execute(f"SELECT * FROM {fq}")

    if cur.description is None:
        cur.close()
        raise RuntimeError(f"Query returned no result-set for {fq} (cursor.description is None).")

    colnames: List[str] = [desc[0] for desc in cur.description]

    with open(output_file, "w", encoding="utf-8") as f:
        f.write("[\n")
        first = True

        while True:
            rows = cur.fetchmany(batch_size)
            if not rows:
                break

            for row in rows:
                obj = dict(zip(colnames, row))
                if not first:
                    f.write(",\n")
                json.dump(obj, f, ensure_ascii=False, default=json_default)
                first = False

        f.write("\n]\n")

    cur.close()
    print(f"Data from {fq} saved to {output_file}")


def looks_like_jpeg(data: bytes) -> bool:
    return len(data) >= 2 and data[0] == 0xFF and data[1] == 0xD8


def _maybe_decode_hex_text(s: str) -> Optional[bytes]:
    s = s.strip()
    if s.startswith("\\\\x"):
        s = s[1:]
    if s.startswith("\\x") or s.startswith("0x"):
        s = s[2:]

    if len(s) >= 8 and (len(s) % 2 == 0) and _HEX_RE.match(s):
        try:
            return bytes.fromhex(s)
        except ValueError:
            return None
    return None


def bytes_from_db_value(v: Any) -> Optional[bytes]:
    """
    Convert picture value into real bytes.
    DB stores ASCII hex inside bytea, so decode twice when needed.
    """
    if v is None:
        return None

    b: Optional[bytes] = None
    if isinstance(v, memoryview):
        b = bytes(v)
    elif isinstance(v, (bytes, bytearray)):
        b = bytes(v)
    elif isinstance(v, str):
        decoded = _maybe_decode_hex_text(v)
        if decoded is not None:
            return decoded
        if v.startswith("b64:"):
            try:
                return base64.b64decode(v[4:], validate=True)
            except Exception:
                return None
        return None
    else:
        return None

    if b and looks_like_jpeg(b):
        return b

    try:
        s = b.decode("ascii")
    except Exception:
        return b

    decoded2 = _maybe_decode_hex_text(s)
    if decoded2 is not None:
        return decoded2

    return b


def chunked(seq: List[str], n: int) -> Iterable[List[str]]:
    for i in range(0, len(seq), n):
        yield seq[i : i + n]


def export_images_two_phase(
    conn,
    backup_folder: str,
    *,
    id_fetch_batch: int = 5000,
    blob_batch: int = 1,
    progress_every: int = 25,
) -> None:
    img_dir = Path(backup_folder) / "images"
    img_dir.mkdir(parents=True, exist_ok=True)

    print("Exporting pictures: phase A (fetch picture IDs)...")
    id_cur = conn.cursor()
    id_cur.itersize = id_fetch_batch
    id_cur.execute("SELECT pictureid FROM public.pictures")

    ids: List[str] = []
    while True:
        rows = id_cur.fetchmany(id_fetch_batch)
        if not rows:
            break
        ids.extend([r[0] for r in rows if r and r[0]])
    id_cur.close()

    print(f"Collected {len(ids)} picture IDs.")
    print(f"Exporting pictures: phase B (fetch blobs in batches of {blob_batch})...")

    out_count = 0
    warn_count = 0

    blob_cur = conn.cursor()

    def out_name(pid: str) -> str:
        # Keep existing behavior (adds .jpg if missing)
        p = str(pid)
        low = p.lower()
        if not (low.endswith(".jpg") or low.endswith(".jpeg")):
            p = p + ".jpg"
        return p

    if blob_batch <= 1:
        stmt = "SELECT picture FROM public.pictures WHERE pictureid = %s"
        for pid in ids:
            out_path = img_dir / out_name(pid)
            if out_path.exists():
                continue

            blob_cur.execute(stmt, (pid,))
            row = blob_cur.fetchone()
            if not row:
                warn_count += 1
                continue

            data = bytes_from_db_value(row[0])
            if not data:
                warn_count += 1
                continue

            if not looks_like_jpeg(data):
                warn_count += 1
                head = data[:16].hex()
                print(f"  WARNING: picture {out_path.name} not JPEG magic (first16={head})")

            with open(out_path, "wb") as f:
                f.write(data)

            out_count += 1
            if out_count % progress_every == 0:
                print(f"  exported {out_count}/{len(ids)} images...")
    else:
        stmt = "SELECT pictureid, picture FROM public.pictures WHERE pictureid = ANY(%s)"
        for pid_batch in chunked(ids, blob_batch):
            blob_cur.execute(stmt, (pid_batch,))
            for pid, blob in blob_cur.fetchall():
                out_path = img_dir / out_name(pid)
                if out_path.exists():
                    continue

                data = bytes_from_db_value(blob)
                if not data:
                    warn_count += 1
                    continue

                if not looks_like_jpeg(data):
                    warn_count += 1
                    head = data[:16].hex()
                    print(f"  WARNING: picture {out_path.name} not JPEG magic (first16={head})")

                with open(out_path, "wb") as f:
                    f.write(data)

                out_count += 1
                if out_count % progress_every == 0:
                    print(f"  exported {out_count}/{len(ids)} images...")

    blob_cur.close()
    print(f"Exported {out_count} images to {img_dir} (warnings: {warn_count})")


# ---------------------------
# NEW: richer meta.json
# ---------------------------

def _fetchall_dict(cur) -> List[Dict[str, Any]]:
    cols = [d[0] for d in cur.description]
    return [dict(zip(cols, r)) for r in cur.fetchall()]


def create_meta_file(cursor, backup_folder: str) -> None:
    print("Creating enhanced meta.json (schema + constraints + indexes + extensions)...")

    # server info
    cursor.execute("SELECT version() AS version;")
    server_version = cursor.fetchone()[0]
    cursor.execute("SELECT current_schema() AS current_schema;")
    current_schema = cursor.fetchone()[0]

    # extensions (needed for gen_random_uuid etc.)
    cursor.execute("""
        SELECT extname, extversion
        FROM pg_extension
        ORDER BY extname
    """)
    extensions = [{"name": r[0], "version": r[1]} for r in cursor.fetchall()]

    # tables
    cursor.execute(
        """
        SELECT table_name
        FROM information_schema.tables
        WHERE table_schema = 'public'
          AND table_type = 'BASE TABLE'
        ORDER BY table_name
        """
    )
    tables = [row[0] for row in cursor.fetchall()]

    table_details: Dict[str, Any] = {}

    for table in tables:
        # columns
        cursor.execute(
            """
            SELECT column_name, data_type, is_nullable, column_default
            FROM information_schema.columns
            WHERE table_schema = 'public'
              AND table_name = %s
            ORDER BY ordinal_position
            """,
            (table,),
        )
        columns = [
            {"name": r[0], "type": r[1], "nullable": r[2], "default": r[3]}
            for r in cursor.fetchall()
        ]

        # constraints (PK/UNIQUE/FK/CHECK)
        cursor.execute(
            """
            SELECT
              c.conname AS name,
              c.contype AS type,
              pg_get_constraintdef(c.oid, true) AS definition
            FROM pg_constraint c
            JOIN pg_class t ON t.oid = c.conrelid
            JOIN pg_namespace n ON n.oid = t.relnamespace
            WHERE n.nspname = 'public'
              AND t.relname = %s
            ORDER BY c.contype, c.conname
            """,
            (table,),
        )
        constraints = _fetchall_dict(cursor)

        # indexes (includes unique indexes)
        cursor.execute(
            """
            SELECT
              i.relname AS name,
              pg_get_indexdef(ix.indexrelid) AS definition,
              ix.indisunique AS is_unique,
              ix.indisprimary AS is_primary
            FROM pg_index ix
            JOIN pg_class t ON t.oid = ix.indrelid
            JOIN pg_class i ON i.oid = ix.indexrelid
            JOIN pg_namespace n ON n.oid = t.relnamespace
            WHERE n.nspname = 'public'
              AND t.relname = %s
            ORDER BY i.relname
            """,
            (table,),
        )
        indexes = _fetchall_dict(cursor)

        # best-effort CREATE TABLE (from columns + defaults + nullability)
        # We store it because it's convenient for restore; it’s not the only source of truth.
        coldefs = []
        for c in columns:
            coldef = f'"{c["name"]}" {c["type"]}'
            if c["nullable"] == "NO":
                coldef += " NOT NULL"
            if c["default"]:
                coldef += f' DEFAULT {c["default"]}'
            coldefs.append(coldef)
        create_table_sql = f'CREATE TABLE IF NOT EXISTS public."{table}" ({", ".join(coldefs)});'

        table_details[table] = {
            "columns": columns,
            "create_table_sql": create_table_sql,
            "constraints": constraints,
            "indexes": indexes,
        }

    # basic grants snapshot for the app user (optional; restore can re-grant)
    # We record privileges as seen by Postgres/YSQL.
    cursor.execute(
        """
        SELECT table_name,
               privilege_type,
               grantee
        FROM information_schema.table_privileges
        WHERE table_schema = 'public'
          AND grantee = %s
        ORDER BY table_name, privilege_type
        """,
        (DB_CONFIG["user"],),
    )
    grants = _fetchall_dict(cursor)

    meta_data = {
        "database": DB_CONFIG["dbname"],
        "user": DB_CONFIG["user"],
        "host": DB_CONFIG["host"],
        "port": DB_CONFIG["port"],
        "timestamp": datetime.now().isoformat(),
        "server": {"version": server_version, "schema": current_schema},
        "extensions": extensions,
        "tables": table_details,
        "grants": grants,
    }

    meta_file = os.path.join(backup_folder, "meta.json")
    with open(meta_file, "w", encoding="utf-8") as f:
        json.dump(meta_data, f, indent=4, ensure_ascii=False, default=json_default)

    print(f"Enhanced meta.json saved to {meta_file}")


def main() -> None:
    parser = argparse.ArgumentParser(description="Backup Bookface DB to files (Yugabyte-friendly).")
    parser.add_argument("--local-images", type=str,
                        help="Copy images from this local folder into the backup (instead of exporting from DB).")
    parser.add_argument("--output-dir", type=str,
                        help="Directory to store the backup (default: timestamped folder).")
    parser.add_argument("--db-user", type=str, help="Use a different user name than bfuser.")
    parser.add_argument("--db-host", type=str, help="Override DB host (default: localhost).")
    parser.add_argument("--db-port", type=str, help="Override DB port (default: 5433).")
    parser.add_argument("--db-password", type=str, help="Override DB password (default: empty).")
    parser.add_argument("--batch-size", type=int, default=1000, help="Batch size for streaming table dumps.")
    parser.add_argument("--pictures-id-batch", type=int, default=5000, help="Batch size for fetching picture IDs.")
    parser.add_argument("--pictures-blob-batch", type=int, default=1, help="Images per query when fetching blobs.")
    parser.add_argument("--pictures-progress-every", type=int, default=25, help="Progress print interval.")
    args = parser.parse_args()

    if args.db_user:
        DB_CONFIG["user"] = args.db_user
    if args.db_host:
        DB_CONFIG["host"] = args.db_host
    if args.db_port:
        DB_CONFIG["port"] = args.db_port
    if args.db_password is not None:
        DB_CONFIG["password"] = args.db_password

    backup_folder = create_backup_folder(args.output_dir)

    conn = None
    cursor = None
    try:
        conn = psycopg2.connect(**DB_CONFIG)
        cursor = conn.cursor()
        cursor.execute("SET statement_timeout = 0")
        cursor.execute("SET idle_in_transaction_session_timeout = 0")

        create_meta_file(cursor, backup_folder)

        stream_table_to_json(conn, "users", os.path.join(backup_folder, "users.json"), batch_size=args.batch_size)
        stream_table_to_json(conn, "posts", os.path.join(backup_folder, "posts.json"), batch_size=args.batch_size)
        stream_table_to_json(conn, "comments", os.path.join(backup_folder, "comments.json"), batch_size=args.batch_size)

        if args.local_images:
            copy_images(args.local_images, backup_folder)
        else:
            export_images_two_phase(
                conn,
                backup_folder,
                id_fetch_batch=args.pictures_id_batch,
                blob_batch=args.pictures_blob_batch,
                progress_every=args.pictures_progress_every,
            )

        print("Backup completed successfully.")

    except Exception as e:
        print(f"Error during backup: {e}")
        raise
    finally:
        if cursor:
            cursor.close()
        if conn:
            conn.close()


if __name__ == "__main__":
    main()
