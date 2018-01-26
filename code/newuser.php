<HTML>
<?PHP
error_reporting(E_ALL);
$username = $_GET['user'];
$image = $_GET['image'];
echo "Creating user: " . $username . "<br>";
echo "image:" . $image . "<br>";
include_once "config.php";
$link = mysqli_connect("$dbhost:$dbport", $dbuser,$dbpassw );
	if ($link){
#    	echo "Connection successful!\n<br>";
    	$bfdb = mysqli_select_db($link,$db);
    	if ( !$bfdb ){
#				echo "Cannot use $db: " . mysqli_error() ."<br>";
    	} else {
#			echo "Correct database found<br>\n";
			$img = mysqli_real_escape_string($link, file_get_contents($image));
#			echo "$img</br>";
#			$fileimg = mysqli_real_escape_string($link, file_get_contents('plot.png'));
			$result = mysqli_query($link, "insert into user (userID,name,picture,status,posts,comments,lastPostDate,createDate) values(NULL,'$username','$img','',0,0,NULL,NULL );");
#			echo "Result: " . mysqli_error() . "<br>\n";			
			if ( ! mysqli_error()){
			echo "OK";
			} else {
				mysqli_error();
			}	
		}
	}
?>

</HTML>
