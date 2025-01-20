<?php
$image = $_GET['image'];
include_once "config.php";
$use_file_store_for_images = 0;
$memcache_picture_duration = 600;
if(isset($_GET['use_file_store_for_images'])){
    $use_file_store_for_images = 1;
}

if ( isset($replica_dbhost) ){
     $dbhost = $replica_dbhost;
}
$memcache = "";
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

    $picture = "";		
    $key = "picture_" . $image;
    if ( $memcache ){
	$picture = $memcache->get($key);
    }

    if( $picture == false){
	$picture = "";
	if( $use_file_store_for_images ){
	    $picture = file_get_contents("images/" . $row["image"]);
	} else {
	    $image_query = "select picture from pictures where pictureid = '" . $image . "'";
	    # echo $image_query . "\n";
	    $stmt = $dbh->query($image_query);
	    $imagerow = $stmt->fetch(PDO::FETCH_ASSOC);
#	    print_r($imagerow);
	    
	    ob_start();
	    fpassthru($imagerow["picture"]);
	   
	    
	    $picture = ob_get_contents();
	    ob_end_clean();
	    #echo $picture_of_user;
	}
    }
        // cache for 10 minutes
    if ( $memcache ){
        $memcache->set($key, $picture,0,$memcache_picture_duration);
    }
    header("Content-type: image/jpg");
    echo hex2bin($picture);				
				
}  catch (Exception $e) {
    echo $e->getMessage() . "\r\n";
}

?>

