#!/bin/bash

OPTIND=1

verbose=0

while getopts "h?vd:o:w:" opt; do
    case "$opt" in
    h|\?)
        echo "./dump_images.sh -d database_server -o output_folder -w webserver "
        exit 0
        ;;
    v)  verbose=1
        ;;
    d)  database=$OPTARG
        ;;
    o)  out=$OPTARG
        ;;
    w)  webserver=$OPTARG
        ;;
    esac
done

shift $((OPTIND-1))

[ "${1:-}" = "--" ] && shift

if [ -z "$database" ]; then
echo "You need to specify a database host to log in to: -d ip-to-db"
exit 1
fi

if [ -z "$webserver" ]; then
echo "You need to specify a webserver which runs bookface: -w ip-to-bookface"
exit 1
fi

if [ -z "$out" ]; then
echo "You need to specify a folder where the images are to be stored: -o /path/to/folder"
exit 1
fi

if [ ! -d "$out" ]; then
echo "The folder $out is not a folder...."
exit 1
fi

IFS=$'\n'

TMP_DIR="/tmp"

echo "getting user list from database"
ssh $database "cockroach sql --insecure --host=127.0.0.1:26257 -d bf --execute=\"select userid,picture from users\"" > $TMP_DIR/userlist.txt

for line in $(cat $TMP_DIR/userlist.txt | grep .jpg ); do

user=$( echo $line | awk '{print $1}');
image=$( echo $line | awk '{print $2}');

if [ ! -f /tmp/$image ]; then
echo "dumping image $image for user $user";
curl -s "http://192.168.131.228/showimage.php?user=$user" > $out/$image;
fi
done
