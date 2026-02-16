#!/usr/bin/env python3

# VERSION 0.4

import os
import json
import argparse
import shutil
import re
from pathlib import Path
from typing import Any, Dict, Optional, List, Tuple

import psycopg2


_HEX_RE = re.compile(r"^[0-9a-fA-F]+$")


def log(msg: str, verbose: bool) -> None:
    if verbose:
        print(msg)


def admin_connect(host: str, port: str, admin_user: str, admin_db: str, admin_password: str = ""):
    return psycopg2.connect(dbname=admin_db, user=admin_user, password=admin_password, host=host, port=port)


def user_connect(host: str, port: str, dbname: str, user: str, password: str = ""):
    return psycopg2.connect(dbname=dbname, user=user, password=password, host=host, port=port)


def copy_images(backup_images_dir: str, target_location: str) -> None:
    os.makedirs(target_location, exist_ok=True)
    for filename in os.listdir(backup_images_dir):
        src = os.path.join(backup_images_dir, filename)
        dst = os.path.join(target_location, filename)
        shutil.copy2(src, dst)
        print(f"Copied: {src} -> {dst}")


def looks_like_jpeg(b: bytes) -> bool:
    return len(b) >= 2 and b[0] == 0xFF and b[1] == 0xD8


def normalize_hex_text(s: str) -> Optional[str]:
    s = s.strip()
    if s.startswith("\\\\x"):
        s = s[1:]
    if s.startswith("\\x") or s.startswith("0x"):
        s = s[2:]
    s = "".join(s.split())
    if len(s) == 0 or (len(s) % 2) != 0:
        return None
    if not _HEX_RE.match(s):
        return None
    return s.lower()


def image_file_to_app_expected_payload(path: Path) -> bytes:
    """
    Store exactly what the PHP expects:
      pictures.picture (bytea) contains ASCII hex of the JPEG bytes.
    """
    raw = path.read_bytes()

    # If it's a real JPEG, convert to hex text.
    if looks_like_jpeg(raw):
        return raw.hex().encode("ascii")

    # If it's already hex text, normalize it and store as ASCII.
    try:
        s = raw.decode("ascii")
    except UnicodeDecodeError:
        raise ValueError(f"{path.name}: not JPEG bytes and not ASCII hex text")

    norm = normalize_hex_text(s)
    if not norm:
        raise ValueError(f"{path.name}: ASCII data is not valid hex text")
    return norm.encode("ascii")


def ensure_role_and_db(admin_cur, dbname: str, app_user: str, verbose: bool) -> None:
    """
    Create role/user and database if missing (Postgres/YSQL).
    """
    log(f"Ensuring role {app_user} exists...", verbose)
    admin_cur.execute("SELECT 1 FROM pg_roles WHERE rolname = %s;", (app_user,))
    if not admin_cur.fetchone():
        admin_cur.execute(f'CREATE ROLE "{app_user}" LOGIN;')
        log(f"Created role {app_user}", verbose)

    log(f"Ensuring database {dbname} exists...", verbose)
    admin_cur.execute("SELECT 1 FROM pg_database WHERE datname = %s;", (dbname,))
    if not admin_cur.fetchone():
        admin_cur.execute(f'CREATE DATABASE "{dbname}" OWNER "{app_user}";')
        log(f"Created database {dbname}", verbose)

    admin_cur.execute(f'GRANT ALL PRIVILEGES ON DATABASE "{dbname}" TO "{app_user}";')
    log(f"Granted DB privileges on {dbname} to {app_user}", verbose)


def apply_extensions(conn, extensions: List[dict], verbose: bool) -> None:
    if not extensions:
        return
    cur = conn.cursor()
    for ext in extensions:
        name = ext["name"]
        # version pinning is optional; CREATE EXTENSION ... VERSION may not be supported/needed
        sql = f'CREATE EXTENSION IF NOT EXISTS "{name}";'
        log(sql, verbose)
        cur.execute(sql)
    cur.close()
    conn.commit()


def apply_tables(conn, tables_meta: Dict[str, Any], verbose: bool) -> None:
    cur = conn.cursor()
    for table, details in tables_meta.items():
        sql = details.get("create_table_sql")
        if not sql:
            raise RuntimeError(f"meta.json missing create_table_sql for table {table}")
        log(sql, verbose)
        cur.execute(sql)
    cur.close()
    conn.commit()


def apply_constraints(conn, tables_meta: Dict[str, Any], verbose: bool) -> None:
    """
    Apply constraints after tables exist.
    We use the saved pg_get_constraintdef output.
    """
    cur = conn.cursor()

    # Apply PK/UNIQUE/CHECK/FK — order usually doesn’t matter, but FK needs referenced tables (which exist now)
    for table, details in tables_meta.items():
        for c in details.get("constraints", []):
            cname = c["name"]
            cdef = c["definition"]
            # Add constraint if not exists: Postgres doesn't support IF NOT EXISTS for ADD CONSTRAINT.
            # So we check pg_constraint first.
            cur.execute(
                """
                SELECT 1
                FROM pg_constraint pc
                JOIN pg_class t ON t.oid = pc.conrelid
                JOIN pg_namespace n ON n.oid = t.relnamespace
                WHERE n.nspname = 'public'
                  AND t.relname = %s
                  AND pc.conname = %s
                """,
                (table, cname),
            )
            if cur.fetchone():
                continue

            sql = f'ALTER TABLE public."{table}" ADD CONSTRAINT "{cname}" {cdef};'
            log(sql, verbose)
            cur.execute(sql)

    cur.close()
    conn.commit()


def apply_indexes(conn, tables_meta: Dict[str, Any], verbose: bool) -> None:
    """
    Apply indexes after constraints.
    We use pg_get_indexdef output; add IF NOT EXISTS when possible.
    """
    cur = conn.cursor()

    for table, details in tables_meta.items():
        for idx in details.get("indexes", []):
            name = idx["name"]
            definition = idx["definition"]

            # Check if index exists
            cur.execute(
                """
                SELECT 1
                FROM pg_class c
                JOIN pg_namespace n ON n.oid = c.relnamespace
                WHERE n.nspname = 'public'
                  AND c.relkind = 'i'
                  AND c.relname = %s
                """,
                (name,),
            )
            if cur.fetchone():
                continue

            # pg_get_indexdef returns "CREATE INDEX ..." (or "CREATE UNIQUE INDEX ...")
            log(definition, verbose)
            cur.execute(definition)

    cur.close()
    conn.commit()


def apply_grants(conn, grants: List[dict], verbose: bool) -> None:
    """
    Reapply table grants (best effort).
    info_schema.table_privileges lists individual privileges; we replay them.
    """
    if not grants:
        return
    cur = conn.cursor()
    for g in grants:
        table = g["table_name"]
        priv = g["privilege_type"]
        grantee = g["grantee"]
        sql = f'GRANT {priv} ON TABLE public."{table}" TO "{grantee}";'
        log(sql, verbose)
        try:
            cur.execute(sql)
        except Exception as e:
            # best effort; grants can fail if grantee doesn't exist or Yugabyte differs
            log(f"WARNING: grant failed: {sql} ({e})", verbose)
            conn.rollback()
            # keep going
            cur = conn.cursor()
    cur.close()
    conn.commit()


def restore_table(conn, table_name: str, file_path: str, verbose: bool, batch: int = 500) -> int:
    with open(file_path, "r", encoding="utf-8") as f:
        data = json.load(f)
    if not data:
        return 0

    cols = list(data[0].keys())
    placeholders = ", ".join([f"%({c})s" for c in cols])
    collist = ", ".join([f'"{c}"' for c in cols])

    sql = f'INSERT INTO public."{table_name}" ({collist}) VALUES ({placeholders})'
    cur = conn.cursor()

    inserted = 0
    chunk: List[dict] = []
    for row in data:
        chunk.append(row)
        if len(chunk) >= batch:
            cur.executemany(sql, chunk)
            inserted += len(chunk)
            chunk.clear()

    if chunk:
        cur.executemany(sql, chunk)
        inserted += len(chunk)

    cur.close()
    conn.commit()
    log(f"Restored {inserted} rows into {table_name}", verbose)
    return inserted


def restore_images_to_db(conn, images_dir: str, verbose: bool, progress_every: int = 50) -> int:
    """
    Restore images into pictures with app-expected format (ASCII hex in bytea),
    and preserve pictureid EXACTLY as filename (no assumptions / no edits).
    """
    files = sorted([p for p in Path(images_dir).iterdir() if p.is_file()])
    if not files:
        return 0

    cur = conn.cursor()

    # With constraints/indexes restored, ON CONFLICT should be available if pictureid is unique/PK.
    # But to be safe even if it isn't, we do DELETE+INSERT for idempotence.
    delete_sql = 'DELETE FROM public."pictures" WHERE "pictureid" = %s'
    insert_sql = 'INSERT INTO public."pictures" ("pictureid", "picture") VALUES (%s, %s)'

    count = 0
    for p in files:
        pictureid = p.name
        payload = image_file_to_app_expected_payload(p)  # ASCII hex bytes

        cur.execute(delete_sql, (pictureid,))
        cur.execute(insert_sql, (pictureid, psycopg2.Binary(payload)))

        count += 1
        if count % progress_every == 0:
            log(f"  restored {count}/{len(files)} images...", verbose)

    cur.close()
    conn.commit()
    log(f"Restored {count} images into pictures.", verbose)
    return count


def main() -> None:
    ap = argparse.ArgumentParser(description="Restore Bookface from backup (meta.json-driven, self-sufficient).")
    ap.add_argument("--from-source", required=True, help="Backup directory (meta.json, *.json, images/).")

    ap.add_argument("--admin-user", required=True, help="Admin user for DB/user creation (e.g. yugabyte).")
    ap.add_argument("--admin-db", default="yugabyte", help="Admin database to connect to (default: yugabyte).")
    ap.add_argument("--admin-password", default="", help="Admin password (if needed).")

    ap.add_argument("--host", default=None, help="Override host (default: meta.json host).")
    ap.add_argument("--port", default=None, help="Override port (default: meta.json port).")

    ap.add_argument("--app-password", default="", help="Password for app user (bfuser) if used.")
    ap.add_argument("--no-create", action="store_true", help="Skip DB/user/schema creation; just load data.")
    ap.add_argument("--local-images", help="Copy images here instead of inserting into DB.")

    ap.add_argument("--batch", type=int, default=500, help="Batch size for JSON table inserts.")
    ap.add_argument("--progress-every", type=int, default=50, help="Progress interval for images.")
    ap.add_argument("-v", "--verbose", action="store_true")
    args = ap.parse_args()

    backup_dir = args.from_source
    meta_path = os.path.join(backup_dir, "meta.json")
    if not os.path.exists(meta_path):
        raise SystemExit(f"meta.json not found in {backup_dir}")

    meta = json.loads(Path(meta_path).read_text(encoding="utf-8"))
    host = args.host or meta.get("host") or "localhost"
    port = str(args.port or meta.get("port") or "5433")

    dbname = meta["database"]
    app_user = meta["user"]
    extensions = meta.get("extensions", [])
    tables_meta = meta.get("tables", {})
    grants = meta.get("grants", [])

    verbose = args.verbose

    # 1) Admin phase: create role+db
    if not args.no_create:
        log(f"[admin] Connecting {args.admin_user}@{host}:{port}/{args.admin_db}", verbose)
        admin_conn = admin_connect(host, port, args.admin_user, args.admin_db, args.admin_password)
        admin_conn.autocommit = True
        admin_cur = admin_conn.cursor()
        try:
            ensure_role_and_db(admin_cur, dbname, app_user, verbose)
        finally:
            admin_cur.close()
            admin_conn.close()

        # 2) Schema phase (as app user)
        log(f"[schema] Connecting {app_user}@{host}:{port}/{dbname}", verbose)
        schema_conn = user_connect(host, port, dbname, app_user, args.app_password)
        try:
            cur = schema_conn.cursor()
            cur.execute("SET statement_timeout = 0")
            cur.execute("SET idle_in_transaction_session_timeout = 0")
            cur.close()
            schema_conn.commit()

            apply_extensions(schema_conn, extensions, verbose)
            apply_tables(schema_conn, tables_meta, verbose)
            apply_constraints(schema_conn, tables_meta, verbose)
            apply_indexes(schema_conn, tables_meta, verbose)
            apply_grants(schema_conn, grants, verbose)
        finally:
            schema_conn.close()

    # 3) Data phase
    log(f"[data] Connecting {app_user}@{host}:{port}/{dbname}", verbose)
    conn = user_connect(host, port, dbname, app_user, args.app_password)
    try:
        cur = conn.cursor()
        cur.execute("SET statement_timeout = 0")
        cur.execute("SET idle_in_transaction_session_timeout = 0")
        cur.close()
        conn.commit()

        for t in ["users", "posts", "comments"]:
            fp = os.path.join(backup_dir, f"{t}.json")
            if os.path.exists(fp):
                restore_table(conn, t, fp, verbose, batch=args.batch)

        images_dir = os.path.join(backup_dir, "images")
        if os.path.exists(images_dir):
            if args.local_images:
                print("Using local storage for images, copying to " + args.local_images)
                copy_images(images_dir, args.local_images)
            else:
                restore_images_to_db(conn, images_dir, verbose, progress_every=args.progress_every)

        print("Restore completed successfully.")

    finally:
        conn.close()


if __name__ == "__main__":
    main()
