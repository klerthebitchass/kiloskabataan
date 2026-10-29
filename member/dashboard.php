<?php
include '../config.php';

// Only a logged-in youth member may see this page
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'member') {
    header("Location: ../login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Member Dashboard | KilosKabataan</title>
  <link rel="stylesheet" href="../style.css">
</head>
<body style="padding:40px">
  <h1>Youth Member Dashboard</h1>
  <p>Welcome, <?= htmlspecialchars($_SESSION['full_name']) ?>!</p>
  <p><a href="../logout.php">Logout</a></p>
</body>
</html>