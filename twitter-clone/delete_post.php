<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if (isset($_POST['tweet_id'])) {
    $tweet_id = intval($_POST['tweet_id']);
    $user_id = $_SESSION['user_id'];

    // Verify that the tweet belongs to the logged-in user
    $stmt = $conn->prepare("DELETE FROM tweets WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $tweet_id, $user_id);
    $stmt->execute();

    // Redirect back to the previous page
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit;
}
?>
