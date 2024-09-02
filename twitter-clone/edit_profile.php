<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch user details
$stmt = $conn->prepare("SELECT username, profile_pic, bio FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    echo "User not found";
    exit;
}

// Handle profile picture upload and bio update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_FILES['profilePic']) && !empty($_FILES['profilePic']['name'])) {
        $fileName = $_FILES['profilePic']['name'];
        $fileTmpName = $_FILES['profilePic']['tmp_name'];
        $fileType = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowedTypes = ['jpg', 'png', 'jpeg', 'gif'];

        if (in_array($fileType, $allowedTypes)) {
            $newFileName = "profile_" . $user_id . "." . $fileType;
            $targetFilePath = "uploads/" . $newFileName;

            if (move_uploaded_file($fileTmpName, $targetFilePath)) {
                // Update user's profile picture in the database
                $stmt = $conn->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
                $stmt->bind_param("si", $targetFilePath, $user_id);
                $stmt->execute();
                $user['profile_pic'] = $targetFilePath; // Update the current profile picture variable
            } else {
                echo "Failed to upload image.";
            }
        } else {
            echo "Only JPG, PNG, JPEG, GIF files are allowed.";
        }
    }

    if (isset($_POST['bio'])) {
        $bio = mysqli_real_escape_string($conn, $_POST['bio']);
        $stmt = $conn->prepare("UPDATE users SET bio = ? WHERE id = ?");
        $stmt->bind_param("si", $bio, $user_id);
        $stmt->execute();
        $user['bio'] = $bio; // Update the current bio variable
    }

    header("Location: user_profile.php?id=$user_id");
    exit;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <div class="container">
        <h1>Edit Profile: <?php echo htmlspecialchars($user['username']); ?></h1>

        <form action="edit_profile.php" method="post" enctype="multipart/form-data">
            <label for="profilePic">Profile Picture:</label><br>
            <img src="<?php echo htmlspecialchars($user['profile_pic'] ?? 'path/to/default/image.jpg'); ?>" alt="Profile Picture" style="width: 150px; height: 150px;"><br>
            <input type="file" name="profilePic"><br><br>

            <label for="bio">Bio:</label><br>
            <textarea name="bio" rows="4" cols="50"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea><br><br>

            <button type="submit">Save Changes</button>
        </form>

        <a href="user_profile.php?id=<?php echo $user_id; ?>">Back to Profile</a>
    </div>
</body>
</html>
