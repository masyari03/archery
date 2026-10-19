# 🏹 Target Archery — Enterprise Multi-Tenant SaaS & Scoring Monitoring Platform

[![PHP Version](https://img.shields.io/badge/PHP-8.3%2B-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-8.0%2B-4479A1?style=for-the-badge&logo=mysql&logoColor=white)](https://www.mysql.com/)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-3.4-38B2AC?style=for-the-badge&logo=tailwind-css&logoColor=white)](https://tailwindcss.com/)
[![Multi-Tenant SaaS](https://img.shields.io/badge/Architecture-Multi--Tenant_SaaS-0284c7?style=for-the-badge)](https://codika.id)
[![Cloudflare Tunnel](https://img.shields.io/badge/Tunnel-Cloudflare_Zero_Trust-F38020?style=for-the-badge&logo=cloudflare&logoColor=white)](https://cloudflare.com)
[![Security Standard](https://img.shields.io/badge/Security-20_Mandatory_Standards-emerald?style=for-the-badge)](https://github.com/masyari03/archery)

> **Platform SaaS Enterprise Multi-Tenant Terpadu untuk Manajemen Klub Panahan, Penjualan Lisensi Berlangganan, Plotter Target Interaktif Real-Time, Monitoring Atlet, Rapor Bulanan Standar Perpani/World Archery, serta Mesin SEO Mandiri.**

---

## 🌐 Live Production Links

| Layanan | URL Produksi | Deskripsi |
| :--- | :--- | :--- |
| **SaaS Platform Landing Page** | `https://archery.codika.my.id` | Web pemasaran & portal pendaftaran klub panahan se-Indonesia |
| **Tenant Club Showcase** | `https://annahl.archery.codika.my.id` | Website resmi & portal atlet Klub Panahan An-Nahl Archery |
| **Dynamic XML Sitemap** | `https://archery.codika.my.id/sitemap.php` | Sitemap dinamis multi-tenant untuk Google Search Console |
| **Robots Directive** | `https://archery.codika.my.id/robots.txt` | Aturan perayapan crawler & proteksi rute privat |

---

## 🚀 Fitur Unggulan (Key Features)

### 1. 🏢 Isolated Multi-Tenant Architecture & Domain Engine
- **Namespace Non-Bentrok**: Menggunakan domain isolasi `*.archery.codika.my.id` dan `*.archery.codika.id` yang diposisikan di atas wildcard `*.codika.id` pada reverse proxy / Cloudflare Tunnel.
- **Auto-Switch Domain Resolver**: Mendukung **Custom Domain Mandiri** (misal: `annahlarchery.com`) dan **Subdomain Otomatis** (`annahl.archery.codika.my.id`). Jika klub mengisi custom domain pada CMS, seluruh tautan, aset, dan Canonical SEO URL otomatis berganti ke domain tersebut.
- **Strict Multi-Tenant Scoping**: Setiap query database membaca data dengan klausa `WHERE club_id = :club_id` untuk menjamin isolasi data 100% antar klub.

### 2. 🎯 Interactive Target Plotter (SVG Real-Time Scoring)
- **Loupe Crosshair Magnifier**: Pembidik kaca pembesar dengan perbesaran optik presisi milimeter untuk memplot perkenaan anak panah pada bantalan target 10-ring FITA/WA.
- **Triple-Shield Auto-Save**: Proteksi kehilangan data skor melalui penyimpanan sinkron ke server, antrean *localStorage* offline, dan mekanisme *retry* otomatis saat koneksi terputus.
- **6-End Evaluation Lock**: Sesi penilaian 6 rambahan terkunci otomatis setelah selesai untuk mencegah manipulasi data sebelum disahkan oleh Pelatih.
- **Arrow Scatter Analysis**: Analisis sebaran tembakan (*grouping analysis*) untuk membantu pelatih mengoreksi teknik tarikan atau setelan alat (*sight/plunger*).

### 3. 🛡️ Dynamic RBAC Menu Manager
- **Matrix Pengaturan Hak Akses**: Super Admin dapat mengaktifkan atau menonaktifkan visibilitas menu navigasi secara dinamis untuk setiap role (**Super Admin**, **Pelatih/Coach**, **Orang Tua/Parent**, dan **Atlet/Archer**).
- **Responsive Sync**: Mengontrol tautan navbar desktop dan *mobile bottom navigation* secara instan dari panel `admin_rbac.php`.

### 4. 📈 Mesin SEO Google & Live SERP Preview
- **Live Google SERP Preview**: Pratinjau tampilan judul dan deskripsi pencarian Google di layar desktop dan mobile secara *real-time* saat diedit.
- **Schema.org Structured Data**:
  - `SportsClub` JSON-LD di setiap web tenant untuk memunculkan rich snippet informasi klub, alamat, telepon, dan jam latihan di Google Maps & Penelusuran.
  - `SoftwareApplication` JSON-LD di halaman utama SaaS untuk mendongkrak peringkat platform panahan nomor #1 di Indonesia.
- **Google Site Verification**: Input kode verifikasi Google Search Console langsung dari form CMS tanpa perlu menyentuh file HTML server.

### 5. 💳 Billing Berlangganan & Aturan Cut-off Tanggal 16
- **Skema Ganda**: Mendukung pembayaran *Flat Bulanan* atau *Per Anak Aktif*.
- **Aturan Cut-off Tanggal 16**: Atlet baru yang bergabung pada atau setelah tanggal 16 bebas tagihan untuk bulan berjalan dan baru dihitung pada siklus bulan berikutnya.
- **Pengingat WhatsApp Otomatis**: Integrasi Fonnte Gateway untuk mengirim pengingat invoice 7 hari sebelum jatuh tempo dan notifikasi pelunasan otomatis.

### 6. 📊 Rapor Digital Atlet & Cetak Format A4
- **Lembar Skor Sesi**: Rangkuman perkenaan panah per end, skor total, hitungan X, 10, dan 9 siap cetak PDF/A4.
- **Rapor Evaluasi Bulanan**: Grafik radar evaluasi fisik, teknik (*stance*, *grip*, *release*), konsistensi, serta catatan perkembangan atlet langsung ke portal orang tua.

---

## 🛠️ Tech Stack & Architecture

- **Backend Language**: PHP 8.3+ (Native OOP, PDO Strict Mode, Prepared Statements)
- **Database**: MySQL 8.0+ (InnoDB Engine, Foreign Key Constraints, utf8mb4)
- **Frontend & UI**: Tailwind CSS 3.4, Vanilla JavaScript ES6+, FontAwesome 6, Google Inter Fonts
- **Messaging Gateway**: Fonnte WhatsApp Official API
- **Web Server / Ingress**: PHP Built-in Server / Nginx, Systemd Daemon, Cloudflare Tunnel (Zero Trust SSL)

---

## 🔒 Standar Keamanan & Zero HTTP 500 Protocol

Sistem dibangun dengan mematuhi protokol keamanan tinggi:
1. **Zero Hardcoded Secrets**: Seluruh kredensial sensitif dimuat melalui variabel lingkungan (`getenv()`).
2. **Anti SQL Injection**: 100% kueri database menggunakan parameterized queries (`:param`). Dilarang konkatenasi string SQL.
3. **Anti XSS**: Seluruh output dinamis yang dirender ke browser dibersihkan menggunakan `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.
4. **Zero HTTP 500 Check**:
   - Blok `try-catch` terpasang di setiap operasi database dan transmisi HTTP.
   - Pengecekan eksplisit nilai null (`fetch() ?: []`) untuk mencegah `TypeError` fatal.
   - Penyetelan batas *timeout* pada panggilan cURL eksternal (maksimal 5 detik).

---

## 📂 Struktur Direktori Proyek

```
target_archery/
├── api/                     # Endpoint API (Scoring, Save End, Plotter)
├── assets/                  # File statis (CSS, JS, Gambar, SVG Targets)
├── config/                  # Konfigurasi Sistem
│   ├── database.php         # Koneksi PDO Database Aman
│   ├── rbac.php             # Helper Role-Based Access Control
│   └── tenant_resolver.php  # Auto-Switch Subdomain & Custom Domain Resolver
├── views/                   # Komponen View Reusable
│   ├── footer.php           # Global Footer
│   ├── mobile_nav.php       # Bottom Bar Navigasi Mobile
│   └── navbar.php           # Header Navigasi Dinamis Terfilter RBAC
├── admin_approvals.php      # Verifikasi Pendaftaran Atlet Baru
├── admin_distances.php      # Pengaturan Jarak & Bantalan Target
├── admin_rbac.php           # Visual Matrix RBAC Menu Manager
├── app_dashboard.php        # Dashboard Utama Atlet & Orang Tua
├── bills.php                # Rincian Tagihan Iuran Atlet
├── cms_admin.php            # CMS Pengelola Website Tenant & Mesin SEO
├── coach_dashboard.php      # Dashboard & Evaluasi Pelatih
├── deploy_target_archery.py # Otomatisasi Deploy Linux & Cloudflare Ingress
├── index.php                # Landing Page Dinamis Tenant / Auto-Redirect Root
├── login.php                # Portal Autentikasi Pengguna
├── master_billing.php       # Master Admin: Tagihan SaaS & Pengingat WA
├── master_cms.php           # Master Admin: Editor Konten Web SaaS
├── master_tenants.php       # Master Admin: Manajemen Klub & Custom Domain
├── plotter.php              # Bidikan Target Panahan Interaktif SVG
├── register_archer.php      # Formulir Pendaftaran Atlet Publik
├── report_monthly.php       # Rapor Kemajuan Bulanan Format A4
├── report_session.php       # Lembar Skor Sesi Latihan Format A4
├── robots.txt               # Pengarah Crawler Search Engine
├── saas_marketing.php       # Website Penjualan SaaS Master Admin
├── settings.php             # Pengaturan Profil Klub & Gateway WA
├── setup_db.php             # Skrip Migrasi & Database Seeder Terpadu
└── sitemap.php              # Dynamic XML Sitemap Generator
```

---

## 💻 Panduan Instalasi Lokal (Laragon / XAMPP)

1. **Clone Repositori**:
   ```bash
   git clone https://github.com/masyari03/archery.git
   cd archery
   ```

2. **Setup Database**:
   - Pastikan MySQL/MariaDB aktif.
   - Jalankan migrasi database otomatis:
     ```bash
     php setup_db.php
     ```

3. **Jalankan Aplikasi**:
   ```bash
   php -S 127.0.0.1:3008
   ```

4. **Akses Sistem**:
   - **Web Penjualan SaaS Platform**: `http://127.0.0.1:3008/saas_marketing.php`
   - **Web Tenant An-Nahl**: `http://127.0.0.1:3008/index.php?club=annahl`
   - **Login Akun**:
     - **Master Admin SaaS**: `masteradmin` / `password123`
     - **Super Admin Klub**: `admin_annahl` / `password123`
     - **Pelatih (Coach)**: `coach_annahl` / `password123`
     - **Orang Tua / Atlet**: `parent_annahl` / `password123`

---

## 👤 Author & Portofolio

**Muhammad Asy'ari Arif Yusuf**  
Founder & Lead Full-Stack Engineer — [Codika Technology](https://codika.id)  
- Website: [https://codika.id](https://codika.id)  
- Portfolio: [https://portfolio.codika.id](https://portfolio.codika.id)  
- GitHub: [@masyari03](https://github.com/masyari03)  
- Email: [arifyusuf3030@gmail.com](mailto:arifyusuf3030@gmail.com)

---

## 📜 Lisensi

Hak Cipta © 2026 **Muhammad Asy'ari Arif Yusuf / Codika**. Seluruh hak cipta dilindungi undang-undang.
Dilisensikan untuk penggunaan portofolio dan manajemen klub panahan di seluruh Indonesia.
