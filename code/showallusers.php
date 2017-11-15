<html>
<HEAD>
    <LINK href="stylesheet.css" rel="stylesheet" type="text/css">
  </HEAD>
<?php
	$user = $_GET['user'];
include_once "config.php";
	$link = mysql_connect($host, 'bfuser', 'bfuserpassword');
	if ($link){
    #	echo "Connection successful!\n<br>";
    	$bfdb = mysql_select_db($db,$link);
    	if ( !$bfdb ){
				echo "Cannot use $db: " . mysql_error() ."<br>";
    	} else {
	#		echo "Correct database found<br>\n";
			$result = mysql_query("select userID,name,status,posts,comments from user");
			echo "<table>\n";
			while ( $res = mysql_fetch_array($result) ){
				echo "<tr>\n";
				echo "<td><a href='/showuser.php?user=" . $res['userID']. "'>" . $res['name'] . "</a></td>";
				echo "<td>" . $res['posts'] . "</td>";
				echo "<td><a href='/showuser.php?user=" . $res['userID']. "'><img src='/showimage.php?user=$res[userID]'></a></td>";
				echo "</tr></a>\n";
			}
			echo "</table>\n";
			
		}
	}

?>
</html>
