<html>
<HEAD>
    <LINK href="stylesheet.css" rel="stylesheet" type="text/css">
  </HEAD>
<!-- bookface version 4 -->
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
   $link = mysqli_connect("$dbhost:$dbport", $dbuser, $dbpassw);
   if ($link){
   #    	echo "Connection successful!\n<br>";
   $bfdb = mysqli_select_db($link,$db);
   if ( !$bfdb ){
     echo "Cannot use $db: " . mysqli_error() ."<br>";
   } else {
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
       $result = mysqli_query($link, "select count(userID) from user;");
       echo "<table>\n";
       $row = mysqli_fetch_row($result);
       echo "<tr><td>Users: </td><td>" . $row[0] . "</td></tr>\n";
       $result = mysqli_query($link, "select count(postID) from posts;");
       $row = mysqli_fetch_row($result);
       #			echo "posts: " . $row[0] . "<br>\n";
       echo "<tr><td>Posts: </td><td>" . $row[0] . "</td></tr>\n";
       $result = mysqli_query($link, "select count(userID) from comments;");
       $row = mysqli_fetch_row($result);
       echo "<tr><td>Comments: </td><td>" . $row[0] . "</td></tr>\n";
       echo "</table>\n";
       $user_list_for_front_page = array();       
       echo "<h2>Latest activity</h2>\n";
       #			$user_list_for_front_page;
       if ( isset($memcache_enabled) and $memcache_enabled == 1 and $memcache ){
    	   $user_list_for_front_page = $memcache->get("user_list_for_front_page");
       }

       if ( empty($user_list_for_front_page) ) {

	      $sql = "select userID,name,status,posts,comments,lastPostDate from user order by lastPostDate desc";
	       if ( isset($frontpage_limit) ){
	         $sql = $sql . " limit $frontpage_limit";
	       }
	       $res = mysqli_query($link, $sql);
	       while($rec = mysqli_fetch_assoc($res)){
	         $user_list_for_front_page[] = $rec;
    	   }
	   // cache for 10 minutes
	       if ( isset($memcache) and $memcache ){
	       $memcache->set("user_list_for_front_page", $user_list_for_front_page,0,600);
	       }	
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
	   echo "<td $style ><a href='/showuser.php?user=" . $res['userID']. "'><img src='/showimage.php?user=$res[userID]'></a></td>";
	   echo "<td $style ><a href='/showuser.php?user=" . $res['userID']. "'>" . $res['name'] . "</a></td>";
	   echo "<td $style >" . $res['posts'] . "</td>";
	   
	   echo "</tr></a>\n";
       }
       echo "</table>\n";
       $totaltime = time() - $starttime;
       echo "load time: " . $totaltime . "s\n";
   }
       
   } else {
       echo "No connection to database at $dbhost on port $dbport<br>\n";
       
   }

	?>		
		
</html>
