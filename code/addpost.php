<html>
<?php
	$user = $_GET['user'];
	$post = $_GET['post'];

	include_once "config.php";
$link = mysqli_connect("$dbhost:$dbport", $dbuser,$dbpassw );
	if ($link){
    	# echo "Connection successful!\n<br>";
    	$bfdb = mysqli_select_db($link,$db);
    	if ( !$bfdb ){
				echo "Cannot use $db: " . mysqli_error() ."<br>";
    	} else {
		#	echo "Correct database found<br>\n";
	    if ( isset( $_GET['nomemcache']) ){
		$memcache_override = $_GET['nomemcache'];
	    }

			if ( isset($memcache_enabled) and $memcache_enabled == 1 and ! $memcache_override ){
		#		echo "<! Memcache is enabled !>";
			    $memcache = new Memcache();
			    $memcache->addServer ( $memcache_server,"11211" );
			}
			$result = mysqli_query($link, "select posts from user where userID = $user");
			$res = mysqli_fetch_array($result);
			$posts = $res['posts'] + 1;
			$result = mysqli_query($link, "update user set posts = $posts, lastPostDate = now() where userID = $user" );
			$result = mysqli_query($link, "insert into posts (text,userID,postDate) values('$post','$user',now() );");
			if ( isset($memcache_enabled) and $memcache_enabled == 1 and $memcache ){
				$memcache->delete("user_list_for_front_page");
				$memcache->delete("posts_by_$user");	
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
