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

---

## 🏆 STATUS EKSEKUSI & DEPLOYMENT
- **Poin 1 s/d 27 & 29**: 100% Selesai & Berfungsi Sempurna.
- **Poin 28 (Standarisasi Total `codika.my.id`)**: Siap dieksekusi setelah konfirmasi pengguna.


