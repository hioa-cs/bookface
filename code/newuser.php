<HTML>
<?PHP

function generateRandomString($length = 30) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
	            $randomString .= $characters[rand(0, $charactersLength - 1)];
	}
        return $randomString;
}

error_reporting(E_ALL);
$username = $_GET['user'];
$image = $_GET['image'];
$use_file_store_for_images = 0;
echo "Creating user: " . $username . "<br>";
echo "image:" . $image . "<br>\n";
include_once "config.php";

if(isset($_GET['use_file_store_for_images'])){
    $use_file_store_for_images = 1;
}


try {
    $dbh = new PDO('pgsql:host=' . $dbhost . ";port=" . $dbport . ";dbname=" . $db . ';sslmode=disable',$dbuser, null, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => true,));
#    $img = imagecreatefromjpeg($image);

	$imagestring = generateRandomString() . ".jpg";
	#    imagejpeg($img, "images/file.jpg");
	#    $img = file_get_contents($image);


	$query =  "insert into users (name,picture,status,posts,comments) values(:username,:image,'',0,0 );";
	$stmt = $dbh->prepare($query);
	$stmt->execute([
			':username' => $username,
			':image' => $imagestring,
			]);
    if($use_file_store_fore_images){
	file_put_contents("images/" . $imagestring, file_get_contents($image));	
    } else {	
	$img = pg_escape_bytea(file_get_contents($image));
	$image_query =  "insert into pictures (pictureID,picture) values('". $imagestring . "','$img');";
#	echo "\n " . $image_query;
	$stmt = $dbh->prepare($image_query);
	$stmt->execute([]);
	
    }

} catch (Exception $e) {
        echo $e->getMessage() . "\r\n";
}
#$link = mysql_connect("$dbhost:$dbport", $dbuser,$dbpassw );
#	if ($link){
#    	echo "Connection successful!\n<br>";
#    	$bfdb = mysql_select_db($db,$link);
#    	if ( !$bfdb ){
#				echo "Cannot use $db: " . mysql_error() ."<br>";
#    	} else {
#			echo "Correct database found<br>\n";
			#$img = mysql_real_escape_string(file_get_contents($image));
#			echo "$img</br>";
#			$fileimg = mysql_real_escape_string(file_get_contents('plot.png'));
#			$result = mysql_query(
#			echo "Result: " . mysql_error() . "<br>\n";			
#			if ( ! mysql_error()){
#			echo "OK";
#			} else {
#				mysql_error();
#			}	
#		}
#	}
?>

</HTML>