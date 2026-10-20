<?php
// bills.php - Manajemen Tagihan SPP & Payment Gateway (QRIS / VA)
require_once __DIR__ . '/config/auth.php';
Auth::requireAuth();

$currentUser = Auth::user();
$currentArcher = Auth::currentArcher($pdo);

if (!$currentArcher) {
    die("Atlet tidak ditemukan.");
}

$archerId = (int)$currentArcher['id'];

// Ambil Seluruh Tagihan Atlet Ini
$stmt = $pdo->prepare("
    SELECT b.*, cl.club_name 
    FROM archery_bills b
    JOIN archery_clubs cl ON cl.id = b.club_id
    WHERE b.archer_id = :aid
    ORDER BY b.due_date DESC, b.id DESC
");
$stmt->execute([':aid' => $archerId]);
$bills = $stmt->fetchAll();
$pageTitle = "Iuran & Pembayaran - TARGET ARCHERY";
require_once __DIR__ . '/views/header.php';
?>

<main class="container">
    <div style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-size: 1.6rem; margin-bottom: 0.25rem;">💳 TAGIHAN IURAN & PEMBAYARAN</h1>
            <p style="color: var(--text-muted); font-size: 0.88rem;">
                Portal pembayaran resmi SPP bulanan latihan panahan, sewa alat, dan sesi turnamen ananda <b><?= htmlspecialchars($currentArcher['full_name']) ?></b>.
            </p>
        </div>
        <div style="display: flex; gap: 0.5rem;">
            <span class="badge badge-gold" style="font-size: 0.85rem; padding: 0.4rem 0.8rem;">
                Payment Gateway Ready (Midtrans / QRIS)
            </span>
        </div>
    </div>

    <!-- DAFTAR TAGIHAN -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">📋 Riwayat Tagihan & Status Pembayaran</h3>
        </div>

        <?php if (empty($bills)): ?>
            <div style="text-align: center; padding: 2.5rem; color: var(--text-dim);">
                Tidak ada tagihan aktif untuk atlet ini.
            </div>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table class="scorecard-table">
                    <thead>
                        <tr>
                            <th style="text-align: left;">Nama Tagihan</th>
                            <th>Kategori</th>
                            <th>Jatuh Tempo</th>
                            <th>Jumlah</th>
                            <th>Status</th>
                            <th>Aksi Pembayaran</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bills as $b): ?>
                            <tr>
                                <td style="text-align: left; font-family: inherit;">
                                    <b><?= htmlspecialchars($b['bill_title']) ?></b><br>
                                    <small style="color: var(--text-dim);">Periode: <?= htmlspecialchars($b['period_month'] ?? '-') ?></small>
                                </td>
                                <td>
                                    <span class="badge badge-blue">
                                        <?= strtoupper(str_replace('_', ' ', $b['bill_type'])) ?>
                                    </span>
                                </td>
                                <td style="color: var(--text-muted);">
                                    <?= date('d M Y', strtotime($b['due_date'])) ?>
                                </td>
                                <td style="font-size: 1.1rem; font-weight: 800; color: #fff;">
                                    Rp <?= number_format($b['amount'], 0, ',', '.') ?>
                                </td>
                                <td>
                                    <?php if ($b['status'] === 'paid'): ?>
                                        <span class="badge badge-success">LUNAS</span><br>
                                        <small style="color: var(--text-dim); font-size: 0.7rem;"><?= htmlspecialchars($b['payment_ref']) ?></small>
                                    <?php else: ?>
                                        <span class="badge badge-danger">BELUM BAYAR</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($b['status'] === 'paid'): ?>
                                        <button class="btn btn-secondary btn-sm" disabled style="opacity: 0.6;">
                                            ✔ Terverifikasi
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-primary btn-sm" onclick="openPaymentModal(<?= $b['id'] ?>, '<?= htmlspecialchars(addslashes($b['bill_title'])) ?>', <?= $b['amount'] ?>)">
                                            Bayar Sekarang ➔
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- MODAL PAYMENT SIMULATION GATEWAY -->
<div id="paymentModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.8); z-index: 200; align-items: center; justify-content: center; backdrop-filter: blur(8px); padding: 1rem;">
    <div class="card" style="max-width: 440px; width: 100%; border-color: var(--primary);">
        <div class="card-header">
            <h3 class="card-title">Pilih Metode Pembayaran</h3>
            <button type="button" onclick="closePaymentModal()" style="background: none; border: none; color: #fff; font-size: 1.25rem; cursor: pointer;">✕</button>
        </div>
        <div style="margin-bottom: 1rem;">
            <div id="modalBillTitle" style="font-weight: 700; font-size: 1rem; margin-bottom: 0.25rem;"></div>
            <div id="modalBillAmount" style="font-size: 1.5rem; font-weight: 800; color: var(--primary);"></div>
        </div>

        <div class="form-group">
            <label class="form-label">Pilih Channel Pembayaran Gateway:</label>
            <select class="form-control" id="payChannelSelect">
                <option value="QRIS Instant (GoPay / ShopeePay / OVO / DANA)">QRIS Instant (GoPay / ShopeePay / OVO / DANA / BCA)</option>
                <option value="BCA Virtual Account">BCA Virtual Account</option>
                <option value="Mandiri Virtual Account">Mandiri Virtual Account</option>
                <option value="BRI Virtual Account">BRI Virtual Account</option>
                <option value="BNI Virtual Account">BNI Virtual Account</option>
            </select>
        </div>

        <div style="background: rgba(255,255,255,0.03); border: 1px dashed var(--border-color); border-radius: var(--radius-sm); padding: 0.75rem; margin-bottom: 1.25rem; font-size: 0.8rem; color: var(--text-muted); text-align: center;">
            🔒 Pembayaran diproses dengan verifikasi enkripsi otomatis ke server.
        </div>

        <div style="display: flex; gap: 0.75rem;">
            <button type="button" class="btn btn-secondary btn-block" onclick="closePaymentModal()">Batal</button>
            <button type="button" class="btn btn-primary btn-block" id="btnConfirmPay" onclick="processPayment()">Konfirmasi Bayar ➔</button>
        </div>
    </div>
</div>

<script>
let currentBillId = null;

function openPaymentModal(id, title, amount) {
    currentBillId = id;
    document.getElementById('modalBillTitle').textContent = title;
    document.getElementById('modalBillAmount').textContent = 'Rp ' + Number(amount).toLocaleString('id-ID');
    document.getElementById('paymentModal').style.display = 'flex';
}

function closePaymentModal() {
    document.getElementById('paymentModal').style.display = 'none';
    currentBillId = null;
}

function processPayment() {
    if (!currentBillId) return;

    const channel = document.getElementById('payChannelSelect').value;
    const btn = document.getElementById('btnConfirmPay');
    btn.disabled = true;
    btn.textContent = 'Memproses Transaksi...';

    const formData = new FormData();
    formData.append('bill_id', currentBillId);
    formData.append('channel', channel);

    fetch('api/payment_api.php?action=pay_simulate', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        if (res.status === 'success') {
            alert('🎉 ' + res.message + '\nKode Ref: ' + res.payment_ref);
            window.location.reload();
        } else {
            alert('Gagal: ' + res.message);
            btn.disabled = false;
            btn.textContent = 'Konfirmasi Bayar ➔';
        }
    })
    .catch(() => {
        alert('Terjadi kesalahan jaringan.');
        btn.disabled = false;
        btn.textContent = 'Konfirmasi Bayar ➔';
    });
}
</script>

<?php include __DIR__ . '/views/footer.php'; ?>
