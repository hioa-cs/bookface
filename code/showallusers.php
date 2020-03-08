<html>
<HEAD>
    <LINK href="stylesheet.css" rel="stylesheet" type="text/css">
  </HEAD>
<?php

include_once "config.php";


    
try {
    $dbh = new PDO('pgsql:host=' . $dbhost . ";port=" . $dbport . ";dbname=" . $db . ';sslmode=disable',$dbuser, null, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => true,));
    //	$bfdb = mysql_select_db($db,$link);
	#		echo "Correct database found<br>\n";
    
    
//    $result = mysql_query("select userID,name,status,posts,comments from user");
    $sql = "select userid,name,status,posts,comments from users";
    echo "<table>\n";
    foreach ($dbh->query($sql) as $rec){

	echo "<tr>\n";
	echo "<td><a href='/showuser.php?user=" . $rec['userid']. "'>" . $rec['name'] . "</a></td>";
	echo "<td>" . $rec['posts'] . "</td>";
	echo "<td><a href='/showuser.php?user=" . $rec['userid']. "'><img src='/showimage.php?user=$rec[userid]'></a></td>";
	echo "</tr></a>\n";
    }
    echo "</table>\n";
    

    
    
} catch (Exception $e) {
    echo $e->getMessage() . "\r\n";
}

    

?>
</html>
