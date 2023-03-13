<html>
<HEAD>
    <LINK href="stylesheet.css" rel="stylesheet" type="text/css">
  </HEAD>
<?php

include_once "config.php";
$use_file_store_for_images = 0;
if(isset($_GET['use_file_store_for_images']) or (isset($use_local_images) and $use_local_images)){
    $use_file_store_for_images = 1;
}

    
try {
    $dbh = new PDO('pgsql:host=' . $dbhost . ";port=" . $dbport . ";dbname=" . $db . ';sslmode=disable',$dbuser, null, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => true,));
    //	$bfdb = mysql_select_db($db,$link);
	#		echo "Correct database found<br>\n";
    
    
//    $result = mysql_query("select userID,name,status,posts,comments from user");

    // Count the number of users, posts, comments and images
    $stmt = $dbh->query('select count(userID) from users;');
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $user_count = $row['count'];
    echo "users: " . $row['count'] . "\n";
    
    $stmt = $dbh->query('select count(postID) from posts;');
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "posts: " . $row['count'] . "\n";
        
    $stmt = $dbh->query('select count(commentID) from comments;');
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "comments: " . $row['count'] . "\n";

    $stmt = $dbh->query('select count(pictureID) from pictures;');
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "pictures: " . $row['count'] . "\n";
    
    $sql = "select userid,name,status,posts,comments,picture from users";
    echo "<table>\n";
    foreach ($dbh->query($sql) as $rec){

	echo "<tr>\n";
	echo "<td><a href='/showuser.php?user=" . $rec['userid']. "'>" . $rec['name'] . "</a></td>";
	echo "<td>" . $rec['posts'] . "</td>";
	if ( $use_file_store_for_images ){
	    echo "<td><a href='/showuser.php?user=" . $rec['userid']. "'><img src='images/" . $rec['picture'] . "'></a></td>";
	} else {
	    echo "<td><a href='/showuser.php?user=" . $rec['userid']. "'><img src='/showimage.php?user=$rec[userid]'></a></td>";
	}
	echo "</tr></a>\n";
    }
    echo "</table>\n";
    

    
    
} catch (Exception $e) {
    echo $e->getMessage() . "\r\n";
}

    

?>
</html>
