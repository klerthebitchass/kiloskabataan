<?php
include '../config.php';
include 'helpers.php';
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// ----- Search -----
$q = trim($_GET['q'] ?? '');

// Each purok gets its own color (0 to 4)
$puroks  = $conn->query("SELECT purok_id, purok_name FROM puroks ORDER BY purok_name")->fetch_all(MYSQLI_ASSOC);
$colorOf = [];
foreach ($puroks as $i => $pk) $colorOf[$pk['purok_id']] = $i % 5;

// ----- Members (ranks are always the overall ranks) -----
$all    = ranked_members($conn);
$total  = count($all);
$maxPts = max(1, (int)max(array_merge([0], array_column($all, 'points'))));

$members = array_values(array_filter($all, function ($m) use ($q) {
    if ($q !== '') {
        $n = mb_strtolower($q);
        return mb_strpos(mb_strtolower($m['member_name']), $n) !== false
            || mb_strpos(mb_strtolower($m['email']), $n) !== false;
    }
    return true;
}));
$shown    = count($members);
$filtered = ($q !== '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Youth Members | KilosKabataan</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="admin.css?v=<?= filemtime(__DIR__ . '/admin.css') ?>">
</head>
<body>

<div class="shell">

  <?php sidebar('dashboard'); ?>

  <main class="card-main">

    <!-- Title + count on the left, search on the right -->
    <header class="top">
      <div>
        <h1>Youth Members</h1>
        <p class="count">
          <b><?= $filtered ? $shown . ' of ' . $total : $total ?></b>
          Youth Member<?= (!$filtered && $total === 1) ? '' : 's' ?>
        </p>
      </div>

      <form class="search-pill" method="GET" action="members.php">
        <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search member" autocomplete="off">
        <button type="submit" aria-label="Search">
          <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7.5"/><line x1="21" x2="16.65" y1="21" y2="16.65"/></svg>
        </button>
      </form>
    </header>

    <!-- Member cards -->
    <div class="mem-list">

      <?php if (!$members): ?>
        <div class="mem-empty">
          <b><?= $filtered ? 'No member found' : 'No youth members yet' ?></b>
          <p><?= $filtered
                ? 'Try a different name or email.'
                : 'Members appear here after they register.' ?></p>
        </div>
      <?php endif; ?>

      <?php foreach ($members as $i => $m):
        $hasPts = (int)$m['points'] > 0;
        $ci     = $colorOf[$m['purok_id']] ?? 0;
        $pct    = round(((int)$m['points'] / $maxPts) * 100);
        $medal  = ($hasPts && (int)$m['rank'] <= 3) ? (int)$m['rank'] : 0; ?>

        <a href="member.php?id=<?= e($m['user_id']) ?>"
           class="mem-card c<?= $ci ?>"
           style="animation-delay: <?= min($i, 12) * 0.05 ?>s">

          <span class="mem-avatar">
            <?= initial($m['member_name']) ?>
            <?php if ($medal): ?><i class="medal m<?= $medal ?>"><?= $medal ?></i><?php endif; ?>
          </span>

          <div class="mem-main">
            <p class="mem-name"><?= e($m['member_name']) ?></p>
            <p class="mem-email"><?= e($m['email']) ?></p>
            <span class="mem-chip"><i></i><?= e($m['purok_name'] ?: 'No purok') ?></span>
          </div>

          <div class="mem-score">
            <div class="mem-pts"><b><?= (int)$m['points'] ?></b> <span>pts</span></div>
            <div class="mem-bar"><div style="width:<?= $pct ?>%"></div></div>
            <p class="mem-rank"><?= $hasPts ? 'Rank #' . (int)$m['rank'] : 'No points yet' ?></p>
          </div>

          <span class="mem-go">&#8250;</span>
        </a>
      <?php endforeach; ?>

    </div>

  </main>

</div>

</body>
</html>