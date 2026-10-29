<?php
include '../config.php';
date_default_timezone_set('Asia/Manila');

// Only a logged-in admin may see this page
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// Small icon helper (all icons are drawn as SVG, no files needed)
function icon($name) {
    $paths = [
        'award'    => '<circle cx="12" cy="8" r="6"/><path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"/>',
        'grid'     => '<rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/>',
        'settings' => '<path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/>',
        'logout'   => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/>',
        'users'    => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'usercheck'=> '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><polyline points="16 11 18 13 22 9"/>',
        'file'     => '<path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/><line x1="16" x2="8" y1="13" y2="13"/><line x1="16" x2="8" y1="17" y2="17"/>',
        'activity' => '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>',
        'check'    => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
        'plus'     => '<line x1="12" x2="12" y1="5" y2="19"/><line x1="5" x2="19" y1="12" y2="12"/>',
        'arrow'    => '<line x1="5" x2="19" y1="12" y2="12"/><polyline points="12 5 19 12 12 19"/>',
    ];
    return '<svg class="ico" viewBox="0 0 24 24">' . ($paths[$name] ?? '') . '</svg>';
}

// ----- Counts -----
$members = (int)$conn->query("SELECT COUNT(*) AS c FROM users WHERE role = 'member'")->fetch_assoc()['c'];
$leaders = (int)$conn->query("SELECT COUNT(*) AS c FROM users WHERE role = 'leader'")->fetch_assoc()['c'];
$unread  = (int)$conn->query("SELECT COUNT(*) AS c FROM reports WHERE status = 'unread'")->fetch_assoc()['c'];

// ----- Recent opened reports + recent activities (QR check-ins) -----
$feed = $conn->query("
    SELECT 'Report' AS kind,
           r.title AS title,
           CONCAT('From ', COALESCE(p.full_name, 'a user')) AS sub,
           COALESCE(r.read_at, r.submitted_at) AS happened_at
    FROM reports r
    LEFT JOIN youth_profiles p ON p.user_id = r.sender_id
    WHERE r.status = 'read'
    UNION ALL
    SELECT 'Activity' AS kind,
           CONCAT(COALESCE(p.full_name, 'A member'), ' checked in') AS title,
           e.title AS sub,
           a.checkin_time AS happened_at
    FROM attendance a
    JOIN registrations rg ON rg.registration_id = a.registration_id
    JOIN events e ON e.event_id = rg.event_id
    LEFT JOIN youth_profiles p ON p.user_id = rg.user_id
    ORDER BY happened_at DESC
    LIMIT 6
")->fetch_all(MYSQLI_ASSOC);

// ----- Recent events (list under the calendar) -----
$recentEvents = $conn->query(
    "SELECT title, purok_scope, event_date FROM events ORDER BY event_date DESC LIMIT 4"
)->fetch_all(MYSQLI_ASSOC);

// ----- Calendar -----
$monthParam = $_GET['month'] ?? date('Y-m');
$first = DateTime::createFromFormat('!Y-m-d', $monthParam . '-01');
$errs  = DateTime::getLastErrors();
if (!$first || ($errs && ($errs['warning_count'] > 0 || $errs['error_count'] > 0))) {
    $first = new DateTime('first day of this month 00:00');
}
$daysInMonth = (int)$first->format('t');
$startOffset = (int)$first->format('w');           // 0 = Sunday
$prevMonth   = (clone $first)->modify('-1 month')->format('Y-m');
$nextMonth   = (clone $first)->modify('+1 month')->format('Y-m');
$monthStart  = $first->format('Y-m-01');
$monthEnd    = $first->format('Y-m-t');

$stmt = $conn->prepare("SELECT DISTINCT DAY(event_date) AS d FROM events WHERE event_date BETWEEN ? AND ?");
$stmt->bind_param("ss", $monthStart, $monthEnd);
$stmt->execute();
$eventDays = array_map('intval', array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'd'));

$isThisMonth = ($first->format('Y-m') === date('Y-m'));
$todayDay    = (int)date('j');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard | KilosKabataan</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="admin.css">
</head>
<body>

<div class="shell">

  <!-- ========== SIDEBAR ========== -->
  <aside class="sidebar">
    <div class="logo">
      <img src="../logo sr.png" alt="" onerror="this.style.display='none'">
    </div>

    <nav class="side-nav">
      <a href="ranking.php" class="side-link" title="Rank Activity"><?= icon('award') ?></a>
      <a href="dashboard.php" class="side-link active" title="Dashboard"><?= icon('grid') ?></a>
      <a href="settings.php" class="side-link" title="Settings"><?= icon('settings') ?></a>
    </nav>
  </aside>

  <!-- ========== MAIN CARD ========== -->
  <main class="card-main">

    <header class="top">
      <div>
        <h1>Welcome back</h1>
        <p class="subtitle"><?= e($_SESSION['full_name']) ?> &middot; <?= date('l, F j, Y') ?></p>
      </div>
      <a href="../logout.php" class="logout" title="Logout"><?= icon('logout') ?></a>
    </header>

    <div class="dash-grid">

      <!-- ===== LEFT COLUMN ===== -->
      <section class="left-col">

        <h3 class="section-title">Users</h3>
        <div class="stats">
          <a href="members.php" class="stat members">
            <span class="stat-ico"><?= icon('users') ?></span>
            <span class="num"><?= $members ?></span>
            <span class="lbl">Youth Members</span>
            <span class="more">View all <?= icon('arrow') ?></span>
          </a>
          <a href="leaders.php" class="stat leaders">
            <span class="stat-ico"><?= icon('usercheck') ?></span>
            <span class="num"><?= $leaders ?></span>
            <span class="lbl">Youth Leaders</span>
            <span class="more">View all <?= icon('arrow') ?></span>
          </a>
        </div>

        <div class="quick">
          <a href="reports.php" class="q-btn q-reports">
            <?= icon('file') ?> REPORTS
            <?php if ($unread > 0): ?>
              <span class="dot" title="<?= $unread ?> unread report(s)"></span>
            <?php endif; ?>
          </a>
          <a href="activities.php" class="q-btn q-activities">
            <?= icon('activity') ?> ACTIVITIES
          </a>
        </div>

        <h3 class="section-title">Recent Reports And Activities</h3>
        <div class="feed">
          <?php if (!$feed): ?>
            <div class="feed-item is-empty">
              <span class="feed-ico"><?= icon('file') ?></span>
              <div class="feed-body"><p class="empty">No opened reports or activities yet.</p></div>
            </div>
          <?php endif; ?>

          <?php foreach ($feed as $row):
            $isReport = ($row['kind'] === 'Report'); ?>
            <div class="feed-item <?= $isReport ? 'report' : 'activity' ?>">
              <span class="feed-ico"><?= icon($isReport ? 'file' : 'check') ?></span>
              <div class="feed-body">
                <div>
                  <p class="feed-title"><?= e($row['title']) ?></p>
                  <p class="feed-sub"><?= e($row['sub']) ?></p>
                </div>
                <div class="feed-meta">
                  <span class="tag"><?= e($row['kind']) ?></span>
                  <span class="feed-time"><?= e(date('M j, g:i A', strtotime($row['happened_at']))) ?></span>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

      </section>

      <!-- ===== RIGHT COLUMN ===== -->
      <section class="right-col">

        <h3 class="section-title">Events</h3>
        <div class="events-panel">

          <div class="cal">
            <div class="cal-head">
              <a href="?month=<?= $prevMonth ?>" title="Previous month">&#8249;</a>
              <span><?= e($first->format('F Y')) ?></span>
              <a href="?month=<?= $nextMonth ?>" title="Next month">&#8250;</a>
            </div>
            <div class="cal-grid">
              <span class="dow">S</span><span class="dow">M</span><span class="dow">T</span>
              <span class="dow">W</span><span class="dow">T</span><span class="dow">F</span><span class="dow">S</span>

              <?php for ($i = 0; $i < $startOffset; $i++): ?><span></span><?php endfor; ?>

              <?php for ($d = 1; $d <= $daysInMonth; $d++):
                $cls = 'day';
                if ($isThisMonth && $d === $todayDay) $cls .= ' today';
                if (in_array($d, $eventDays, true))   $cls .= ' has-event';
              ?>
                <span class="<?= $cls ?>"><?= $d ?></span>
              <?php endfor; ?>
            </div>
          </div>

          <a href="add_event.php" class="add-event"><?= icon('plus') ?> ADD EVENT</a>

          <p class="panel-label">Recent Events</p>
          <div class="event-list">
            <?php if (!$recentEvents): ?>
              <p class="empty">No events yet. Click ADD EVENT to post one.</p>
            <?php endif; ?>

            <?php foreach ($recentEvents as $ev):
              $ts = strtotime($ev['event_date']); ?>
              <div class="event-item">
                <div class="event-date">
                  <b><?= date('j', $ts) ?></b>
                  <span><?= date('M', $ts) ?></span>
                </div>
                <div>
                  <p class="event-title"><?= e($ev['title']) ?></p>
                  <p class="event-sub"><?= e($ev['purok_scope'] ?: 'All puroks') ?></p>
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