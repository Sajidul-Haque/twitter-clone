<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Determine which feed to display
$feed = isset($_GET['feed']) ? $_GET['feed'] : 'all';

// Fetching tweets from all users
$sqlAll = "SELECT tweets.id AS tweet_id, tweets.content, tweets.created_at, tweets.image, tweets.user_id, 
           users.fullName, users.username, users.profile_pic,
           (SELECT COUNT(*) FROM likes WHERE likes.tweet_id = tweets.id) AS like_count,
           (SELECT COUNT(*) FROM likes WHERE likes.tweet_id = tweets.id AND likes.user_id = ?) AS user_liked
           FROM tweets 
           JOIN users ON tweets.user_id = users.id 
           ORDER BY tweets.created_at DESC";
$stmtAll = $conn->prepare($sqlAll);
$stmtAll->bind_param("i", $user_id);
$stmtAll->execute();
$tweetsAll = $stmtAll->get_result();

// Fetching tweets from followed users
$sqlFollowing = "SELECT tweets.id AS tweet_id, tweets.content, tweets.created_at, tweets.image, tweets.user_id, 
                 users.fullName, users.username, users.profile_pic,
                 (SELECT COUNT(*) FROM likes WHERE likes.tweet_id = tweets.id) AS like_count,
                 (SELECT COUNT(*) FROM likes WHERE likes.tweet_id = tweets.id AND likes.user_id = ?) AS user_liked
                 FROM tweets 
                 JOIN followers ON tweets.user_id = followers.following_id 
                 JOIN users ON tweets.user_id = users.id 
                 WHERE followers.follower_id = ?
                 ORDER BY tweets.created_at DESC";
$stmtFollowing = $conn->prepare($sqlFollowing);
$stmtFollowing->bind_param("ii", $user_id, $user_id);
$stmtFollowing->execute();
$tweetsFollowing = $stmtFollowing->get_result();

// Handle search
$search_results = null;
if (isset($_GET['search'])) {
    $search = mysqli_real_escape_string($conn, $_GET['search']);
    $sqlSearch = "SELECT id, fullName, username FROM users WHERE username LIKE ? OR fullName LIKE ?";
    $stmtSearch = $conn->prepare($sqlSearch);
    $searchTerm = "%$search%";
    $stmtSearch->bind_param("ss", $searchTerm, $searchTerm);
    $stmtSearch->execute();
    $search_results = $stmtSearch->get_result();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Welcome to Your Home Feed</h1>
            <nav>
                <a href="user_profile.php?id=<?php echo $user_id; ?>" class="button">My Profile</a>
                <a href="logout.php" class="button">Log Out</a>
            </nav>
            <div class="search-bar">
                <form action="home.php" method="get">
                    <input type="text" name="search" placeholder="Search users...">
                    <button type="submit">Search</button>
                </form>
            </div>
        </header>

        <form action="tweet_post.php" method="post" enctype="multipart/form-data" class="tweet-form">
            <textarea name="tweetContent" placeholder="What's happening?" required></textarea><br>
            <input type="file" name="tweetImage"><br>
            <button type="submit">Post Tweet</button>
        </form>

        <!-- Navigation for feeds -->
        <nav class="feed-navigation">
            <ul>
                <li><a href="home.php?feed=all">All Tweets</a></li>
                <li><a href="home.php?feed=following">Following</a></li>
            </ul>
        </nav>

        <!-- Display search results -->
        <?php if ($search_results): ?>
            <h3>Search Results:</h3>
            <ul>
                <?php while ($user = $search_results->fetch_assoc()): ?>
                    <li>
                        <a href="user_profile.php?id=<?php echo $user['id']; ?>">
                            <?php echo htmlspecialchars($user['fullName']); ?> 
                            @<?php echo htmlspecialchars($user['username']); ?>
                        </a>
                    </li>
                <?php endwhile; ?>
            </ul>
        <?php endif; ?>

        <h2>Feed</h2>
        <?php 
        $tweetsToDisplay = ($feed === 'following') ? $tweetsFollowing : $tweetsAll;
        while ($tweet = $tweetsToDisplay->fetch_assoc()): ?>
            <div class="tweet">
                <a href="user_profile.php?id=<?php echo $tweet['user_id']; ?>">
                    <img src="<?php echo htmlspecialchars($tweet['profile_pic'] ?? 'path/to/default/image.jpg'); ?>" alt="Profile Picture" style="width: 50px; height: 50px; border-radius: 50%;">
                    <strong><?php echo htmlspecialchars($tweet['fullName']); ?></strong><br>
                    <span>@<?php echo htmlspecialchars($tweet['username']); ?></span>
                </a>: 
                <?php echo htmlspecialchars($tweet['content']); ?><br>
                <?php if ($tweet['image']): ?>
                    <img src="<?php echo htmlspecialchars($tweet['image']); ?>" alt="Tweet Image" style="max-width: 500px;"><br>
                <?php endif; ?>
                <small>Posted on: <?php echo date('Y-m-d H:i:s', strtotime($tweet['created_at'])); ?></small><br>
                
                <!-- Like feature -->
                <form action="like.php" method="post" style="display: inline;">
                    <input type="hidden" name="tweet_id" value="<?php echo $tweet['tweet_id']; ?>">
                    <button type="submit"><?php echo $tweet['user_liked'] ? 'Unlike' : 'Like'; ?></button>
                </form>
                <span><?php echo $tweet['like_count']; ?> Likes</span>

                <!-- Delete button for user's own tweets -->
                <?php if ($tweet['user_id'] == $user_id): ?>
                    <form action="delete_post.php" method="post" style="display: inline;">
                        <input type="hidden" name="tweet_id" value="<?php echo $tweet['tweet_id']; ?>">
                        <button type="submit">Delete</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endwhile; ?>
    </div>
</body>
</html>
