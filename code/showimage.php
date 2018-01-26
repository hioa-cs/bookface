<?php
	$user = $_GET['user'];
	include_once "config.php";

#	echo "host: $dbhost, $dbport, $dbuser, $dbpassw<br>";
if ( isset($replica_dbhost) ){
     $dbhost = $replica_dbhost;
}
$link = mysqli_connect("$dbhost:$dbport", $dbuser,$dbpassw );
if ($link){
 #   	echo "Connection successful!\n<br>";
    	$bfdb = mysqli_select_db($link,$db);
    	if ( !$bfdb ){
#				echo "Cannot use $db: " . mysqli_error() ."<br>";
    	} else {
	    $memcache_override = 0;
	    if ( isset($_GET['nomemcache'])){
		$memcache_override = $_GET['nomemcache'];
	    }
#			$memcache = 0;
			if ( $memcache_enabled == 1 and ! $memcache_override ){
			#	echo "Memcache is enabled";
				$memcache = new Memcache();
    			$memcache->addServer ( $memcache_server,"11211" );
			}
#			echo "Correct database found<br>\n";
			
			$key = "picture_of_" . $user;
			if ( $memcache ){
#                                error_log("Running memcahce get for $key");
				$picture_of_user = $memcache->get($key);
			}
			if( $picture_of_user == false){
#    					error_log("No memcache object for image with key: $key, memcache is $memcache_enabled and pictures are $memcache_enabled_pictures");
						$picture_of_user = array();
    					$sql = "select picture from user where userID = $user";
    					$res = mysqli_query($link, $sql);
    					while($rec = mysqli_fetch_assoc($res)){
        					$picture_of_user[] = $rec;
    		                         }
    // cache for 10 minutes
         			         if ( $memcache ){
#                                            error_log("doing memcache set for $key");
					    $memcache->set($key, $picture_of_user,0,6000);
				         }
			}
		#	$result = mysqli_query($link, "select picture from user where userID = $user");
		#	$res = mysqli_fetch_array($result);
			header("Content-type: image/png");
			foreach ( $picture_of_user as $res ){
				echo $res['picture'];

				
			}
			
		}
}

?>

