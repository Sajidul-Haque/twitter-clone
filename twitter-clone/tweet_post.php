<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = $_SESSION['user_id'];
    $content = mysqli_real_escape_string($conn, $_POST['tweetContent']);
    $imagePath = null;

    // Handle image upload if there's an image
    if (!empty($_FILES['tweetImage']['name'])) {
        $imageName = $_FILES['tweetImage']['name'];
        $imageTmpName = $_FILES['tweetImage']['tmp_name'];
        $imageType = strtolower(pathinfo($imageName, PATHINFO_EXTENSION));
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($imageType, $allowedTypes)) {
            $imagePath = "uploads/tweet_" . time() . ".$imageType";
            if (!move_uploaded_file($imageTmpName, $imagePath)) {
                $imagePath = null; // If the upload fails, do not save the image
            }
        } else {
            // Optionally, handle invalid file type
        }
    }

    // Insert the tweet into the database
    $stmt = $conn->prepare("INSERT INTO tweets (user_id, content, image, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("iss", $user_id, $content, $imagePath);
    $stmt->execute();

    // Redirect back to the homepage
    header("Location: home.php");
    exit;
}
?>
