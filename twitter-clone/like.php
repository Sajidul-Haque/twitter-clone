<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    // Redirect to login page if not logged in
    header("Location: login.php");
    exit;
}

if (isset($_POST['tweet_id'])) {
    $tweet_id = $_POST['tweet_id'];
    $user_id = $_SESSION['user_id'];

    // Check if the user has already liked the tweet to prevent duplicate likes
    $sql = "SELECT id FROM likes WHERE tweet_id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $tweet_id, $user_id);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows == 0) {
        // Insert like into the database
        $sql = "INSERT INTO likes (tweet_id, user_id) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $tweet_id, $user_id);
        $stmt->execute();
    } else {
        // If already liked, remove the like
        $sql = "DELETE FROM likes WHERE tweet_id = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $tweet_id, $user_id);
        $stmt->execute();
    }

    $stmt->close();
}

$conn->close();

// Redirect back to the previous page after processing
header("Location: " . $_SERVER['HTTP_REFERER']);
exit;
?>
