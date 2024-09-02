<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$logged_in_user_id = $_SESSION['user_id'];
$viewed_user_id = isset($_GET['id']) ? intval($_GET['id']) : $logged_in_user_id;

// Fetch user details
$stmt = $conn->prepare("SELECT fullName, username, profile_pic, bio FROM users WHERE id = ?");
$stmt->bind_param("i", $viewed_user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    echo "User not found";
    exit;
}

// Fetch follower and following counts
$stmt = $conn->prepare("SELECT 
                            (SELECT COUNT(*) FROM followers WHERE following_id = ?) AS follower_count,
                            (SELECT COUNT(*) FROM followers WHERE follower_id = ?) AS following_count");
$stmt->bind_param("ii", $viewed_user_id, $viewed_user_id);
$stmt->execute();
$result = $stmt->get_result();
$counts = $result->fetch_assoc();

// Check if the logged-in user is following the viewed user
$stmt = $conn->prepare("SELECT id FROM followers WHERE follower_id = ? AND following_id = ?");
$stmt->bind_param("ii", $logged_in_user_id, $viewed_user_id);
$stmt->execute();
$is_following = $stmt->get_result()->num_rows > 0;

// Handle follow/unfollow actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($is_following) {
        // Unfollow
        $stmt = $conn->prepare("DELETE FROM followers WHERE follower_id = ? AND following_id = ?");
        $stmt->bind_param("ii", $logged_in_user_id, $viewed_user_id);
    } else {
        // Follow
        $stmt = $conn->prepare("INSERT INTO followers (follower_id, following_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $logged_in_user_id, $viewed_user_id);
    }
    $stmt->execute();
    header("Location: user_profile.php?id=$viewed_user_id");
    exit;
}

// Fetch followers and following lists if requested
$followers = $following = [];
if (isset($_GET['view']) && $_GET['view'] === 'followers') {
    $stmt = $conn->prepare("SELECT users.id, users.fullName, users.username FROM followers 
                            JOIN users ON followers.follower_id = users.id 
                            WHERE followers.following_id = ?");
    $stmt->bind_param("i", $viewed_user_id);
    $stmt->execute();
    $followers = $stmt->get_result();
} elseif (isset($_GET['view']) && $_GET['view'] === 'following') {
    $stmt = $conn->prepare("SELECT users.id, users.fullName, users.username FROM followers 
                            JOIN users ON followers.following_id = users.id 
                            WHERE followers.follower_id = ?");
    $stmt->bind_param("i", $viewed_user_id);
    $stmt->execute();
    $following = $stmt->get_result();
}

// Function to display tweets
function displayTweets($viewed_user_id, $logged_in_user_id, $conn) {
    $html = '';
    $stmt = $conn->prepare("SELECT tweets.id AS tweet_id, tweets.content, tweets.created_at, tweets.image, tweets.user_id, 
                            users.fullName, users.username, users.profile_pic,
                            (SELECT COUNT(*) FROM likes WHERE likes.tweet_id = tweets.id) AS like_count,
                            (SELECT COUNT(*) FROM likes WHERE likes.tweet_id = tweets.id AND likes.user_id = ?) AS user_liked
                            FROM tweets 
                            JOIN users ON tweets.user_id = users.id 
                            WHERE tweets.user_id = ? 
                            ORDER BY tweets.created_at DESC");
    $stmt->bind_param("ii", $logged_in_user_id, $viewed_user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $html .= '<div class="tweet">' .
                        '<a href="user_profile.php?id=' . $row['user_id'] . '">' .
                        '<img src="' . htmlspecialchars($row['profile_pic'] ?? 'path/to/default/image.jpg') . '" alt="Profile Picture" style="width: 50px; height: 50px; border-radius: 50%;">' .
                        htmlspecialchars($row['fullName']) . "<br>" .
                        '@' . htmlspecialchars($row['username']) . '</a>: ' .
                        htmlspecialchars($row['content']) . "<br>";
            if ($row['image']) {
                $html .= '<img src="' . htmlspecialchars($row['image']) . '" alt="Tweet Image" style="max-width: 500px;"><br>';
            }
            $html .= "<small>Posted on: " . date('Y-m-d H:i:s', strtotime($row['created_at'])) . "</small><br>" .
                     '<form action="like.php" method="post" style="display: inline;">
                          <input type="hidden" name="tweet_id" value="' . $row['tweet_id'] . '">
                          <button type="submit">' . ($row['user_liked'] ? 'Unlike' : 'Like') . '</button>
                      </form>
                      <span>' . $row['like_count'] . ' Likes</span>';

            // Show delete button only if the logged-in user is viewing their own profile
            if ($row['user_id'] == $logged_in_user_id) {
                $html .= '<form action="delete_post.php" method="post" style="display: inline;">
                              <input type="hidden" name="tweet_id" value="' . $row['tweet_id'] . '">
                              <button type="submit">Delete</button>
                          </form>';
            }

            $html .= '</div>';
        }
    } else {
        $html = '<p>No tweets to display.</p>';
    }
    return $html;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <div class="container">
        <header>
            <h1><?php echo htmlspecialchars($user['fullName']); ?></h1>
            <nav>
                <a href="home.php">Home</a>
                <?php if ($logged_in_user_id == $viewed_user_id): ?>
                    <a href="edit_profile.php" class="button">Edit Profile</a>
                    <a href="logout.php">Sign Out</a>
                <?php endif; ?>
            </nav>
        </header>

        <div class="profile-header">
            <img src="<?php echo htmlspecialchars($user['profile_pic'] ?? 'path/to/default/image.jpg'); ?>" alt="Profile Picture" style="width: 150px; height: 150px;">
            <p>@<?php echo htmlspecialchars($user['username']); ?></p>
            <p><?php echo nl2br(htmlspecialchars($user['bio'] ?? 'No bio available.')); ?></p>

            <!-- Follow/Unfollow Button -->
            <?php if ($logged_in_user_id !== $viewed_user_id): ?>
                <form action="user_profile.php?id=<?php echo $viewed_user_id; ?>" method="post">
                    <button type="submit"><?php echo $is_following ? 'Unfollow' : 'Follow'; ?></button>
                </form>
            <?php endif; ?>

            <!-- Follower and Following Counts -->
            <p>
                <a href="user_profile.php?id=<?php echo $viewed_user_id; ?>&view=followers">Followers: <?php echo $counts['follower_count']; ?></a> |
                <a href="user_profile.php?id=<?php echo $viewed_user_id; ?>&view=following">Following: <?php echo $counts['following_count']; ?></a>
            </p>
        </div>

        <!-- Display Follower or Following List -->
        <?php if ($followers): ?>
            <h3>Followers</h3>
            <ul>
                <?php while ($follower = $followers->fetch_assoc()): ?>
                    <li><a href="user_profile.php?id=<?php echo $follower['id']; ?>"><?php echo htmlspecialchars($follower['fullName']); ?> @<?php echo htmlspecialchars($follower['username']); ?></a></li>
                <?php endwhile; ?>
            </ul>
        <?php elseif ($following): ?>
            <h3>Following</h3>
            <ul>
                <?php while ($follow = $following->fetch_assoc()): ?>
                    <li><a href="user_profile.php?id=<?php echo $follow['id']; ?>"><?php echo htmlspecialchars($follow['fullName']); ?> @<?php echo htmlspecialchars($follow['username']); ?></a></li>
                <?php endwhile; ?>
            </ul>
        <?php endif; ?>

        <h2>Tweets</h2>
        <?php echo displayTweets($viewed_user_id, $logged_in_user_id, $conn); ?>

        <footer>
            <a href="home.php">Back to Home</a>
        </footer>
    </div>
</body>
</html>

<?php
$conn->close();
?>
