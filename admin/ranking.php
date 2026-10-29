<?php
include '../config.php';
include 'helpers.php';
date_default_timezone_set('Asia/Manila');

// Only a logged-in admin may see this page
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// ----- Purok filter -----
$puroks    = $conn->query("SELECT purok_id, purok_name FROM puroks ORDER BY purok_name")->fetch_all(MYSQLI_ASSOC);
$purok_ids = array_column($puroks, 'purok_id');
$purokFilter = $_GET['purok'] ?? '';
if (!in_array($purokFilter, $purok_ids, true)) $purokFilter = '';

// ----- Leaderboard -----
$ranked = ranked_members($conn, $purokFilter);

// Podium: top 3 who actually have points
$top = array_values(array_filter(array_slice($ranked, 0, 3), fn($r) => (int)$r['points'] > 0));
$podiumOrder = [1, 0, 2];                    // 2nd, 1st, 3rd (1st in the middle)
$podiumClass = ['first', 'second', 'third'];

// ----- Purok standings -----
$standings = $conn->query("
    SELECT pk.purok_name, COALESCE(SUM(pa.points_earned), 0) AS points
    FROM puroks pk
    LEFT JOIN users u ON u.purok_id = pk.purok_id AND u.role = 'member'
    LEFT JOIN points_achievements pa ON pa.user_id = u.user_id
    GROUP BY pk.purok_id, pk.purok_name
    ORDER BY points DESC, pk.purok_name ASC
")->fetch_all(MYSQLI_ASSOC);
$maxPts = max(1, (int)max(array_merge([0], array_column($standings, 'points'))));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Rewards | KilosKabataan</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="admin.css">
</head>
<body>

<div class="shell">

  <?php sidebar('ranking'); ?>

  <main class="card-main">

    <header class="top">
      <div>
        <h1>Rewards</h1>
        <p class="subtitle">Rank activity of youth members &middot; <?= date('F j, Y') ?></p>
      </div>
      <a href="../logout.php" class="logout" title="Logout"><?= icon('logout') ?></a>
    </header>

    <div class="dash-grid">

      <!-- ===== LEFT: filter, podium, leaderboard ===== -->
      <section class="left-col">

        <div class="pills">
          <a href="ranking.php" class="pill <?= $purokFilter === '' ? 'active' : '' ?>">All Puroks</a>
          <?php foreach ($puroks as $pk): ?>
            <a href="?purok=<?= e($pk['purok_id']) ?>"
               class="pill <?= $purokFilter === $pk['purok_id'] ? 'active' : '' ?>"><?= e($pk['purok_name']) ?></a>
          <?php endforeach; ?>
        </div>

        <!-- Podium -->
        <div class="podium">
          <?php if (!$top): ?>
            <p class="empty" style="padding:70px 0">No points have been awarded yet.</p>
          <?php else: ?>
            <?php foreach ($podiumOrder as $idx):
              if (!isset($top[$idx])) continue;
              $m = $top[$idx]; ?>
              <div class="pod <?= $podiumClass[$idx] ?>">
                <div class="pod-avatar"><?= initial($m['member_name']) ?></div>
                <p class="pod-name"><?= e($m['member_name']) ?></p>
                <p class="pod-sub"><?= e($m['purok_name'] ?: 'No purok') ?></p>
                <p class="pod-pts"><?= (int)$m['points'] ?> pts</p>
                <div class="pod-block"><?= (int)$m['rank'] ?></div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <!-- Full leaderboard (click a row to open the member's profile) -->
        <h3 class="section-title">Leaderboard</h3>
        <div class="rank-list">
          <?php if (!$ranked): ?>
            <div class="rank-row">
              <p class="empty">No youth members<?= $purokFilter ? ' in this purok' : '' ?> yet.</p>
            </div>
          <?php endif; ?>

          <?php foreach ($ranked as $m):
            $hasPts = (int)$m['points'] > 0;
            $cls = ($hasPts && $m['rank'] <= 3) ? ' r' . (int)$m['rank'] : ''; ?>
            <a href="member.php?id=<?= e($m['user_id']) ?>" class="rank-row<?= $cls ?>">
              <span class="rank-no"><?= $hasPts ? (int)$m['rank'] : '&ndash;' ?></span>
              <span class="avatar"><?= initial($m['member_name']) ?></span>
              <div class="rank-info">
                <p class="rank-name"><?= e($m['member_name']) ?></p>
                <p class="rank-sub"><?= e($m['purok_name'] ?: 'No purok') ?> &middot; <?= (int)$m['achievements'] ?> achievement<?= (int)$m['achievements'] === 1 ? '' : 's' ?></p>
              </div>
              <div class="rank-pts">
                <b><?= (int)$m['points'] ?></b>
                <span>points</span>
              </div>
            </a>
          <?php endforeach; ?>
        </div>

      </section>

      <!-- ===== RIGHT: purok standings + find a member ===== -->
      <section class="right-col">

        <h3 class="section-title">Purok Standings</h3>
        <div class="events-panel">

          <?php foreach ($standings as $s):
            $pct = round(((int)$s['points'] / $maxPts) * 100); ?>
            <div class="bar-row">
              <div class="bar-top">
                <span><?= e($s['purok_name']) ?></span>
                <span><?= (int)$s['points'] ?> pts</span>
              </div>
              <div class="bar-track"><div class="bar-fill" style="width:<?= $pct ?>%"></div></div>
            </div>
          <?php endforeach; ?>

          <p class="panel-label" style="margin-top:20px">Find a Member</p>
          <?php search_box('', 'Search name or email'); ?>
          <p class="empty" style="color:#3b6a46">Search a member, then click their profile to see their rank.</p>

        </div>
      </section>

    </div>
  </main>

</div>

</body>
</html>