<html>
  <HEAD>
    <LINK href="stylesheet.css" rel="stylesheet" type="text/css">
  </HEAD>
<?php

$user = $_GET['user'];
$use_file_store_for_images = 0;
include_once "config.php";

if(isset($_GET['use_file_store_for_images'])){
    $use_file_store_for_images = 1;
}


echo "\n<table class=headertable>\n<tr>";
echo "<td class=header ><td class=header>";
echo "<h1 class=header><a class=title href='/index.php'>bookface</a></h1>";
echo "</tr></table><br><br>\n";
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
	echo "<! Memcache is enabled !>";
	$memcache = new Memcache();
	$memcache->addServer ( $memcache_server,"11211" );
    }
    
    $stmt = $dbh->query("select name,status,posts,comments,createdate,picture from users where userid = $user");   
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "\n<table class=headertable>\n<tr>";
    if ( $use_file_store_for_images ){
	echo "<td class=header ><img src='/images/" . trim($res['picture']) . "'><td class=header>";
    } else {
	echo "<td class=header ><img src='/showimage.php?user=" . $user . "'><td class=header>";
    }
    echo "<h2 class=hader>" . trim($res['name']) . "</h2>";
    echo "</tr></table>\n";
    echo "<b>Member since: " . $res['createdate'] . " posts: " . $res['posts'] . "</b><br>\n";						
    
    $posts_by_user = array();
    if ( isset($memcache) and $memcache ){
	$posts_by_user = $memcache->get("posts_by_$user");
    }
    
    if (empty($posts_by_user)){
	echo "<! No memcache object found: posts_by_$user !>\n";
	$posts_by_user = array();
	$sql = "select postid,text,postdate from posts where userid = $user order by postdate desc;";
	foreach ($dbh->query($sql) as $rec)
	  $posts_by_user[] = $rec;
	
	// cache for 10 minutes
	if ( $memcache ){
	    $memcache->set("posts_by_$user", $posts_by_user,0,600);
	}	
    }
    
    
    $postcount = 0;
    $table = "<table>\n";
    if ( isset($posts_by_user) ){
	foreach ( $posts_by_user as $res ){
	    $table .= "<! postID:". $res['postid'] . " !><tr><td class=post>" . $res['postdate'] . "</td><td class=postcontent>" . $res['text'] . "</td><tr>\n";
	    $postcount++;
	    
	    $key = "comments_on_" . $res['postid'];
	    $comments_on_post = array();
	    if (  isset($memcache) and $memcache ){
		$comments_on_post = $memcache->get($key);
	    }
	    
	    if( empty($comments_on_post)){
		echo "<! No memcache object for comment found: $key !>\n";
		$comments_on_post = array();
		$sql = "select commentid,text,userid,postdate from comments where postid = " . $res['postid'] . " order by postdate asc;";
		foreach ($dbh->query($sql) as $rec)
		  $comments_on_post[] = $rec;
		
		// cache for 10 minutes
		if ( $memcache ){
		    $memcache->set($key, $comments_on_post,0,600);
		}	
	    }
	    
	    if ( count($comments_on_post) > 0 ){
		$table .= "</table>\n<table class=commentrow>\n";
		foreach ( $comments_on_post as $cres ){
		    $stmt = $dbh->query("select name,picture from users where userid = $cres[userid]");
		    $ures = $stmt->fetch(PDO::FETCH_ASSOC);
		    if ( $use_file_store_for_images ){
			$table .= "<tr  ><td class=commentpost >" . $cres['postdate'] . "</td><td><a href='/showuser.php?user=" . $cres['userid']. "'><img src='/images/".trim($ures['picture']) ."'></a></td><td><b><a href='/showuser.php?user=" . $cres['userid']. "'>" . trim($ures['name']) . ": </a></b></td><td>" . $cres['text'] . "</td></tr>";
		    } else {
			$table .= "<tr  ><td class=commentpost >" . $cres['postdate'] . "</td><td><a href='/showuser.php?user=" . $cres['userid']. "'><img src='/showimage.php?user=".trim($cres['userid']) ."'></a></td><td><b><a href='/showuser.php?user=" . $cres['userid']. "'>" . trim($ures['name']) . ": </a></b></td><td>" . $cres['text'] . "</td></tr>";
		    }


		    
		}
		$comments_on_post = array();
		$table .= "</table>\n<table>\n";							
	    }
	}
    }
    $table .= "</table>\n";    
    echo "$table\n";
    echo "</table>\n";
} catch (Exception $e) {
    echo $e->getMessage() . "\r\n";
}


?>

</html>
