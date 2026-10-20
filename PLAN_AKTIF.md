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

### 34. 📐 Perapihan Layout Lebar Merata & Responsif ke Samping pada Seluruh Tenant (`index.php`, `saas_marketing.php`) + Auto Scroll-Margin Top
- **Masalah pada Gambar 1**:
  - Halaman `index.php` pada tenant (`annahl.codika.my.id/#tentang`) saat diklik menu navigasi terpotong kepalanya oleh header sticky (`scroll-margin-top` belum ada).
  - Konten di layar laptop terkungkung dalam container sempit dan tidak merata ke samping, menyisakan ruang kosong abu-abu berlebih di kiri-kanan.
- **Rencana Perbaikan**:
  1. Pasang aturan global `html { scroll-behavior: smooth; } section[id] { scroll-margin-top: 90px; }` sehingga semua anchor (`#tentang`, `#filosofi`, `#program`, `#jadwal`, `#pelatih`, `#galeri`, `#daftar`, `#kontak`) mendarat presisi di bawah navbar tanpa menutupi judul.
  2. Perluas seluruh container section di `index.php` menggunakan `container-fluid px-3 px-lg-5` dengan batas elegan `max-width: 1560px` agar merata, simetris, dan gagah di laptop maupun monitor besar.
  3. Tata ulang grid kolom `#tentang`, `#filosofi`, `#program`, dan `#jadwal` agar kartu komitmen, tabel jadwal, dan galeri sejajar sempurna dan tidak terjepit.

### 35. 🔐 Pemisahan Ketat & Perbaikan Role `super_admin` vs `master_admin` + Multi-Tenant Scoping Login
- **Penyebab Masalah Pengguna**:
  - Di `setup_db.php`, user `superadmin` sebelumnya keliru diberi role `'master_admin'` (`[1, 'superadmin', $passHash, 'Master Super Admin', 'master_admin', ...]`).
  - Akibatnya saat login sebagai superadmin An-Nahl, pengguna mendapatkan wewenang Master Admin platform dan melihat data SaaS/klub lain.
  - Di `login.php`, data klub hardcoded `WHERE id = 1` alih-alih mengambil klub dari subdomain host aktif (`TenantResolver::resolve($pdo)`).
- **Rencana Perbaikan**:
  1. Ubah role user `superadmin` (dan admin klub lainnya) secara permanen di database dan `setup_db.php` menjadi murni **`super_admin`** dengan `club_id = 1` (An-Nahl).
  2. Pisahkan akun khusus platform owner: **`masteradmin`** (role `master_admin`), hanya akun ini yang berhak mengakses menu SaaS, Master Tenants, Billing Antar-Klub, dan Marketing CMS.
  3. Perbarui `login.php` agar dinamis mendeteksi tenant aktif via subdomain host (`TenantResolver::resolve($pdo)`) dan memvalidasi bahwa `super_admin`, `coach`, `parent`, dan `archer` hanya dapat mengakses klub tempat mereka terdaftar (mencegah akses lintas klub).

### 36. 📋 Matriks & Rincian Hak Akses Lengkap 5 Role (Buku Panduan Hak Akses Sistem)
- Dokumentasikan dan tegakkan secara ketat hierarki 5 role:
  1. **👑 Master Admin (Platform Owner / Codika)**:
     - Akses: `master_tenants.php`, `master_billing.php`, `master_cms.php`, impersonasi tenant klub klien, kontrol lisensi & kuota klub.
     - Fungsi: Pemilik platform bisnis SaaS, menerima bayaran sewa dari pengurus klub.
  2. **🛡️ Super Admin (Ketua / Pengurus Klub Klien, misal An-Nahl Archery)**:
     - Akses: `cms_admin.php`, `settings.php` (logo, favicon, warna, WA Fonnte), `admin_distances.php` (master jarak), `admin_approvals.php` (pengesahan rapot & naik jarak), `admin_rbac.php` (atur menu internal), `bills.php` (tagihan SPP atlet).
     - Fungsi: Manajer operasional klub panahan, tidak bisa melihat klub lain atau tagihan sewa SaaS platform.
  3. **🎯 Coach (Pelatih Berlisensi Klub)**:
     - Akses: `coach_dashboard.php` (pantau murid binaan), `scoring.php` (input skor target plotter milimeter), `report_session.php` & `report_monthly.php` (evaluasi rapot resmi), ACC/rekomendasi ujian naik jarak atlet.
     - Fungsi: Pembina teknis atlet di lapangan.
  4. **👨‍👩‍👧‍👦 Parent (Orang Tua Santri / Atlet)**:
     - Akses: `app_dashboard.php` (grafik skor anak, multi-child switcher), `report_session.php` & `report_monthly.php` (lihat & cetak rapot A4 resmi), `bills.php` (cek status SPP & upload bukti/konfirmasi WA), `share_card.php` (kartu prestasi medsos).
     - Fungsi: Wali murid yang memantau perkembangan dan membayar iuran.
  5. **🏹 Archer (Atlet / Santri Mandiri)**:
     - Akses: Login instan via PIN 6-digit di perangkat lapangan (`login.php?type=pin`), `app_dashboard.php`, `scoring.php` (catat skor latihan mandiri).
     - Fungsi: Praktisi tembakan panahan.

### 37. 💎 Pembersihan Total "Badge/Teks Demo Cacat" & Elevasi Komersial Siap Jual (Production Commercial Grade)
- Hapus seluruh kata-kata bernada uji coba amatir/demo murahan seperti *"Coba demo gratis"*, *"akun percobaan"*, *"simulasi klub"*, dll.
- Ganti dengan terminologi komersial profesional: *"Mulai Digitalisasi Klub Panahan Anda"*, *"Konsultasi Kemitraan Resmi"*, *"Akses Portal Pengguna"*.
- Pastikan seluruh halaman web publik dan portal klien siap dipresentasikan dan dijual ke calon klien klub panahan di seluruh Indonesia dengan rasa percaya diri tinggi.

### 38. 🎯 Sinkronisasi Anti-Terpotong Anchor Header & Layout Presisi di Web Utama SaaS (`saas_marketing.php`) Tanpa Mengubah Isi Konten
- Telah selesai 100% dan aktif live di server.

### 39. 🔐 Isolasi Ketat Login Antar-Tenant (Blokir 100% Login di Klub/Sekolah Lain)
- **Penyebab Masalah Pengguna**:
  - Pengguna mencoba login dengan akun An-Nahl di subdomain Focus (`focus.codika.my.id/login.php`), dan masih bisa login.
  - Hal ini terjadi karena:
    1. Di `login.php`, logika boundary sebelumnya melewatkan pengecekan jika host teridentifikasi platform root atau jika session cookie antar-subdomain (`*.codika.my.id`) membagikan session PHP yang sudah aktif.
    2. Pada halaman terproteksi (`app_dashboard.php`, `coach_dashboard.php`, `settings.php`), tidak ada middleware yang memvalidasi kecocokan `$_SESSION['club_id']` dengan tenant dari domain yang sedang dibuka.
- **Rencana Perbaikan Presisi**:
  1. Pada `login.php`:
     - Validasi mutlak: Jika user yang berhasil terotentikasi memiliki `role !== 'master_admin'` dan `club_id !== $currentTenant['id']`, login DITOLAK SEKETIKA dengan notifikasi error jelas: *"Akun Anda terdaftar di [Nama Klub Asli]. Anda tidak memiliki izin login di portal [Nama Klub Saat Ini]. Silakan gunakan portal resmi klub Anda."*
     - Hapus fallback session `club_id` lama yang berpotensi membingungkan tenant resolver saat berganti subdomain.
  2. Pada middleware `Auth::requireAuth()` & `views/navbar.php`:
     - Setiap kali pengguna mengakses halaman aplikasi, sistem membandingkan `(int)$_SESSION['club_id']` dengan tenant domain aktif. Jika tidak cocok (dan bukan `master_admin`), sesi langsung dibatalkan (force logout) dan dialihkan ke domain klub aslinya.

### 40. 🚪 Pintu Gerbang Rahasia Khusus Master Admin (`master_gate_auth.php`) & Eliminasi Tombol "Masuk Akun" di Web Utama
- **Permintaan Pengguna**:
  - Tombol "Masuk Akun" di navbar web utama (`saas_marketing.php`) dihilangkan total.
  - Dibuatkan pintu gerbang login khusus yang agak rumit/terproteksi khusus untuk Master Admin Platform.
  - Akun An-Nahl (atau tenant biasa lainnya) DILARANG KERAS bisa login di pintu Master Admin ini.
  - Di dalam Master Admin, HANYA berfokus pada:
    1. Pembuatan & pengelolaan tenant klub (`master_tenants.php`).
    2. Pengaturan CMS pemasaran SaaS (`master_cms.php`).
    3. Pembayaran & invoice sewa SaaS (`master_billing.php`).
    4. Log aktivitas seluruh tenant dengan filter (`master_activity_logs.php`).
    5. Manajemen sesi pengguna aktif, deteksi IP address, dan fitur **Putus Sesi (Kill Session / Revoke)** (`master_user_sessions.php`).
- **Rencana Perbaikan Presisi**:
  1. Hapus tombol *"Masuk Akun"* di header navigasi `saas_marketing.php`.
  2. Buat pintu gerbang login rahasia: `master_gate_auth.php` (dengan URL aman berparameter / secret token check atau standalone secret route).
  3. Di `master_gate_auth.php`:
     - Hanya menerima akun dengan `role === 'master_admin'`.
     - Jika ada akun An-Nahl (`superadmin`, `coach`, `parent`, `archer`) mencoba login di sini, sistem menolak 100%: *"Akses Ditolak: Halaman ini khusus Master Admin Platform Codika."*

### 41. 📜 Sistem Log Aktivitas Audit Trail Terpadu & Pemutus Sesi Pengguna (Kill Session / Revoke)
- **Tabel Database Baru**:
  1. `archery_activity_logs`:
     - `id`, `club_id`, `user_id`, `action_type` (LOGIN, LOGOUT, SCORING_INPUT, CMS_UPDATE, RBAC_UPDATE, DISTANCE_ACC, dll), `action_description`, `ip_address`, `user_agent`, `created_at`.
  2. `archery_user_sessions`:
     - `id`, `session_id`, `user_id`, `club_id`, `ip_address`, `user_agent`, `login_at`, `last_activity_at`, `is_revoked`.
- **Fitur di Master Admin (`master_activity_logs.php` & `master_user_sessions.php`)**:
  - Menampilkan audit trail log aktivitas seluruh tenant dengan filter: filter per Tenant Klub, filter per Role, filter per Tanggal, dan pencarian kata kunci.
  - Menampilkan daftar pengguna yang sedang aktif online beserta IP Address, lokasi/browser, dan tombol **"Putus Sesi (Kill Session)"**.
  - Jika sesi diputus oleh Master Admin, `is_revoked = 1`. Saat pengguna me-refresh halaman, `Auth::check()` mendeteksi sesi dicabut dan langsung me-logout paksa pengguna tersebut.
- **Fitur di Super Admin Setiap Tenant (`tenant_activity_logs.php`)**:
  - Menu baru di navbar Super Admin: *"⚙️ Manajemen" -> "📜 Log Aktivitas Klub"*.
  - Menampilkan log aktivitas yang terjadi di klubnya sendiri (`WHERE club_id = :cid`), meliputi: siapa yang login, siapa pelatih yang menginput skor atlet, siapa yang mengubah jadwal/CMS, dan status kenaikan jarak atlet beserta IP address.

### 42. ⚡ Optimasi Kecepatan Seluruh Web Tenant Platform (An-Nahl, Focus Archery, & Seluruh Klien Tenant Mendatang) — Zero Infinite Loading
- **Instruksi Pengguna**:
  - Optimasi dilakukan menyeluruh untuk SEMUA website tenant (`annahl.codika.my.id`, `focus.codika.my.id`, dan semua subdomain klien baru), bukan hanya satu klub saja.
- **Akar Masalah dari Investigasi**:
  1. Direktori aset gambar `assets/img/` dan `uploads/` belum ada di filesystem lokal maupun laptopserver.
  2. Data galeri & media di database (`assets/img/gallery_range.jpg`, dll.) gagal dimuat (404), yang memicu penanganan `onerror` memanggil URL eksternal gambar resolusi tinggi dari Unsplash (`https://images.unsplash.com/...`).
  3. CDN Unsplash seringkali mengalami throttling/kecepatan lambat dari provider internet di Indonesia, sehingga browser terus-menerus menahan status *loading spinner* tanpa henti di seluruh tenant.
- **Rencana Perbaikan Presisi Menyeluruh**:
  1. Buat folder `assets/img/` dan `uploads/` (beserta subfolder `logos`, `favicons`, `galleries`, `avatars`) secara permanen dengan perizinan penuh (0777).
  2. Sediakan aset gambar lokal ringan (*featherweight responsive assets*) berformat WebP/SVG atau CSS visual cards untuk SEMUA tenant (An-Nahl, Focus, dan fallback tenant baru) sehingga tidak ada lagi permintaan jaringan yang menggantung.
  3. Tambahkan atribut `loading="lazy"` dan `decoding="async"` pada seluruh elemen `<img>` di `index.php` (yang merupakan core engine landing page seluruh tenant).
  4. Hapus total seluruh URL eksternal Unsplash di `index.php`, `setup_db.php`, dan `saas_marketing.php`. Ganti dengan fallback lokal instan yang termuat dalam hitungan milidetik.
  5. Menjamin waktu buka halaman web seluruh tenant super cepat (< 300ms total load time) tanpa ada aset eksternal yang menghambat rendering browser.

### 43. 🖼️ Perbaikan Menyeluruh Bug Upload Gambar (Logo, Navicon / Favicon, & Galeri)
- **Akar Masalah dari Investigasi**:
  1. Pada form `settings.php` dan `master_cms.php`, tag `<form method="POST">` **tidak memiliki atribut `enctype="multipart/form-data"`**.
  2. Akibatnya browser mengirim data form sebagai string teks biasa, sehingga variabel PHP `$_FILES['club_logo']` dan `$_FILES['club_favicon']` selalu kosong (tidak pernah terkirim ke server).
  3. Pada `cms_admin.php`, input galeri sebelumnya hanya berupa link teks URL dan belum mendukung unggah file gambar langsung dari komputer/HP.
- **Rencana Perbaikan Presisi**:
  1. Pasang atribut `enctype="multipart/form-data"` pada seluruh formulir upload di `settings.php`, `master_cms.php`, dan `cms_admin.php`.
  2. Tambahkan fitur upload file gambar langsung di form Galeri `cms_admin.php` (bisa upload foto dari HP/laptop, tersimpan aman di `uploads/galleries/`).
  3. Pastikan verifikasi format MIME type (PNG, JPG, WEBP, ICO, SVG), limit ukuran file (max 5MB), dan penamaan acak ber-UUID/timestamp aman diterapkan.

### 44. 👤 Profil Pengguna Lengkap di Kanan Header (`profile.php` - Ganti Foto, Data Diri, & Password)
- **Desain & Navigasi**:
  - Di sisi kanan header `views/navbar.php`, tambahkan Avatar bulat pengguna dengan lencana role yang elegan.
  - Klik avatar membuka dropdown profil cepat atau tautan ke halaman profil mandiri: [`profile.php`](file:///c:/laragon/www/target_archery/profile.php).
- **Fitur di Halaman `profile.php` (Dapat Diakses Seluruh Role: Master Admin, Super Admin, Coach, Parent, Archer)**:
  1. **Tab 1: Data Diri**:
     - Form ubah Nama Lengkap, Nomor HP / WhatsApp, dan Alamat Email.
  2. **Tab 2: Foto Profil (Avatar)**:
     - Form unggah foto profil (didukung pratinjau langsung / instant preview).
     - File tersimpan di `uploads/avatars/` dan otomatis mengupdate kolom `avatar_url` di `archery_users`.
  3. **Tab 3: Keamanan & Ganti Password**:
     - Form ganti kata sandi: Password Saat Ini, Password Baru (minimal 6 karakter), dan Konfirmasi Password Baru.
     - Verifikasi `password_verify` sebelum mengupdate hash baru via `password_hash(..., PASSWORD_BCRYPT)`.

### 45. 📱 Header Responsif Dinamis Mode Tablet & Mobile (Auto-Scale Logo & Tulisan, Mobile Minimalis: Logo + Data Diri)
- **Masalah dari Screenshot Pengguna (1112px Mode Tab)**:
  - Di ukuran tablet (sekitar 768px - 1199px), header terlihat penuh sesak, ukuran logo dan font teks masih terlalu besar, dan jarak antar menu membuat tombol/isi terdesak.
- **Rencana Perbaikan Presisi**:
  1. **Mode Tablet (Fluid Auto-Scaling via Media Query 768px - 1199px)**:
     - Gunakan ukuran fluid `font-size: clamp(0.85rem, 1.4vw, 1.15rem);` untuk nama klub/brand.
     - Logo mengecil proporsional dari 42px menjadi 32px–36px.
     - Margin dan gap navigasi dipadatkan (`gap: clamp(0.5rem, 1vw, 1.25rem);`).
     - Seluruh elemen header muat sempurna dalam satu baris datar tanpa bertumpuk atau terpotong.
  2. **Mode HP / Mobile Screen (<= 767px)**:
     - Header disederhanakan secara elegan: Sisi kiri **HANYA menampilkan Logo + Nama Klub Ramping**, dan sisi kanan **HANYA menampilkan Foto Profil / Data Diri Pengguna (atau tombol Masuk)**.
     - Menu navigasi dipindahkan ke bottom nav bar atau dropdown offcanvas yang rapi, menjaga layar HP bersih dan nyaman.
  3. Terapkan pada `views/navbar.php` (aplikasi), `index.php` (web tenant), dan `saas_marketing.php` (web platform).

### 46. 🏹 Pilihan Paket & Frekuensi Latihan Otomatis (1x, 2x, 3x Sepekan) pada Pendaftaran Atlet
- **Fitur Baru**:
  - Pada formulir pendaftaran atlet ([`register_archer.php`](file:///c:/laragon/www/target_archery/register_archer.php)):
    - Calon atlet / orang tua dapat memilih program latihan sekaligus opsi frekuensi kedatangan (misal: *1x sepekan / 4 sesi per bulan*, *2x sepekan / 8 sesi per bulan*, atau *3x sepekan / 12 sesi per bulan*).
    - Biaya SPP/iuran bulanan dan rincian benefit (busur dipinjamkan, target face, pendampingan coach) otomatis dihitung dan ditampilkan secara transparan secara real-time via JavaScript interaktif sebelum submit.
  - Pada panel Super Admin ([`cms_admin.php`](file:///c:/laragon/www/target_archery/cms_admin.php)):
    - Super Admin dapat mengatur kelipatan biaya dan variasi frekuensi mingguan pada masing-masing program kursus.

### 47. ⏳ Alur Pendaftaran Menunggu ACC Super Admin & Pembuatan Akun Otomatis (Username + Password + PIN)
- **Alur Pendaftaran Publik**:
  - Saat calon atlet mengisi formulir di `register_archer.php`, status pendaftaran tersimpan sebagai `pending` (Menunggu Verifikasi & ACC).
  - Tampilkan halaman konfirmasi sukses: *"Pendaftaran Anda berhasil dicatat. Status: Menunggu Verifikasi & ACC dari Super Admin [Nama Klub]. Notifikasi akun login akan diinformasikan."*
- **Panel Pengelolaan Pendaftaran Super Admin**:
  - Pada [`cms_admin.php`](file:///c:/laragon/www/target_archery/cms_admin.php) / [`admin_approvals.php`](file:///c:/laragon/www/target_archery/admin_approvals.php):
    - Super Admin melihat daftar santri/atlet yang mendaftar.
    - Super Admin dapat mengklik tombol **"ACC & Terbitkan Akun"**.
    - Muncul modal konfirmasi di mana Super Admin bisa melihat atau mengkustomisasi **Username**, **Password Awal**, serta **PIN Lapangan 6-Digit**.
    - Saat tombol "Konfirmasi ACC" ditekan:
      1. Sistem otomatis membuat akun orang tua / user di `archery_users` (role `parent`) dengan username dan password yang telah ditentukan.
      2. Sistem otomatis membuat data atlet resmi di `archery_archers` (nomor induk atlet NIS, PIN perangkat lapangan, jarak awal 5m).
      3. Status pendaftaran diubah menjadi `verified`.
      4. Sistem menghasilkan rincian akun dan tautan template WhatsApp otomatis: *"Selamat! Pendaftaran [Nama Santri] di [Nama Klub] telah disetujui. Username: [user], Password: [pass], PIN Lapangan: [pin], Portal Login: [url]"*.
  - **Form Tambah Atlet Langsung dari Super Admin**:
    - Super Admin juga dapat mendaftarkan atlet baru secara manual dari panel admin dengan langsung menentukan username dan password tanpa melalui alur publik.

### 48. 🔐 Fitur "Ingat Saya" (Remember Me) di Login & Pengaturan Durasi Cache Sesi oleh Super Admin
- **Fitur pada `login.php`**:
  - Tambahkan checkbox modern: `[x] Ingat Saya di Perangkat Ini (Remember Me)`.
  - Jika dicentang saat login, cookie sesi dibuat *persistent* dan aman (`HttpOnly`, `SameSite=Lax`).
- **Pengaturan oleh Super Admin pada `settings.php`**:
  - Tambahkan kolom baru di `archery_clubs`: `session_remember_days INT DEFAULT 7`.
  - Super Admin klub dapat memilih durasi simpan sesi:
    - *1 Hari* (Keamanan Tinggi / Komputer Bersama)
    - *3 Hari*
    - *7 Hari (Standar)*
    - *14 Hari*
    - *30 Hari (Perangkat Pribadi)*
  - Sistem mengadopsi durasi tersebut untuk menghitung waktu kadaluarsa cookie login pengguna di klub tersebut.

---

## 🏆 STATUS EKSEKUSI & DEPLOYMENT
- **Poin 1 s/d 48**: 100% Selesai, Teruji, dan Aktif di Server & Cloudflare (Domain `archery.codika.my.id`).
- **Tahap Eksekusi (Tahap 2) & Dokumentasi Manual (Tahap 3)**: SELESAI SEMPURNA.






