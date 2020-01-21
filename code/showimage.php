<?php
$user = $_GET['user'];
include_once "config.php";
$use_file_store_for_images = 0;
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

    if ( $memcache_enabled == 1 and ! $memcache_override ){
	$memcache = new Memcache();
    	$memcache->addServer ( $memcache_server,"11211" );
    }

			
    $key = "picture_of_" . $user;
    if ( $memcache ){
	$picture_of_user = $memcache->get($key);
    }

    if( $picture_of_user == false){
	$picture_of_user = "";
	if( $use_file_store_for_images ){
	    $sql = "select picture from users where userid = $user";
	    $stmt = $dbh->query($sql);
	    $row = $stmt->fetch(PDO::FETCH_ASSOC);
	    $picture_of_user = file_get_contents("images/" . $row["picture"]);
	} else {
	    $sql = "select picture from users where userid = $user";
	    $stmt = $dbh->query($sql);
	    $row = $stmt->fetch(PDO::FETCH_ASSOC);
	    
	    $image_query = "select picture from pictures where pictureid = '" . $row["picture"] . "'";
	    # echo $image_query . "\n";
	    $stmt = $dbh->query($image_query);
	    $imagerow = $stmt->fetch(PDO::FETCH_ASSOC);
#	    print_r($imagerow);
	    
	    ob_start();
	    fpassthru($imagerow["picture"]);
	   
	    
	    $picture_of_user = ob_get_contents();
	    ob_end_clean();
	    #echo $picture_of_user;
	}
    }
        // cache for 10 minutes
    if ( $memcache ){
        $memcache->set($key, $picture_of_user,0,6000);
    }
    header("Content-type: image/jpg");
    echo $picture_of_user;				
				
}  catch (Exception $e) {
    echo $e->getMessage() . "\r\n";
}

?>

