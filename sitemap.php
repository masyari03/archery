<?php
// c:\laragon\www\target_archery\sitemap.php
// Dynamic XML Sitemap Generator for Target Archery SaaS Platform & Tenant Clubs

header('Content-Type: application/xml; charset=utf-8');

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/tenant_resolver.php';

global $pdo;
$currentHost = $_SERVER['HTTP_HOST'] ?? 'archery.codika.my.id';
$isRoot = TenantResolver::isPlatformRoot($currentHost);

// Fetch all active clubs
$clubs = [];
try {
    if ($pdo) {
        $clubs = TenantResolver::getAllTenants($pdo);
    }
} catch (Exception $e) {
    // Graceful fallback to avoid HTTP 500 on crawlers
    $clubs = [];
}

// Check if this request is directly for a specific tenant or the root platform
$isRoot = TenantResolver::isPlatformRoot();
$activeTenant = !$isRoot ? TenantResolver::resolve($pdo) : null;

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php if ($activeTenant && !$isRoot): 
    // SPECIFIC TENANT SITEMAP
    $tenantBase = TenantResolver::getBaseUrl($activeTenant);
    $lastMod = !empty($activeTenant['updated_at']) ? date('c', strtotime($activeTenant['updated_at'])) : date('c');
    $slug = $activeTenant['subdomain_slug'] ?? $activeTenant['club_code'] ?? 'annahl';
?>
    <!-- Tenant Club Homepage -->
    <url>
        <loc><?= htmlspecialchars($tenantBase . '/', ENT_XML1, 'UTF-8') ?></loc>
        <lastmod><?= $lastMod ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
    </url>
    <!-- Public Archer Registration -->
    <url>
        <loc><?= htmlspecialchars($tenantBase . '/register_archer.php?club=' . urlencode($slug), ENT_XML1, 'UTF-8') ?></loc>
        <lastmod><?= $lastMod ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.8</priority>
    </url>
    <!-- Member & Coach Login -->
    <url>
        <loc><?= htmlspecialchars($tenantBase . '/login.php', ENT_XML1, 'UTF-8') ?></loc>
        <changefreq>monthly</changefreq>
        <priority>0.5</priority>
    </url>
<?php else: 
    // MASTER SAAS ROOT SITEMAP
    $cleanHost = preg_replace('/:\d+$/', '', $currentHost);
    $platformBase = "https://" . ($cleanHost ?: 'archery.codika.my.id');
    $today = date('c');
?>
    <!-- SaaS Marketing & Portal Root -->
    <url>
        <loc><?= htmlspecialchars($platformBase . '/', ENT_XML1, 'UTF-8') ?></loc>
        <lastmod><?= $today ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>1.0</priority>
    </url>
    <url>
        <loc><?= htmlspecialchars($platformBase . '/saas_marketing.php', ENT_XML1, 'UTF-8') ?></loc>
        <lastmod><?= $today ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.9</priority>
    </url>
    <url>
        <loc><?= htmlspecialchars($platformBase . '/login.php', ENT_XML1, 'UTF-8') ?></loc>
        <changefreq>monthly</changefreq>
        <priority>0.6</priority>
    </url>

    <?php foreach ($clubs as $club): 
        $clubBase = TenantResolver::getBaseUrl($club);
        $clubMod = !empty($club['updated_at']) ? date('c', strtotime($club['updated_at'])) : $today;
        $cSlug = $club['subdomain_slug'] ?? $club['club_code'] ?? 'club';
    ?>
    <!-- Tenant Club: <?= htmlspecialchars($club['club_name'], ENT_XML1, 'UTF-8') ?> -->
    <url>
        <loc><?= htmlspecialchars($clubBase . '/', ENT_XML1, 'UTF-8') ?></loc>
        <lastmod><?= $clubMod ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>0.8</priority>
    </url>
    <url>
        <loc><?= htmlspecialchars($clubBase . '/register_archer.php?club=' . urlencode($cSlug), ENT_XML1, 'UTF-8') ?></loc>
        <lastmod><?= $clubMod ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.7</priority>
    </url>
    <?php endforeach; ?>
<?php endif; ?>
</urlset>
