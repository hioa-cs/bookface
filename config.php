<?php
$dbhost = getenv("BF_DB_HOST");
$dbport =  getenv("BF_DB_PORT");
$db = getenv("BF_DB_NAME");
$dbuser = getenv("BF_DB_USER");
$dbpassw = getenv("BF_DB_PASS");
$webhost = getenv("BF_WEBHOST");
$imagepath = getenv("BF_IMAGE_PATH");
$weburl = 'http://' . $webhost ;
$frontpage_limit = 100000;
if ( getenv("BF_FRONTPAGE_LIMIT") ){
   $frontpage_limit = getenv("BF_FRONTPAGE_LIMIT");
}
if ( getenv("BF_MEMCACHE_SERVER")){
   $memcache_enabled_pictures = 1;
   $memcache_server = getenv("BF_MEMCACHE_SERVER");
   $memcache_enabled = 1;
}
?>
