<HTML>
<?PHP
error_reporting(E_ALL);
$username = $_GET['user'];
$image = $_GET['image'];
echo "Creating user: " . $username . "<br>";
echo "image:" . $image . "<br>";
include_once "config.php";
$link = mysql_connect("$dbhost:$dbport", $dbuser,$dbpassw );
	if ($link){
#    	echo "Connection successful!\n<br>";
    	$bfdb = mysql_select_db($db,$link);
    	if ( !$bfdb ){
#				echo "Cannot use $db: " . mysql_error() ."<br>";
    	} else {
#			echo "Correct database found<br>\n";
			$img = mysql_real_escape_string(file_get_contents($image));
#			echo "$img</br>";
#			$fileimg = mysql_real_escape_string(file_get_contents('plot.png'));
			$result = mysql_query("insert into user (userID,name,picture,status,posts,comments,lastPostDate,createDate) values(NULL,'$username','$img','',0,0,NULL,NULL );");
#			echo "Result: " . mysql_error() . "<br>\n";			
			if ( ! mysql_error()){
			echo "OK";
			} else {
				mysql_error();
			}	
		}
	}
?>

</HTML>