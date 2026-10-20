<?php
// master_billing.php - Laporan Tagihan Sewa SaaS & Pengingat WhatsApp Jatuh Tempo (Master Admin)
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/includes/fonnte.php';
Auth::requireAuth();

// Proteksi Akses: Hanya Master Admin
if (($_SESSION['role'] ?? '') !== 'master_admin') {
    die("Akses ditolak. Halaman ini khusus untuk Master Admin.");
}

$alertMsg = null;
$alertType = 'success';
$selectedMonth = $_GET['month'] ?? date('Y-m');

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. GENERATE INVOICE BULAN INI UNTUK SEMUA KLUB MITRA
    if ($action === 'generate_invoices') {
        $month = trim($_POST['period_month'] ?? date('Y-m'));
        $dueDate = date('Y-m-d', strtotime("{$month}-25"));

        try {
            $stmtClubs = $pdo->query("SELECT * FROM archery_clubs WHERE is_active = 1");
            $clubs = $stmtClubs->fetchAll();
            $generatedCount = 0;

            foreach ($clubs as $club) {
                $clubId = (int)$club['id'];
                $scheme = $club['pricing_scheme'] ?? 'flat_monthly';
                $cutoffDay = (int)($club['cutoff_day'] ?? 16);

                // Cek apakah invoice bulan ini sudah dibuat
                $stmtCheck = $pdo->prepare("SELECT id FROM archery_saas_invoices WHERE club_id = :cid AND period_month = :pm");
                $stmtCheck->execute([':cid' => $clubId, ':pm' => $month]);
                if ($stmtCheck->fetch()) {
                    continue; // Lewati jika sudah ada
                }

                $invoiceNo = 'INV-SAAS-' . str_replace('-', '', $month) . '-' . str_pad($clubId, 3, '0', STR_PAD_LEFT);

                if ($scheme === 'flat_monthly') {
                    $totalAmount = (float)$club['monthly_flat_fee'];
                    $rate = (float)$club['monthly_flat_fee'];
                    
                    // Hitung jumlah atlet untuk info statistik
                    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM archery_archers WHERE club_id = :cid AND is_active = 1");
                    $stmtCount->execute([':cid' => $clubId]);
                    $totalArchers = (int)$stmtCount->fetchColumn();
                    $billableArchers = $totalArchers;
                    $freeArchers = 0;
                } else {
                    // SKEMA PER ANAK DENGAN ATURAN CUT-OFF TANGGAL 16:
                    // Jika anak masuk setelah tanggal 16 di bulan berjalan, anak tersebut GRATIS (belum ditagihkan)
                    $rate = (float)$club['fee_per_archer'];

                    // Ambil seluruh atlet aktif klub
                    $stmtArchers = $pdo->prepare("SELECT id, created_at FROM archery_archers WHERE club_id = :cid AND is_active = 1");
                    $stmtArchers->execute([':cid' => $clubId]);
                    $allArchers = $stmtArchers->fetchAll();

                    $totalArchers = count($allArchers);
                    $billableArchers = 0;
                    $freeArchers = 0;

                    foreach ($allArchers as $a) {
                        $joinMonth = date('Y-m', strtotime($a['created_at']));
                        $joinDay = (int)date('d', strtotime($a['created_at']));

                        // Jika atlet bergabung di bulan yang sama dengan bulan tagihan dan tanggalnya > cutoffDay (16)
                        if ($joinMonth === $month && $joinDay > $cutoffDay) {
                            $freeArchers++;
                        } else {
                            $billableArchers++;
                        }
                    }

                    $totalAmount = $billableArchers * $rate;
                }

                $stmtInsertInv = $pdo->prepare("
                    INSERT INTO archery_saas_invoices
                    (invoice_no, club_id, period_month, pricing_scheme, rate_per_unit, total_archers_count, billable_archers_count, prorated_free_archers_count, total_amount, due_date, status, notes)
                    VALUES
                    (:inv, :cid, :pm, :sch, :rate, :tot, :bill, :free, :amount, :due, 'unpaid', :notes)
                ");
                $stmtInsertInv->execute([
                    ':inv' => $invoiceNo,
                    ':cid' => $clubId,
                    ':pm' => $month,
                    ':sch' => $scheme,
                    ':rate' => $rate,
                    ':tot' => $totalArchers,
                    ':bill' => $billableArchers,
                    ':free' => $freeArchers,
                    ':amount' => $totalAmount,
                    ':due' => $dueDate,
                    ':notes' => ($scheme === 'per_archer') 
                        ? "Dihitung dari {$billableArchers} anak aktif (bebas biaya {$freeArchers} anak masuk setelah tgl {$cutoffDay})"
                        : "Paket Sewa Flat Bulanan {$club['club_name']}"
                ]);
                $generatedCount++;
            }

            $alertMsg = "✔ Berhasil men-generate {$generatedCount} invoice SaaS untuk periode {$month}!";
        } catch (Exception $e) {
            $alertMsg = "Gagal membuat invoice: " . $e->getMessage();
            $alertType = 'danger';
        }
    }

    // 2. VERIFIKASI PEMBAYARAN LUNAS DARI KLUB (PERPANJANG MASA AKTIF)
    elseif ($action === 'mark_paid') {
        $invId = (int)($_POST['invoice_id'] ?? 0);
        $payRef = trim($_POST['payment_ref'] ?? 'TRX-MANUAL-' . time());

        try {
            $stmtInv = $pdo->prepare("SELECT * FROM archery_saas_invoices WHERE id = :id");
            $stmtInv->execute([':id' => $invId]);
            $inv = $stmtInv->fetch();

            if ($inv && $inv['status'] !== 'paid') {
                $pdo->beginTransaction();

                // Update invoice menjadi 'paid'
                $stmtUpInv = $pdo->prepare("UPDATE archery_saas_invoices SET status = 'paid', payment_ref = :ref, paid_at = NOW() WHERE id = :id");
                $stmtUpInv->execute([':ref' => $payRef, ':id' => $invId]);

                // Perpanjang masa aktif klub +30 hari dari tanggal jatuh tempo atau tanggal sekarang
                $stmtExt = $pdo->prepare("
                    UPDATE archery_clubs 
                    SET subscription_status = 'active',
                        subscription_expires_at = DATE_ADD(GREATEST(subscription_expires_at, CURDATE()), INTERVAL 30 DAY)
                    WHERE id = :cid
                ");
                $stmtExt->execute([':cid' => $inv['club_id']]);

                $pdo->commit();
                $alertMsg = "✔ Invoice {$inv['invoice_no']} BERHASIL DIVERIFIKASI LUNAS! Masa aktif klub otomatis diperpanjang +30 hari.";
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $alertMsg = "Gagal verifikasi pembayaran: " . $e->getMessage();
            $alertType = 'danger';
        }
    }

    // 3. KIRIM PENGINGAT WHATSAPP TAGIHAN KE ADMIN KLUB
    elseif ($action === 'send_wa_reminder') {
        $invId = (int)($_POST['invoice_id'] ?? 0);

        $stmtInv = $pdo->prepare("
            SELECT i.*, c.club_name, c.admin_wa_phone, c.subscription_expires_at,
                   s.bank_account_info, s.wa_reminder_template
            FROM archery_saas_invoices i
            JOIN archery_clubs c ON c.id = i.club_id
            JOIN archery_saas_settings s ON s.id = 1
            WHERE i.id = :id
        ");
        $stmtInv->execute([':id' => $invId]);
        $inv = $stmtInv->fetch();

        if ($inv) {
            $daysLeft = max(0, (int)round((strtotime($inv['due_date']) - time()) / 86400));
            $schemeText = $inv['pricing_scheme'] === 'flat_monthly' ? 'Flat Bulanan' : 'Per Anak (Rp ' . number_format($inv['rate_per_unit'], 0, ',', '.') . '/anak)';

            $tpl = $inv['wa_reminder_template'];
            $vars = [
                '{nama_klub}' => $inv['club_name'],
                '{jatuh_tempo}' => date('d M Y', strtotime($inv['due_date'])),
                '{sisa_hari}' => $daysLeft,
                '{bulan}' => $inv['period_month'],
                '{skema_tarif}' => $schemeText,
                '{total_atlet}' => $inv['total_archers_count'],
                '{atlet_bayar}' => $inv['billable_archers_count'],
                '{atlet_gratis}' => $inv['prorated_free_archers_count'],
                '{total_tagihan}' => number_format($inv['total_amount'], 0, ',', '.'),
                '{info_rekening}' => $inv['bank_account_info'] ?? 'BCA 1234567890 a/n Master Admin'
            ];

            $msg = str_replace(array_keys($vars), array_values($vars), $tpl);
            $res = FonnteWA::send($inv['admin_wa_phone'], $msg, 1);

            // Update tanggal pengiriman pengingat
            $pdo->prepare("UPDATE archery_saas_invoices SET reminder_wa_sent_at = NOW() WHERE id = :id")->execute([':id' => $invId]);

            if ($res['status']) {
                $alertMsg = "✔ Pesan pengingat WhatsApp berhasil dikirim ke Admin {$inv['club_name']} ({$inv['admin_wa_phone']})!";
            } else {
                $alertMsg = "Invoice tercatat, namun pengiriman WA gagal: " . ($res['message'] ?? 'Periksa token Fonnte');
                $alertType = 'warning';
            }
        }
    }

    // 4. SIMPAN PENGATURAN REKENING & TEMPLATE WA MASTER ADMIN
    elseif ($action === 'save_settings') {
        $bankInfo = trim($_POST['bank_account_info'] ?? '');
        $waReminderTpl = trim($_POST['wa_reminder_template'] ?? '');
        $masterWa = trim($_POST['master_admin_wa'] ?? '');

        try {
            $stmtSaasUp = $pdo->prepare("
                UPDATE archery_saas_settings 
                SET bank_account_info = :bank, 
                    wa_reminder_template = :tpl,
                    master_admin_wa = :wa
                WHERE id = 1
            ");
            $stmtSaasUp->execute([
                ':bank' => $bankInfo,
                ':tpl' => $waReminderTpl,
                ':wa' => $masterWa
            ]);
            $alertMsg = "✔ Pengaturan rekening Master Admin & template pengingat WA berhasil disimpan!";
        } catch (PDOException $e) {
            $alertMsg = "Gagal menyimpan pengaturan: " . $e->getMessage();
            $alertType = 'danger';
        }
    }
}

// Ambil Seluruh Invoice untuk Bulan Terpilih
$stmtInvoices = $pdo->prepare("
    SELECT i.*, c.club_name, c.admin_wa_phone, c.city, c.subscription_status
    FROM archery_saas_invoices i
    JOIN archery_clubs c ON c.id = i.club_id
    WHERE i.period_month = :pm
    ORDER BY i.due_date ASC, i.id DESC
");
$stmtInvoices->execute([':pm' => $selectedMonth]);
$invoices = $stmtInvoices->fetchAll();

// Hitung Statistik Tagihan
$totalRevenueBilled = array_sum(array_column($invoices, 'total_amount'));
$totalRevenuePaid = 0;
$unpaidInvoicesCount = 0;
foreach ($invoices as $iv) {
    if ($iv['status'] === 'paid') $totalRevenuePaid += (float)$iv['total_amount'];
    if ($iv['status'] === 'unpaid') $unpaidInvoicesCount++;
}

// Ambil Pengaturan Master Admin
$saasSettings = $pdo->query("SELECT * FROM archery_saas_settings WHERE id = 1")->fetch();
?>
$pageTitle = "Laporan Tagihan & Pembayaran SaaS - TARGET ARCHERY";
require_once __DIR__ . '/views/header.php';
?>

<main class="container">
    <!-- HEADER -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-size: 1.6rem; margin-bottom: 0.25rem;">
                💰 Laporan Pembayaran SaaS & Penagihan Klub
            </h1>
            <p style="color: var(--text-muted); font-size: 0.85rem; margin: 0;">
                Kelola tagihan sewa software para tenant (Flat vs Per Anak dengan prorata tgl 16) & kirim pengingat WhatsApp otomatis.
            </p>
        </div>
        <div style="display: flex; gap: 0.5rem; align-items: center;">
            <form method="GET" style="display: flex; gap: 0.5rem; align-items: center;">
                <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted);">PERIODE:</label>
                <input type="month" name="month" class="form-control" value="<?= htmlspecialchars($selectedMonth) ?>" onchange="this.form.submit()" style="padding: 0.35rem 0.75rem; width: auto;">
            </form>
            <form method="POST" style="margin: 0;">
                <input type="hidden" name="action" value="generate_invoices">
                <input type="hidden" name="period_month" value="<?= htmlspecialchars($selectedMonth) ?>">
                <button type="submit" class="btn btn-primary btn-sm" onclick="return confirm('Generate invoice untuk seluruh klub bulan ini?');">
                    ⚡ Generate Tagihan Bulan Ini
                </button>
            </form>
        </div>
    </div>

    <?php if ($alertMsg): ?>
        <div class="card" style="margin-bottom: 1.5rem; padding: 1rem 1.25rem; border-left: 4px solid <?= $alertType === 'danger' ? 'var(--danger)' : ($alertType === 'warning' ? '#f59e0b' : 'var(--success)') ?>; background: rgba(0,0,0,0.2);">
            <?= htmlspecialchars($alertMsg) ?>
        </div>
    <?php endif; ?>

    <!-- STATS CARD -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.75rem;">
        <div class="card" style="text-align: center; border-left: 4px solid var(--primary);">
            <span class="stat-label">TOTAL TAGIHAN BULAN INI</span>
            <div class="stat-value" style="color: var(--primary);">
                Rp <?= number_format($totalRevenueBilled, 0, ',', '.') ?>
            </div>
            <span class="stat-sub">Dari <?= count($invoices) ?> klub tenant</span>
        </div>
        <div class="card" style="text-align: center; border-left: 4px solid #34d399;">
            <span class="stat-label">SUDAH DITERIMA (LUNAS)</span>
            <div class="stat-value" style="color: #34d399;">
                Rp <?= number_format($totalRevenuePaid, 0, ',', '.') ?>
            </div>
            <span class="stat-sub">Kas sewa SaaS masuk</span>
        </div>
        <div class="card" style="text-align: center; border-left: 4px solid #ef4444;">
            <span class="stat-label">BELUM MEMBAYAR</span>
            <div class="stat-value" style="color: #ef4444;">
                <?= $unpaidInvoicesCount ?> Klub
            </div>
            <span class="stat-sub">Bisa diingatkan via WhatsApp</span>
        </div>
    </div>

    <!-- PENJELASAN ATURAN PRORATA TANGGAL 16 -->
    <div class="card" style="margin-bottom: 1.5rem; border-left: 4px solid #34d399; background: rgba(16, 185, 129, 0.08); padding: 1.25rem;">
        <h3 style="margin: 0 0 0.5rem 0; font-size: 0.95rem; color: #34d399;">
            🎯 Aturan Skema Per Anak & Prorata Tanggal 16 (Fair Billing)
        </h3>
        <p style="font-size: 0.82rem; color: var(--text-muted); line-height: 1.5; margin: 0;">
            Pada klub berskema <b>Per Anak</b>, atlet yang baru terdaftar <b>setelah tanggal 16</b> di bulan berjalan (hari ke-17 s/d akhir bulan) berstatus <b>Bebas Biaya / Belum Dihitung Tagihan</b> pada bulan tersebut. Klub tidak akan dibebani biaya atlet baru yang baru mencoba latihan di paruh akhir bulan, dan baru akan mulai ditagihkan di periode berikutnya.
        </p>
    </div>

    <!-- TABEL INVOICE SAAS -->
    <div class="card" style="margin-bottom: 2rem;">
        <div class="card-header">
            <h2 class="card-title">Daftar Tagihan Sewa SaaS Periode <?= date('F Y', strtotime($selectedMonth . '-01')) ?></h2>
            <span style="font-size: 0.8rem; color: var(--text-dim);">
                Tagihan dihitung otomatis per klub
            </span>
        </div>

        <?php if (empty($invoices)): ?>
            <div style="text-align: center; padding: 2.5rem; color: var(--text-muted);">
                Belum ada tagihan yang dibuat untuk bulan <?= date('F Y', strtotime($selectedMonth . '-01')) ?>.<br>
                Klik tombol <b>"⚡ Generate Tagihan Bulan Ini"</b> di atas untuk menghitung otomatis.
            </div>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table class="scorecard-table">
                    <thead>
                        <tr>
                            <th>No. Invoice</th>
                            <th>Klub Panahan</th>
                            <th>Skema & Rincian Atlet</th>
                            <th>Jatuh Tempo</th>
                            <th>Total Tagihan</th>
                            <th>Status Bayar</th>
                            <th>Aksi Tagihan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invoices as $inv): ?>
                            <tr>
                                <td>
                                    <b><?= htmlspecialchars($inv['invoice_no']) ?></b><br>
                                    <span style="font-size: 0.72rem; color: var(--text-dim);"><?= date('d M Y', strtotime($inv['created_at'])) ?></span>
                                </td>
                                <td>
                                    <b style="color: #fff; font-size: 0.95rem;"><?= htmlspecialchars($inv['club_name']) ?></b><br>
                                    <span style="font-size: 0.75rem; color: var(--text-muted);"><?= htmlspecialchars($inv['city']) ?> • WA: <?= htmlspecialchars($inv['admin_wa_phone']) ?></span>
                                </td>
                                <td style="font-size: 0.8rem;">
                                    <?php if ($inv['pricing_scheme'] === 'flat_monthly'): ?>
                                        <span class="badge badge-primary">Flat Bulanan</span>
                                    <?php else: ?>
                                        <span class="badge" style="background: rgba(16,185,129,0.15); color: #34d399; border: 1px solid #10b981;">
                                            Per Anak @ Rp <?= number_format($inv['rate_per_unit'], 0, ',', '.') ?>
                                        </span><br>
                                        <span style="color: #34d399; font-weight: 700;"><?= $inv['billable_archers_count'] ?> anak ditagih</span>
                                        <?php if ($inv['prorated_free_archers_count'] > 0): ?>
                                            &bull; <span style="color: #f59e0b;"><?= $inv['prorated_free_archers_count'] ?> anak bebas biaya (tgl >16)</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <div style="font-size: 0.72rem; color: var(--text-dim); margin-top: 2px;">
                                        Total Atlet: <?= $inv['total_archers_count'] ?> anak
                                    </div>
                                </td>
                                <td style="white-space: nowrap; font-size: 0.82rem;">
                                    <?= date('d M Y', strtotime($inv['due_date'])) ?>
                                </td>
                                <td style="font-size: 1.15rem; font-weight: 800; color: #fff;">
                                    Rp <?= number_format($inv['total_amount'], 0, ',', '.') ?>
                                </td>
                                <td>
                                    <?php if ($inv['status'] === 'paid'): ?>
                                        <span class="badge badge-success">✔ LUNAS</span><br>
                                        <span style="font-size: 0.7rem; color: var(--text-dim);"><?= htmlspecialchars($inv['payment_ref']) ?></span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">BELUM BAYAR</span>
                                    <?php endif; ?>
                                </td>
                                <td style="white-space: nowrap;">
                                    <div style="display: flex; gap: 0.35rem;">
                                        <?php if ($inv['status'] !== 'paid'): ?>
                                            <!-- VERIFIKASI LUNAS -->
                                            <button type="button" class="btn btn-primary btn-sm" onclick="openMarkPaidModal(<?= $inv['id'] ?>, '<?= htmlspecialchars(addslashes($inv['club_name'])) ?>', <?= $inv['total_amount'] ?>)">
                                                ✔ Tandai Lunas
                                            </button>

                                            <!-- KIRIM PENGINGAT WA -->
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Kirimkan pesan pengingat tagihan WA ke Admin Klub?');">
                                                <input type="hidden" name="action" value="send_wa_reminder">
                                                <input type="hidden" name="invoice_id" value="<?= $inv['id'] ?>">
                                                <button type="submit" class="btn btn-secondary btn-sm" style="color: #25d366; border-color: #25d366;" title="Kirim Pengingat WhatsApp Fonnte">
                                                    💬 Kirim WA
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span style="font-size: 0.75rem; color: #34d399; font-weight: 700;">
                                                Lunas (<?= date('d/m/Y', strtotime($inv['paid_at'] ?? 'now')) ?>)
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- PENGATURAN REKENING PEMBAYARAN & TEMPLATE WA MASTER ADMIN -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">⚙️ Rekening Penerimaan Master Admin & Template Pengingat WA</h2>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="save_settings">

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label class="form-label">Nomor WhatsApp Master Admin (Call Center SaaS):</label>
                    <input type="text" class="form-control" name="master_admin_wa" value="<?= htmlspecialchars($saasSettings['master_admin_wa'] ?? '081299887766') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Informasi Rekening Pembayaran Sewa (Muncul di WA Klub):</label>
                    <input type="text" class="form-control" name="bank_account_info" value="<?= htmlspecialchars($saasSettings['bank_account_info'] ?? '') ?>" placeholder="BCA 1234567890 a/n Master Admin Platform" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Template Pesan Pengingat Jatuh Tempo (WhatsApp Bot Fonnte):</label>
                <div style="font-size: 0.75rem; color: var(--text-dim); margin-bottom: 0.35rem;">
                    Variabel: <code>{nama_klub}</code>, <code>{jatuh_tempo}</code>, <code>{sisa_hari}</code>, <code>{bulan}</code>, <code>{skema_tarif}</code>, <code>{total_atlet}</code>, <code>{atlet_bayar}</code>, <code>{atlet_gratis}</code>, <code>{total_tagihan}</code>, <code>{info_rekening}</code>
                </div>
                <textarea class="form-control" name="wa_reminder_template" rows="7"><?= htmlspecialchars($saasSettings['wa_reminder_template']) ?></textarea>
            </div>

            <button type="submit" class="btn btn-secondary">
                💾 Simpan Rekening & Template WA
            </button>
        </form>
    </div>
</main>

<!-- MODAL TANDAI LUNAS -->
<div id="markPaidModal" class="login-modal-overlay" style="display: none;">
    <div class="login-error-modal">
        <h3 style="margin-top: 0; color: #34d399;">✔ Verifikasi Pembayaran Sewa SaaS Lunas</h3>
        <p style="font-size: 0.85rem; color: var(--text-muted);">
            Konfirmasi pelunasan tagihan dari klub <b id="paidClubName" style="color: #fff;"></b> sebesar <b id="paidAmount" style="color: #34d399;"></b>.
        </p>
        <p style="font-size: 0.78rem; color: var(--text-dim);">
            Masa aktif klub akan otomatis diperpanjang +30 hari di sistem.
        </p>

        <form method="POST">
            <input type="hidden" name="action" value="mark_paid">
            <input type="hidden" name="invoice_id" id="paidInvoiceId">
            <div class="form-group" style="text-align: left;">
                <label class="form-label">Nomor Referensi Transfer / Catatan:</label>
                <input type="text" class="form-control" name="payment_ref" placeholder="cth: TRF-BCA-889911" required value="TRF-BCA-<?= time() ?>">
            </div>
            <div style="display: flex; gap: 0.75rem; margin-top: 1rem;">
                <button type="button" class="btn btn-secondary btn-block" onclick="closeMarkPaidModal()">Batal</button>
                <button type="submit" class="btn btn-primary btn-block">✔ Konfirmasi Lunas & Perpanjang</button>
            </div>
        </form>
    </div>
</div>

<script>
function openMarkPaidModal(id, clubName, amount) {
    document.getElementById('paidInvoiceId').value = id;
    document.getElementById('paidClubName').textContent = clubName;
    document.getElementById('paidAmount').textContent = 'Rp ' + Number(amount).toLocaleString('id-ID');
    document.getElementById('markPaidModal').style.display = 'flex';
}
function closeMarkPaidModal() {
    document.getElementById('markPaidModal').style.display = 'none';
}
</script>

<?php include __DIR__ . '/views/footer.php'; ?>
