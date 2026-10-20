<?php
// setup_db.php - Migration & Seeder Lengkap untuk Database archery (SaaS Multi-Tenant Edition)
$host = '127.0.0.1';
$port = '3306';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : '';

try {
    $pdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    // 1. Buat Database archery
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `archery` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
    $pdo->exec("USE `archery`;");
    echo "✔ Database `archery` berhasil dibuat/dipilih.\n";

    // Helper untuk tambah kolom jika belum ada
    function addColumnIfNotExists(PDO $pdo, string $table, string $column, string $definition) {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `{$table}` LIKE ?");
        $stmt->execute([$column]);
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition};");
        }
    }

    // 2. Tabel archery_clubs
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS `archery_clubs` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `club_name` VARCHAR(150) NOT NULL,
        `club_code` VARCHAR(50) NOT NULL UNIQUE,
        `logo_url` VARCHAR(255) DEFAULT 'assets/img/default_logo.png',
        `address` TEXT DEFAULT NULL,
        `phone` VARCHAR(30) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    addColumnIfNotExists($pdo, 'archery_clubs', 'admin_wa_phone', "VARCHAR(30) DEFAULT '081299887766'");
    addColumnIfNotExists($pdo, 'archery_clubs', 'chairman_name', "VARCHAR(150) DEFAULT 'Ustadz Asy\'ari, S.Pd.'");
    addColumnIfNotExists($pdo, 'archery_clubs', 'primary_color', "VARCHAR(20) DEFAULT '#0284c7'");
    addColumnIfNotExists($pdo, 'archery_clubs', 'theme_mode', "ENUM('dark', 'light') DEFAULT 'dark'");
    addColumnIfNotExists($pdo, 'archery_clubs', 'fonnte_token', "VARCHAR(255) DEFAULT ''");
    addColumnIfNotExists($pdo, 'archery_clubs', 'fonnte_enabled', "TINYINT(1) DEFAULT 1");
    addColumnIfNotExists($pdo, 'archery_clubs', 'wa_payment_template', "TEXT DEFAULT NULL");
    addColumnIfNotExists($pdo, 'archery_clubs', 'wa_promotion_template', "TEXT DEFAULT NULL");
    addColumnIfNotExists($pdo, 'archery_clubs', 'is_active', "TINYINT(1) DEFAULT 1");
    
    // SaaS Multi-Tenant Billing & Subscription Columns
    addColumnIfNotExists($pdo, 'archery_clubs', 'pricing_scheme', "ENUM('flat_monthly', 'per_archer') NOT NULL DEFAULT 'flat_monthly'");
    addColumnIfNotExists($pdo, 'archery_clubs', 'monthly_flat_fee', "DECIMAL(12,2) DEFAULT 150000.00");
    addColumnIfNotExists($pdo, 'archery_clubs', 'fee_per_archer', "DECIMAL(12,2) DEFAULT 10000.00");
    addColumnIfNotExists($pdo, 'archery_clubs', 'cutoff_day', "INT NOT NULL DEFAULT 16");
    addColumnIfNotExists($pdo, 'archery_clubs', 'subscription_status', "ENUM('active', 'trial', 'past_due', 'suspended') DEFAULT 'active'");
    addColumnIfNotExists($pdo, 'archery_clubs', 'subscription_expires_at', "DATE DEFAULT '2027-12-31'");
    addColumnIfNotExists($pdo, 'archery_clubs', 'max_archers_quota', "INT DEFAULT 100");
    addColumnIfNotExists($pdo, 'archery_clubs', 'city', "VARCHAR(100) DEFAULT 'Bogor'");
    addColumnIfNotExists($pdo, 'archery_clubs', 'subdomain_slug', "VARCHAR(50) DEFAULT 'annahl'");
    addColumnIfNotExists($pdo, 'archery_clubs', 'custom_domain', "VARCHAR(150) DEFAULT NULL");
    addColumnIfNotExists($pdo, 'archery_clubs', 'favicon_url', "VARCHAR(255) DEFAULT 'assets/img/default_favicon.png'");
    echo "✔ Tabel `archery_clubs` siap dengan kolom modern & SaaS Domain Resolver.\n";

    // 3. Tabel archery_users (Update ENUM role with master_admin)
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS `archery_users` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `club_id` INT DEFAULT 1,
        `username` VARCHAR(100) NOT NULL UNIQUE,
        `password_hash` VARCHAR(255) NOT NULL,
        `full_name` VARCHAR(150) NOT NULL,
        `role` ENUM('master_admin', 'super_admin', 'coach', 'parent', 'archer') NOT NULL DEFAULT 'parent',
        `phone` VARCHAR(30) DEFAULT NULL,
        `email` VARCHAR(150) DEFAULT NULL,
        `avatar_url` VARCHAR(255) DEFAULT NULL,
        `is_active` TINYINT(1) DEFAULT 1,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    $pdo->exec("ALTER TABLE `archery_users` MODIFY COLUMN `role` ENUM('master_admin', 'super_admin', 'coach', 'parent', 'archer') NOT NULL DEFAULT 'parent';");
    echo "✔ Tabel `archery_users` siap dengan role `master_admin`.\n";

    // 3b. Tabel archery_saas_invoices (Tagihan Sewa SaaS Klub ke Master Admin)
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS `archery_saas_invoices` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `invoice_no` VARCHAR(50) NOT NULL UNIQUE,
        `club_id` INT NOT NULL,
        `period_month` VARCHAR(7) NOT NULL,
        `pricing_scheme` ENUM('flat_monthly', 'per_archer') NOT NULL DEFAULT 'flat_monthly',
        `rate_per_unit` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `total_archers_count` INT NOT NULL DEFAULT 0,
        `billable_archers_count` INT NOT NULL DEFAULT 0,
        `prorated_free_archers_count` INT NOT NULL DEFAULT 0,
        `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `due_date` DATE NOT NULL,
        `status` ENUM('unpaid', 'paid', 'overdue', 'cancelled') NOT NULL DEFAULT 'unpaid',
        `payment_method` VARCHAR(50) DEFAULT NULL,
        `payment_ref` VARCHAR(100) DEFAULT NULL,
        `paid_at` DATETIME DEFAULT NULL,
        `reminder_wa_sent_at` DATETIME DEFAULT NULL,
        `notes` TEXT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (`club_id`),
        INDEX (`period_month`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "✔ Tabel `archery_saas_invoices` siap.\n";

    // 3c. Tabel archery_saas_settings (Pengaturan Master Admin SaaS)
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS `archery_saas_settings` (
        `id` INT PRIMARY KEY,
        `platform_name` VARCHAR(150) NOT NULL DEFAULT 'TARGET ARCHERY SAAS PLATFORM',
        `master_admin_wa` VARCHAR(30) NOT NULL DEFAULT '081299887766',
        `fonnte_token` VARCHAR(255) DEFAULT '',
        `bank_account_info` TEXT DEFAULT NULL,
        `default_flat_fee` DECIMAL(12,2) NOT NULL DEFAULT 150000.00,
        `default_per_archer_fee` DECIMAL(12,2) NOT NULL DEFAULT 10000.00,
        `default_cutoff_day` INT NOT NULL DEFAULT 16,
        `wa_reminder_template` TEXT DEFAULT NULL,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "✔ Tabel `archery_saas_settings` siap.\n";

    // 3d. Tabel archery_cms_content (Konten Web Publik Profil Tiap Klub)
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS `archery_cms_content` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `club_id` INT NOT NULL UNIQUE,
        `hero_headline` VARCHAR(255) NOT NULL DEFAULT 'Membangun Karakter & Prestasi Memanah Sesuai Sunnah',
        `hero_subheadline` TEXT DEFAULT NULL,
        `hero_cta_text` VARCHAR(100) DEFAULT 'Daftar Latihan Sekarang',
        `hero_image_url` VARCHAR(255) DEFAULT 'assets/img/default_hero.jpg',
        `about_title` VARCHAR(255) DEFAULT 'Tentang Perkumpulan Panahan Kami',
        `about_content` TEXT DEFAULT NULL,
        `about_points` TEXT DEFAULT NULL,
        `philosophy_title` VARCHAR(255) DEFAULT 'Filosofi Olahraga Sunnah Memanah',
        `philosophy_content` TEXT DEFAULT NULL,
        `google_maps_url` TEXT DEFAULT NULL,
        `instagram_url` VARCHAR(255) DEFAULT NULL,
        `facebook_url` VARCHAR(255) DEFAULT NULL,
        `youtube_url` VARCHAR(255) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (`club_id`) REFERENCES `archery_clubs`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "✔ Tabel `archery_cms_content` siap.\n";

    // 3e. Tabel archery_cms_programs (Program Kursus / Kelas Panahan)
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS `archery_cms_programs` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `club_id` INT NOT NULL,
        `program_name` VARCHAR(150) NOT NULL,
        `target_audience` VARCHAR(100) NOT NULL,
        `bow_type` VARCHAR(100) NOT NULL,
        `fee_info` VARCHAR(100) NOT NULL,
        `session_frequency` VARCHAR(100) NOT NULL,
        `description` TEXT DEFAULT NULL,
        `equipment_included` TINYINT(1) DEFAULT 1,
        `order_num` INT DEFAULT 1,
        `is_active` TINYINT(1) DEFAULT 1,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (`club_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "✔ Tabel `archery_cms_programs` siap.\n";

    // 3f. Tabel archery_cms_schedules (Jadwal Latihan Mingguan)
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS `archery_cms_schedules` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `club_id` INT NOT NULL,
        `day_name` VARCHAR(50) NOT NULL,
        `time_range` VARCHAR(100) NOT NULL,
        `session_type` VARCHAR(100) NOT NULL,
        `range_location` VARCHAR(150) NOT NULL,
        `coach_in_charge` VARCHAR(150) DEFAULT 'Coach Fadli',
        `order_num` INT DEFAULT 1,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (`club_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "✔ Tabel `archery_cms_schedules` siap.\n";

    // 3g. Tabel archery_cms_galleries (Dokumentasi Lapangan & Prestasi)
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS `archery_cms_galleries` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `club_id` INT NOT NULL,
        `title` VARCHAR(150) NOT NULL,
        `image_url` VARCHAR(255) NOT NULL,
        `category` ENUM('fasilitas', 'latihan', 'turnamen', 'prestasi') DEFAULT 'latihan',
        `order_num` INT DEFAULT 1,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (`club_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "✔ Tabel `archery_cms_galleries` siap.\n";

    // 3h. Tabel archery_cms_registrations (Pendaftaran Atlet Baru dari Web CMS)
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS `archery_cms_registrations` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `club_id` INT NOT NULL,
        `student_name` VARCHAR(150) NOT NULL,
        `student_nickname` VARCHAR(100) DEFAULT NULL,
        `gender` ENUM('L', 'P') DEFAULT 'L',
        `birth_date` DATE DEFAULT NULL,
        `parent_name` VARCHAR(150) NOT NULL,
        `parent_phone` VARCHAR(30) NOT NULL,
        `parent_email` VARCHAR(150) DEFAULT NULL,
        `program_choice` VARCHAR(150) DEFAULT 'Kelas Pemula (Dasar)',
        `has_own_bow` TINYINT(1) DEFAULT 0,
        `notes` TEXT DEFAULT NULL,
        `status` ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (`club_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "✔ Tabel `archery_cms_registrations` siap.\n";

    // 3i. Tabel archery_rbac_permissions (Manajemen Hak Akses Menu Header per Role)
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS `archery_rbac_permissions` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `club_id` INT NOT NULL,
        `role` ENUM('super_admin', 'coach', 'parent', 'archer') NOT NULL,
        `menu_key` VARCHAR(50) NOT NULL,
        `is_visible` TINYINT(1) NOT NULL DEFAULT 1,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `unique_club_role_menu` (`club_id`, `role`, `menu_key`),
        INDEX (`club_id`, `role`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "✔ Tabel `archery_rbac_permissions` siap.\n";

    // 3j. Tambah Kolom SEO & Metadata ke archery_cms_content
    addColumnIfNotExists($pdo, 'archery_cms_content', 'meta_title', "VARCHAR(255) DEFAULT NULL");
    addColumnIfNotExists($pdo, 'archery_cms_content', 'meta_description', "TEXT DEFAULT NULL");
    addColumnIfNotExists($pdo, 'archery_cms_content', 'meta_keywords', "TEXT DEFAULT NULL");
    addColumnIfNotExists($pdo, 'archery_cms_content', 'og_image_url', "VARCHAR(255) DEFAULT NULL");
    addColumnIfNotExists($pdo, 'archery_cms_content', 'google_site_verification', "VARCHAR(150) DEFAULT NULL");
    addColumnIfNotExists($pdo, 'archery_cms_content', 'seo_keywords_preset', "VARCHAR(255) DEFAULT 'les panahan anak, klub panahan sunnah, kursus memanah'");
    echo "✔ Kolom SEO Engine di tabel `archery_cms_content` siap.\n";

    // 3k. Tabel archery_saas_marketing_cms (Web Penjualan SaaS Master Admin)
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS `archery_saas_marketing_cms` (
        `id` INT PRIMARY KEY,
        `hero_badge` VARCHAR(150) NOT NULL DEFAULT 'PLATFORM DIGITALISASI OLAHRAGA SUNNAH & PRESTASI',
        `hero_title` VARCHAR(255) NOT NULL DEFAULT 'Aplikasi Manajemen & Scoring Panahan Multi-Club #1 di Indonesia',
        `hero_subtitle` TEXT DEFAULT NULL,
        `cta_text` VARCHAR(100) DEFAULT 'Coba Demo Gratis 14 Hari',
        `features_json` TEXT DEFAULT NULL,
        `pricing_tiers_json` TEXT DEFAULT NULL,
        `showcase_clubs_json` TEXT DEFAULT NULL,
        `faq_json` TEXT DEFAULT NULL,
        `meta_title` VARCHAR(255) DEFAULT 'Target Archery - Software Manajemen & Scoring Panahan Terlengkap di Indonesia',
        `meta_description` TEXT DEFAULT NULL,
        `meta_keywords` VARCHAR(255) DEFAULT 'software panahan, aplikasi scoring panahan, sistem klub panahan, archery software indonesia',
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    addColumnIfNotExists($pdo, 'archery_saas_marketing_cms', 'platform_brand_name', "VARCHAR(150) DEFAULT 'TARGET ARCHERY SAAS'");
    addColumnIfNotExists($pdo, 'archery_saas_marketing_cms', 'platform_logo_url', "VARCHAR(255) DEFAULT 'assets/img/default_logo.png'");
    addColumnIfNotExists($pdo, 'archery_saas_marketing_cms', 'platform_favicon_url', "VARCHAR(255) DEFAULT 'assets/img/default_favicon.png'");
    echo "✔ Tabel `archery_saas_marketing_cms` siap.\n";

    // 4. Tabel archery_distances
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS `archery_distances` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `club_id` INT DEFAULT 1,
        `distance_meter` INT NOT NULL,
        `distance_label` VARCHAR(100) NOT NULL,
        `min_sessions_required` INT NOT NULL DEFAULT 12,
        `min_months_required` INT NOT NULL DEFAULT 2,
        `min_passing_score` INT NOT NULL DEFAULT 250,
        `description` VARCHAR(255) DEFAULT NULL,
        `is_active` TINYINT(1) DEFAULT 1,
        UNIQUE KEY `club_distance_unique` (`club_id`, `distance_meter`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "✔ Tabel `archery_distances` siap.\n";

    // 5. Tabel archery_archers
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS `archery_archers` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `club_id` INT DEFAULT 1,
        `parent_user_id` INT DEFAULT NULL,
        `nis` VARCHAR(50) NOT NULL UNIQUE,
        `full_name` VARCHAR(150) NOT NULL,
        `nickname` VARCHAR(100) DEFAULT NULL,
        `gender` ENUM('L', 'P') DEFAULT 'L',
        `birth_date` DATE DEFAULT NULL,
        `parent_phone` VARCHAR(30) NOT NULL,
        `access_pin` VARCHAR(6) NOT NULL DEFAULT '123456',
        `bow_category` ENUM('Standard Bow', 'Barebow', 'Recurve', 'Compound', 'Traditional') DEFAULT 'Standard Bow',
        `draw_weight_lbs` DECIMAL(4,1) DEFAULT 18.0,
        `photo_url` VARCHAR(255) DEFAULT NULL,
        `current_level` VARCHAR(50) DEFAULT 'Pemula (5m)',
        `personal_best_score` INT DEFAULT 0,
        `is_active` TINYINT(1) DEFAULT 1,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    addColumnIfNotExists($pdo, 'archery_archers', 'assigned_coach_id', 'INT DEFAULT 2');
    addColumnIfNotExists($pdo, 'archery_archers', 'current_distance_meter', 'INT NOT NULL DEFAULT 5');
    addColumnIfNotExists($pdo, 'archery_archers', 'can_exam_promotion', 'TINYINT(1) DEFAULT 0');
    addColumnIfNotExists($pdo, 'archery_archers', 'coach_exam_notes', 'TEXT DEFAULT NULL');
    addColumnIfNotExists($pdo, 'archery_archers', 'total_sessions_count', 'INT DEFAULT 0');
    echo "✔ Tabel `archery_archers` siap dengan kolom modern.\n";

    // 6. Tabel archery_targets
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS `archery_targets` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `target_name` VARCHAR(100) NOT NULL,
        `code` VARCHAR(50) NOT NULL UNIQUE,
        `diameter_cm` INT NOT NULL DEFAULT 80,
        `rings_count` INT NOT NULL DEFAULT 10,
        `has_inner_ten` TINYINT(1) DEFAULT 1,
        `description` VARCHAR(255) DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "✔ Tabel `archery_targets` siap.\n";

    // 7. Tabel archery_sessions
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS `archery_sessions` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `club_id` INT DEFAULT 1,
        `archer_id` INT NOT NULL,
        `coach_id` INT DEFAULT NULL,
        `session_date` DATE NOT NULL,
        `start_time` TIME DEFAULT NULL,
        `end_time` TIME DEFAULT NULL,
        `distance_meter` INT NOT NULL DEFAULT 5,
        `bow_category` VARCHAR(50) NOT NULL DEFAULT 'Standard Bow',
        `target_id` INT NOT NULL DEFAULT 4,
        `total_ends` INT NOT NULL DEFAULT 6,
        `arrows_per_end` INT NOT NULL DEFAULT 5,
        `total_score` INT DEFAULT 0,
        `max_possible_score` INT DEFAULT 300,
        `ten_count` INT DEFAULT 0,
        `x_count` INT DEFAULT 0,
        `accuracy_pct` DECIMAL(5,2) DEFAULT 0.00,
        `avg_arrow_score` DECIMAL(4,2) DEFAULT 0.00,
        `coach_notes_technique` TEXT DEFAULT NULL,
        `coach_notes_mental` TEXT DEFAULT NULL,
        `coach_recommendation` TEXT DEFAULT NULL,
        `status` ENUM('in_progress', 'completed') DEFAULT 'in_progress',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    addColumnIfNotExists($pdo, 'archery_sessions', 'session_type', "ENUM('practice', 'exam') NOT NULL DEFAULT 'practice'");
    addColumnIfNotExists($pdo, 'archery_sessions', 'exam_status', "ENUM('not_exam', 'pending_approval', 'approved', 'rejected') NOT NULL DEFAULT 'not_exam'");
    addColumnIfNotExists($pdo, 'archery_sessions', 'exam_reviewed_by', 'INT DEFAULT NULL');
    addColumnIfNotExists($pdo, 'archery_sessions', 'exam_reviewed_at', 'DATETIME DEFAULT NULL');
    echo "✔ Tabel `archery_sessions` siap dengan kolom modern.\n";

    // 8. Tabel archery_ends
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS `archery_ends` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `session_id` INT NOT NULL,
        `end_number` INT NOT NULL,
        `end_score` INT DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `session_end_unique` (`session_id`, `end_number`),
        FOREIGN KEY (`session_id`) REFERENCES `archery_sessions`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // 9. Tabel archery_shots
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS `archery_shots` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `end_id` INT NOT NULL,
        `session_id` INT NOT NULL,
        `arrow_number` INT NOT NULL,
        `x_coord` DECIMAL(6,4) NOT NULL,
        `y_coord` DECIMAL(6,4) NOT NULL,
        `radius` DECIMAL(6,4) NOT NULL,
        `score` INT NOT NULL,
        `is_x` TINYINT(1) DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`end_id`) REFERENCES `archery_ends`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // 10. Tabel archery_promotion_logs
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS `archery_promotion_logs` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `club_id` INT DEFAULT 1,
        `archer_id` INT NOT NULL,
        `from_distance_meter` INT NOT NULL,
        `to_distance_meter` INT NOT NULL,
        `exam_session_id` INT DEFAULT NULL,
        `exam_score` INT NOT NULL,
        `approved_by_user_id` INT NOT NULL,
        `coach_id` INT DEFAULT NULL,
        `notes` TEXT DEFAULT NULL,
        `approved_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // 11. Tabel archery_bills
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS `archery_bills` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `club_id` INT DEFAULT 1,
        `archer_id` INT NOT NULL,
        `bill_title` VARCHAR(150) NOT NULL,
        `bill_type` ENUM('spp_bulanan', 'sesi_latihan', 'sewa_alat', 'event_turnamen') DEFAULT 'spp_bulanan',
        `amount` DECIMAL(12,2) NOT NULL,
        `period_month` VARCHAR(7) DEFAULT NULL,
        `due_date` DATE DEFAULT NULL,
        `status` ENUM('unpaid', 'pending', 'paid', 'cancelled') DEFAULT 'unpaid',
        `payment_channel` VARCHAR(50) DEFAULT NULL,
        `payment_ref` VARCHAR(100) DEFAULT NULL,
        `paid_at` DATETIME DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // SEED DATA DASAR
    $defaultPayTemplate = "Halo Bapak/Ibu {nama_ortu},\n\nAlhamdulillah pembayaran iuran panahan ananda *{nama_atlet}* sebesar *Rp {nominal}* untuk periode *{bulan}* telah BERHASIL diverifikasi (Ref: {ref}).\n\nTerima kasih atas dukungannya untuk prestasi ananda di *{nama_klub}* 🎯";
    $defaultPromoTemplate = "🎉 *SELAMAT! KELULUSAN KENAIKAN JARAK* 🎯\n\nKpd Yth Orang Tua ananda *{nama_atlet}*,\n\nDengan bangga kami sampaikan bahwa ananda telah dinyatakan *LULUS* uji kompetensi dan resmi *NAIK JARAK TEMBAK* dari *{jarak_lama} Meter* ke *{jarak_baru} Meter* dengan perolehan skor *{skor_ujian}/300*.\n\nPenguji: Coach {nama_coach}\nDisahkan oleh: {nama_ketua}\n\nTerus semangat berlatih! 🏹";

    $stmtClub = $pdo->prepare("
    INSERT INTO `archery_clubs` 
    (`id`, `club_name`, `club_code`, `subdomain_slug`, `custom_domain`, `city`, `address`, `phone`, `admin_wa_phone`, `chairman_name`, `primary_color`, `theme_mode`, `fonnte_token`, `fonnte_enabled`, `wa_payment_template`, `wa_promotion_template`, `pricing_scheme`, `monthly_flat_fee`, `fee_per_archer`, `cutoff_day`, `subscription_status`, `subscription_expires_at`)
    VALUES 
    (1, 'AN-NAHL ARCHERY CLUB', 'ANNAHL-ARC', 'annahl', NULL, 'Bogor', 'Jl. Ciangsana Raya No. 1, Gunung Putri, Bogor', '081299887766', '081299887766', 'Ustadz Asy\'ari, S.Pd.', '#0284c7', 'dark', '', 1, ?, ?, 'flat_monthly', 150000.00, 10000.00, 16, 'active', '2026-12-31'),
    (2, 'FOCUS ARCHERY CLUB', 'FOCUS-ARC', 'focus', NULL, 'Bandung', 'Jl. Sukajadi No. 45, Bandung', '081377889900', '081377889900', 'Coach Hendra, S.Or.', '#10b981', 'dark', '', 0, ?, ?, 'per_archer', 150000.00, 10000.00, 16, 'active', '2026-11-15')
    ON DUPLICATE KEY UPDATE `club_name`=VALUES(`club_name`), `subdomain_slug`=VALUES(`subdomain_slug`), `primary_color`=VALUES(`primary_color`), `admin_wa_phone`=VALUES(`admin_wa_phone`), `pricing_scheme`=VALUES(`pricing_scheme`);
    ");
    $stmtClub->execute([$defaultPayTemplate, $defaultPromoTemplate, $defaultPayTemplate, $defaultPromoTemplate]);
    echo "✔ Data Tenant Klub (An-Nahl & Focus Archery) terisi.\n";

    // Seed Web CMS Content
    $stmtCms = $pdo->prepare("
    INSERT INTO `archery_cms_content` 
    (`club_id`, `hero_headline`, `hero_subheadline`, `hero_cta_text`, `about_title`, `about_content`, `about_points`, `philosophy_title`, `philosophy_content`, `instagram_url`, `facebook_url`, `youtube_url`)
    VALUES 
    (1, 
     'Pusat Pembinaan Karakter & Prestasi Panahan Berstandar World Archery',
     'Menghidupkan olahraga sunnah dengan pendekatan modern, pelatih berlisensi, analisis scoring digital presisi, dan fasilitas target range 5m - 50m lengkap.',
     'Daftar Santri / Atlet Baru',
     'Membangun Generasi Tangguh Berakhlak Mulia',
     'An-Nahl Archery Club didirikan atas kecintaan pada sunnah Nabi ﷺ sekaligus komitmen melahirkan bibit-bibit pemanah handal yang bermental juara. Kami menggabungkan disiplin ilmu kepelatihan modern dengan nilai-nilai adab Islami.',
     'Pelatih Bersertifikat Nasional & Berpengalaman,Fasilitas Range Indoor & Outdoor Standar WA,Kurikulum Berjenjang dengan Uji Kenaikan Jarak,Sistem Plotting & Scoring Digital Real-Time,Peralatan Busur dan Target Disediakan Klub',
     '3 Pilar Panahan: Ketenangan, Fokus, dan Konsistensi',
     'Dalam setiap tarikan string dan bidikan, kami mengajarkan bahwa anak panah yang melesat adalah manifestasi ketenangan jiwa. Fokus pada sasaran, tenangkan tarikan nafas, dan pasrahkan hasil kepada Allah setelah ikhtiar terbaik.',
     'https://instagram.com/annahl_archery',
     'https://facebook.com/annahlarchery',
     'https://youtube.com/@annahlarchery'
    ),
    (2,
     'Focus Archery: Precision, Discipline, and Championship Spirit',
     'Klub panahan berorientasi prestasi dan kebugaran keluarga di Bandung dengan bimbingan pelatih profesional.',
     'Gabung Latihan Sekarang',
     'Tentang Focus Archery Bandung',
     'Focus Archery menyediakan lingkungan latihan yang suportif bagi siapa saja yang ingin belajar panahan dari nol maupun mendalami divisi Barebow dan Recurve.',
     'Bimbingan Teknik Fundamental yang Aman,Uji Kenaikan Jarak Berkala,Scoring Digital Akurat,Komunitas Ramah Pemula',
     'Fokus Penuh pada Proses & Form Gerak',
     'Skor tinggi adalah akibat alami dari form tembakan yang benar dan pikiran yang fokus.',
     'https://instagram.com/focusarchery',
     NULL,
     NULL
    )
    ON DUPLICATE KEY UPDATE `hero_headline`=VALUES(`hero_headline`), `about_title`=VALUES(`about_title`);
    ");
    $stmtCms->execute();
    echo "✔ Konten Profil Web CMS terisi.\n";

    // Seed Program Kursus CMS
    $pdo->exec("
    INSERT INTO `archery_cms_programs` 
    (`club_id`, `program_name`, `target_audience`, `bow_type`, `fee_info`, `session_frequency`, `description`, `equipment_included`, `order_num`)
    VALUES 
    (1, 'Kelas Junior Foundation (Pemula)', 'Usia 6 - 12 Tahun', 'Standard Bow / Horsebow', 'Rp 250.000 / Bulan', '1x Sepekan (Sabtu/Ahad)', 'Pengenalan keselamatan memanah (safety rules), pembentukan form dasar, anchor point, dan konsistensi tembakan jarak 5m.', 1, 1),
    (1, 'Kelas Prestasi Junior (Intermediate)', 'Usia 10 - 17 Tahun', 'Standard Bow & Barebow', 'Rp 350.000 / Bulan', '2x Sepekan (Rabu & Sabtu)', 'Pengasahan konsistensi rilis, analisis grouping tembakan digital, persiapan seleksi POPDA & Kejurda, jarak 10m - 18m.', 1, 2),
    (1, 'Kelas Sunnah Dewasa & Eksekutif', 'Dewasa / Mahasiswa / Ortu', 'Barebow & Recurve', 'Rp 300.000 / Bulan', '1x Sepekan (Sabtu/Ahad Pagi)', 'Olahraga sunnah untuk kebugaran fisik, kestabilan mental, serta relaksasi stres akhir pekan bersama komunitas positif.', 1, 3),
    (2, 'Focus Beginner Class', 'Anak & Remaja', 'Standard Bow', 'Rp 200.000 / Bulan', '4x Pertemuan', 'Dasar memanah yang menyenangkan dan terarah.', 1, 1)
    ON DUPLICATE KEY UPDATE `program_name`=VALUES(`program_name`);
    ");
    echo "✔ Program Kursus Web CMS terisi.\n";

    // Seed Jadwal Latihan CMS
    $pdo->exec("
    INSERT INTO `archery_cms_schedules`
    (`club_id`, `day_name`, `time_range`, `session_type`, `range_location`, `coach_in_charge`, `order_num`)
    VALUES
    (1, 'Sabtu Pagi', '08:00 - 10:00 WIB', 'Kelas Pemula & Junior (5m - 7m)', 'Lapangan Target Utama (Outdoor Range)', 'Coach Fadli & Coach Asisten', 1),
    (1, 'Sabtu Sore', '15:30 - 17:30 WIB', 'Kelas Prestasi & Ujian Scoring Jarak', 'Lapangan Presisi Line A (10m - 18m)', 'Coach Fadli Archery', 2),
    (1, 'Ahad Pagi', '08:00 - 10:30 WIB', 'Kelas Dewasa, Eksekutif & Sunnah Archery', 'Lapangan Target Line B', 'Coach Fadli & Ustadz Asy\'ari', 3),
    (2, 'Ahad Pagi', '08:30 - 10:30 WIB', 'Regular Focus Session', 'Bandung Archery Range', 'Coach Hendra', 1)
    ON DUPLICATE KEY UPDATE `session_type`=VALUES(`session_type`);
    ");
    echo "✔ Jadwal Latihan Web CMS terisi.\n";

    // Seed Galeri Foto & Prestasi CMS
    $pdo->exec("
    INSERT INTO `archery_cms_galleries`
    (`club_id`, `title`, `image_url`, `category`, `order_num`)
    VALUES
    (1, 'Fasilitas Lapangan Target Range Luas & Aman', 'assets/img/gallery_range.jpg', 'fasilitas', 1),
    (1, 'Bimbingan Teknik Tarikan & Anchor Konsisten oleh Coach', 'assets/img/gallery_coach.jpg', 'latihan', 2),
    (1, 'Evaluasi Scoring Digital & Ujian Kenaikan Jarak', 'assets/img/gallery_scoring.jpg', 'latihan', 3),
    (1, 'Juara 1 Divisi Standard Bow Kejuaraan Pelajar 2026', 'assets/img/gallery_champion.jpg', 'prestasi', 4)
    ON DUPLICATE KEY UPDATE `title`=VALUES(`title`);
    ");
    echo "✔ Galeri Web CMS terisi.\n";

    // Master Distances Fleksibel Super Admin
    $distances = [
        [1, 5, 'Jarak 5 Meter (Dasar Pemula)', 8, 1, 240, 'Fokus form, grip, dan anchor point dasar'],
        [1, 7, 'Jarak 7 Meter (Pemula Madya)', 12, 2, 250, 'Konsistensi rilis dan kestabilan bow arm'],
        [1, 10, 'Jarak 10 Meter (Standar Junior)', 16, 3, 260, 'Stabilitas repetisi tarikan dan fokus ritme tembakan'],
        [1, 15, 'Jarak 15 Meter (Pra-Lanjutan)', 20, 4, 265, 'Persiapan kompetisi divisi standard bow junior'],
        [1, 18, 'Jarak 18 Meter (Standar World Archery Indoor)', 24, 6, 275, 'Jarak resmi kompetisi indoor World Archery'],
        [1, 30, 'Jarak 30 Meter (Outdoor Intermediate)', 30, 8, 280, 'Kompensasi windage outdoor dan setelan pisir'],
        [1, 50, 'Jarak 50 Meter (Outdoor Mahir / Compound)', 36, 12, 285, 'Jarak resmi outdoor divisi compound & barebow dewasa'],
        [2, 5, 'Jarak 5 Meter Pemula Focus', 8, 1, 240, 'Dasar form panahan'],
        [2, 10, 'Jarak 10 Meter Junior Focus', 14, 2, 255, 'Konsistensi bidikan']
    ];
    $stmtDist = $pdo->prepare("INSERT INTO `archery_distances` (`club_id`, `distance_meter`, `distance_label`, `min_sessions_required`, `min_months_required`, `min_passing_score`, `description`)
        VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE `distance_label`=VALUES(`distance_label`), `min_passing_score`=VALUES(`min_passing_score`);");
    foreach ($distances as $d) {
        $stmtDist->execute($d);
    }
    echo "✔ Master distances fleksibel terisi.\n";

    // Master Targets
    $targets = [
        ['WA 10-Ring 122cm', 'WA_122', 122, 10, 1, 'Target Standar Outdoor Jarak Jauh (50m, 70m)'],
        ['WA 10-Ring 80cm', 'WA_80', 80, 10, 1, 'Target Standar Jarak 30m / 50m Compound'],
        ['WA 10-Ring 60cm', 'WA_60', 60, 10, 1, 'Target Standar Jarak 20m - 25m'],
        ['WA 10-Ring 40cm', 'WA_40', 40, 10, 1, 'Target Standar Indoor 18m'],
        ['3-Spot Vegas Face', 'VEGAS_3SPOT', 40, 5, 1, 'Target Segitiga 3-Spot Scoring 6-10']
    ];
    $stmtTarget = $pdo->prepare("INSERT INTO `archery_targets` (`target_name`, `code`, `diameter_cm`, `rings_count`, `has_inner_ten`, `description`) 
        VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE `target_name`=VALUES(`target_name`);");
    foreach ($targets as $t) {
        $stmtTarget->execute($t);
    }
    echo "✔ Master targets terisi.\n";

    // Seed Pengaturan Master Admin SaaS
    $defaultWaReminderTpl = "🔔 *PENGINGAT PERPANJANGAN SEWA SAAS TARGET ARCHERY*\n\nYth. Admin Klub *{nama_klub}*,\n\nKami informasikan masa aktif langganan aplikasi Target Archery untuk klub Anda akan berakhir pada tanggal *{jatuh_tempo}* ({sisa_hari} hari lagi).\n\n📊 *Rincian Tagihan Periode {bulan}*:\n- Skema: {skema_tarif}\n- Total Atlet Terdaftar: {total_atlet} anak\n- Atlet Kena Tagihan: {atlet_bayar} anak\n- Atlet Bebas Biaya (Masuk setelah tgl 16): {atlet_gratis} anak\n- *Total Tagihan: Rp {total_tagihan}*\n\nSilakan lakukan pembayaran ke rekening Master Admin ({info_rekening}) agar layanan operasional klub tidak terputus.\n\nTerima kasih atas kemitraannya! 🎯";

    $stmtSaas = $pdo->prepare("
        INSERT INTO `archery_saas_settings` 
        (`id`, `platform_name`, `master_admin_wa`, `fonnte_token`, `bank_account_info`, `default_flat_fee`, `default_per_archer_fee`, `default_cutoff_day`, `wa_reminder_template`)
        VALUES (1, 'TARGET ARCHERY SAAS PLATFORM', '081299887766', '', 'BCA 1234567890 a/n Master Admin Platform Panahan', 150000.00, 10000.00, 16, ?)
        ON DUPLICATE KEY UPDATE `platform_name`=VALUES(`platform_name`), `bank_account_info`=VALUES(`bank_account_info`);
    ");
    $stmtSaas->execute([$defaultWaReminderTpl]);
    echo "✔ Pengaturan Master Admin SaaS terisi.\n";

    // User Master Admin, Super Admin, Coach, Parent
    $passHash = password_hash('admin123', PASSWORD_BCRYPT);
    $users = [
        [1, 'masteradmin', $passHash, 'Master Admin (Platform Owner)', 'master_admin', '081299887766', 'master@archery.local'],
        [1, 'superadmin', $passHash, 'Super Admin (An-Nahl Archery)', 'super_admin', '081299990001', 'superadmin@annahl.local'],
        [1, 'coach_fadli', $passHash, 'Coach Fadli Archery', 'coach', '081299990002', 'coach@archery.local'],
        [1, 'ortu_budi', $passHash, 'Bapak Budi Santoso', 'parent', '081288889999', 'budi@gmail.com'],
        [2, 'admin_focus', $passHash, 'Admin Focus Archery', 'super_admin', '081377889900', 'admin@focusarchery.local']
    ];
    $stmtUser = $pdo->prepare("INSERT INTO `archery_users` (`club_id`, `username`, `password_hash`, `full_name`, `role`, `phone`, `email`)
        VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE `full_name`=VALUES(`full_name`), `role`=VALUES(`role`), `club_id`=VALUES(`club_id`);");
    foreach ($users as $u) {
        $stmtUser->execute($u);
    }
    echo "✔ User masteradmin, superadmin (An-Nahl), coach, parent, tenant admin terisi.\n";

    // Atlet (Ahmad di 7m & Fatimah di 5m)
    $archers = [
        [1, 4, 3, 'ARC-001', 'Muhammad Ahmad Santoso', 'Ahmad', 'L', '2012-05-14', '081288889999', '111111', 7, 1, 'Rekomendasi coach: Anchor sangat konsisten, diizinkan ujian naik jarak ke 10m.', 'Barebow', 20.0, 'Pemula Lanjutan (7m)', 285, 14, '2026-09-01'],
        [1, 4, 3, 'ARC-002', 'Fatimah Az-Zahra Santoso', 'Fatimah', 'P', '2014-08-20', '081288889999', '222222', 5, 0, NULL, 'Standard Bow', 16.0, 'Pemula (5m)', 260, 5, '2026-10-05'],
        [1, 4, 3, 'ARC-003', 'Zaidan Al-Farisi (Atlet Baru Masuk Tgl 20)', 'Zaidan', 'L', '2013-02-10', '081288889999', '333333', 5, 0, NULL, 'Standard Bow', 14.0, 'Pemula (5m)', 180, 1, '2026-10-20']
    ];
    $stmtArch = $pdo->prepare("INSERT INTO `archery_archers` 
        (`club_id`, `parent_user_id`, `assigned_coach_id`, `nis`, `full_name`, `nickname`, `gender`, `birth_date`, `parent_phone`, `access_pin`, `current_distance_meter`, `can_exam_promotion`, `coach_exam_notes`, `bow_category`, `draw_weight_lbs`, `current_level`, `personal_best_score`, `total_sessions_count`, `created_at`)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE `full_name`=VALUES(`full_name`), `current_distance_meter`=VALUES(`current_distance_meter`), `can_exam_promotion`=VALUES(`can_exam_promotion`), `total_sessions_count`=VALUES(`total_sessions_count`), `created_at`=VALUES(`created_at`);");
    foreach ($archers as $a) {
        $stmtArch->execute($a);
    }
    echo "✔ Atlet contoh terisi (termasuk atlet tgl 20 untuk uji coba aturan cut-off tgl 16).\n";

    // Seed Invoice Tagihan SaaS
    $stmtInv = $pdo->prepare("
        INSERT INTO `archery_saas_invoices`
        (`invoice_no`, `club_id`, `period_month`, `pricing_scheme`, `rate_per_unit`, `total_archers_count`, `billable_archers_count`, `prorated_free_archers_count`, `total_amount`, `due_date`, `status`, `notes`)
        VALUES
        ('INV-SAAS-2026-001', 1, '2026-10', 'flat_monthly', 150000.00, 3, 2, 1, 150000.00, '2026-10-25', 'paid', 'Langganan Flat Bulanan An-Nahl Archery Club'),
        ('INV-SAAS-2026-002', 2, '2026-10', 'per_archer', 10000.00, 2, 2, 0, 20000.00, '2026-10-28', 'unpaid', 'Tagihan Berbasis 2 Atlet Aktif Focus Archery')
        ON DUPLICATE KEY UPDATE `total_amount`=VALUES(`total_amount`);
    ");
    $stmtInv->execute();
    echo "✔ Contoh Invoice SaaS Master Admin terisi.\n";

    // Seed Default RBAC Permissions (Super Admin, Coach, Parent, Archer) untuk Club 1 & 2
    $defaultMenus = [
        'scoring' => 'Portal Scoring',
        'web_profil' => 'Lihat Web Publik',
        'rapot' => 'Rapot Sesi A4',
        'rekap' => 'Rekap Bulanan',
        'medsos' => 'Medsos Share Card',
        'cms_admin' => 'Kelola Web CMS',
        'settings' => 'Pengaturan Identitas Klub',
        'distances' => 'Master Jarak',
        'approvals' => 'ACC Naik Jarak',
        'coach_dashboard' => 'Dashboard Pelatih',
        'bills' => 'Iuran & Pembayaran',
        'rbac' => 'Hak Akses Menu'
    ];

    $roleDefaults = [
        'super_admin' => ['scoring', 'web_profil', 'rapot', 'rekap', 'medsos', 'cms_admin', 'settings', 'distances', 'approvals', 'coach_dashboard', 'bills', 'rbac'],
        'coach'       => ['scoring', 'web_profil', 'rapot', 'rekap', 'medsos', 'coach_dashboard'],
        'parent'      => ['scoring', 'web_profil', 'rapot', 'rekap', 'medsos', 'bills'],
        'archer'      => ['scoring', 'web_profil']
    ];

    $stmtRbac = $pdo->prepare("
        INSERT INTO `archery_rbac_permissions` (`club_id`, `role`, `menu_key`, `is_visible`)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE `is_visible`=VALUES(`is_visible`);
    ");

    foreach ([1, 2] as $cId) {
        foreach (['super_admin', 'coach', 'parent', 'archer'] as $r) {
            $allowedList = $roleDefaults[$r] ?? [];
            foreach ($defaultMenus as $mKey => $mLabel) {
                $isVisible = in_array($mKey, $allowedList) ? 1 : 0;
                $stmtRbac->execute([$cId, $r, $mKey, $isVisible]);
            }
        }
    }
    echo "✔ Default RBAC Permissions Header terisi untuk seluruh role.\n";

    // Seed Konten Web Penjualan SaaS Master Admin
    $features = json_encode([
        ['title' => 'Target Plotter & Loupe Zoom 2.4x', 'desc' => 'Scoring presisi standar World Archery dengan crosshair milimeter dan grouping analytics.'],
        ['title' => 'Triple-Shield Auto-Save', 'desc' => 'Anti hilang skor saat sinyal drop atau baterai habis dengan auto-save AJAX & LocalStorage.'],
        ['title' => 'Ujian Naik Jarak & Jam Terbang', 'desc' => 'Kurikulum berjenjang 5m - 50m dengan kunci ujian otomatis dan fast-track izin coach.'],
        ['title' => 'Rapor Sesi & Rekap Bulanan A4', 'desc' => 'Cetak dokumen resmi tanda tangan pelatih dan ketua klub siap PDF.'],
        ['title' => 'Web Profil CMS Mandiri Tiap Klub', 'desc' => 'Setiap klub memiliki website profil sendiri dengan program, jadwal, dan galeri.'],
        ['title' => 'WhatsApp Bot Fonnte Gateway', 'desc' => 'Notifikasi otomatis bukti pembayaran dan kelulusan naik jarak langsung ke ortu.']
    ], JSON_UNESCAPED_UNICODE);

    $pricing = json_encode([
        ['name' => 'Paket Flat Klub', 'price' => 'Rp 150.000 / Bulan', 'desc' => 'Unlimited atlet, seluruh fitur lengkap, web CMS mandiri, WA Gateway bot.'],
        ['name' => 'Paket Per-Atlet (Fleksibel)', 'price' => 'Rp 10.000 / Anak / Bulan', 'desc' => 'Bayar hanya untuk atlet aktif, bebas biaya untuk atlet masuk setelah tgl 16.']
    ], JSON_UNESCAPED_UNICODE);

    $showcase = json_encode([
        ['name' => 'AN-NAHL ARCHERY CLUB', 'city' => 'Bogor', 'slug' => 'annahl', 'desc' => 'Pusat Pembinaan Panahan Sunnah & Prestasi'],
        ['name' => 'FOCUS ARCHERY CLUB', 'city' => 'Bandung', 'slug' => 'focus', 'desc' => 'Klub Panahan Kompetisi & Rekreasi Keluarga']
    ], JSON_UNESCAPED_UNICODE);

    $faq = json_encode([
        ['q' => 'Apakah klub panahan kami bisa memakai nama domain sendiri?', 'a' => 'Sangat bisa! Anda bisa memakai subdomain platform (misal: namaklub.archery.codika.id) atau domain pribadi Anda (misal: namaklub.com).'],
        ['q' => 'Bagaimana jika saat mencatat tembakan di lapangan HP mati?', 'a' => 'Sistem dilengkapi Triple-Shield Auto-Save. Skor tersimpan di memori HP dan otomatis pulih saat HP dinyalakan kembali.'],
        ['q' => 'Bagaimana cara mendaftarkan klub baru ke platform?', 'a' => 'Cukup isi formulir registrasi demo di bawah ini atau hubungi Master Admin via WhatsApp untuk aktivasi tenant instan.']
    ], JSON_UNESCAPED_UNICODE);

    $stmtSaasMkt = $pdo->prepare("
        INSERT INTO `archery_saas_marketing_cms`
        (`id`, `hero_badge`, `hero_title`, `hero_subtitle`, `cta_text`, `features_json`, `pricing_tiers_json`, `showcase_clubs_json`, `faq_json`, `meta_title`, `meta_description`, `meta_keywords`)
        VALUES
        (1,
         'PLATFORM DIGITALISASI OLAHRAGA SUNNAH & PRESTASI PANAHAN #1',
         'Solusi Software All-in-One Manajemen Klub & Scoring Panahan Modern',
         'Tingkatkan profesionalisme perkumpulan panahan Anda dengan sistem scoring standar World Archery, rapor resmi otomatis, website profil mandiri, dan notifikasi WhatsApp.',
         'Daftarkan Klub Panahan Anda (Free Demo)',
         ?, ?, ?, ?,
         'Target Archery Platform - Software Manajemen & Scoring Panahan Terlengkap di Indonesia',
         'Platform digitalisasi klub panahan terbaik di Indonesia. Dilengkapi target plotter presisi, rapor A4, ujian naik jarak, web CMS mandiri, dan integrasi WhatsApp.',
         'software panahan, aplikasi scoring archery, sistem klub panahan indonesia, manajemen panahan sunnah'
        )
        ON DUPLICATE KEY UPDATE `hero_title`=VALUES(`hero_title`), `features_json`=VALUES(`features_json`);
    ");
    $stmtSaasMkt->execute([$features, $pricing, $showcase, $faq]);
    echo "✔ Konten Web Penjualan SaaS Master Admin terisi.\n";

    echo "\n🎉 SEED DATABASE ARCHERY SAAS MULTI-TENANT SELESAI SEMPURNA!\n";

} catch (PDOException $e) {
    echo "❌ ERROR DATABASE: " . $e->getMessage() . "\n";
    exit(1);
}
