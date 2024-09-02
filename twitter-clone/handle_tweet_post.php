<?php

if (isset($_POST['tweetContent'])) {
    $content = mysqli_real_escape_string($conn, $_POST['tweetContent']);
    $imagePath = NULL;

    // Check if a file was uploaded
    if (!empty($_FILES['tweetImage']['name'])) {
        $targetDir = "uploads/";
        $fileName = time() . basename($_FILES['tweetImage']['name']);
        $targetFilePath = $targetDir . $fileName;
        $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));

        // Allow certain file formats
        $allowTypes = array('jpg', 'png', 'jpeg', 'gif');
        if (in_array($fileType, $allowTypes)) {
            // Check if file already exists
            if (!file_exists($targetFilePath)) {
                // Upload file to the server
                if (move_uploaded_file($_FILES['tweetImage']['tmp_name'], $targetFilePath)) {
                    $imagePath = $targetFilePath;
                } else {
                    $error = "Failed to upload image.";
                }
            } else {
                $error = "File already exists. Please rename the file before uploading.";
            }
        } else {
            $error = "Invalid file type. Allowed types are JPEG, PNG, GIF.";
        }
    }

    if (!empty($content) || !empty($imagePath)) {
        $sql = "INSERT INTO tweets (user_id, content, image) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iss", $_SESSION['user_id'], $content, $imagePath);
        if ($stmt->execute()) {
            $success = "Tweet posted successfully!";
        } else {
            $error = "Error posting tweet: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $error = "Please enter some text to tweet or select an image.";
    }
}

if (isset($error)) {
    echo $error; // Ideally handle this more gracefully in production
}

if (isset($success)) {
    echo $success; // Display success message or handle redirect
}
?>
