<HTML>
<?PHP
error_reporting(E_ALL);
$username = $_GET['user'];
$image = $_GET['image'];
echo "Creating user: " . $username . "<br>";
echo "image:" . $image . "<br>\n";
include_once "config.php";
try {
    $dbh = new PDO('pgsql:host=' . $dbhost . ";port=" . $dbport . ";dbname=" . $db . ';sslmode=disable',$dbuser, null, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => true,));
#    $fileData = $dbh->pgsqlLOBCreate();
#    $stream = $this->pdo->pgsqlLOBOpen($fileData, 'w');
#    $fh = fopen($pathToFile, 'rb');
#    stream_copy_to_stream($fh, $stream);
                //
#    $fh = null;
#    $stream = null;
#    $img = file_get_contents($image);
    $query =  "insert into users (name,picture,status,posts,comments) values(:username,:image,'',0,0 );";
    $stmt = $dbh->prepare($query);
    $stmt->execute([
		    ':username' => $username,
		    ':image' => $image,
		    ]);

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