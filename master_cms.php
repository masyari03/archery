<?php
// master_cms.php - Panel Kelola Konten & SEO Web Penjualan SaaS (Khusus Master Admin)
require_once __DIR__ . '/config/auth.php';
Auth::requireRole(['master_admin'], 'login.php');

$currentUser = Auth::user();
$alertMsg = '';
$alertType = 'success';

// Handle POST Save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_marketing_cms') {
        $heroBadge = trim($_POST['hero_badge'] ?? '');
        $heroTitle = trim($_POST['hero_title'] ?? '');
        $heroSubtitle = trim($_POST['hero_subtitle'] ?? '');
        $ctaText = trim($_POST['cta_text'] ?? '');
        $metaTitle = trim($_POST['meta_title'] ?? '');
        $metaDesc = trim($_POST['meta_description'] ?? '');
        $metaKeywords = trim($_POST['meta_keywords'] ?? '');

        try {
            $stmtUpd = $pdo->prepare("
                INSERT INTO `archery_saas_marketing_cms`
                (`id`, `hero_badge`, `hero_title`, `hero_subtitle`, `cta_text`, `meta_title`, `meta_description`, `meta_keywords`)
                VALUES (1, :badge, :title, :sub, :cta, :mtitle, :mdesc, :mkw)
                ON DUPLICATE KEY UPDATE
                `hero_badge`=VALUES(`hero_badge`),
                `hero_title`=VALUES(`hero_title`),
                `hero_subtitle`=VALUES(`hero_subtitle`),
                `cta_text`=VALUES(`cta_text`),
                `meta_title`=VALUES(`meta_title`),
                `meta_description`=VALUES(`meta_description`),
                `meta_keywords`=VALUES(`meta_keywords`);
            ");
            $stmtUpd->execute([
                ':badge' => $heroBadge,
                ':title' => $heroTitle,
                ':sub' => $heroSubtitle,
                ':cta' => $ctaText,
                ':mtitle' => $metaTitle,
                ':mdesc' => $metaDesc,
                ':mkw' => $metaKeywords
            ]);
            $alertMsg = 'Konten dan SEO Web Penjualan SaaS berhasil disimpan!';
        } catch (PDOException $e) {
            $alertMsg = 'Gagal menyimpan konten: ' . $e->getMessage();
            $alertType = 'danger';
        }
    }
}

// Ambil Data CMS Saat Ini
$stmtMkt = $pdo->query("SELECT * FROM archery_saas_marketing_cms WHERE id = 1 LIMIT 1");
$mkt = $stmtMkt->fetch() ?: [];

$pageTitle = "Kelola Marketing SaaS & SEO Platform";
require_once __DIR__ . '/views/navbar.php';
?>

<div class="container pb-5">
    <div class="d-flex justify-content-between align-items-center mb-4 mt-2">
        <div>
            <h3 class="fw-bold mb-1 d-flex align-items-center gap-2">
                <i class="bi bi-rocket-takeoff-fill text-primary"></i> Kelola Web Penjualan SaaS &amp; SEO Platform
            </h3>
            <p class="text-muted mb-0">
                Atur kata-kata promosi penjualan software, paket harga, dan meta tag SEO utama platform Target Archery.
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="saas_marketing.php" target="_blank" class="btn btn-warning fw-bold text-dark d-flex align-items-center gap-2">
                <i class="bi bi-box-arrow-up-right"></i> Lihat Live Web Penjualan
            </a>
        </div>
    </div>

    <?php if ($alertMsg): ?>
        <div class="alert alert-<?= $alertType ?> alert-dismissible fade show d-flex align-items-center gap-2 mb-4" role="alert">
            <i class="bi bi-check-circle-fill fs-5"></i>
            <div><?= htmlspecialchars($alertMsg) ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card p-4 border-secondary shadow-sm">
        <form action="" method="POST">
            <input type="hidden" name="action" value="save_marketing_cms">

            <h5 class="fw-bold text-info mb-3"><i class="bi bi-megaphone-fill"></i> 1. Teks Headline &amp; Banner Utama</h5>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Badge Penanda Atas</label>
                    <input type="text" name="hero_badge" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($mkt['hero_badge'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Teks Tombol Aksi (CTA)</label>
                    <input type="text" name="cta_text" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($mkt['cta_text'] ?? '') ?>" required>
                </div>
                <div class="col-12">
                    <label class="form-label fw-bold">Judul Utama Penawaran (Hero Headline)</label>
                    <input type="text" name="hero_title" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($mkt['hero_title'] ?? '') ?>" required>
                </div>
                <div class="col-12">
                    <label class="form-label fw-bold">Sub-Headline Penjelas</label>
                    <textarea name="hero_subtitle" rows="3" class="form-control bg-dark text-white border-secondary" required><?= htmlspecialchars($mkt['hero_subtitle'] ?? '') ?></textarea>
                </div>
            </div>

            <h5 class="fw-bold text-warning mb-3"><i class="bi bi-google"></i> 2. Pengaturan SEO &amp; Google Rich Snippets</h5>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Meta Title Google</label>
                    <input type="text" name="meta_title" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($mkt['meta_title'] ?? '') ?>" required>
                    <div class="form-text text-muted">Judul yang muncul di hasil pencarian Google (optimal 50-60 karakter).</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Meta Keywords</label>
                    <input type="text" name="meta_keywords" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($mkt['meta_keywords'] ?? '') ?>">
                    <div class="form-text text-muted">Pisahkan dengan koma (contoh: software panahan, aplikasi scoring panahan).</div>
                </div>
                <div class="col-12">
                    <label class="form-label fw-bold">Meta Description</label>
                    <textarea name="meta_description" rows="2" class="form-control bg-dark text-white border-secondary" required><?= htmlspecialchars($mkt['meta_description'] ?? '') ?></textarea>
                    <div class="form-text text-muted">Ringkasan yang muncul di bawah judul di Google (optimal 150-160 karakter).</div>
                </div>
            </div>

            <button type="submit" class="btn btn-warning fw-bold text-dark px-4 py-2 shadow-sm">
                <i class="bi bi-save-fill"></i> Simpan Perubahan Web Marketing
            </button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/views/footer.php'; ?>
