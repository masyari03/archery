<?php
// pin_login.php - Gerbang Masuk Scoring Mandiri Atlet di Lapangan via PIN 5 Jam
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/tenant_resolver.php';

$currentTenant = TenantResolver::resolve($pdo);
$clubName = $currentTenant['club_name'] ?? 'Target Archery Club';
$clubLogo = $currentTenant['logo_url'] ?? '';
$pageTitle = "Login PIN Lapangan Atlet - " . htmlspecialchars($clubName);
require_once __DIR__ . '/views/header.php';
?>

<style>
.pin-card-wrapper {
    max-width: 440px;
    margin: 2.5rem auto 4rem auto;
    padding: 2.25rem;
    background: #111827;
    border: 1px solid #374151;
    border-radius: 16px;
    box-shadow: 0 20px 45px -10px rgba(0, 0, 0, 0.7);
    text-align: center;
}
.pin-digit-input {
    letter-spacing: 0.5rem;
    font-size: 2.2rem;
    font-weight: 800;
    text-align: center;
    background: #0b1329 !important;
    border: 2px solid #374151 !important;
    color: #f59e0b !important;
    border-radius: 12px;
    padding: 0.6rem 1rem;
    box-shadow: inset 0 2px 4px rgba(0,0,0,0.6);
}
.pin-digit-input:focus {
    border-color: #f59e0b !important;
    box-shadow: 0 0 0 0.25rem rgba(245, 158, 11, 0.25) !important;
}
</style>

<div class="container">
    <div class="pin-card-wrapper">
        <div class="mb-3">
            <?php if (!empty($clubLogo)): ?>
                <img src="<?= htmlspecialchars($clubLogo) ?>" alt="Logo" style="height: 64px; object-fit: contain; margin-bottom: 0.75rem;">
            <?php else: ?>
                <div style="width: 64px; height: 64px; border-radius: 50%; background: rgba(245, 158, 11, 0.15); color: #f59e0b; display: flex; align-items: center; justify-content: center; font-size: 28px; margin: 0 auto 0.75rem auto; border: 1px solid #f59e0b;">
                    🎯
                </div>
            <?php endif; ?>
            <h2 class="fw-bold text-white fs-4 mb-1">Akses Scoring Atlet Lapangan</h2>
            <p class="text-muted small mb-0"><?= htmlspecialchars($clubName) ?></p>
        </div>

        <div class="alert alert-dark border-secondary small py-2 px-3 mb-4 text-start d-flex align-items-center gap-2">
            <i class="bi bi-shield-lock-fill text-warning fs-5"></i>
            <div>
                Masukkan <strong>PIN 6-Digit</strong> yang telah diterbitkan oleh orang tua Anda hari ini (berlaku 5 jam).
            </div>
        </div>

        <form id="pinLoginForm" onsubmit="submitFieldPin(event)">
            <div class="mb-4">
                <label class="form-label small text-muted text-uppercase fw-bold">6 Digit PIN Lapangan</label>
                <input type="text" 
                       id="inputPin" 
                       name="pin" 
                       class="form-control pin-digit-input" 
                       maxlength="6" 
                       inputmode="numeric" 
                       pattern="[0-9]*" 
                       placeholder="••••••" 
                       autocomplete="one-time-code" 
                       required 
                       autofocus>
                <div id="pinAlertBox" class="mt-2 text-danger small" style="display: none;"></div>
            </div>

            <button type="submit" id="btnSubmitPin" class="btn btn-warning w-100 py-3 fw-bold text-dark fs-6 d-flex align-items-center justify-content-center gap-2 shadow-sm">
                <i class="bi bi-bullseye fs-5"></i> Masuk &amp; Buka Papan Bidik
            </button>
        </form>

        <div class="mt-4 pt-3 border-top border-secondary border-opacity-25 text-center">
            <span class="small text-muted d-block mb-1">Bukan atlet atau ingin akses penuh?</span>
            <a href="login.php" class="text-info text-decoration-none small fw-bold">
                <i class="bi bi-box-arrow-in-right"></i> Login Akun Orang Tua / Pelatih ➔
            </a>
        </div>
    </div>
</div>

<script>
async function submitFieldPin(e) {
    e.preventDefault();
    const pin = document.getElementById('inputPin').value.trim();
    const alertBox = document.getElementById('pinAlertBox');
    const btn = document.getElementById('btnSubmitPin');

    if (pin.length !== 6) {
        alertBox.textContent = 'Harap masukkan 6 digit PIN secara lengkap.';
        alertBox.style.display = 'block';
        return;
    }

    alertBox.style.display = 'none';
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Memverifikasi PIN...';

    try {
        const formData = new FormData();
        formData.append('action', 'verify_pin');
        formData.append('pin', pin);

        const res = await fetch('api/pin_auth.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();

        if (data.status === 'success') {
            btn.className = 'btn btn-success w-100 py-3 fw-bold text-white fs-6';
            btn.innerHTML = '✔ Berhasil! Mengalihkan...';
            window.location.href = data.redirect_url || 'plotter.php';
        } else {
            alertBox.textContent = data.message || 'PIN tidak valid atau telah kadaluarsa.';
            alertBox.style.display = 'block';
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-bullseye fs-5"></i> Masuk &amp; Buka Papan Bidik';
            document.getElementById('inputPin').focus();
        }
    } catch (err) {
        alertBox.textContent = 'Terjadi gangguan jaringan saat verifikasi PIN.';
        alertBox.style.display = 'block';
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-bullseye fs-5"></i> Masuk &amp; Buka Papan Bidik';
    }
}
</script>

<?php require_once __DIR__ . '/views/footer.php'; ?>
