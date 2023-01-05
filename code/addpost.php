<html>
<?php
$user = $_GET['user'];
$post = $_GET['post'];
$image = $_GET['image'];
$server = $_GET['server'];
$use_file_store_for_images = 0;
include_once "config.php";

if(isset($use_local_images) and $use_local_images == 1){
    $use_file_store_for_images = 1;
}


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

    if($image){
	if($use_file_store_for_images){
	    file_put_contents("images/" . $image,file_get_contents("http://" . $server . "/" . $image));
	} else {	
	    $img = pg_escape_bytea(file_get_contents("http://" . $server . "/" . $image ));
	    $image_query =  "insert into pictures (pictureID,picture) values('". $image . "','$img');";
	    #	echo "\n " . $image_query;
	    $stmt = $dbh->prepare($image_query);
	    $stmt->execute([]);	    
	}
    }
    $posts = $res['posts'] + 1;
    $result = $dbh->query("update users set posts = $posts, lastpostdate = now() where userID = $user" );
    if ( $image ){
	$result = $dbh->query("insert into posts (text,userid,image) values('$post','$user','$image');");
    } else {
	$result = $dbh->query("insert into posts (text,userid) values('$post','$user');");
    }
    if ( isset($memcache_enabled) and $memcache_enabled == 1 and $memcache ){
	$memcache->delete("user_list_for_front_page");
	$memcache->delete("posts_by_$user");	
    }


} catch (Exception $e) {
    echo $e->getMessage() . "\r\n";
}
?>
</html>
