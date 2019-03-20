<?php
$dbhost = "roach_db-proxy";
$dbport = "26257";
$db = "bf";
$dbuser = "bfuser";
$dbpassw = '';
$webhost = '';
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
