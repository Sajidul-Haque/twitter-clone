<?php
session_start();

// Check if the user is already logged in
if (isset($_SESSION['user_id'])) {
    // If logged in, redirect to home page
    header("Location: home.php");
    exit;
} else {
    // If not logged in, redirect to the login page
    header("Location: login.php");
    exit;
}
?>
