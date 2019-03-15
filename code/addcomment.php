<html>
<?php
	$user = $_GET['user'];
	$post = $_GET['comment'];
	$postID = $_GET['postID'];
	
	include_once "config.php";
try {
    $dbh = new PDO('pgsql:host=' . $dbhost . ";port=" . $dbport . ";dbname=" . $db . ';sslmode=disable',$dbuser, null, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => true,));
    echo "Connection successful!\n<br>";
    if ( isset($_GET['nomemcache'])) {
	$memcache_override = $_GET['nomemcache'];
    }
    if ( $memcache_enabled == 1 and ! $memcache_override ){
	echo "<! Memcache is enabled !>";
	$memcache = new Memcache();
	$memcache->addServer ( $memcache_server,"11211" );
    }

    $result = $dbh->query("insert into comments (text,userid,postid ) values('$post','$user','$postID' );");
    if ( isset($memcache) and $memcache ){
	$key = "comments_on_$postID";
	$memcache->delete($key);
    }
}  catch (Exception $e) {
    echo $e->getMessage() . "\r\n";
}

?>
</html>
