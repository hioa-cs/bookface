<html>
<?php
$user = $_GET['user'];
$post = $_GET['post'];
$stats = $_GET['stats'];
$image_stats = $_GET['image_stats'];

if (isset($_GET['image'])) {
$image = $_GET['image'];
} else {
    $image = '';
}

if (isset($_GET['server'])) {
$server = $_GET['server'];
} else {
    $server = '';
}
$use_file_store_for_images = 0;
include_once "config.php";

if(isset($use_local_images) and $use_local_images == 1){
    $use_file_store_for_images = 1;
}

$currentDateTime = new DateTime();
$formattedDateTime = $currentDateTime->format('Y-m-d H:i:s');

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
    
    $stmt = $dbh->query("select posts from users where userid = '$user'");
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
      
    if($image){
	if($use_file_store_for_images){
	    file_put_contents("images/" . $image,file_get_contents("http://" . $server . "/" . $image));
	} else {	
        echo("Fetching image from: " . $server . "/" . $image );
        $img_binary = file_get_contents("http://" . $server . "/" . $image);
        file_put_contents('/tmp/debug_post_image.png', $img_binary); // Write the downloaded image to disk

        // Check the length and first bytes of the binary data
        echo "<pre>Image length: " . strlen($img_binary) . "</pre>";
        echo "<pre>First bytes (hex): " . bin2hex(substr($img_binary, 0, 20)) . "</pre>";
        $md5_checksum = md5($img_binary);
        echo "<pre>MD5 checksum: " . $md5_checksum . "</pre>";
        
	    $img = bin2hex(file_get_contents("http://" . $server . "/" . $image ));
        $image_query = "INSERT INTO pictures (pictureID, picture,stats) VALUES (:pictureID, :picture, :image_stats )";
        $stmt = $dbh->prepare($image_query);
        $stmt->execute([
            ':pictureID' => $image,
            ':picture' => $img,
	    ':image_stats' => $image_stats,
        ]);
	}
    }
    $posts = $res['posts'] + 1;
    $result = $dbh->query("update users set posts = $posts, lastpostdate = '" . $formattedDateTime . "' where userID = '$user'" );
    // Prepare the SQL statement with placeholders
    if ( $image ){
        $stmt = $dbh->prepare("insert into posts (text,userid,image,stats,postdate) values(:text,:userId,:image,:stats,:postdate);");
        $stmt->execute([':text' => $post, ':userId' => $user, ':image' => $image, ':stats' => $stats, ':postdate' => $formattedDateTime ]);
    } else {
        $stmt = $dbh->prepare("insert into posts (text,userid,stats,postdate) values(:text,:userId,:stats, :postdate );");
        $stmt->execute([':text' => $post, ':userId' => $user, ':stats' => $stats, ':postdate' => $formattedDateTime]);
        echo "Log: Executing query with prepared statement.";
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
