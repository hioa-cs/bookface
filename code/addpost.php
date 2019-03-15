<html>
<?php
$user = $_GET['user'];
$post = $_GET['post'];

include_once "config.php";

try {
    $dbh = new PDO('pgsql:host=' . $dbhost . ";port=" . $dbport . ";dbname=" . $db . ';sslmode=disable',$dbuser, null, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => true,));
    if ( isset( $_GET['nomemcache']) ){
	$memcache_override = $_GET['nomemcache'];
    }

    if ( isset($memcache_enabled) and $memcache_enabled == 1 and ! $memcache_override ){
	echo "<! Memcache is enabled !>";
	$memcache = new Memcache();
	$memcache->addServer ( $memcache_server,"11211" );
    }
    
    $stmt = $dbh->query("select posts from users where userid = $user");
    $res = $stmt->fetch(PDO::FETCH_ASSOC);

    $posts = $res['posts'] + 1;
    $result = $dbh->query("update users set posts = $posts, lastpostdate = now() where userID = $user" );
    $result = $dbh->query("insert into posts (text,userid) values('$post','$user');");
    if ( isset($memcache_enabled) and $memcache_enabled == 1 and $memcache ){
	$memcache->delete("user_list_for_front_page");
	$memcache->delete("posts_by_$user");	
    }	
} catch (Exception $e) {
    echo $e->getMessage() . "\r\n";
}
?>
</html>
