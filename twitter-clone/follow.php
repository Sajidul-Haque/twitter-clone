<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['follow'])) {
    header("Location: login.php");
    exit;
}

$followUserId = $_GET['follow'];
$userId = $_SESSION['user_id'];

// Check if already following
$sql = "SELECT * FROM followers WHERE follower_id = ? AND following_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $userId, $followUserId);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    // Unfollow user
    $sql = "DELETE FROM followers WHERE follower_id = ? AND following_id = ?";
    $action = 'unfollowed';
} else {
    // Follow user
    $sql = "INSERT INTO followers (follower_id, following_id) VALUES (?, ?)";
    $action = 'followed';
}

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $userId, $followUserId);
$stmt->execute();

header("Location: home.php");  // Redirect back to home page or to a specific URL
exit;
?>
