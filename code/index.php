<html>
<HEAD>
    <LINK href="stylesheet.css" rel="stylesheet" type="text/css">
  </HEAD>
     <!-- bookface version 23 -->
  <body>
<?php
$starttime = time();
$use_file_store_for_images = 0;
$frontpage_cutoff_days = "";
$fast_random_search = 0;
$fast_cutoff_search = 0;

$characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

include_once "config.php";

echo "<div class=\"container\" >\n";

echo "<header>\n";
echo "<h1 class=header><a class=title href='/index.php'>bookface</a></h1>";
echo "</header>\n";

$memcache = "";

if(isset($_GET['use_file_store_for_images']) or (isset($use_local_images) and $use_local_images)){
    $use_file_store_for_images = 1;
}

if ( isset($use_activity_cutoff_days) and $use_activity_cutoff_days > 1 ){
    echo "<! cutoff days is enabled for frontpage: " . $use_activity_cutoff_days . "!>\n";
    $frontpage_cutoff_days = $use_activity_cutoff_days;
}

if ( isset($use_activity_cutoff_random_search) and $use_activity_cutoff_random_search > 1 ){
    echo "<! cutoff days is enabled for random searches: " . $use_activity_cutoff_random_search . "!>\n";
    $fast_cutoff_search = $use_activity_cutoff_random_search;
}
if ( isset($use_fast_random_search) and $use_fast_random_search == 1 ){
    $fast_random_search = $use_fast_random_search;
}

if ( isset($replica_dbhost) ){
    $dbhost = $replica_dbhost;
}

function get_random_user($dbh){

    global $fast_cutoff_search;
    global $fast_random_search;

    if ( isset($fast_cutoff_search) and $fast_cutoff_search > 1 ){
	echo "<! Trying cutoff-based random search " . $fast_cutoff_search . " !>\n";    	
	#	  $start_interval = rand(0,$frontpage_cutoff_days);

	$start_interval = date("Y-m-d", strtotime("-" . $fast_cutoff_search . " days"));
	$end_interval = date("Y-m-d", strtotime("-" . ($fast_cutoff_search + $fast_cutoff_search) . " days"));
	echo "<! cutoff start date: " . $start_interval . " and end date: " . $end_interval ." !>\n";
	$sql = "select userid from users where ( lastPostDate >= '" . $end_interval . "' and lastPostDate <= '" . $start_interval . "' ) order by random() limit 1;";
	$stmt = $dbh->query($sql);
	$row = $stmt->fetch(PDO::FETCH_ASSOC);
	$random_user = $row['userid'];
	if ( $random_user > 1 ){
	    return $random_user;
	}
    } 

    if ( isset($fast_random_search) and $fast_random_search == 1 ){
	echo "<! Trying fast random search !>\n";    
	$character = $characters[rand(0, strlen($characters))];
	$random_user = "";
	
	$stmt = $dbh->query('select userID from users where ( name like \''. $character .'%\') order by random() limit 1;');
	$row = $stmt->fetch(PDO::FETCH_ASSOC);
	$random_user = $row['userid'];
	
	if ( $random_user > 1 ){
	    return $random_user;
	}
    }
    
    $stmt = $dbh->query('select userID from users order by random() limit 1;');
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $random_user = $row['userid'];
    
    return $random_user;
    
}

function get_random_poster($dbh){
    
    global $fast_random_search;
    global $fast_cutoff_search;


    if ( isset($fast_cutoff_search) and $fast_cutoff_search > 1 ){
	#	  $start_interval = rand(0,$frontpage_cutoff_days);
	echo "<! Trying cutoff-based random post search " . $fast_cutoff_search . " !>\n";    	
	$start_interval = date("Y-m-d", strtotime("-" . $fast_cutoff_search . " days"));
	$end_interval = date("Y-m-d", strtotime("-" . ($fast_cutoff_search + $$fast_cutoff_search) . " days"));
	echo "<! cutoff start date: " . $start_interval . " and end date: " . $end_interval ." !>\n";
	$sql = "select postid,userid from posts where ( PostDate >= '" . $end_interval . "' and PostDate <= '" . $start_interval . "' ) order by random() limit 1;";
	$stmt = $dbh->query($sql);
	$row = $stmt->fetch(PDO::FETCH_ASSOC);
	$random_user = $row['userid'];
	if ( $random_user > 1 ){
	    return $row;
	}
    } 
    
    if ( $fast_random_search > 0 ){
	echo "<! Trying fast random poster search !>\n";
	$character = $characters[rand(0, strlen($characters))];
	$row = "";
	
	$stmt = $dbh->query('select postid,userid from posts where ( text like \''. $character . '%\') order by random() limit 1;');
	
	$row = $stmt->fetch(PDO::FETCH_ASSOC);
	$random_user = $row['userid'];
	if ( $random_user >= 1 ){
	    echo "<! Fast random search worked !>\n";
	    return $row;
	}
    }
    $stmt = $dbh->query('select postid,userid from posts order by random() limit 1;');
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $row;
    
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

    ### Users
    if (  isset($memcache_enabled) and $memcache_enabled == 1 and $memcache_for_random ){
        echo "<! using memcache for randomized users !>\n";
        $random_user = $memcache->get("random_user_id");
        if ( $random_user < 1){
	    
	    $random_user = get_random_user($dbh);
	    
            $memcache->set("random_user_id", $random_user,0,30); 
        }
        echo "<! Random user: " . $random_user . " !>\n";

        
    } else {
        echo "<! Random user: " . get_random_user($dbh) . " !>\n";
    }

    ### POST and USER
    if (  isset($memcache_enabled) and $memcache_enabled == 1 and $memcache_for_random ){
        echo "<! using memcache for randomized posters !>\n";
        $random_userid = $memcache->get("random_userid");
        $random_postid = $memcache->get("random_postid");
        if ( $random_userid < 1){
	    echo "<! Cache miss. Going to the DB !>\n";
	    $row = get_random_poster($dbh);
            
            $random_userid = $row['userid'];
            $random_postid = $row['postid'];

            $memcache->set("random_userid", $random_userid,0,60);
            $memcache->set("random_postid", $random_postid,0,60); 
        }
        echo "<! Random post: " . $random_postid . " !>\n";
        echo "<! Random poster: " . $random_userid . " !>\n";
    } else {
	$row = get_random_poster($dbh);
        echo "<! Random post: " . $row['postid'] . " !>\n";
        echo "<! Random poster: " . $row['userid'] . " !>\n";
    }

    
    if ( isset($memcache_enabled) and $memcache_enabled == 1 and $memcache_for_counters ){
        $user_count = $memcache->get("user_count");
        $posts_count = $memcache->get("posts_count");
        $comments_count = $memcache->get("comments_count");
        echo "<! Using memcache to display counters !>\n";
        if ( $user_count < 1 ){
	    echo "<! Count too low or Cache miss. Going to the DB !>\n";
            
	    $stmt = $dbh->query('select count(userID) from users;');
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $user_count = $row['count'];
        
            $stmt = $dbh->query('select count(postID) from posts;');
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $posts_count = $row['count'];
        
            $stmt = $dbh->query('select count(commentID) from comments;');
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $comments_count = $row['count'];

            $memcache->set("user_count",$user_count,0,60);
            $memcache->set("posts_count",$posts_count,0,60);
            $memcache->set("comments_count",$comments_count,0,60);
        }

        echo "<table class=info-table >\n";    
        echo "<tr><td>Users: </td><td>" . $user_count . "</td></tr>\n";
        
        echo "<tr><td>Posts: </td><td>" . $posts_count . "</td></tr>\n";
        
        echo "<tr><td>Comments: </td><td>" . $comments_count . "</td></tr>\n";
        echo "</table>\n";
		       
    
    } else {
        
        echo "<table class=info-table >\n";    
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
#    echo "<h2>Latest activity</h2>\n";

    if ( isset($memcache_enabled) and $memcache_enabled == 1 and $memcache ){
	$user_list_for_front_page = $memcache->get("user_list_for_front_page");
    }
    
    if ( empty($user_list_for_front_page) ) {
 	$sql = "";
        if ( isset($frontpage_cutoff_days) and $frontpage_cutoff_days ){
            # get date for X days since cutoff
#            $cutoff = date('Y-m-d', strtotime('-' . $frontpage_cutoff_days .' days', strtotime(date())));
            $cutoff = date("Y-m-d", strtotime("-" . $frontpage_cutoff_days . " days"));
            echo "<! cutoff date: " . $cutoff . "!>\n";
            $sql = "select userID,name,status,posts,comments,lastPostDate,picture from users where ( lastPostDate >= '" . $cutoff . "' ) order by lastPostDate desc";
        } else {
	    $sql = "select userID,name,status,posts,comments,lastPostDate,picture from users order by lastPostDate desc";
	}
	if ( isset($frontpage_limit) ){
	    $sql = $sql . " limit $frontpage_limit";
	}
	# $res = $dbh->query($sql);
	foreach ($dbh->query($sql) as $rec)
	  $user_list_for_front_page[] = $rec;
    }
    // cache for 10 minutes
    if ( isset($memcache) and $memcache ){
	$memcache->set("user_list_for_front_page", $user_list_for_front_page,0,60);
    }	

    
    
//    echo "<tr><td></td><td>Name:</td><td>Posts</td></tr>\n";
    $alternator = 0;
    foreach ( $user_list_for_front_page as $res ){
	$style = "class=header";
	echo "<div class=user-row >\n";	
	echo "\t<! userline: " . $res['userid'] . ">\n";
	echo "\t<div class=user-details >";

	if ( $use_file_store_for_images ){
	    echo "\t\t<a href='/showuser.php?user=" . $res['userid']. "'><img class='profile-image' src='/images/" . trim($res['picture']) . "'></a>\n";
	} else {
	    echo "\t\t<a href='/showuser.php?user=" . $res['userid']. "'><img class='profile-image' src='/showimage.php?user=" . trim($res['userid']) . "'></a>\n";
	}

	echo "\t\t<h3 class=header><a href='/showuser.php?user=" . $res['userid']. "'>" . trim($res['name']) . "</a></h3>\n";;
	echo "\t\t<b>Posts: " . $res['posts'] . "</b>\n";
	echo "\t</div>\n";
	
	
	if ( $res['posts'] > 0 ){
	    # show latest post

	    echo "\t<div class=user-posts >\n";
	    
	    $latest_post_by_user = array();
	    if ( isset($memcache) and $memcache ){
		$latest_post_by_user = $memcache->get("latest_post_by_" . $res['userid']);
		
		if (empty($latest_post_by_user)){
		    echo "\t\t<! No memcache object found: latest_post_by_" . $res['userid'] . ", fetching from DB instead !>\n";
		    $latest_post_by_user = array();
		    $sql = "select postid,text,postdate,image from posts where userid = '" . $res['userid'] . "' order by postdate desc LIMIT 1;";
		    $latest_post_query = $dbh->query($sql);		  
		    $latest_post_by_user = $latest_post_query->fetch(PDO::FETCH_ASSOC);
		    // cache for an hour
		    if ( isset($memcache) and $memcache ){
			echo "\t\t<! Storing into memcache for next time: latest_post_by_" . $res['userid'] . " : " . $latest_post_by_user['text'] . " !>\n";  
			$memcache->set("latest_post_by_" . $res['userid'], $latest_post_by_user,0,3600);
		    }
		}
	    } else {
		$sql = "select postid,text,postdate,image from posts where userid = '" . $res['userid'] . "' order by postdate desc LIMIT 1;";
		$latest_post_query = $dbh->query($sql);		  
		$latest_post_by_user = $latest_post_query->fetch(PDO::FETCH_ASSOC);
	    }
	    
	    echo "\t\t<! postID:". $latest_post_by_user['postid'] . " !>\n";
            $formatted_date = date('F j, Y g:i A', strtotime($latest_post_by_user['postdate']));

            echo "\t\t<div class=post-content >\n";
	    echo "\t\t\t<strong>" . $formatted_date . "</strong>\n";
	    echo "\t\t\t<p>" . $latest_post_by_user['text'] . "</p>\n";
	    


	    if( $latest_post_by_user['image'] ){
		echo "\t\t<! image for post: " . $latest_post_by_user['image'] . ">\n";
		if ( $use_file_store_for_images ){
		    echo "\t\t<img class='post-image' src='/images/" . trim($latest_post_by_user['image']) . "'>\n";
		} else {
		    echo "\t\t<img class='post-image' src='/postimage.php?image=" . trim($latest_post_by_user['image']) . "'>\n";
		}		
	    }
	    echo"\t\t</div>\n";
	    echo "\t</div>\n";

	}
	
	echo "</div>\n";

    }
    echo "</div>\n";
    echo "</div>\n";
    
    $totaltime = time() - $starttime;
    echo "load time: " . $totaltime . "s\n";
   
} catch (Exception $e) {
    echo $e->getMessage() . "\r\n";
}

?>		
		
</body>
</html>
