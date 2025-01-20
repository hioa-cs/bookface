<html>
  <HEAD>
    <LINK href="showuser.css" rel="stylesheet" type="text/css">
  </HEAD>
<?php

$user = $_GET['user'];
$use_file_store_for_images = 0;
include_once "config.php";

if(isset($use_local_images) and $use_local_images == 1){
    $use_file_store_for_images = 1;
}

echo "<body>\n";
echo "<div class=container >\n";
echo "\t<header>";
echo "\t\t<h1><a class=title href='/index.php'>bookface</a></h1>";
echo "\t</header>";

$currentDateTime = new DateTime();
$formattedDateTime = $currentDateTime->format('Y-m-d H:i:s');


If ( isset($replica_dbhost) ){
    $dbhost = $replica_dbhost;
}

try {
    $dbh = new PDO('pgsql:host=' . $dbhost . ";port=" . $dbport . ";dbname=" . $db . ';sslmode=disable',$dbuser, null, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => true,));

    $memcache = "";
    $memcache_override = 0;
    if ( isset($_GET['nomemcache'])){
	$memcache_override = $_GET['nomemcache'];
    }
    
    
    if ( isset($memcache_enabled) and $memcache_enabled == 1 and ! $memcache_override ){
	echo "\t<! Memcache is enabled !>\n";
	$memcache = new Memcache();
	$memcache->addServer ( $memcache_server,"11211" );
    }
    
    $stmt = $dbh->query("select name,status,posts,comments,createdate,picture,bio from users where userid = '$user'");   
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "\t<div class=user-header >\n";
    if ( $use_file_store_for_images ){
	echo "\t\t<img src='/images/" . trim($res['picture']) . "'>\n";
    } else {
	echo "\t\t<img src='/showimage.php?user=" . $user . "'>\n";
    }
    echo "\t\t<div class=user-info >\n";
    echo "\t\t\t<h2>" . trim($res['name']) . "</h2>\n";
    echo "\t\t\t<p><b>Member since:</b> " . date('D, M j, Y H:i', strtotime($res['createdate'])) . "</p>\n";
    echo "\t\t\t<p><b>Posts:</b> " . $res['posts'] . "</p>\n";
    echo "\t\t</div>\n";
    echo "\t</div>\n";
    
    echo "\t<!-- bio: -->\n";
    echo "\t<p>" . $res['bio'] . "</p>\n";
    
    echo "\t<div class=post-section>\n";
    echo "\t\t<h2>Latest Posts:</h2>\n";
    
    $posts_by_user = array();
    if ( isset($memcache) and $memcache ){
	$posts_by_user = $memcache->get("posts_by_$user");
    }
    
    if (empty($posts_by_user)){
	echo "\t\t<! No memcache object found: posts_by_$user !>\n";
	$posts_by_user = array();
	$sql = "select postid,text,postdate,image,stats from posts where userid = '$user' order by postdate desc;";
	foreach ($dbh->query($sql) as $rec)
	  $posts_by_user[] = $rec;
	
	// cache for 10 minutes
	if ( $memcache ){
	    $memcache->set("posts_by_$user", $posts_by_user,0,600);
	}	
    }
    
    
    $postcount = 0;
    if ( isset($posts_by_user) ){
	foreach ( $posts_by_user as $res ){
	    // Convert the postdate to a more human-readable format
	    $formatted_date = date('D, M j, Y H:i', strtotime($res['postdate']));
	    $table .= "\t\t<div class=post-card >\n";
	    $table .= "\t\t\t<! postID:". $res['postid'] . " !><div class=post-header>" . $formatted_date . "</div><div class=post-body><p>" . $res['text'] . "</p>\n";
	    $table .= "\t\t\t<! Stats: " . $res['stats'] . "!>\n";
	    
	    if( $res['image'] ){
		$table .= "\t\t\t<! image for post: " . $res['image'] . ">\n";
		if ( $use_file_store_for_images ){
		    $table .= "\t\t\t<img class=post-image src='/images/" . trim($res['image']) . "'>\n";
		} else {
		    $table .= "\t\t\t<img class=post-image src='/postimage.php?image=" . trim($res['image']) . "'>\n";
		}		
	    }
	    $table .= "\t\t\t</div>\n";

	    
	    $postcount++;
	    
	    $key = "comments_on_" . $res['postid'];
	    $comments_on_post = array();
	    if (  isset($memcache) and $memcache ){
		$comments_on_post = $memcache->get($key);
	    }
	    
	    if( empty($comments_on_post)){
		echo "<! No memcache object for comment found: $key !>\n";
		$comments_on_post = array();
		$sql = "select commentid,text,userid,postdate,stats from comments where postid = '" . $res['postid'] . "' order by postdate asc;";
		foreach ($dbh->query($sql) as $rec)
		  $comments_on_post[] = $rec;
		
		// cache for 10 minutes
		if ( $memcache ){
		    $memcache->set($key, $comments_on_post,0,600);
		}	
	    }
	    
	    if ( count($comments_on_post) > 0 ){
		$table .= "\t\t\t<div class=comment-section >\n";
		$table .= "\t\t\t\t<h3>Comments:</h3>\n";
		
		foreach ( $comments_on_post as $cres ){
		    $stmt = $dbh->query("select name,picture from users where userid = '$cres[userid]'");
		    $ures = $stmt->fetch(PDO::FETCH_ASSOC);
		    $formatted_date = date('D, M j, Y H:i', strtotime($cres['postdate']));
		    $table .= "\t\t\t\t<div class=comment-card >\n";
		    
		    if ( $use_file_store_for_images ){
			$table .= "\t\t\t\t\t<a href='/showuser.php?user=" . $cres['userid']. "'><img class=comment-image src='/images/".trim($ures['picture']) ."'></a>\n";
		    } else {
			$table .= "\t\t\t\t\t<a href='/showuser.php?user=" . $cres['userid']. "'><img class=comment-image src='/showimage.php?user=".trim($cres['userid']) ."'></a>\n";
		    }
		    
		    $table .= "\t\t\t\t\t<div class=comment-content >\n";
		    $table .= "\t\t\t\t\t\t<div class=comment-header >" . $formatted_date . " <span class=comment-user > " . trim($ures['name']) . ":</span></div>\n";
		    $table .= "\t\t\t\t\t\t<div class=comment-body>\n";
		    $table .= "\t\t\t\t\t\t\t<p> " . $cres['text'] . "</p>\n";
		    $table .= "\t\t\t\t\t\t\t<! Stats: " . $cres['stats'] . " !>\n";
		    $table .= "\t\t\t\t\t\t</div>\n";
		    $table .= "\t\t\t\t\t</div>\n";
		    $table .= "\t\t\t\t</div>\n";
		    
		    
		    
		}
		$table .= "\t\t\t</div>\n";
		
		$comments_on_post = array();
	    }
	    $table .= "\t\t</div>\n";

	}
    }
    echo "$table\n";
    echo "\t</div>\n";    
    echo "</div>\n";
    echo "</body>\n";
    
} catch (Exception $e) {
    echo $e->getMessage() . "\r\n";
}


?>
</html>
