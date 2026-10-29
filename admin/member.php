<?php
include '../config.php';
include 'helpers.php';
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$id  = $_GET['id'] ?? '';
$all = ranked_members($conn);
$me  = null;
foreach ($all as $m) {
    if ($m['user_id'] === $id) { $me = $m; break; }
}
if (!$me) {
    header("Location: members.php");
    exit;
}

$total   = count($all);
$points  = (int)$me['points'];
$hasPts  = $points > 0;

// Rank inside the member's own purok
$purokRank  = 1;
$purokTotal = 0;
foreach ($all as $m) {
    if ($m['purok_id'] === $me['purok_id']) {
        $purokTotal++;
        if ((int)$m['points'] > $points) $purokRank++;
    }
}

// Points history
$h = $conn->prepare("
    SELECT points_earned, achievement_type, awarded_at
    FROM points_achievements
    WHERE user_id = ?
    ORDER BY awarded_at DESC
    LIMIT 8
");
$h->bind_param("s", $id);
$h->execute();
$history = $h->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($me['member_name']) ?> | KilosKabataan</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="admin.css">
</head>
<body>

<div class="shell">

  <?php sidebar('dashboard'); ?>

  <main class="card-main">

    <header class="top">
      <div>
        <h1>Member Profile</h1>
        <p class="subtitle">Rank and points of this youth member</p>
      </div>
      <a href="../logout.php" class="logout" title="Logout"><?= icon('logout') ?></a>
    </header>

    <div class="dash-grid">

      <!-- ===== LEFT: profile + ranks ===== -->
      <section class="left-col">

        <a href="members.php" class="back"><?= icon('arrow') ?> Back to members</a>

        <div class="profile-hero">
          <span class="avatar big"><?= initial($me['member_name']) ?></span>
          <div>
            <h2><?= e($me['member_name']) ?></h2>
            <p><?= e($me['purok_name'] ?: 'No purok') ?></p>
            <p><?= e($me['email']) ?></p>
            <?php if ($me['emergency_contact']): ?>
              <p>Emergency contact: <?= e($me['emergency_contact']) ?></p>
            <?php endif; ?>
          </div>
        </div>

        <h3 class="section-title">Rank</h3>
        <div class="stats three">
          <div class="stat gold">
            <span class="num"><?= $hasPts ? '#' . (int)$me['rank'] : '&ndash;' ?></span>
            <span class="lbl">Overall Rank</span>
            <span class="more"><?= $hasPts ? 'of ' . $total . ' members' : 'No points yet' ?></span>
          </div>
          <div class="stat members">
            <span class="num"><?= $hasPts ? '#' . $purokRank : '&ndash;' ?></span>
            <span class="lbl">In <?= e($me['purok_name'] ?: 'Purok') ?></span>
            <span class="more"><?= $hasPts ? 'of ' . $purokTotal . ' members' : 'No points yet' ?></span>
          </div>
          <div class="stat leaders">
            <span class="num"><?= $points ?></span>
            <span class="lbl">Total Points</span>
            <span class="more"><?= (int)$me['achievements'] ?> achievement<?= (int)$me['achievements'] === 1 ? '' : 's' ?></span>
          </div>
        </div>

      </section>

      <!-- ===== RIGHT: points history ===== -->
      <section class="right-col">

        <h3 class="section-title">Points History</h3>
        <div class="events-panel">
          <div class="event-list">
            <?php if (!$history): ?>
              <p class="empty">No points yet. They are added when this member checks in at an event.</p>
            <?php endif; ?>

            <?php foreach ($history as $a): ?>
              <div class="event-item">
                <div class="event-date">
                  <b>+<?= (int)$a['points_earned'] ?></b>
                  <span>pts</span>
                </div>
                <div>
                  <p class="event-title"><?= e($a['achievement_type'] ?: 'Achievement') ?></p>
                  <p class="event-sub"><?= $a['awarded_at'] ? e(date('M j, Y, g:i A', strtotime($a['awarded_at']))) : '' ?></p>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

      </section>

    </div>
  </main>

</div>

</body>
</html>