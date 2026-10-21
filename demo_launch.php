<?php
// demo_launch.php - 1-Click Instant Pure Demo Access (Tanpa Password & Form Login)
// Menyediakan simulasi real-time langsung ke dashboard Pelatih, Admin, Ortu, dan Atlet
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$requestedRole = trim($_GET['role'] ?? 'coach');
$allowedRoles = ['coach', 'admin', 'parent', 'archer'];
if (!in_array($requestedRole, $allowedRoles)) {
    $requestedRole = 'coach';
}

// 1. Dapatkan atau Pastikan Klub Demo (ID = 3) Ada di Database
$stmtClub = $pdo->prepare("SELECT * FROM archery_clubs WHERE subdomain_slug = 'demo' LIMIT 1");
$stmtClub->execute();
$demoClub = $stmtClub->fetch();

if (!$demoClub) {
    $stmtInsClub = $pdo->prepare("
        INSERT INTO archery_clubs 
        (id, club_name, club_code, subdomain_slug, city, address, primary_color, secondary_color, subscription_status, is_active)
        VALUES 
        (3, 'DEMO ARCHERY CLUB (SANDBOX)', 'DEMO-ARC', 'demo', 'Jakarta Sandbox', 'Pusat Simulasi Sistem Target Archery Indonesia', '#f59e0b', '#0284c7', 'active', 1)
        ON DUPLICATE KEY UPDATE club_name = VALUES(club_name)
    ");
    $stmtInsClub->execute();
    $demoClubId = 3;
} else {
    $demoClubId = (int)$demoClub['id'];
}

// 2. Pemetaan Role ke Akun Pengguna Demo
$targetUsername = 'demo_coach';
$targetRole = 'coach';
$targetRedirect = 'coach_dashboard.php';

if ($requestedRole === 'admin') {
    $targetUsername = 'demo_admin';
    $targetRole = 'super_admin';
    $targetRedirect = 'cms_admin.php';
} elseif ($requestedRole === 'parent') {
    $targetUsername = 'demo_parent';
    $targetRole = 'parent';
    $targetRedirect = 'app_dashboard.php';
} elseif ($requestedRole === 'archer') {
    $targetUsername = 'demo_parent';
    $targetRole = 'parent';
    $targetRedirect = 'plotter.php';
}

// 3. Ambil data User Demo dari Database
$stmtUser = $pdo->prepare("SELECT * FROM archery_users WHERE username = :u AND club_id = :cid LIMIT 1");
$stmtUser->execute([':u' => $targetUsername, ':cid' => $demoClubId]);
$demoUser = $stmtUser->fetch();

if (!$demoUser) {
    // Buat akun demo secara otomatis jika belum ada
    $defPass = password_hash('demo123', PASSWORD_BCRYPT);
    $fullName = 'Simulasi ' . ucfirst($requestedRole);
    $stmtCreate = $pdo->prepare("
        INSERT INTO archery_users 
        (club_id, username, password_hash, full_name, role, phone, is_active)
        VALUES 
        (:cid, :u, :p, :fn, :r, '081299887766', 1)
    ");
    $stmtCreate->execute([
        ':cid' => $demoClubId,
        ':u'   => $targetUsername,
        ':p'   => $defPass,
        ':fn'  => $fullName,
        ':r'   => $targetRole
    ]);
    $userId = (int)$pdo->lastInsertId();
    $demoUser = [
        'id'        => $userId,
        'club_id'   => $demoClubId,
        'username'  => $targetUsername,
        'full_name' => $fullName,
        'role'      => $targetRole,
        'phone'     => '081299887766'
    ];
}

// 4. Pastikan Data Atlet Demo (Bintang Panahan Demo) Tersedia
$stmtArcher = $pdo->prepare("SELECT * FROM archery_archers WHERE club_id = :cid ORDER BY id ASC LIMIT 1");
$stmtArcher->execute([':cid' => $demoClubId]);
$demoArcher = $stmtArcher->fetch();

if (!$demoArcher) {
    $stmtCreateArcher = $pdo->prepare("
        INSERT INTO archery_archers 
        (club_id, parent_user_id, nis, full_name, nickname, birth_date, gender, current_level, current_distance_meter, bow_category, parent_name, parent_phone, is_active)
        VALUES 
        (:cid, :puid, 'DEMO-001', 'Bintang Panahan Demo', 'Bintang', '2012-05-10', 'L', 'Standar Tingkat 2', 18, 'Standard Bow', 'Wali Santri Sandbox Demo', '081299887766', 1)
    ");
    $stmtCreateArcher->execute([
        ':cid'  => $demoClubId,
        ':puid' => $demoUser['id']
    ]);
    $demoArcherId = (int)$pdo->lastInsertId();
} else {
    $demoArcherId = (int)$demoArcher['id'];
}

// 5. Jika role adalah archer/plotter, pastikan ada sesi latihan aktif
$activeSessionId = 0;
if ($requestedRole === 'archer') {
    $stmtSess = $pdo->prepare("SELECT id FROM archery_sessions WHERE archer_id = :aid ORDER BY id DESC LIMIT 1");
    $stmtSess->execute([':aid' => $demoArcherId]);
    $activeSessionId = (int)$stmtSess->fetchColumn();

    if ($activeSessionId <= 0) {
        $today = date('Y-m-d');
        $stmtNewSess = $pdo->prepare("
            INSERT INTO archery_sessions 
            (club_id, archer_id, coach_id, session_date, start_time, distance_meter, bow_category, target_id, total_ends, arrows_per_end, total_score, max_possible_score, status)
            VALUES 
            (:cid, :aid, :uid, :dt, CURTIME(), 18, 'Standard Bow', 1, 6, 5, 0, 300, 'in_progress')
        ");
        $stmtNewSess->execute([
            ':cid' => $demoClubId,
            ':aid' => $demoArcherId,
            ':uid' => $demoUser['id'],
            ':dt'  => $today
        ]);
        $activeSessionId = (int)$pdo->lastInsertId();
    }
    $targetRedirect = "plotter.php?session_id={$activeSessionId}";
}

// 6. Tulis Session Login Bersih & Tandai Mode Demo
$_SESSION['user_id']            = (int)$demoUser['id'];
$_SESSION['username']           = $demoUser['username'];
$_SESSION['full_name']          = $demoUser['full_name'];
$_SESSION['role']               = $demoUser['role'];
$_SESSION['phone']              = $demoUser['phone'];
$_SESSION['club_id']            = $demoClubId;
$_SESSION['current_tenant_id']  = $demoClubId;
$_SESSION['active_child_id']    = $demoArcherId;
$_SESSION['is_demo']            = true;
$_SESSION['logged_in_at']       = time();

// Redirect instan ke dashboard yang dituju
header("Location: {$targetRedirect}");
exit;
