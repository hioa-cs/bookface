<?php
	$user = $_GET['user'];
#	include "config.php";
	$dbport; # = 8889;
$dbhost; # = "localhost";
$db; # = "bookface";
$dbuser; #;  = 'bfuser';
$dbpassw; # = 'bfuserpassword';
#$webhost = 'localhost:8888';

$memcache_enabled = 0;
$picture_of_user = 0;
$memcache = 0;
$memcache_server;

$confarray = file("config.php");
foreach ( $confarray as $confline){
	if ( preg_match('/^\s*\$dbhost\s+=\s+("|\')(.*)("|\')/',$confline,$matches) ){
#		echo "dbhost: " . $confline . "$matches[2]";
		$dbhost = $matches[2];
	} elseif ( preg_match('/^\s*\$dbport\s+=\s+("|\')(.*)("|\')/',$confline,$matches) ){
#		echo "dbhost: " . $confline . "$matches[2]";
		$dbport = $matches[2];
	} elseif ( preg_match('/^\s*\$dbuser\s+=\s+("|\')(.*)("|\')/',$confline,$matches) ){
#		echo "dbhost: " . $confline . "$matches[2]";
		$dbuser = $matches[2];
	} elseif ( preg_match('/^\s*\$dbpassw\s+=\s+("|\')(.*)("|\')/',$confline,$matches) ){
#		echo "dbhost: " . $confline . "$matches[2]";
		$dbpassw = $matches[2];
	} elseif ( preg_match('/^\s*\$db\s+=\s+("|\')(.*)("|\')/',$confline,$matches) ){
#		echo "dbhost: " . $confline . "$matches[2]";
		$db = $matches[2];
	} elseif ( preg_match('/^\s*\$memcache\_enabled\_pictures\s+=\s+("|\'|)(.*)("|\'|)/',$confline,$matches) ){
#		echo "dbhost: " . $confline . "$matches[2]";
		$memcache_enabled = $matches[2];
	} elseif ( preg_match('/^\s*\$replica\_dbhost\s+=\s+("|\'|)(.*)("|\'|)/',$confline,$matches) ){
#		echo "dbhost: " . $confline . "$matches[2]";
		$replica_dbhost = $matches[2];
	} elseif ( preg_match('/^\s*\$memcache\_server\s+=\s+("|\')(.*)("|\')/',$confline,$matches) ){
#		echo "dbhost: " . $confline . "$matches[2]";
		$memcache_server = $matches[2];
	}

	
}
#	echo "host: $dbhost, $dbport, $dbuser, $dbpassw<br>";
if ( isset($replica_dbhost) ){
     $dbhost = $replica_dbhost;
}
$link = mysql_connect("$dbhost:$dbport", $dbuser,$dbpassw );
if ($link){
 #   	echo "Connection successful!\n<br>";
    	$bfdb = mysql_select_db($db,$link);
    	if ( !$bfdb ){
#				echo "Cannot use $db: " . mysql_error() ."<br>";
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
    					$res = mysql_query($sql);
    					while($rec = mysql_fetch_assoc($res)){
        					$picture_of_user[] = $rec;
    		                         }
    // cache for 10 minutes
         			         if ( $memcache ){
#                                            error_log("doing memcache set for $key");
					    $memcache->set($key, $picture_of_user,0,6000);
				         }
			}
		#	$result = mysql_query("select picture from user where userID = $user");
		#	$res = mysql_fetch_array($result);
			header("Content-type: image/png");
			foreach ( $picture_of_user as $res ){
				echo $res['picture'];

				
			}
			
		}
}

?>

