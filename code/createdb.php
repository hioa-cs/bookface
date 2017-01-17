<html>
<HEAD>
    <LINK href="stylesheet.css" rel="stylesheet" type="text/css">
  </HEAD>
<?php
	$starttime = time();
	echo "\n<table class=headertable>\n<tr>";
			echo "<td class=header ><td class=header>";
						echo "<h1 class=header><a class=title href='$weburl'>bookface</a></h1>";
						echo "</tr></table>\n";
	include_once "config.php";
	$link = mysql_connect("$dbhost:$dbport", $dbuser, $dbpassw);
	if ($link){
	    $bfdb = mysql_select_db($db,$link);
	    if ( !$bfdb ){
		echo "Cannot use $db: " . mysql_error() ."<br>";
	    } else {
		$result = mysql_query("show tables;");
		$row = mysql_fetch_row($result);
		if ( $row ){
		    echo "Tables are created, proceed to index.php<br>\n";
		} else {
		    echo "No tables found, creating...\n";
		    mysql_query("create table user ( userID INT(10) NOT NULL AUTO_INCREMENT, name VARCHAR(100) , picture BLOB, status VARCHAR(500), posts INT NOT NULL, comments INT NOT NULL, lastPostDate datetime, createDate timestamp default now(), UNIQUE (userID));");
		    mysql_query("create table posts (postID INT(10) NOT NULL AUTO_INCREMENT, userID INT, text VARCHAR(1000), postDate timestamp default now(), UNIQUE (postID));");
		    mysql_query("create table comments ( commentID INT(10) NOT NULL AUTO_INCREMENT, postID INT, userID INT, text VARCHAR(500), postDate timestamp default now(), UNIQUE (commentID));");
		}
		
	    }
	}
?>
</HTML>
