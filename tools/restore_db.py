import os
import psycopg2
import json
import binascii
import argparse
from datetime import datetime

# Database configuration
DB_CONFIG = {
    "dbname": "bf",
    "user": "bfuser",
    "password": "",  # Add your password if required
    "host": "localhost",
    "port": "26257",
}


def log(message, verbose):
    """Log a message if verbose mode is enabled."""
    if verbose:
        print(message)


def restore_meta(root_cursor, meta_file, db_user, verbose):
    """Restore metadata: create database, assign privileges, and dynamically recreate tables."""
    log("Restoring metadata from meta.json...", verbose)

    # Load metadata
    with open(meta_file, "r") as f:
        meta_data = json.load(f)

    # Extract database name
    db_name = meta_data["database"]

    # Step 1: Create the database as root
    try:
        log(f"Ensuring database {db_name} exists...", verbose)
        root_cursor.execute(f"CREATE DATABASE IF NOT EXISTS {db_name};")
        log(f"Database {db_name} created or already exists.", verbose)
    except Exception as e:
        log(f"Error creating database {db_name}: {e}", verbose)
        raise

    # Step 2: Create the user if it doesn't exist
    try:
        log(f"Checking if user {db_user} exists...", verbose)
        root_cursor.execute(f"SELECT 1 FROM pg_roles WHERE rolname = '{db_user}';")
        if not root_cursor.fetchone():
            log(f"User {db_user} does not exist. Creating user...", verbose)
            root_cursor.execute(f"CREATE USER {db_user};")
            log(f"User {db_user} created successfully.", verbose)
        else:
            log(f"User {db_user} already exists.", verbose)
    except Exception as e:
        log(f"Error checking/creating user {db_user}: {e}", verbose)
        raise

    # Step 3: Grant privileges to the target user
    try:
        log(f"Granting privileges on {db_name} to user {db_user}...", verbose)
        root_cursor.execute(f"GRANT ALL ON DATABASE {db_name} TO {db_user};")
        log(f"Privileges granted to {db_user} on {db_name}.", verbose)
    except Exception as e:
        log(f"Error granting privileges: {e}", verbose)
        raise

    # Step 4: Reconnect as the target user for further operations
    log(f"Switching to database {db_name} as user {db_user}...", verbose)
    conn_user = psycopg2.connect(
        dbname=db_name,
        user=db_user,
        password=DB_CONFIG["password"],
        host=DB_CONFIG["host"],
        port=DB_CONFIG["port"],
    )
    conn_user.autocommit = True
    user_cursor = conn_user.cursor()

    try:
        # Step 5: Create tables dynamically
        for table, details in meta_data["tables"].items():
            column_definitions = []
            for column in details["columns"]:
                col_def = f"{column['name']} {column['type']}"
                if column["nullable"] == "NO":
                    col_def += " NOT NULL"
                if column["default"]:
                    col_def += f" DEFAULT {column['default']}"
                column_definitions.append(col_def)

            create_table_query = f"CREATE TABLE IF NOT EXISTS {table} ({', '.join(column_definitions)});"
            log(f"Executing: {create_table_query}", verbose)
            user_cursor.execute(create_table_query)
            log(f"Table {table} created successfully.", verbose)

        log("All tables created successfully.", verbose)

    finally:
        # Close the user connection
        user_cursor.close()
        conn_user.close()

    log("Metadata restoration complete.", verbose)


def restore_table(cursor, table_name, file_path, verbose):
    """Restore data from a JSON file into a table."""
    log(f"Restoring table: {table_name} from {file_path}...", verbose)

    # Load the JSON file
    with open(file_path, "r") as f:
        data = json.load(f)

    # Dynamically generate the insert query
    if data:
        columns = data[0].keys()
        placeholders = ", ".join([f"%({col})s" for col in columns])
        insert_query = f"INSERT INTO {table_name} ({', '.join(columns)}) VALUES ({placeholders})"

        # Insert the rows
        for row in data:
            log(f"Executing: {insert_query} with {row}", verbose)
            cursor.execute(insert_query, row)

    log(f"Restored {len(data)} rows into {table_name}.", verbose)


def restore_images(cursor, images_dir, verbose):
    """Restore images from the images directory into the pictures table."""
    log(f"Restoring images from {images_dir}...", verbose)

    for image_file in os.listdir(images_dir):
        image_path = os.path.join(images_dir, image_file)
        with open(image_path, "rb") as f:
            image_binary = f.read()

        # Convert binary data to hex for storage
        image_hex = binascii.hexlify(image_binary).decode()

        # Insert into the pictures table
        insert_query = "INSERT INTO pictures (pictureID, picture) VALUES (%s, %s)"
        log(f"Executing: {insert_query} with ({image_file}, [binary data])", verbose)
        cursor.execute(insert_query, (image_file, image_hex))

    log(f"Restored images from {images_dir}.", verbose)


def main():
    parser = argparse.ArgumentParser(description="Restore CockroachDB database from backup.")
    parser.add_argument(
        "--from-source",
        type=str,
        required=True,
        help="Path to the backup directory.",
    )
    parser.add_argument(
        "--restore-meta",
        action="store_true",
        help="Recreate database, tables, and grant permissions based on metadata.",
    )
    parser.add_argument(
        "-v", "--verbose",
        action="store_true",
        help="Enable verbose output for debugging.",
    )
    args = parser.parse_args()

    backup_dir = args.from_source
    verbose = args.verbose

    if not os.path.exists(backup_dir):
        print(f"Error: Backup directory {backup_dir} does not exist.")
        return

    try:
        # Connect as root
        log("Connecting to database as root...", verbose)
        root_conn = psycopg2.connect(
            dbname="defaultdb",
            user="root",  # Assuming root for initial actions
            host=DB_CONFIG["host"],
            port=DB_CONFIG["port"],
        )
        root_conn.autocommit = True
        root_cursor = root_conn.cursor()

        # Restore metadata if requested
        if args.restore_meta:
            meta_file = os.path.join(backup_dir, "meta.json")
            if not os.path.exists(meta_file):
                print(f"Error: Metadata file {meta_file} not found.")
                return
            restore_meta(root_cursor, meta_file, DB_CONFIG["user"], verbose)

        # Restore data and images
        log("Connecting to database as user...", verbose)
        conn_user = psycopg2.connect(**DB_CONFIG)
        user_cursor = conn_user.cursor()

        for table_name in ["users", "posts", "comments"]:
            file_path = os.path.join(backup_dir, f"{table_name}.json")
            if os.path.exists(file_path):
                restore_table(user_cursor, table_name, file_path, verbose)

        images_dir = os.path.join(backup_dir, "images")
        if os.path.exists(images_dir):
            restore_images(user_cursor, images_dir, verbose)

        conn_user.commit()
        print("Database restoration completed successfully.")

    except Exception as e:
        print(f"Error during restoration: {e}")
    finally:
        if root_cursor:
            root_cursor.close()
        if root_conn:
            root_conn.close()


if __name__ == "__main__":
    main()
