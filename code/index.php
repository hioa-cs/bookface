<html>
<HEAD>
    <LINK href="stylesheet.css" rel="stylesheet" type="text/css">
  </HEAD>
<!-- bookface version 5 -->
<?php
   $starttime = time();
   include_once "config.php";
   echo "\n<table class=headertable>\n<tr>";
   echo "<td class=header ><td class=header>";
   echo "<h1 class=header><a class=title href='/index.php'>bookface</a></h1>";
   echo "</tr></table>\n";
   #	echo "<h1>BookFace</h1>\n";	
   
if ( isset($replica_dbhost) ){
    $dbhost = $replica_dbhost;
}
try {
    $dbh = new PDO('pgsql:host=' . $dbhost . ";port=" . $dbport . ";dbname=" . $db . ';sslmode=disable',$dbuser, null, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => true,));
   #$link = mysql_connect("$dbhost:$dbport", $dbuser, $dbpassw);
   #if ($link){
   #    	echo "Connection successful!\n<br>";
   #$bfdb = mysql_select_db($db,$link);
   #if ( !$bfdb ){
   #  echo "Cannot use $db: " . mysql_error() ."<br>";
   # } else {
    $memcache_override = 0;
    if ( isset($_GET['nomemcache'])){
	$memcache_override = $_GET['nomemcache'];
    }

    
    if ( isset($memcache_enabled) and $memcache_enabled == 1 and ! $memcache_override ){
	echo "<! Memcache is enabled !>";
	$memcache = new Memcache();
	$memcache->addServer ( $memcache_server,"11211" );
    }
       
    #			echo "Correct database found<br>\n";
    $stmt = $dbh->query('select userID from users order by random() limit 1;');
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<! Random poster: " . $row['userid'] . " !>\n";
    $stmt = $dbh->query('select postid,userid from posts order by random() limit 1;');
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<! Random post: " . $row['postid'] . " !>\n";
    echo "<! Random user: " . $row['userid'] . " !>\n";
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
    $user_list_for_front_page = array();       
    echo "<h2>Latest activity</h2>\n";
    #			$user_list_for_front_page;
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
	echo "<td $style ><a href='/showuser.php?user=" . $res['userid']. "'><img src='/images/" . $res['picture'] . "'></a></td>";
	echo "<td $style ><a href='/showuser.php?user=" . $res['userid']. "'>" . $res['name'] . "</a></td>";
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
