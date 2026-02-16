# Backup and Restore Instructions

This document describes the current usage of:

- `tools/backup_db.py`
- `tools/restore_db.py`

These scripts are designed for a Postgres-compatible database (including Yugabyte YSQL).

## Prerequisites

- Python 3
- `psycopg2` installed

Example:

```bash
pip3 install psycopg2
```

You also need network access to the target database host/port.

## Backup

`backup_db.py` creates a backup directory containing:

- `meta.json` (DB metadata, schema details, constraints, indexes, grants)
- `users.json`
- `posts.json`
- `comments.json`
- `images/` (copied from local folder or exported from `pictures` table)

### Syntax

```bash
python3 tools/backup_db.py [options]
```

### Options

- `--local-images <path>`: Copy images from a local folder instead of exporting image blobs from DB.
- `--output-dir <path>`: Output backup directory. If omitted, a timestamped directory is created.
- `--db-user <user>`: Override DB user (default is `bfuser`).
- `--db-host <host>`: Override DB host (default is `localhost`).
- `--db-port <port>`: Override DB port (default is `5433`).
- `--db-password <password>`: Override DB password (default is empty).
- `--batch-size <n>`: Batch size for table JSON export (default: `1000`).
- `--pictures-id-batch <n>`: Batch size when collecting `pictureid` values (default: `5000`).
- `--pictures-blob-batch <n>`: Number of images fetched per blob query (default: `1`).
- `--pictures-progress-every <n>`: Progress interval for image export (default: `25`).

### Examples

Recommended way to back up a single DB running on IP X.X.X.X:

```bash
python3 backup_tool.py --db-host X.X.X.X --pictures-blob-batch 1 --output-dir /path/to/backup
```


Create a timestamped backup:

```bash
python3 tools/backup_db.py
```

Write backup to a specific folder:

```bash
python3 tools/backup_db.py --output-dir /path/to/backup
```

Use local images instead of DB images:

```bash
python3 tools/backup_db.py --local-images /bf_images
```

## Restore

`restore_db.py` restores from a backup folder created by `backup_db.py`.

By default, restore does:

1. Admin phase: create app role/database if missing.
2. Schema phase: apply extensions, tables, constraints, indexes, grants from `meta.json`.
3. Data phase: restore JSON table data and images.

### Syntax

```bash
python3 tools/restore_db.py --from-source <backup_dir> --admin-user <admin_user> [options]
```

### Required options

- `--from-source <path>`: Backup directory containing `meta.json`.
- `--admin-user <user>`: Admin user used for role/database creation (for example `yugabyte`).

### Optional options

- `--admin-db <db>`: Admin DB name (default: `yugabyte`).
- `--admin-password <password>`: Admin password (default empty).
- `--host <host>`: Override DB host (otherwise uses `meta.json` value, then `localhost` fallback).
- `--port <port>`: Override DB port (otherwise uses `meta.json` value, then `5433` fallback).
- `--app-password <password>`: Password for app user from `meta.json` (commonly `bfuser`).
- `--no-create`: Skip admin/schema creation and only load data.
- `--local-images <path>`: Copy backup images to local folder instead of inserting into DB `pictures`.
- `--batch <n>`: Batch size for table inserts (default: `500`).
- `--progress-every <n>`: Progress interval for image restore (default: `50`).
- `-v`, `--verbose`: Verbose output.

### Examples

Full restore (create role/db/schema + load data):

```bash
python3 tools/restore_db.py \
  --from-source /path/to/backup \
  --admin-user yugabyte
```

Restore data only into existing schema:

```bash
python3 tools/restore_db.py \
  --from-source /path/to/backup \
  --admin-user yugabyte \
  --no-create
```

Restore and copy images to filesystem instead of DB:

```bash
python3 tools/restore_db.py \
  --from-source /path/to/backup \
  --admin-user yugabyte \
  --local-images /bf_images
```

## Notes

- The restore script expects `meta.json` in the backup directory.
- Table restore currently targets: `users`, `posts`, `comments`.
- If `images/` exists in backup:
  - with `--local-images`, files are copied to that directory.
  - without `--local-images`, images are inserted into `public.pictures`.
