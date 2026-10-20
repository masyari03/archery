<?php
// profile.php - Pengaturan Profil Pengguna & Keamanan Akun
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/activity_logger.php';
Auth::requireAuth();

$currentUser = Auth::user();
$userId = (int)($currentUser['id'] ?? 0);
$clubId = (int)($currentUser['club_id'] ?? 1);

$alertMsg = null;
$alertType = 'success';

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. UPDATE DATA DIRI & AVATAR
    if ($action === 'update_profile') {
        $fullName = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if (empty($fullName)) {
            $alertMsg = "Nama lengkap wajib diisi.";
            $alertType = 'danger';
        } else {
            $avatarUrl = null;

            // Handle Avatar Upload jika ada
            if (isset($_FILES['avatar_file']) && $_FILES['avatar_file']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['avatar_file'];
                $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);

                if (in_array($mime, $allowedMimes) && $file['size'] <= 3 * 1024 * 1024) {
                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $newFileName = 'avatar_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
                    $uploadDir = __DIR__ . '/uploads/avatars/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    $destPath = $uploadDir . $newFileName;
                    if (move_uploaded_file($file['tmp_name'], $destPath)) {
                        $avatarUrl = 'uploads/avatars/' . $newFileName;
                    } else {
                        $alertMsg = "Gagal memindahkan file upload foto.";
                        $alertType = 'danger';
                    }
                } else {
                    $alertMsg = "Format avatar harus JPG/PNG/WEBP dan ukuran maksimal 3MB.";
                    $alertType = 'danger';
                }
            }

            if (!$alertMsg) {
                try {
                    $params = [
                        ':fn' => $fullName,
                        ':ph' => $phone,
                        ':em' => $email,
                        ':uid' => $userId
                    ];

                    $sqlExtra = "";
                    if ($avatarUrl !== null) {
                        $sqlExtra .= ", avatar_url = :av";
                        $params[':av'] = $avatarUrl;
                        $_SESSION['avatar_url'] = $avatarUrl;
                    }

                    $stmtUp = $pdo->prepare("
                        UPDATE archery_users
                        SET full_name = :fn,
                            phone = :ph,
                            email = :em
                            $sqlExtra
                        WHERE id = :uid
                    ");
                    $stmtUp->execute($params);

                    $_SESSION['full_name'] = $fullName;

                    ActivityLogger::log(
                        $userId,
                        $clubId,
                        'UPDATE_PROFILE',
                        "Memperbarui profil data diri" . ($avatarUrl ? " dan foto avatar" : "")
                    );

                    $alertMsg = "✔ Data profil dan foto avatar berhasil diperbarui!";
                } catch (PDOException $e) {
                    $alertMsg = "Gagal memperbarui profil: " . $e->getMessage();
                    $alertType = 'danger';
                }
            }
        }
    }

    // 2. GANTI PASSWORD
    elseif ($action === 'change_password') {
        $oldPass = $_POST['old_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        if (empty($oldPass) || empty($newPass)) {
            $alertMsg = "Password lama dan password baru wajib diisi.";
            $alertType = 'danger';
        } elseif ($newPass !== $confirmPass) {
            $alertMsg = "Konfirmasi password baru tidak cocok.";
            $alertType = 'danger';
        } elseif (strlen($newPass) < 6) {
            $alertMsg = "Password baru minimal 6 karakter.";
            $alertType = 'danger';
        } else {
            // Verifikasi password lama
            $stmtUser = $pdo->prepare("SELECT password_hash FROM archery_users WHERE id = :uid LIMIT 1");
            $stmtUser->execute([':uid' => $userId]);
            $userData = $stmtUser->fetch();

            if (!$userData || !password_verify($oldPass, $userData['password_hash'])) {
                $alertMsg = "Password lama tidak sesuai.";
                $alertType = 'danger';
            } else {
                $newHash = password_hash($newPass, PASSWORD_BCRYPT);
                $stmtUpdatePass = $pdo->prepare("UPDATE archery_users SET password_hash = :p WHERE id = :uid");
                $stmtUpdatePass->execute([':p' => $newHash, ':uid' => $userId]);

                ActivityLogger::log($userId, $clubId, 'CHANGE_PASSWORD', "Berhasil mengganti kata sandi akun");

                $alertMsg = "✔ Password berhasil diubah! Gunakan password baru Anda pada login berikutnya.";
            }
        }
    }
}

// Ambil data user terkini dari database
$stmtCur = $pdo->prepare("SELECT id, club_id, username, role, full_name, phone, email, avatar_url, created_at FROM archery_users WHERE id = :uid LIMIT 1");
$stmtCur->execute([':uid' => $userId]);
$user = $stmtCur->fetch();

if (!$user) {
    die("Pengguna tidak ditemukan.");
}

$pageTitle = "Profil Saya & Keamanan Akun - TARGET ARCHERY";
require_once __DIR__ . '/views/header.php';
?>

<style>
.profile-card {
    background: var(--surface);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    padding: 1.5rem;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.profile-avatar-wrapper {
    position: relative;
    width: 110px;
    height: 110px;
    margin: 0 auto 1rem auto;
}
.profile-avatar-img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid var(--primary);
    box-shadow: 0 4px 10px rgba(0,0,0,0.25);
    background: #1e293b;
}
.avatar-badge-edit {
    position: absolute;
    bottom: 2px;
    right: 2px;
    background: var(--primary);
    color: #fff;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid var(--surface);
    cursor: pointer;
    font-size: 0.9rem;
}
.role-pill {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
</style>

<main class="container">
    <div style="margin-bottom: 1.5rem;">
        <h1 style="font-size: 1.6rem; margin-bottom: 0.25rem;">
            👤 Profil Pengguna &amp; Keamanan Akun
        </h1>
        <p style="color: var(--text-muted); font-size: 0.85rem; margin: 0;">
            Kelola data pribadi, perbarui foto profil, dan ubah kata sandi akun Anda.
        </p>
    </div>

    <?php if ($alertMsg): ?>
        <div class="card" style="margin-bottom: 1.5rem; padding: 1rem 1.25rem; border-left: 4px solid <?= $alertType === 'danger' ? 'var(--danger)' : 'var(--success)' ?>; background: rgba(0,0,0,0.2);">
            <?= htmlspecialchars($alertMsg) ?>
        </div>
    <?php endif; ?>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem;">
        <!-- KARTU DATA DIRI & AVATAR -->
        <div class="profile-card">
            <h2 style="font-size: 1.15rem; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.5rem;">
                <span>✏️</span> Data Diri &amp; Foto Profil
            </h2>

            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update_profile">

                <div class="profile-avatar-wrapper">
                    <?php 
                        $avatarSrc = !empty($user['avatar_url']) ? $user['avatar_url'] : 'assets/img/default_avatar.png';
                    ?>
                    <img src="<?= htmlspecialchars($avatarSrc) ?>" alt="Avatar" class="profile-avatar-img" id="avatarPreview" loading="lazy" decoding="async">
                    <label for="avatarInput" class="avatar-badge-edit" title="Ganti Foto">
                        📷
                    </label>
                    <input type="file" id="avatarInput" name="avatar_file" accept="image/jpeg,image/png,image/webp" style="display: none;" onchange="previewAvatar(this)">
                </div>

                <div style="text-align: center; margin-bottom: 1.25rem;">
                    <div style="font-weight: 700; font-size: 1.1rem; color: #fff;">
                        <?= htmlspecialchars($user['full_name'] ?: $user['username']) ?>
                    </div>
                    <div style="margin-top: 0.35rem;">
                        <span class="role-pill" style="background: rgba(2, 132, 199, 0.15); color: var(--primary); border: 1px solid var(--primary);">
                            <?= htmlspecialchars(strtoupper(str_replace('_', ' ', $user['role']))) ?>
                        </span>
                    </div>
                    <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 0.5rem;">
                        Username: <b>@<?= htmlspecialchars($user['username']) ?></b>
                    </div>
                </div>

                <div class="form-group mb-3">
                    <label class="form-label">Nama Lengkap:</label>
                    <input type="text" class="form-control" name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>" required>
                </div>

                <div class="form-group mb-3">
                    <label class="form-label">Nomor WhatsApp / HP:</label>
                    <input type="text" class="form-control" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="0812xxxxxxxx">
                </div>

                <div class="form-group mb-3">
                    <label class="form-label">Alamat Email:</label>
                    <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" placeholder="nama@email.com">
                </div>

                <div class="form-group mb-3">
                    <label class="form-label">Upload Foto Profil Baru:</label>
                    <input type="file" class="form-control form-control-sm" name="avatar_file" accept="image/jpeg,image/png,image/webp">
                    <span style="font-size: 0.72rem; color: var(--text-dim);">Format: JPG, PNG, WEBP. Maks 3MB.</span>
                </div>

                <button type="submit" class="btn btn-primary btn-block mt-3" style="width: 100%;">
                    💾 Simpan Perubahan Profil
                </button>
            </form>
        </div>

        <!-- KARTU GANTI PASSWORD -->
        <div class="profile-card">
            <h2 style="font-size: 1.15rem; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.5rem;">
                <span>🔒</span> Ganti Kata Sandi (Password)
            </h2>

            <form method="POST">
                <input type="hidden" name="action" value="change_password">

                <div class="form-group mb-3">
                    <label class="form-label">Password Saat Ini:</label>
                    <input type="password" class="form-control" name="old_password" required placeholder="Masukkan password lama...">
                </div>

                <div class="form-group mb-3">
                    <label class="form-label">Password Baru:</label>
                    <input type="password" class="form-control" name="new_password" minlength="6" required placeholder="Minimal 6 karakter...">
                </div>

                <div class="form-group mb-3">
                    <label class="form-label">Ulangi Password Baru:</label>
                    <input type="password" class="form-control" name="confirm_password" minlength="6" required placeholder="Ketik ulang password baru...">
                </div>

                <div style="background: rgba(255,255,255,0.03); border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 0.85rem; margin-bottom: 1.25rem;">
                    <div style="font-size: 0.78rem; color: var(--text-muted); line-height: 1.4;">
                        🛡️ <b>Tips Keamanan:</b> Gunakan kombinasi huruf besar, angka, dan simbol untuk perlindungan maksimal. Jangan berikan kata sandi Anda kepada siapa pun.
                    </div>
                </div>

                <button type="submit" class="btn btn-secondary btn-block" style="width: 100%; border-color: var(--primary); color: #fff;">
                    🔑 Perbarui Password
                </button>
            </form>

            <?php if ($user['role'] === 'super_admin'): ?>
                <div style="margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color);">
                    <h3 style="font-size: 0.95rem; margin-bottom: 0.5rem; color: var(--text-muted);">
                        📋 Log Aktivitas Klub
                    </h3>
                    <p style="font-size: 0.78rem; color: var(--text-dim); margin-bottom: 0.75rem;">
                        Lihat riwayat audit login, mutasi, penilaian, dan perubahan sistem di klub Anda.
                    </p>
                    <a href="tenant_activity_logs.php" class="btn btn-outline btn-sm" style="display: inline-block;">
                        🔍 Buka Log Aktivitas Klub
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
function previewAvatar(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('avatarPreview').src = e.target.result;
        }
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

<?php require_once __DIR__ . '/views/footer.php'; ?>
