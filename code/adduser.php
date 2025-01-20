<?php
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
include_once "config.php";

try {
    $dbh = new PDO('pgsql:host=' . $dbhost . ";port=" . $dbport . ";dbname=" . $db . ';sslmode=disable', $dbuser, null, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => true));
    $dest_path = "";
    $name = $_GET['name'];
    $age = $_GET['age'];
    $bio = $_GET['bio'];
    $userID = $_GET['uuid'];
    $image = $_GET['image'];
    
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        // Get user input
        $name = $_POST['name'];
        $age = $_POST['age'];
        $bio = $_POST['bio'];
        $userID = $_POST['uuid'];
	$image = $_POST['image'];
    } 
	
	
    
    // Handle file upload
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == UPLOAD_ERR_OK) {
	$fileTmpPath = $_FILES['profile_picture']['tmp_name'];
	$fileName = $_FILES['profile_picture']['name'];
	$fileSize = $_FILES['profile_picture']['size'];
	$fileType = $_FILES['profile_picture']['type'];
	$fileNameCmps = explode(".", $fileName);
	$fileExtension = strtolower(end($fileNameCmps));
	
	// Sanitize file name and specify upload path
	$newFileName = md5(time() . $fileName) . '.' . $fileExtension;
	$uploadFileDir = '/tmp/';
	$dest_path = $uploadFileDir . $newFileName;
	
	if (move_uploaded_file($fileTmpPath, $dest_path)) {
	    $profilePictureURL = $dest_path; // Save path to database
	} else {
	    die('There was an error uploading the file.');
	}
    } else {
	
	$dest_path = $image;
	$newFilename = $userID . ".jpg";
	
    }
    
        // Insert user data into the database
    $query = "INSERT INTO users (userID, name, age, bio, picture, status, posts, comments) VALUES (:userID, :name, :age, :bio, :profile_picture, '', 0, 0)";
    $stmt = $dbh->prepare($query);
    $stmt->execute([
		    ':userID' => $userID,
		    ':name' => $name,
		    ':age' => $age,
		    ':bio' => $bio,
		    ':profile_picture' => $userID . ".jpg",
		    ]);
    
    // Handle storing the profile picture in the pictures table if required
    $use_file_store_for_images = 0;
    if(isset($use_local_images) and $use_local_images == 1){
	$use_file_store_for_images = 1;
        }
    
    if($use_file_store_for_images){
	file_put_contents("images/" . $newFileName, file_get_contents($dest_path));
    } else {
	echo "Fetching file from: " . $dest_path;
	$img = file_get_contents($dest_path);
	$img_hex = bin2hex($img);
	$image_query = "INSERT INTO pictures (pictureID, picture) VALUES (:pictureID, :picture)";
	$stmt = $dbh->prepare($image_query);
	$stmt->execute([
			':pictureID' => $userID . ".jpg",
			':picture' => $img_hex,
			]);
    }
    
    echo "User added successfully!";
} catch (Exception $e) {
    echo $e->getMessage() . "\r\n";
}
?>
