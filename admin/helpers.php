<?php
function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// Icons are drawn as SVG. To use your own image for one, add it to $custom
// (files live in kiloskabataan/icons/), e.g. 'award' => 'ranking.png'
function icon($name) {
    $custom = [
        // 'award'    => 'ranking.png',
        // 'grid'     => 'dashboard.png',
        // 'settings' => 'settings.png',
        // 'logout'   => 'logout.png',
    ];
    if (isset($custom[$name])) {
        return '<img class="ico-img" src="../icons/' . rawurlencode($custom[$name]) . '" alt="">';
    }

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

// The sidebar. $active = 'ranking', 'dashboard' or 'settings'
function sidebar($active) {
    $items = [
        'ranking'   => ['ranking.php',   'award',    'Rewards / Rank Activity'],
        'dashboard' => ['dashboard.php', 'grid',     'Dashboard'],
        'settings'  => ['settings.php',  'settings', 'Settings'],
    ];
    echo '<aside class="sidebar">';
    echo '<div class="logo"><img src="../logo%20sr.png" alt="" onerror="this.style.display=\'none\'"></div>';
    echo '<nav class="side-nav">';
    foreach ($items as $key => [$href, $ico, $title]) {
        $cls = 'side-link' . ($active === $key ? ' active' : '');
        echo '<a href="' . $href . '" class="' . $cls . '" title="' . e($title) . '">' . icon($ico) . '</a>';
    }
    echo '</nav></aside>';
}

// Members ranked by total points. Same points = same rank.
function ranked_members($conn, $purokId = '') {
    $sql = "SELECT u.user_id, u.email, u.purok_id,
                   COALESCE(p.full_name, u.email) AS member_name,
                   p.emergency_contact,
                   pk.purok_name,
                   COALESCE(SUM(pa.points_earned), 0) AS points,
                   COUNT(pa.points_id) AS achievements
            FROM users u
            LEFT JOIN youth_profiles p ON p.user_id = u.user_id
            LEFT JOIN puroks pk ON pk.purok_id = u.purok_id
            LEFT JOIN points_achievements pa ON pa.user_id = u.user_id
            WHERE u.role = 'member'"
            . ($purokId !== '' ? " AND u.purok_id = ?" : "") . "
            GROUP BY u.user_id, u.email, u.purok_id, p.full_name, p.emergency_contact, pk.purok_name
            ORDER BY points DESC, member_name ASC";
    $stmt = $conn->prepare($sql);
    if ($purokId !== '') $stmt->bind_param("s", $purokId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $prevPts = null; $prevRank = 0;
    foreach ($rows as $i => &$r) {
        $pts = (int)$r['points'];
        $r['rank'] = ($pts === $prevPts) ? $prevRank : $i + 1;
        $prevPts = $pts; $prevRank = $r['rank'];
    }
    unset($r);
    return $rows;
}

function initial($name) { return e(mb_strtoupper(mb_substr($name, 0, 1))); }

// Search bar (always sends you to the Members page)
function search_box($q = '', $placeholder = 'Search a member by name or email') {
    echo '<form class="search" method="GET" action="members.php">'
       . '<svg class="ico" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" x2="16.65" y1="21" y2="16.65"/></svg>'
       . '<input type="text" name="q" value="' . e($q) . '" placeholder="' . e($placeholder) . '" autocomplete="off">'
       . '<button type="submit">Search</button></form>';
}