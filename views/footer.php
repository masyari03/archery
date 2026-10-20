<?php
// views/footer.php - Standard Modern Footer & Global Scripts
$footerClubName = $clubMeta['club_name'] ?? 'TARGET ARCHERY PLATFORM';
?>
<footer class="no-print" style="margin-top: auto; border-top: 1px solid var(--border-color); padding: 1.5rem; text-align: center; color: var(--text-dim); font-size: 0.85rem; background: var(--bg-card);">
    <div style="max-width: 1200px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
            <b><?= htmlspecialchars($footerClubName) ?></b> &copy; <?= date('Y') ?> &bull; Target Scoring, Performance &amp; Club Management
        </div>
        <div style="display: flex; gap: 1rem; align-items: center;">
            <span class="badge bg-secondary bg-opacity-25 text-info">World Archery Standard</span>
            <span class="text-muted">&bull;</span>
            <span style="color: var(--primary); font-weight: 600;">Multi-Device Ready</span>
        </div>
    </div>
</footer>

<!-- 1. Bootstrap 5.3.3 Bundle JS (Modal, Dropdown, Tooltip, Collapse, Tabs) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- 2. Helper Script untuk Auto-Init Bootstrap Components -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Inisialisasi seluruh tooltip jika ada
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

</body>
</html>
