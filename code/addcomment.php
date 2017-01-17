<html>
<?php
	$user = $_GET['user'];
	$post = $_GET['comment'];
	$postID = $_GET['postID'];
	
	include_once "config.php";
$link = mysql_connect("$dbhost:$dbport", $dbuser,$dbpassw );
	if ($link){
    	echo "Connection successful!\n<br>";
    	$bfdb = mysql_select_db($db,$link);
    	if ( !$bfdb ){
				echo "Cannot use $db: " . mysql_error() ."<br>";
    	} else {
			echo "Correct database found<br>\n";
	    if ( isset($_GET['nomemcache']){
		$memcache_override = $_GET['nomemcache'];
	    }
			if ( $memcache_enabled == 1 and ! $memcache_override ){
				echo "<! Memcache is enabled !>";
				$memcache = new Memcache();
    			$memcache->addServer ( $memcache_server,"11211" );
			}

			$result = mysql_query("insert into comments (text,userID,postID ) values('$post','$user','$postID' );");
			if ( isset($memcache) and $memcache ){
				$key = "comments_on_$postID";
				$memcache->delete($key);
			}
			 if ( ! mysql_error()){
			  echo "OK";
			} else {
				mysql_error();
			}	
		}
	}
	?>
	</html>
