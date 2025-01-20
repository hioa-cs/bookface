<?php
$user = $_GET['user'];
include_once "config.php";
if (isset($_GET['image'])){
 $user = $_GET['image'];   
}
$use_file_store_for_images = 0;
$memcache_picture_duration = 600;
$picture_of_user = false;
$memcache = "";

if(isset($_GET['use_file_store_for_images'])){
    $use_file_store_for_images = 1;
}

if ( isset($replica_dbhost) ){
     $dbhost = $replica_dbhost;
}

try {
    $dbh = new PDO('pgsql:host=' . $dbhost . ";port=" . $dbport . ";dbname=" . $db . ';sslmode=disable',$dbuser, null, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => true,));
    $memcache_override = 0;
    if ( isset($_GET['nomemcache'])){
	$memcache_override = $_GET['nomemcache'];
    }

    if ( isset($memcache_enabled) and $memcache_enabled == 1 and ! $memcache_override ){
	$memcache = new Memcache();
    	$memcache->addServer ( $memcache_server,"11211" );
	if ( isset($memcache_picture_keep_minutes) and $memcache_picture_keep_minutes > 0 ){
	    $memcache_picture_duration = $memcache_picture_keep_minutes * 60;
	}
    }

			
    $key = "picture_of_" . $user;
    if ( $memcache ){
	$picture_of_user = $memcache->get($key);
    }

    if( $picture_of_user == false){
	$picture_of_user = "";
	if( $use_file_store_for_images ){
	    $picture_of_user = file_get_contents("images/" . $row["picture"]);
	} else {
	    $sql = "select picture from users where userid = '$user'";
	    $stmt = $dbh->query($sql);
	    $row = $stmt->fetch(PDO::FETCH_ASSOC);
		
		$image_query = "SELECT picture FROM pictures WHERE pictureID = :pictureID";
		$stmt = $dbh->prepare($image_query);
		$stmt->execute([':pictureID' => $row["picture"]]);
		$imagerow = $stmt->fetch(PDO::FETCH_ASSOC);
		
		if ($imagerow && isset($imagerow["picture"])) {
			$picture_hex = $imagerow["picture"]; // Hexadecimal string
		
			// Convert hex string back to binary
			$picture_binary = hex2bin(stream_get_contents($picture_hex));
		
			// Output the binary image data
			header("Content-type: image/jpeg"); // Adjust MIME type if needed
			echo $picture_binary;
			exit;
		} else {
			http_response_code(404);
			echo "Image not found.";
		}  
	}
    }
        // cache for 10 minutes
    if ( $memcache ){
        $memcache->set($key, $picture_of_user,0,$memcache_picture_duration);
    }
    header("Content-type: image/jpg");
    echo $picture_of_user;				
				
}  catch (Exception $e) {
    echo $e->getMessage() . "\r\n";
}
?>