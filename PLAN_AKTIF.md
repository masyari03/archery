# 🎯 RENCANA KERJA KUMULATIF LENGKAP: SISTEM TARGET ARCHERY (EDISI SAAS MULTI-TENANT & AUTO-RECOVERY)
*(Platform Bisnis Multi-Tenant Ala Codika + Anti-Hilang AJAX & LocalStorage + Otomatis Jarak & Lock Ujian + Izin/Tunda Coach + Template WA & Toggle ON/OFF)*

---

## 📌 DAFTAR FITUR LENGKAP TERPADU

### 1. 🏢 Arsitektur Bisnis SaaS Multi-Tenant (Ala Codika Al-Qur'an / Codika CMS)
- **Role Master Admin (Platform Owner / Anda)**:
  - Anda memegang kendali penuh di tingkat teratas (*Super Master Admin*).
  - Bisa membuat dan mendaftarkan tenant klub-klub panahan baru (misal: An-Nahl Archery, Focus Archery, Garuda Archery, dll.).
  - Setiap klub memiliki:
    - Admin Klub & Coach sendiri.
    - Logo, Alamat, dan Warna Tema sendiri.
    - Data atlet, sesi, rapot, dan murid yang terisolasi 100% via `club_id`.
  - Monetisasi SaaS: Master Admin dapat mengenakan biaya paket langganan software per klub (Bulanan / Tahunan).

### 2. 🛡️ Sistem Anti-Hilang Skor di Lapangan: "Triple Shield Auto-Save"
- **Tantangan Lapangan**: 1 seri memakan waktu 3–5 menit tembakan, risiko sinyal drop, baterai habis, atau layar mati.
- **Solusi Triple Shield**:
  1. **Layer 1: Auto-Save AJAX Instant per Shot**: Seketika 1 panah diplot di layar, sistem langsung menembakkan AJAX asinkron ke server di background (<50ms).
  2. **Layer 2: LocalStorage Offline Shield**: Koordinat dan skor panah langsung dicadangkan instan ke memori HP (*browser localStorage*). Jika HP mati atau browser tertutup tiba-tiba, saat dibuka kembali data seri tersebut **100% pulih otomatis**!
  3. **Layer 3: End Commit Confirmation**: Saat seluruh panah dalam seri selesai (misal 5 panah), status hijau *"Seri Tersimpan & Sinkron"* muncul sebelum beralih ke seri berikutnya.

### 3. 🎯 Otomatisasi Input Sesi & Lock Ujian Kenaikan Jarak
- **Jarak Terinput Otomatis**:
  - Saat atlet/ortu klik *"Input Tembakan Baru"*, **jarak tembak otomatis terisi sesuai jarak aktif atlet saat ini** (misal jika atlet masih di tingkat 5m, langsung otomatis terkunci di 5m).
- **Pengguna Tinggal Memilih**:
  1. Coach Pendamping di lapangan.
  2. Tipe Latihan: *Latihan Rutin* vs *Ujian Kenaikan Jarak*.
- **Smart Lock Ujian Kenaikan Jarak**:
  - Jika atlet belum memenuhi syarat jam terbang latihan DAN belum mendapat izin rekomendasi dari coach, opsi *"Ujian Kenaikan Jarak"* **terkunci otomatis (disabled)** dengan teks ramah: *"Belum memenuhi syarat jam terbang / belum ada izin coach"*.

### 4. ✋ Wewenang Coach: Izin, Menunda, atau Membatalkan Ujian Naik Jarak
- Coach memiliki kendali lapangan penuh di `coach_dashboard.php`:
  - **Memberi Izin**: Jika atlet dinilai sudah siap ambil nilai scoring kenaikan jarak.
  - **Menunda / Membatalkan Sementara**: Jika pada sesi latihan terbaru form atlet dinilai belum stabil atau perlu pematangan teknik lebih lanjut, Coach berhak menunda izin ujian tersebut demi kebaikan atlet.

### 5. 🤖 WA Bot Fonnte: Template Kustom & Sakelar ON/OFF
- Di menu Super Admin (`settings.php`):
  - **Sakelar Toggle [ ON / OFF ]**: Fitur notifikasi WhatsApp bisa dimatikan sepenuhnya jika klub tidak ingin menggunakannya.
  - **Custom Message Template**: Admin bisa bebas mengedit kata-kata dan variabel pesan:
    - Template Notifikasi Pembayaran Lunas (`{nama_atlet}`, `{bulan}`, `{nominal}`, `{ref}`).
    - Template Notifikasi Kelulusan Naik Jarak (`{nama_atlet}`, `{jarak_lama}`, `{jarak_baru}`, `{nama_coach}`).

### 6. 🏹 Dual Jalur Kenaikan Jarak: Jam Terbang Reguler & Jalur Rekomendasi Coach (Fast-Track)
- **Jalur Reguler**: Terbuka otomatis saat target kuota sesi tercapai (cocok bagi anak yang latihan rutin 3x seminggu).
- **Jalur Rekomendasi Coach**: Coach dapat membuka izin ujian kapan saja jika atlet berbakat dan siap.
- Visual Progress Bar Jam Terbang di dashboard atlet: `[ 🎯 9 / 12 Sesi Terpenuhi ]`.

### 7. ⏱️ Logika Evaluasi Coach: Baru Muncul Setelah Selesai 6 Seri
- Seri 1 s/d 5: Layar fokus 100% pada target bidik, kaca pembesar (*loupe zoom*), dan live scorecard. Form evaluasi pelatih **tidak muncul**.
- Tepat setelah Seri 6 selesai: Form evaluasi resmi coach terbuka (Form/Teknik, Mental, PR di Rumah) untuk diisi oleh coach pendamping yang dipilih.

### 8. 🧹 Perapihan Navigasi & UI Mobile-First (Header Bersih)
- Hapus menu *"Papan Bidik"* di navbar header (akses hanya via tombol *"Input Tembakan Baru"*).
- Hapus tombol *"Rekap Bulanan"* di samping tombol input tembakan (cukup di navbar atas).
- Mobile-First Top App Bar ramping (tinggi 50px) + Modern Bottom Navigation Bar di bawah layar ponsel: `[📊 Dashboard] [📜 Rapot] [📱 Medsos] [💳 Iuran]`.

### 9. 🔒 Hak Akses Rapot Sesi: Khusus Ortu, Coach, dan Admin
- Mode Perangkat Anak (PIN Lapangan): Tombol rapot sesi disembunyikan.
- Mode Akun Orang Tua & Coach/Admin: Tombol rapot sesi ditampilkan lengkap dengan kop surat dan kolom tanda tangan resmi.

### 10. 👨‍🏫 Scoping Dashboard Coach (`coach_dashboard.php`)
- Coach yang login hanya melihat anak-anak yang memilih beliau sebagai pendamping sesi atau atlet binaan beliau, serta tombol mengizinkan/menunda ujian naik jarak.

### 11. 🎨 Sistem Tema Terang (Light Mode) & Aksen Biru Terang
- Warna aksen utama: **Biru Terang / Sky Electric Blue** (`#0284c7` / `#0ea5e9` / `#38bdf8`).
- Tombol toggle tema **☀️ Terang / 🌙 Gelap** di Top App Bar tersimpan otomatis di `localStorage`.

### 12. 🚨 Modal Pop-up Error Login Keren & Direct WhatsApp Admin
- Pop-up modal glassmorphic dengan logo klub saat login salah + tombol langsung chat WA ke nomor admin klub.

### 13. ⚙️ Menu Pengaturan Lengkap Super Admin (`settings.php`)
- Ganti Nama Klub, Alamat, Logo Klub, Nama Ketua Klub, No WA Bantuan Admin, dan Custom Color Picker.

### 14. 🎯 Core Plotting Engine & Visual Target (Adopsi MyTargets)
- Sistem Koordinat Kartesius $[-1.0, 1.0]$ berpusat di $(0,0)$.
- Magnifier Loupe Zoom 2.4x melayang di atas jari pemanah dengan crosshair presisi milimeter.
- Kalkulasi Skor Otomatis ($X, 10, 9, \dots, M$) dan Grouping Analytics (*Windage & Elevation*).

### 15. 👨‍👩‍👧‍👦 Solusi 2 Anak & 2 Device (Family Hierarchy)
- Akun Utama Orang Tua dengan **Child Switcher** di navbar.
- Mode PIN Cepat Lapangan untuk HP masing-masing anak secara paralel tanpa bentrok sesi.

### 16. 🖨️ Cetak Rapot Sesi & Rekap Bulanan Resmi (A4 / PDF Ready)
- Rapot Sesi resmi dengan TTD Coach & Ketua Klub.
- Rekap Bulanan volume panah, grafik tren kenaikan skor, dan evaluasi bulanan.

### 17. 📱 Template Social Media Shareable Card (Ala Roti Bakar)
- Generator kartu medsos Story (9:16) dan Feed (1:1) dengan logo klub dan sebaran titik panah asli.

### 18. 💳 Payment Gateway & Billing
- Manajemen tagihan SPP bulanan, sewa alat, dan sesi turnamen dengan QRIS & Virtual Account.

### 19. 🛡️ Standar Keamanan OWASP 20 Poin & Zero HTTP 500
- Prepared statements PDO 100%, Password Bcrypt, HttpOnly Session, Atomic DB Transaction.

### 20. ☁️ Laptopserver & Cloudflare Tunnel
- Auto-sync via `deploy_target_archery.py` ke port 3008 dan domain `archery.codika.my.id`.

### 21. 🎨 Perbaikan UI Desktop & Anti-Leak Mobile Nav
- Penegakan CSS `@media (min-width: 769px) { .bottom-nav, .no-desktop { display: none !important; } }` di `style.css` dan inline `navbar.php`.
- Cache-buster parameter `?v=<?= time() ?>` pada link stylesheet seluruh halaman.

### 22. 👑 Arsitektur SaaS Master Admin Tenant (`master_tenants.php`)
- Role khusus `master_admin` untuk Platform Owner (Anda).
- Manajemen seluruh klub panahan mitra (Tenant): tambah klub baru, setting kuota atlet, ubah status aktif/suspend.
- Fitur 1-Click "Masuk ke Klub" (Impersonasi Tenant) tanpa perlu tahu password admin klub mitra, dilengkapi banner pengingat mode master admin di atas layar.

### 23. 💰 Sistem Penagihan SaaS & Fair Billing Prorata Tgl 16 (`master_billing.php`)
- Pilihan skema pembayaran sewa: Flat Bulanan vs Dihitung Per Anak (tetap klub yang membayar ke Master Admin).
- **Aturan Prorata Tanggal 16 (Fair Billing)**: Atlet baru yang terdaftar setelah tanggal 16 di bulan berjalan berstatus bebas biaya / belum ditagihkan pada bulan tersebut, dan baru akan mulai dihitung di periode bulan berikutnya.
- Fitur 1-Click Kirim Pengingat WhatsApp Tagihan Sewa SaaS via Fonnte langsung ke nomor admin klub.

### 24. 🌐 Domain & Subdomain Tenant Resolver (`config/tenant_resolver.php`)
- Sistem multi-tenant terpisah ala Codika Al-Qur'an.
- Mendukung pemetaan nama domain klub sendiri (`custom_domain`, contoh: `annahlarchery.com`) atau subdomain platform (`subdomain_slug`, contoh: `annahl.archery.codika.my.id`).
- Fallback cerdas dengan parameter testing `?club=slug` dan session context.

### 25. 🏹 Halaman Depan Web CMS Publik (`index.php`)
- Halaman awal publik adalah Web Profil Klub Panahan modern berstandar World Archery, BUKAN langsung form login atau scorecard kosong.
- Dilengkapi Hero Banner dinamis, Filosofi 3 Pilar Panahan Sunnah (Ketenangan, Fokus, Disiplin), Dalil Hadits, Program Kursus & Kategori Busur, Jadwal Lapangan Mingguan, Profil Coach Berlisensi, Galeri Fasilitas, dan Tombol Masuk Portal Member.

### 26. 📝 Panel Manajemen Web CMS Super Admin (`cms_admin.php`)
- Super Admin klub dapat mengelola seluruh konten halaman web publik:
  - Tab 1: Hero Banner & Profil Utama & Link Medsos.
  - Tab 2: Program Kursus Panahan (tambah, edit, hapus, fasilitas busur).
  - Tab 3: Jadwal Lapangan Mingguan (hari, jam, coach penanggung jawab, lokasi).
  - Tab 4: Galeri Dokumentasi Foto (fasilitas, latihan, turnamen, prestasi).
  - Tab 5: Data Pendaftaran Masuk & Verifikasi Atlet Baru (lengkap tombol direct WA ke orang tua).

### 27. 📋 Formulir Pendaftaran Atlet Online Terintegrasi WA (`register_archer.php`)
- Calon santri & orang tua dapat mendaftar secara mandiri dari website publik.
- Mengirimkan data pendaftaran ke `archery_cms_registrations`.
- Notifikasi instan via WhatsApp Bot Fonnte langsung ke nomor admin klub saat ada pendaftar baru.

### 28. 🌐 Standarisasi Total Domain Induk ke `codika.my.id` & Resolusi Multi-Tenant Bebas Tabrakan
- **Pemisahan Total dari `codika.id`**: Seluruh sistem Target Archery dipusatkan 100% pada domain `codika.my.id`, sehingga port 3000 pada `*.codika.id` (Quran & Sekolah) bebas dari risiko tabrakan (*zero collision*).
- **Struktur Domain Platform & Tenant**:
  - **Portal SaaS Utama (Master Admin)**: `https://archery.codika.my.id`
  - **Tenant Subdomain Otomatis**:
    - Format 1-Level (Universal SSL Gratis Cloudflare): `https://[slug]-archery.codika.my.id` (contoh: `https://annahl-archery.codika.my.id`).
    - Format 2-Level: `https://[slug].archery.codika.my.id` (contoh: `https://annahl.archery.codika.my.id` dengan CNAME `*.archery`).
    - Fallback Langsung: `https://archery.codika.my.id/?club=[slug]`.
    - Custom Domain Mandiri: `https://[custom_domain]` (jika klub punya domain sendiri).
- **Pembaruan Kode Terpadu**:
  - `config/tenant_resolver.php`: Standarisasi parent domain ke `codika.my.id`, pemetaan `getBaseUrl()`, dan Canonical SEO tags.
  - `sitemap.php` & `robots.txt`: Seluruh referensi XML dan crawler merujuk ke `archery.codika.my.id`.
  - Cloudflare Ingress laptopserver: Menjamin `archery.codika.my.id`, `*.archery.codika.my.id`, dan `*-archery.codika.my.id` mengarah presisi ke port 3008.
  - `README.md`: Update tautan portofolio GitHub resmi ke `codika.my.id`.

### 29. 🌓 Dua Tema Adaptif (Gelap 🌙 & Terang ☀️) Kontras Tinggi di Seluruh Halaman
- Penyesuaian otomatis warna huruf: saat terang menjadi gelap tajam (`#0f172a`), saat gelap menjadi putih bersih (`#f8fafc`).
- Sakelar toggle tema di seluruh header publik & internal dengan inisialisasi instan (*anti-flicker*).

### 30. 🎨 Standarisasi Total Layout & CSS Terpadu (`views/header.php` & `views/footer.php`)
- **Penyebab Tampilan Berantakan Sebelumnya**: 
  - Halaman `app_dashboard.php`, `cms_admin.php`, `master_cms.php`, dan `admin_rbac.php` memanggil `navbar.php` tanpa tag `<!DOCTYPE html>`, `<head>`, maupun `<link>` CSS apapun, sehingga browser merender halaman sebagai teks mentah tanpa gaya.
  - Halaman internal lainnya (`coach_dashboard.php`, `master_tenants.php`, `settings.php`, `bills.php`, dll.) hanya memuat `style.css` tanpa CDN Bootstrap 5.3.3 CSS & Bootstrap Icons, sehingga ikon panahan hilang dan tata letak grid/tombol bergeser.
  - `views/footer.php` belum menyertakan `bootstrap.bundle.min.js`, menyebabkan seluruh modal dialog (input tembakan, filter, dsb.) tidak bisa terbuka saat diklik.
  - Skrip tema ganda belum menyinkronkan atribut resmi `data-bs-theme="dark|light"` ke elemen `<html>`, mengakibatkan kartu modal Bootstrap berlatar putih kontras di tengah latar belakang gelap.
- **Rencana Eksekusi Perbaikan Menyeluruh**:
  1. **Buat Layout Header Terpadu `views/header.php`**:
     - Struktur HTML5 valid: `<!DOCTYPE html>`, `<html lang="id">`, `<head>`.
     - Injeksi Google Fonts (`Outfit` & `Plus Jakarta Sans`).
     - Injeksi CDN Bootstrap 5.3.3 CSS & CDN Bootstrap Icons 1.11.3.
     - Injeksi Target Archery Core Theme `assets/css/style.css?v=<?= time() ?>`.
     - Inisialisasi instan anti-flicker tema ganda (`data-theme` dan `data-bs-theme`).
     - Otomatis memuat `views/navbar.php`.
  2. **Defensive Guard di `views/navbar.php`**:
     - Tambahkan proteksi `if (!defined('ARCHERY_HEADER_LOADED'))`: jika ada file yang kelupaan memanggil `header.php`, `navbar.php` akan otomatis menginjeksi stylesheet darurat agar tidak ada satu pun halaman yang tampil telanjang tanpa CSS.
  3. **Penyempurnaan `views/footer.php`**:
     - Muat `bootstrap.bundle.min.js` dari CDN resmi.
     - Muat skrip global helper (modal, tooltips, theme synchronization).
  4. **Harmonisasi Komponen di `assets/css/style.css`**:
     - Integrasikan styling kartu Bootstrap `.card`, modal `.modal-content`, tabel `.table`, form `.form-control`, dan tab `.nav-tabs` agar menyatu dengan palet tema gelap & terang Target Archery.
  5. **Refactoring Bersih pada 16 Halaman Terkait**:
     - Ganti pembuka halaman di `app_dashboard.php`, `coach_dashboard.php`, `cms_admin.php`, `master_cms.php`, `admin_rbac.php`, `master_tenants.php`, `master_billing.php`, `settings.php`, `bills.php`, `admin_distances.php`, `admin_approvals.php`, `plotter.php`, `share_card.php`, `report_session.php`, `report_monthly.php`, dan `login.php` agar menggunakan arsitektur layout terpadu standar.

### 31. 🎯 Perapihan Header Nyaman & Modern + Penghilangan Otomatis Menu saat RBAC Nonaktif
- **Penghilangan Menu Total Berdasarkan RBAC**:
  - Jika hak akses menu diatur tidak aktif (`is_visible = 0`) atau role pengguna tidak memiliki izin, tulisan, ikon, dan tautan menu di header atas **HILANG TOTAL (Zero Residual Item)**, bukan sekadar dinonaktifkan atau digelapkan.
  - Jika seluruh sub-item dalam sebuah kelompok/dropdown (misal: semua submenu Laporan) tidak aktif, maka **judul tombol dropdown kelompok tersebut otomatis hilang seutuhnya**.
  - Pada `admin_rbac.php`, tambahkan hak kelola untuk role `super_admin` agar Super Admin klub dapat secara leluasa merapikan headernya sendiri dengan mematikan menu yang jarang dipakai.
- **Desain Header Modern, Lapang, & Sangat Nyaman (Anti-Menumpuk)**:
  - Mengganti 15 deretan link teks horizontal yang menumpuk & berdesakan dengan arsitektur navigasi cerdas:
    1. **Logo & Nama Klub**: Bersih, proporsional, dan elegan di kiri.
    2. **Child Switcher (Untuk Akun Ortu/Keluarga)**: Tampil rapi sebagai pill dropdown ramping dengan indikator target panah.
    3. **Menu Utama Cepat**: Tautan langsung ke `📊 Portal Scoring` & `🌐 Web Publik`.
    4. **Dropdown Ramping "📜 Laporan & Prestasi"**: Menampung `Rapot Sesi A4`, `Rekap Bulanan`, dan `Medsos Share Card` (hanya tampil jika minimal 1 submenu aktif).
    5. **Dropdown Ramping "⚙️ Manajemen Klub"** (Untuk Admin/Pelatih): Menampung `Web CMS`, `Hak Akses RBAC`, `Master Jarak`, `ACC Ujian`, `Pengaturan Klub`, `Dashboard Pelatih` (hanya tampil jika minimal 1 submenu aktif).
    6. **Dropdown Ramping "👑 SaaS Platform"** (Khusus Master Admin): Menampung `Master Tenant`, `Tagihan SaaS`, dan `Marketing SaaS`.
    7. **Profil & Kontrol di Kanan**:
       - Badge Role Pengguna berdesain pill premium.
       - Tombol Cepat Tema ☀️/🌙 (dengan tooltip dan animasi halus).
       - Tombol Keluar (Logout) 🚪 dengan warna merah lembut.
  - **Kenyamanan Spacing & Tipografi**:
    - Padding dan margin yang lapang, tinggi navbar konsisten 54px, zero horizontal text overflow di semua ukuran resolusi layar (laptop 1366px, monitor besar, tablet, maupun HP).

### 32. 💎 Penyempurnaan Arsitektur SaaS Eksekutif: Header Full-Width Laptop, Heading 2-Baris Hero, Showcase Tenant Dinamis Langsung ke Subdomain Klien, Pembersihan Demo Portal, & Penghapusan Kebocoran Dropdown di Web Tenant
- **Header Full-Width & Judul Hero 2-Baris Rapi di Laptop (`saas_marketing.php`)**:
  - Perlebar container navbar dan hero section agar proporsional dan membentang lapang hingga ujung layar laptop (`max-width: 1440px` / `container-fluid px-lg-5`).
  - Atur tipografi judul hero agar tampil gagah dan seimbang dalam **tepat 2 baris** (tanpa pecah jadi 3 atau 4 baris):
    - Baris 1: `Solusi Software All-in-One Manajemen Klub`
    - Baris 2: `& Scoring Panahan Modern Berstandar Dunia`
- **Showcase Tenant Dinamis Otomatis Bertambah & Tautan Langsung ke Subdomain Klien (Gambar 2)**:
  - Query dinamis `SELECT * FROM archery_clubs WHERE is_active = 1` pada section `#showcase` di `saas_marketing.php`.
  - Desain grid kartu tenant yang otomatis bertambah rapi (*auto-fill responsive grid*) setiap kali ada klien baru mendaftar di sistem SaaS tanpa perlu edit kode lagi.
  - Tampilkan badge domain Level 1 resmi: `https://[subdomain_slug].codika.my.id` (atau custom domain mandiri).
  - Tombol "Kunjungi Website Resmi Klub ➔" langsung membuka URL subdomain resmi milik klien di tab baru tanpa parameter percobaan.
- **Pembersihan Demo Portal Live (Gambar 3)**:
  - Hapus dropdown pemilih klub dari kartu "Live Simulation Portal" di `saas_marketing.php`.
  - Hadirkan kartu Live Demo interaktif yang bersih & meyakinkan dengan tombol CTA tunggal: **"🎯 Coba Demo Live Portal Panahan ➔"** yang langsung membuka portal interaktif.
  - Tampilkan info akun uji coba (Coach, Ortu, Santri) secara elegan di kartu tersebut.
- **Isolasi Penuh Web Tenant Publik (`index.php`) & Eliminasi Kebocoran SaaS (Gambar 4)**:
  - Hapus total dropdown "Pilih Klub:" dari top bar di `index.php`.
  - Hapus total tombol "👑 Master SaaS" dari halaman publik tenant klub.
  - Ganti top bar publik klub dengan info resmi klub:
    - Kiri: Lokasi Kota Klub & No. WhatsApp Resmi Klub.
    - Kanan: Jadwal Jam Operasional / Hari Latihan & Tautan Media Sosial Klub.
  - Di `config/tenant_resolver.php`, jadikan deteksi Host Subdomain Level 1 (`annahl.codika.my.id`, `focus.codika.my.id`) sebagai **Prioritas Tertinggi (Prioritas 1)** dan batasi parameter `?club=` hanya untuk domain platform root (`archery.codika.my.id` atau `localhost`). Dengan demikian, saat mengakses `annahl.codika.my.id`, sistem 100% terisolasi ke An-Nahl dan mustahil tertimpa klub lain.

### 33. 🏆 Pengaturan Navicon (Favicon), Logo & Branding Dinamis (Super Admin & Master Admin) + Ultra-Professional Sport-Tech Polish
- **Pengaturan Navicon (Favicon) & Logo di Super Admin (`settings.php`)**:
  - Kolom `favicon_url` & `logo_url` di tabel `archery_clubs`.
  - Super Admin dapat mengubah URL Logo Klub dan URL Navicon/Favicon klub di menu Pengaturan Klub (`settings.php`).
  - Browser tab di seluruh halaman web publik klub (`index.php`) dan portal member (`app_dashboard.php`, `login.php`, dll.) otomatis menampilkan favicon resmi milik klub tersebut.
- **Pengaturan Navicon (Favicon), Logo, & Brand di Master Admin (`master_cms.php`)**:
  - Kolom `platform_brand_name`, `platform_logo_url`, dan `platform_favicon_url` di tabel `archery_saas_marketing_cms`.
  - Master Admin dapat mengubah Logo Platform, Favicon Platform, dan Nama Brand SaaS langsung dari formulir CMS (`master_cms.php`).
  - Halaman web penjualan SaaS (`saas_marketing.php`) dan seluruh portal Master Admin (`master_tenants.php`, `master_billing.php`, `master_cms.php`) otomatis menggunakan favicon dan logo resmi platform.
- **Peningkatan Visual Web Penjualan SaaS & Web Publik Klien Menjadi Sangat Profesional (World-Class Sport-Tech)**:
  - Tampilan visual premium kelas dunia: kontras tajam, aksen emas cincin X World Archery & biru elektrik, bayangan halus (*subtle glassmorphism*), tipografi presisi, dan susunan hero yang memikat calon mitra klub panahan se-Indonesia.

---

## 🏆 STATUS EKSEKUSI & DEPLOYMENT
- **Poin 1 s/d 31**: 100% Selesai & Berfungsi Sempurna.
- **Poin 32 & 33**: Menunggu konfirmasi pengguna untuk eksekusi terpadu.


