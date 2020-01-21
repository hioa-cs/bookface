<html>
<HEAD>
    <LINK href="stylesheet.css" rel="stylesheet" type="text/css">
  </HEAD>
     <!-- bookface version 9 -->
<?php
$starttime = time();
$use_file_store_for_images = 0;
include_once "config.php";
echo "\n<table class=headertable>\n<tr>";
echo "<td class=header ><td class=header>";
echo "<h1 class=header><a class=title href='/index.php'>bookface</a></h1>";
echo "</tr></table>\n";

if(isset($_GET['use_file_store_for_images'])){
    $use_file_store_for_images = 1;
}


if ( isset($replica_dbhost) ){
    $dbhost = $replica_dbhost;
}
try {
    if ( isset($dbpassw) ){
	    $dbh = new PDO('pgsql:host=' . $dbhost . ";port=" . $dbport . ";dbname=" . $db . ';sslmode=disable',$dbuser, $dbpassw, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => true,));
	} else {
        $dbh = new PDO('pgsql:host=' . $dbhost . ";port=" . $dbport . ";dbname=" . $db . ';sslmode=disable',$dbuser, null, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => true,));
	}

    $memcache_override = 0;
    if ( isset($_GET['nomemcache'])){
        $memcache_override = $_GET['nomemcache'];
    }
    
    if ( isset($memcache_enabled) and $memcache_enabled == 1 and ! $memcache_override ){
	echo "<! Memcache is enabled !>";
	$memcache = new Memcache();
	$memcache->addServer ( $memcache_server,"11211" );
    }

    $memcache_for_random = 1;
    $memcache_for_counters = 1;
    #			echo "Correct database found<br>\n";

    ### Users
    if (  isset($memcache_enabled) and $memcache_enabled == 1 and $memcache_for_random ){
        echo "<! using memcache for randomized users !>\n";
        $random_user = $memcache->get("random_user_id");
        if ( $random_user < 1){
            $stmt = $dbh->query('select userID from users order by random() limit 1;');
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $random_user = $row['userid'];
            $memcache->set("random_user_id", $random_user,0,30); 
        }
        echo "<! Random poster: " . $random_user . " !>\n";

        
    } else {

        $stmt = $dbh->query('select userID from users order by random() limit 1;');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<! Random poster: " . $row['userid'] . " !>\n";
    }

    ### POST and USER
    if (  isset($memcache_enabled) and $memcache_enabled == 1 and $memcache_for_random ){
        echo "<! using memcache for randomized posters !>\n";
        $random_userid = $memcache->get("random_userid");
        $random_postid = $memcache->get("random_postid");
        if ( $random_userid < 1){
            $stmt = $dbh->query('select postid,userid from posts order by random() limit 1;');
      
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            
            $random_userid = $row['userid'];
            $random_postid = $row['postid'];

            $memcache->set("random_userid", $random_userid,0,60);
            $memcache->set("random_postid", $random_postid,0,60); 
        }
        echo "<! Random post: " . $random_postid . " !>\n";
        echo "<! Random user: " . $random_userid . " !>\n";
    } else {
        $stmt = $dbh->query('select postid,userid from posts order by random() limit 1;');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<! Random post: " . $row['postid'] . " !>\n";
        echo "<! Random user: " . $row['userid'] . " !>\n";
    }

    
    if ( isset($memcache_enabled) and $memcache_enabled == 1 and $memcache_for_counters ){
        $user_count = $memcache->get("user_count");
        $posts_count = $memcache->get("posts_count");
        $comments_count = $memcache->get("comments_count");
        echo "<! Using memcache to display counters !>\n";
        if ( $user_count < 100 ){
            $stmt = $dbh->query('select count(userID) from users;');
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $user_count = $row['count'];
        
            $stmt = $dbh->query('select count(postID) from posts;');
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $posts_count = $row['count'];
        
            $stmt = $dbh->query('select count(commentID) from comments;');
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $comments_count = $row['count'];

            $user_count = $memcache->set("user_count",$user_count,0,180);
            $posts_count = $memcache->set("posts_count",$posts_count,0,180);
            $comments_count = $memcache->set("comments_count",$comments_count,0,180);
        }

        echo "<table>\n";    
        echo "<tr><td>Users: </td><td>" . $user_count . "</td></tr>\n";
        
        echo "<tr><td>Posts: </td><td>" . $posts_count . "</td></tr>\n";
        
        echo "<tr><td>Comments: </td><td>" . $comments_count . "</td></tr>\n";
        echo "</table>\n";
		       
    
    } else {
        
        echo "<table>\n";    
        $stmt = $dbh->query('select count(userID) from users;');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<tr><td>Users: </td><td>" . $row['count'] . "</td></tr>\n";
    
        $stmt = $dbh->query('select count(postID) from posts;');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<tr><td>Posts: </td><td>" . $row['count'] . "</td></tr>\n";

        $stmt = $dbh->query('select count(commentID) from comments;');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<tr><td>Comments: </td><td>" . $row['count'] . "</td></tr>\n";
        echo "</table>\n";

    }
    
    $user_list_for_front_page = array();       
    echo "<h2>Latest activity</h2>\n";

    if ( isset($memcache_enabled) and $memcache_enabled == 1 and $memcache ){
	$user_list_for_front_page = $memcache->get("user_list_for_front_page");
    }
    
    if ( empty($user_list_for_front_page) ) {

	$sql = "select userID,name,status,posts,comments,lastPostDate,picture from users order by lastPostDate desc";
	if ( isset($frontpage_limit) ){
	    $sql = $sql . " limit $frontpage_limit";
	}
	# $res = $dbh->query($sql);
	foreach ($dbh->query($sql) as $rec)
	  $user_list_for_front_page[] = $rec;
    }
    // cache for 10 minutes
    if ( isset($memcache) and $memcache ){
	$memcache->set("user_list_for_front_page", $user_list_for_front_page,0,600);
    }	

    echo "<table class=row >\n";
    echo "<tr><td></td><td>Name:</td><td>Posts</td></tr>\n";
    $alternator = 0;
    foreach ( $user_list_for_front_page as $res ){
	$style = "class=row";
	if ( $alternator % 2 ){
	    $style = "";					
	}
	$alternator++;
	echo "<tr >\n";
	if ( $use_file_store_for_images ){
	    echo "<td $style ><a href='/showuser.php?user=" . $res['userid']. "'><img src='/images/" . trim($res['picture']) . "'></a></td>";
	} else {
	    echo "<td $style ><a href='/showuser.php?user=" . $res['userid']. "'><img src='/showimage.php?user=" . trim($res['userid']) . "'></a></td>";
	}
	echo "<td $style ><a href='/showuser.php?user=" . $res['userid']. "'>" . trim($res['name']) . "</a></td>";
	echo "<td $style >" . $res['posts'] . "</td>";
	
	echo "</tr></a>\n";
    }
    echo "</table>\n";
    $totaltime = time() - $starttime;
    echo "load time: " . $totaltime . "s\n";
   
} catch (Exception $e) {
    echo $e->getMessage() . "\r\n";
}

?>		
		
</html>
