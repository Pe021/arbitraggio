<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Area Protetta</title>
</head>
<body>
    <h1>Benvenuto, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
    <p>Sei nell'area protetta.</p>
    <p><a href="logout.php">Logout</a></p>
    <!-- Link per accedere al bot -->
    <p><a href="bot.php">Vai al Bot di Arbitraggio</a></p>
</body>
</html>
