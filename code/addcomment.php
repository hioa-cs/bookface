<html>
<?php
	$user = $_GET['user'];
	$post = $_GET['comment'];
	$postID = $_GET['postID'];
	
	include_once "config.php";
    $link = mysqli_connect("$dbhost:$dbport", $dbuser, $dbpassw);
    echo "Connecting to db at $dbhost:$dbport<br>\n";
	if ($link){
    	echo "Connection successful!\n<br>";
    	$bfdb = mysqli_select_db($link,$db);
    	if ( !$bfdb ){
				echo "Cannot use $db: " . mysqli_error() ."<br>";
    	} else {
			echo "Correct database found<br>\n";
	    if ( isset($_GET['nomemcache'])) {
		$memcache_override = $_GET['nomemcache'];
	    }
			if ( $memcache_enabled == 1 and ! $memcache_override ){
				echo "<! Memcache is enabled !>";
				$memcache = new Memcache();
    			$memcache->addServer ( $memcache_server,"11211" );
			}

			$result = mysqli_query($link, "insert into comments (text,userID,postID ) values('$post','$user','$postID' );");
			if ( isset($memcache) and $memcache ){
				$key = "comments_on_$postID";
				$memcache->delete($key);
			}
			 if ( ! mysqli_error()){
			  echo "OK";
			} else {
				mysqli_error();
			}	
		}
	}
	?>
	</html>
