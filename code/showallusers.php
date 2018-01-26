<html>
<HEAD>
    <LINK href="stylesheet.css" rel="stylesheet" type="text/css">
  </HEAD>
<?php
	$user = $_GET['user'];
include_once "config.php";
	$link = mysqli_connect($host, 'bfuser', 'bfuserpassword');
	if ($link){
    #	echo "Connection successful!\n<br>";
    	$bfdb = mysqli_select_db($link,$db);
    	if ( !$bfdb ){
				echo "Cannot use $db: " . mysqli_error() ."<br>";
    	} else {
	#		echo "Correct database found<br>\n";
			$result = mysqli_query($link, "select userID,name,status,posts,comments from user");
			echo "<table>\n";
			while ( $res = mysqli_fetch_array($result) ){
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
