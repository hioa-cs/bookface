<html>
<?php
$user = $_GET['user'];
$post = $_GET['comment'];
$postID = $_GET['postID'];
$stats = $_GET['stats'];
$currentDateTime = new DateTime();
$formattedDateTime = $currentDateTime->format('Y-m-d H:i:s');
	
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
    
    $stmt = $dbh->prepare("insert into comments (text,userid,postid,stats,postdate ) values(:post,:user,:postID,:stats, :postdate );");
    $stmt->execute([':post' => $post, ':user' => $user, ':postID' => $postID, ':stats' => $stats, ':postdate' => $formattedDateTime ]);

    
    if ( isset($memcache) and $memcache ){
	$key = "comments_on_$postID";
	$memcache->delete($key);
    }
}  catch (Exception $e) {
    echo $e->getMessage() . "\r\n";
}

?>
</html>
