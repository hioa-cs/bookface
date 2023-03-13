<HTML>
<?PHP
ini_set('max_execution_time', 0);

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

$SQL_CODE_FILE = "/tmp/sql_bookface_insert.sql";

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
    
    echo "Connected to local DB, preparing to import data...\n";
    	

    $stmt = $dbh->query('select max(userid) from users;');
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $max = $row['max'];
    
    $user_list = array();       

    $sql = "select userID from users;";
    foreach ($dbh->query($sql) as $rec){
	$user_list[] = $rec[0];
    }

    $post_list = array();       
    $sql = "select postID from posts;";
    foreach ($dbh->query($sql) as $rec)
	  $post_list[] = $rec[0];

    $comment_list = array();       
    $sql = "select commentID from comments;";
    foreach ($dbh->query($sql) as $rec)
	  $comment_list[] = $rec[0];

    
    $picture_list = array();       
    try {
    $sql = "select pictureID from pictures;";
    foreach ($dbh->query($sql) as $rec)
	  $picture_list[] = $rec[0];
    } catch (Exception $e) {
	echo "The local database does not have a pictures table, assuming we store them as files\n";
    }
    
    $oldpage = file("http://" . $oldip . "/showallusers.php");
    $counter = 0;

    $dbcounter = 0;

    $pictures_total = 0; # $pictures_all - $user_count;
    $users_total = 0;
    $posts_total = 0;
    $comments_total = 0;
    $total_content = 0; # $user_count + $posts_count + $comment_count + $pictures_count;

    $users_counter = 0;
    $posts_counter = 0;
    $comment_counter = 0;
    $pictures_counter = 0;
    $total_counter = 0;
    
    $correct_version = 0;
    
    foreach ( $oldpage as $line ){

	if ( preg_match("/users: (\d+)/",$line,$result )){
	    $users_total = $result[1];
	    $total_content += $result[1];
	    $correct_version = 1;
	}
	if ( preg_match("/posts: (\d+)/",$line,$result )){
	    $posts_total = $result[1];
	    $total_content += $result[1];

	}
	if ( preg_match("/comments: (\d+)/",$line,$result )){
	    $comments_total = $result[1];
	    $total_content += $result[1];

	}
	if ( preg_match("/pictures: (\d+)/",$line,$result )){
	    $pictures_total = $result[1] - $users_total;
	    $total_content += $pictures_total;
	}
	if ( preg_match("/user=(\d+)\'>([^<]+)/",$line,$result )){
	    
	    if ( $correct_version == 0 ){
		echo "The version of bookface/code/showallusers.php is too old. You need to have version 17 or higher.\n";
		exit(0);
	    }
	    
	    $users_counter++;
	    $total_counter++;
	    $percent = sprintf( "%3.2f",($total_counter / $total_content * 100));
	    echo "[ $percent % | $total_counter / $total_content ]";
	    echo "[ $users_counter / $users_total users ] found user: " . $result[2];
	    if ( in_array($result[1],$user_list) ){
		echo ", [exists]\n";
	    } else {
		echo "\n";
	    }
	    $userid = $result[1];
	    $username = $result[2];
	    
	    $imageurl = "http://" . $oldip . "/showimage.php?user=" . $result[1];
	    
	    if ( preg_match("/images\/(.+\.jpg)/",$line,$iresult )){
		# this site stores images in files, we need to change the URL
		$imageurl = "http://" . $oldip . "/images/" . $iresult[1];
	    }
	    
	    
	    $imagestring = generateRandomString() . ".jpg";
	    file_put_contents("images/" . $imagestring, file_get_contents($imageurl));

	    $showuser = file("http://" . $oldip . "/showuser.php?user=" . $userid);
	    $startdate = "";
	    $posts = "";
	    $lastpost = null;
	    $lastpostid = 0;
	    foreach ( $showuser as $shline ){
		if ( preg_match("/Member since: (.*) posts: (\d+)</",$shline,$shresult )){
		    $startdate = $shresult[1];
		    $posts = $shresult[2];
		} else if ( preg_match("/postID:(\d+) .*post>(.*)<.td>.*postcontent>(.*)<.td><.tr>/",$shline,$shresult )){
		    $posts_counter++;
		    $total_counter++;
		    $percent = sprintf( "%3.2f",($total_counter / $total_content * 100));
		    echo "[ $percent % | $total_counter / $total_content ]";
		    echo "[ $posts_counter / $posts_total posts ] post: " . $shresult[3];

		    if ( in_array($shresult[1],$post_list)){
			echo ", [exists]\n";
		    } else {
			$postquery = "insert into posts (postid,text,userid,postdate) values('" . $shresult[1] . "','" . $shresult[3] . "','" . $result[1] . "','" . $shresult[2] . "');";
			try {
			    $postresult = $dbh->query($postquery);
			} catch (Exception $e) {
			    echo $e->getMessage() . "\r\n";
			}
			echo "\n";
		    }
		    $lastpostid = $shresult[1];
		} else if ( preg_match("/postimage.php.*image=(.*)'/",$shline,$iresult )){
		    $pictures_counter++;
		    $total_counter++;
		    $percent = sprintf( "%3.2f",($total_counter / $total_content * 100));
		    echo "[ $percent % | $total_counter / $total_content ]";
		    echo "[ $pictures_counter / $pictures_total pictures ] found image: " . $iresult[1];

		    $imageurl = "http://" . $oldip . "/postimage.php?image=" . $iresult[1];
		    
		    if ( file_exists("images/" . $iresult[1]) ){
			echo ", [exists]\n";
		    } else {
			file_put_contents("images/" . $iresult[1], file_get_contents($imageurl));
			$imageupdate = "UPDATE posts SET image = '" . $iresult[1] . "' WHERE postid = '" . $lastpostid  . "';";
			try {
			    $postresult = $dbh->query($imageupdate);
			} catch (Exception $e) {
			    echo $e->getMessage() . "\r\n";
			}
			echo "\n";
		    }
		    
		
		} else if ( preg_match("/.*commentpost >(.*:\d\d).*user=(\d+)'.*<\/td><td>(.*)<\/td>.*commentid: (.*) -/",$shline,$cresult)){
		    $comments_counter++;
		    $total_counter++;
		    $percent = sprintf( "%3.2f",($total_counter / $total_content * 100));
		    echo "[ $percent % | $total_counter / $total_content ]";
		    echo "[ $comments_counter / $comments_total comments ] comment: " . $cresult[3];
		    
		    if ( in_array($cresult[4],$comment_list)){
			echo ", [exists]\n";
		    } else {
			try {
			    $postresult = $dbh->query("insert into comments (commentid,postid,userid,postdate,text) values('" . $cresult[4] ."','" . $lastpostid . "','" . $cresult[2] . "','" . $cresult[1] . "','" . $cresult[3] . "');");								
			} catch (Exception $e) {
			    echo $e->getMessage() . "\r\n";
			}
			echo "\n";
		    }
		    
		} else {
#		    echo "no match\n";
		}
	    }
	    if ( in_array($userid,$user_list) ){
#		echo "skipping\n";
	    } else {
		$query =  "insert into users (userid,name,picture,status,posts,comments,createdate,lastpostdate) values(:userid,:username,:image,'',:posts,0,:createdate,:lastpostdate );";
		$stmt = $dbh->prepare($query);
		$username = substr($username,0,49);
		$stmt->execute([
				':userid' => $userid,
				':posts' => $posts,
				':createdate' => $startdate,
				':username' => $username,
				':image' => $imagestring,
				':lastpostdate' => $lastpost
				]);

	    }
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

    
} catch (Exception $e) {
    echo $e->getMessage() . "\r\n";
}

?>

</HTML>

