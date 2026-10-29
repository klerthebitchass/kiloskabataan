<?php
include '../config.php';

// Only a logged-in youth leader may see this page
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'leader') {
    header("Location: ../login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Leader Dashboard | KilosKabataan</title>
  <link rel="stylesheet" href="../style.css">
</head>
<body style="padding:40px">
  <h1>Youth Leader Dashboard</h1>
  <p>Welcome, <?= htmlspecialchars($_SESSION['full_name']) ?>!</p>
  <p><a href="../logout.php">Logout</a></p>
</body>
</html>