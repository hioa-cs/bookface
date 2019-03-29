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
$oldip = $_GET['entrypoint'];
$migration_key = $_GET['key'];

include_once "config.php";

// $link = mysqli_connect("$olddb:$oldport", $olduser,$oldpassword );
// if ($link){
//     $bfdb = mysqli_select_db($link,$olddbname);
// } else {	   
//     echo "Cannot use $db: " . mysqli_error($link) ."<br>";
// }

try {
    $dbh = new PDO('pgsql:host=' . $dbhost . ";port=" . $dbport . ";dbname=" . $db . ';sslmode=disable',$dbuser, null, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => true,));

	try {
		$stmt = $dbh->query('select value from config where key = \'migration_key\';');
    	$row = $stmt->fetch(PDO::FETCH_ASSOC);

	    $config_key = $row['value'];

		if ( ! $config_key ){
			echo "This system is not set up to import data. You have to specify a migration key first\n";
		}

	    if ( ! $config_key == $migration_key ) {
 		   	echo "The migration key does not match\n";
 		   	exit(1);
    	}
	} catch (Exception $e) {
		    echo $e->getMessage() . "\r\n";
		    exit(1);
	}

    	

	$stmt = $dbh->query('select max(userid) from users;');
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $max = $row['max'];
    
    $oldpage = file("http://" . $oldip . "/showallusers.php");
    $counter = 0;
    $dbcounter = 0;
    
    foreach ( $oldpage as $line ){
	if ( preg_match("/user=(\d+)\'>([^<]+)/",$line,$result )){
	    
	    echo "found user: " . $result[2] . " <br>\n";
	    echo "User ID: " . $result[1] . " <br>\n";
		if ( $result[1] <= $max ) {
			continue;
		}
	    
	    $imageurl = "http://" . $oldip . "/showimage.php?user=" . $result[1] . "<br>\n";
	    echo "Image: " . $imageurl;

		# attemp to insert user
	    $imagestring = generateRandomString() . ".jpg";
#    imagejpeg($img, "images/file.jpg");
#    $img = file_get_contents($image);
   	   file_put_contents("images/" . $imagestring, file_get_contents($imageurl));

		$showuser = file("http://" . $oldip . "/showuser.php?user=" . $result[1]);
		$startdate = "";
		$posts = "";
		$lastpost = null;
		$lastpostid = 0;
		foreach ( $showuser as $shline ){
		  if ( preg_match("/Member since: (.*) posts: (\d+)</",$shline,$shresult )){
			$startdate = $shresult[1];
			$posts = $shresult[2];
		  } else if ( preg_match("/postID:(\d+) .*post>(.*)<.td>.*postcontent>(.*)<.td><tr>/",$shline,$shresult )){
#		    $postresult = $dbh->query("update users set posts = $posts, lastpostdate = now() where userID = $user" );
			$postquery = "insert into posts (postid,text,userid,postdate) values('" . $shresult[1] . "','" . $shresult[3] . "','" . $result[1] . "','" . $shresult[2] . "');";
#			echo "Postquery: " . $postquery . "\n";
			try {
    		$postresult = $dbh->query($postquery);
    		} catch (Exception $e) {
    		        echo $e->getMessage() . "\r\n";
    		}
    		$lastpostid = $shresult[1];
		  } else if ( preg_match("/commentpost /",$shline,$shresult )){
#		  	echo "Found comment: " . $shline . "\n";
			$commentarray = explode("</tr>", $shline, 10000);
			foreach ( $commentarray as $comment ){
#			    echo "single comment: " . $comment . "\n";
			    if ( preg_match("/.*commentpost >(.*:\d\d).*user=(\d+)'.*<\/td><td>(.*)<\/td>/",$comment,$cresult)){
				 echo "Comment: " . $cresult[3]. "\n";
				 try {
	       		 $postresult = $dbh->query("insert into comments (postid,userid,postdate,text) values('" . $lastpostid . "','" . $cresult[2] . "','" . $cresult[1] . "','" . $cresult[3] . "');");								
	       		 } catch (Exception $e) {
	       		         echo $e->getMessage() . "\r\n";
	       		 }
	    		}
			}
  		  }
		}
       $query =  "insert into users (userid,name,picture,status,posts,comments,createdate,lastpostdate) values(:userid,:username,:image,'',:posts,0,:createdate,:lastpostdate );";
       $stmt = $dbh->prepare($query);
       $username = substr($result[2],0,49);
       $stmt->execute([
       	    ':userid' => $result[1],
       	    ':posts' => $posts,
       	    ':createdate' => $startdate,
		    ':username' => $username,
		    ':image' => $imagestring,
		    ':lastpostdate' => $lastpost
		    ]);
		    $counter++;
		    $dbcounter++;

	   if ( $dbcounter > 100 ){
		    $dbh = null;
		    $dbh = new PDO('pgsql:host=' . $dbhost . ";port=" . $dbport . ";dbname=" . $db . ';sslmode=disable',$dbuser, null, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => true,));		    
	   		$dbcounter = 0;
	   }
	}
	
    }
    echo "<h1>Added " . $counter . " users</h1>\n";
    # get list of users ( everything but the picture )
    # userID,name,picture,status,posts,comments,lastPostDate,createDate
    // $sql = "select userID,name,status,posts,comments,lastPostDate from user order by userID desc";
    // $res = mysqli_query($link, $sql);
    // while($rec = mysqli_fetch_assoc($res)){
	
    // 	echo "Found user: " . $res['userID'] . "\n";
    // 	exit(0);
	
    // 	# prepare the URL for the image
    
    // 	# Try to insert it with existing ID

    
    // }
    
    
    
    
    
    #    $img = imagecreatefromjpeg($image);


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

