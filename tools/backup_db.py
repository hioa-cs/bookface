import os
import psycopg2
import json
from datetime import datetime
import binascii
import argparse
import shutil

# Database configuration
DB_CONFIG = {
    "dbname": "bf",
    "user": "bfuser",
    "password": "",  # Add your password if required
    "host": "localhost",
    "port": "26257",
}


def copy_images(backup_location,target_location):
    """Restore images, but to a local folder. """
    for filename in os.listdir(backup_location):
        source_file = os.path.join(backup_location, filename)
        destination_file = os.path.join(target_location, filename)
        shutil.copy2(source_file, destination_file)
        print(f"Copied: {source_file} -> {destination_file}")


def create_backup_folder(output_dir=None):
    """Create a backup folder with a timestamp or use the specified directory."""
    if output_dir:
        backup_folder = output_dir
    else:
        backup_folder = f"backup_{datetime.now().strftime('%Y%m%d_%H%M%S')}"
    os.makedirs(backup_folder, exist_ok=True)
    os.makedirs(os.path.join(backup_folder, "images"), exist_ok=True)
    print(f"Backup folder created: {backup_folder}")
    return backup_folder


def create_meta_file(cursor, backup_folder):
    """Generate a meta.json file containing table and schema details."""
    print("Creating enhanced meta.json...")

    # Fetch all table names in the public schema
    cursor.execute("""
        SELECT table_name
        FROM information_schema.tables
        WHERE table_schema = 'public'
    """)
    tables = [row[0] for row in cursor.fetchall()]

    # Fetch column details for each table
    table_details = {}
    for table in tables:
        cursor.execute(f"""
            SELECT column_name, data_type, is_nullable, column_default
            FROM information_schema.columns
            WHERE table_name = '{table}'
        """)
        columns = [
            {
                "name": row[0],
                "type": row[1],
                "nullable": row[2],
                "default": row[3]
            }
            for row in cursor.fetchall()
        ]
        table_details[table] = {"columns": columns}

    # Add metadata about the database and tables
    meta_data = {
        "database": DB_CONFIG["dbname"],
        "user": DB_CONFIG["user"],
        "timestamp": datetime.now().isoformat(),
        "tables": table_details
    }

    # Save the metadata to a JSON file
    meta_file = os.path.join(backup_folder, "meta.json")
    with open(meta_file, "w") as f:
        json.dump(meta_data, f, indent=4)
    print(f"Enhanced meta.json saved to {meta_file}")


def fetch_data_and_save(cursor, table_name, output_file):
    """Fetch data from a table and save it as a JSON file."""
    cursor.execute(f"SELECT * FROM {table_name}")
    rows = cursor.fetchall()
    column_names = [desc[0] for desc in cursor.description]

    # Convert rows into dictionaries
    data = []
    for row in rows:
        row_dict = dict(zip(column_names, row))

        # Convert datetime objects to strings
        for key, value in row_dict.items():
            if isinstance(value, datetime):
                row_dict[key] = value.isoformat()  # Convert to ISO 8601 string

        data.append(row_dict)

    # Save the data to a JSON file
    with open(output_file, "w") as f:
        json.dump(data, f, indent=4)
    print(f"Data from {table_name} saved to {output_file}")


def fetch_and_save_images(cursor, backup_folder):
    """Fetch hex-encoded images, decode them, and save as image files."""
    cursor.execute("SELECT pictureID, picture FROM pictures")
    rows = cursor.fetchall()

    for picture_id, picture_hex in rows:
        if picture_hex:
            # Decode the hex-encoded image to binary
            try:
                picture_binary = binascii.unhexlify(picture_hex)
            except binascii.Error:
                print(f"Error decoding image {picture_id}")
                continue

            # Save the image file
            image_path = os.path.join(backup_folder, "images", picture_id)
            with open(image_path, "wb") as img_file:
                img_file.write(picture_binary)
            print(f"Image {picture_id} saved to {image_path}")


def main():
    parser = argparse.ArgumentParser(description="Backup CockroachDB database to files.")
    parser.add_argument(
        "--local-images",
        type=str,
        required=True,
        help="Store images in this location instead of the database.",
    )
    parser.add_argument(
        "--output-dir",
        type=str,
        help="Specify a directory to store the backup. If not provided, a timestamped folder will be created.",
    )
    parser.add_argument(
        "--db-user",
        type=str,
        help="Specify a different user name than bfuser",
    )
    args = parser.parse_args()

    # Create the backup folder
    backup_folder = create_backup_folder(args.output_dir)
    
    if args.db-user:
        DB_CONFIG["user"] = args.db-user
        

    try:
        # Connect to the database
        conn = psycopg2.connect(**DB_CONFIG)
        cursor = conn.cursor()

        # Create the meta.json file
        create_meta_file(cursor, backup_folder)

        # Fetch and save data from each table
        fetch_data_and_save(cursor, "users", os.path.join(backup_folder, "users.json"))
        fetch_data_and_save(cursor, "posts", os.path.join(backup_folder, "posts.json"))
        fetch_data_and_save(cursor, "comments", os.path.join(backup_folder, "comments.json"))

        # Fetch and save images
        if args.local_images:
            copy_images(args.local_images,backup_folder)
        else:
            fetch_and_save_images(cursor, backup_folder)

        print("Backup completed successfully.")

    except Exception as e:
        print(f"Error during backup: {e}")
    finally:
        if cursor:
            cursor.close()
        if conn:
            conn.close()


if __name__ == "__main__":
    main()
