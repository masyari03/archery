<?php
// create_default_assets.php - Menghasilkan aset default lokal super ringan (zero external network stall)
$dir = __DIR__ . '/assets/img';
if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}

function makeTargetImage($path, $width, $height, $title, $subtitle, $bgColor = [15, 23, 42], $accentColor = [2, 132, 199]) {
    $im = imagecreatetruecolor($width, $height);
    $bg = imagecolorallocate($im, $bgColor[0], $bgColor[1], $bgColor[2]);
    $accent = imagecolorallocate($im, $accentColor[0], $accentColor[1], $accentColor[2]);
    $gold = imagecolorallocate($im, 245, 158, 11);
    $red = imagecolorallocate($im, 239, 68, 68);
    $white = imagecolorallocate($im, 255, 255, 255);
    $gray = imagecolorallocate($im, 148, 163, 184);

    imagefill($im, 0, 0, $bg);

    // Target archery concentric rings graphic
    $cx = (int)($width / 2);
    $cy = (int)($height * 0.42);
    $maxR = (int)(min($width, $height) * 0.35);

    imagefilledellipse($im, $cx, $cy, $maxR * 2, $maxR * 2, $gray);
    imagefilledellipse($im, $cx, $cy, (int)($maxR * 1.6), (int)($maxR * 1.6), $accent);
    imagefilledellipse($im, $cx, $cy, (int)($maxR * 1.2), (int)($maxR * 1.2), $red);
    imagefilledellipse($im, $cx, $cy, (int)($maxR * 0.7), (int)($maxR * 0.7), $gold);
    imagefilledellipse($im, $cx, $cy, (int)($maxR * 0.25), (int)($maxR * 0.25), $white);

    // Render Text
    $font = 5; // Built-in font
    $textX = max(10, (int)($width / 2 - (strlen($title) * imagefontwidth($font)) / 2));
    $textY = (int)($height * 0.75);
    imagestring($im, $font, $textX, $textY, $title, $white);

    $subFont = 3;
    $subX = max(10, (int)($width / 2 - (strlen($subtitle) * imagefontwidth($subFont)) / 2));
    $subY = (int)($height * 0.85);
    imagestring($im, $subFont, $subX, $subY, $subtitle, $gray);

    if (str_ends_with($path, '.png')) {
        imagepng($im, $path, 6);
    } else {
        imagejpeg($im, $path, 85);
    }
    imagedestroy($im);
}

// 1. Default Logo (200x200)
makeTargetImage("$dir/default_logo.png", 200, 200, "TARGET ARCHERY", "Official Club Platform", [11, 15, 25], [2, 132, 199]);

// 2. Default Favicon (64x64)
$fav = imagecreatetruecolor(64, 64);
$favBg = imagecolorallocate($fav, 11, 15, 25);
$favGold = imagecolorallocate($fav, 245, 158, 11);
$favRed = imagecolorallocate($fav, 239, 68, 68);
$favBlue = imagecolorallocate($fav, 2, 132, 199);
$favWhite = imagecolorallocate($fav, 255, 255, 255);
imagefill($fav, 0, 0, $favBg);
imagefilledellipse($fav, 32, 32, 56, 56, $favBlue);
imagefilledellipse($fav, 32, 32, 42, 42, $favRed);
imagefilledellipse($fav, 32, 32, 26, 26, $favGold);
imagefilledellipse($fav, 32, 32, 10, 10, $favWhite);
imagepng($fav, "$dir/default_favicon.png", 6);
imagedestroy($fav);

// 3. Default Avatar (128x128)
$av = imagecreatetruecolor(128, 128);
$avBg = imagecolorallocate($av, 30, 41, 59);
$avPrimary = imagecolorallocate($av, 2, 132, 199);
$avWhite = imagecolorallocate($av, 255, 255, 255);
imagefill($av, 0, 0, $avBg);
// Head
imagefilledellipse($av, 64, 46, 44, 44, $avPrimary);
// Body
imagefilledellipse($av, 64, 110, 80, 56, $avPrimary);
imagepng($av, "$dir/default_avatar.png", 6);
imagedestroy($av);

// 4. Gallery 1 - Range
makeTargetImage("$dir/gallery_range.jpg", 600, 400, "Fasilitas Range Resmi", "Target Face 5m - 50m World Archery", [15, 23, 42], [2, 132, 199]);

// 5. Gallery 2 - Coach
makeTargetImage("$dir/gallery_coach.jpg", 600, 400, "Bimbingan Teknik Coach", "Pelatih Berlisensi & Pembinaan Sunnah", [15, 23, 42], [16, 185, 129]);

// 6. Gallery 3 - Scoring
makeTargetImage("$dir/gallery_scoring.jpg", 600, 400, "Scoring & Ujian Jarak", "Target Plotter Presisi Milimeter", [15, 23, 42], [245, 158, 11]);

// 7. Gallery 4 - Champion
makeTargetImage("$dir/gallery_champion.jpg", 600, 400, "Prestasi Juara Pelajar", "Pencapaian Medali Kejuaraan Resmi", [15, 23, 42], [239, 68, 68]);

echo "✔ Seluruh aset lokal super ringan berhasil dibuat di assets/img/\n";
