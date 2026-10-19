/**
 * assets/js/social_card.js
 * Social Media Share Card Generator (Ala Roti Bakar / Infografis Atlet)
 * Merender kartu estetis untuk Instagram Story (9:16) dan WhatsApp Status
 * lengkap dengan Logo Klub, Nama Atlet, Visual Target Plot, dan Statistik Skor.
 */

class SocialCardGenerator {
    constructor(canvasId, cardData) {
        this.canvas = document.getElementById(canvasId);
        this.ctx = this.canvas.getContext('2d');
        this.data = cardData || {};
        this.format = 'story'; // 'story' (9:16) atau 'feed' (1:1)
        this.render();
    }

    setFormat(fmt) {
        this.format = fmt;
        this.render();
    }

    render() {
        if (!this.canvas) return;

        // Dimensi Canvas HD
        if (this.format === 'story') {
            this.canvas.width = 1080;
            this.canvas.height = 1920;
        } else {
            this.canvas.width = 1080;
            this.canvas.height = 1080;
        }

        const w = this.canvas.width;
        const h = this.canvas.height;
        const ctx = this.ctx;

        // 1. Background Gradient Sport-Tech Gelap Mewah
        const bgGrad = ctx.createLinearGradient(0, 0, w, h);
        bgGrad.addColorStop(0, '#090d16');
        bgGrad.addColorStop(0.5, '#0f172a');
        bgGrad.addColorStop(1, '#050811');
        ctx.fillStyle = bgGrad;
        ctx.fillRect(0, 0, w, h);

        // Aksen Lingkaran Cahaya Emas di Pojok
        const glow = ctx.createRadialGradient(w * 0.85, h * 0.15, 50, w * 0.85, h * 0.15, 600);
        glow.addColorStop(0, 'rgba(245, 158, 11, 0.22)');
        glow.addColorStop(1, 'transparent');
        ctx.fillStyle = glow;
        ctx.fillRect(0, 0, w, h);

        const glowBlue = ctx.createRadialGradient(w * 0.15, h * 0.85, 50, w * 0.15, h * 0.85, 600);
        glowBlue.addColorStop(0, 'rgba(2, 132, 199, 0.18)');
        glowBlue.addColorStop(1, 'transparent');
        ctx.fillStyle = glowBlue;
        ctx.fillRect(0, 0, w, h);

        // 2. Header: Logo & Nama Klub
        ctx.fillStyle = '#f59e0b';
        ctx.beginPath();
        ctx.arc(120, 140, 48, 0, Math.PI * 2);
        ctx.fill();
        ctx.fillStyle = '#0b0f19';
        ctx.font = 'bold 44px sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText('🎯', 120, 140);

        ctx.textAlign = 'left';
        ctx.fillStyle = '#ffffff';
        ctx.font = 'bold 42px Outfit, sans-serif';
        ctx.fillText(this.data.clubName || 'AN-NAHL ARCHERY CLUB', 190, 130);

        ctx.fillStyle = '#94a3b8';
        ctx.font = '500 24px Plus Jakarta Sans, sans-serif';
        ctx.fillText('OFFICIAL SCORECARD & TRAINING RECAP', 190, 165);

        // Garis Pembatas
        ctx.strokeStyle = 'rgba(255, 255, 255, 0.12)';
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.moveTo(70, 220);
        ctx.lineTo(w - 70, 220);
        ctx.stroke();

        // 3. Info Atlet & Tanggal
        ctx.fillStyle = '#38bdf8';
        ctx.font = 'bold 24px Outfit, sans-serif';
        ctx.fillText('ARCHER PROFILE', 70, 280);

        ctx.fillStyle = '#ffffff';
        ctx.font = 'bold 54px Outfit, sans-serif';
        ctx.fillText(this.data.archerName || 'Muhammad Ahmad Santoso', 70, 345);

        ctx.fillStyle = '#cbd5e1';
        ctx.font = '500 28px Plus Jakarta Sans, sans-serif';
        const bowInfo = `${this.data.bowCategory || 'Barebow'} • ${this.data.distance || '18 Meter'} • ${this.data.sessionDate || '19 Okt 2026'}`;
        ctx.fillText(bowInfo, 70, 395);

        // 4. Panel Skor Utama (Vibrant Gold Card)
        const scoreBoxY = 460;
        const scoreBoxH = (this.format === 'story') ? 340 : 260;

        ctx.fillStyle = 'rgba(245, 158, 11, 0.08)';
        ctx.strokeStyle = 'rgba(245, 158, 11, 0.4)';
        ctx.lineWidth = 3;
        this.roundRect(ctx, 70, scoreBoxY, w - 140, scoreBoxH, 28);
        ctx.fill();
        ctx.stroke();

        ctx.fillStyle = '#f59e0b';
        ctx.font = 'bold 26px Outfit, sans-serif';
        ctx.fillText('TOTAL SCORE', 110, scoreBoxY + 55);

        // Angka Skor Besar
        ctx.fillStyle = '#ffffff';
        ctx.font = '800 110px Outfit, sans-serif';
        const scoreText = `${this.data.totalScore || 282}`;
        ctx.fillText(scoreText, 110, scoreBoxY + 175);

        ctx.fillStyle = '#94a3b8';
        ctx.font = '600 42px Outfit, sans-serif';
        ctx.fillText(`/ ${this.data.maxPossible || 300}`, 110 + ctx.measureText(scoreText).width + 20, scoreBoxY + 175);

        // Sub-stat: Akurasi & Total 10/X
        const subStatY = scoreBoxY + ((this.format === 'story') ? 270 : 210);
        ctx.fillStyle = '#10b981';
        ctx.font = 'bold 32px Outfit, sans-serif';
        ctx.fillText(`⚡ Akurasi: ${this.data.accuracyPct || '94.0'}%`, 110, subStatY);

        ctx.fillStyle = '#fbbf24';
        ctx.fillText(`🎯 10s: ${this.data.tenCount || 14} | Xs: ${this.data.xCount || 6}`, w - 420, subStatY);

        // 5. Visualisasi Target Face di Tengah (dengan Sebaran Panah Asli)
        const targetCenterY = (this.format === 'story') ? 1160 : 860;
        const targetRadius = (this.format === 'story') ? 260 : 160;
        this.drawTargetWithShots(ctx, w / 2, targetCenterY, targetRadius, this.data.shots || []);

        // Label Target
        ctx.textAlign = 'center';
        ctx.fillStyle = '#94a3b8';
        ctx.font = '500 24px Plus Jakarta Sans, sans-serif';
        ctx.fillText('SHOT CLUSTER & GROUPING PATTERN', w / 2, targetCenterY + targetRadius + 60);

        // 6. Footer (Story Format)
        if (this.format === 'story') {
            const footerY = 1760;
            ctx.fillStyle = 'rgba(255, 255, 255, 0.05)';
            this.roundRect(ctx, 70, footerY, w - 140, 100, 20);
            ctx.fill();

            ctx.textAlign = 'center';
            ctx.fillStyle = '#ffffff';
            ctx.font = '600 28px Plus Jakarta Sans, sans-serif';
            ctx.fillText('🏆 "Konsistensi Membentuk Sang Juara"', w / 2, footerY + 58);
        }
    }

    /**
     * Gambar Cincin Target & Titik Tembakan Panah
     */
    drawTargetWithShots(ctx, cx, cy, R, shots) {
        const rings = [
            { r: 1.00, color: '#f8fafc' },
            { r: 0.90, color: '#f8fafc' },
            { r: 0.80, color: '#1e293b' },
            { r: 0.70, color: '#1e293b' },
            { r: 0.60, color: '#0284c7' },
            { r: 0.50, color: '#0284c7' },
            { r: 0.40, color: '#ef4444' },
            { r: 0.30, color: '#ef4444' },
            { r: 0.20, color: '#f59e0b' },
            { r: 0.10, color: '#f59e0b' },
            { r: 0.05, color: '#f59e0b' }
        ];

        // Background shadow target
        ctx.save();
        ctx.shadowColor = 'rgba(0, 0, 0, 0.7)';
        ctx.shadowBlur = 40;
        ctx.fillStyle = '#0f172a';
        ctx.beginPath();
        ctx.arc(cx, cy, R * 1.05, 0, Math.PI * 2);
        ctx.fill();
        ctx.restore();

        // Render Cincin
        rings.forEach(ring => {
            ctx.fillStyle = ring.color;
            ctx.beginPath();
            ctx.arc(cx, cy, R * ring.r, 0, Math.PI * 2);
            ctx.fill();
            ctx.strokeStyle = (ring.color === '#1e293b') ? '#475569' : '#000000';
            ctx.lineWidth = 1.2;
            ctx.stroke();
        });

        // Silang Tengah
        ctx.strokeStyle = '#000000';
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.moveTo(cx - 12, cy);
        ctx.lineTo(cx + 12, cy);
        ctx.moveTo(cx, cy - 12);
        ctx.lineTo(cx, cy + 12);
        ctx.stroke();

        // Plot Titik-titik Panah Asli
        shots.forEach(s => {
            const px = cx + s.x * R;
            const py = cy + s.y * R;

            ctx.fillStyle = 'rgba(16, 185, 129, 0.4)';
            ctx.beginPath();
            ctx.arc(px, py, 14, 0, Math.PI * 2);
            ctx.fill();

            ctx.fillStyle = '#10b981';
            ctx.beginPath();
            ctx.arc(px, py, 7, 0, Math.PI * 2);
            ctx.fill();
            ctx.strokeStyle = '#ffffff';
            ctx.lineWidth = 2;
            ctx.stroke();
        });
    }

    roundRect(ctx, x, y, width, height, radius) {
        ctx.beginPath();
        ctx.moveTo(x + radius, y);
        ctx.lineTo(x + width - radius, y);
        ctx.quadraticCurveTo(x + width, y, x + width, y + radius);
        ctx.lineTo(x + width, y + height - radius);
        ctx.quadraticCurveTo(x + width, y + height, x + width - radius, y + height);
        ctx.lineTo(x + radius, y + height);
        ctx.quadraticCurveTo(x, y + height, x, y + height - radius);
        ctx.lineTo(x, y + radius);
        ctx.quadraticCurveTo(x, y, x + radius, y);
        ctx.closePath();
    }

    downloadImage(filename = 'archery_scorecard.png') {
        const link = document.createElement('a');
        link.download = filename;
        link.href = this.canvas.toDataURL('image/png');
        link.click();
    }

    shareWhatsApp(customText = '') {
        const text = encodeURIComponent(
            customText || `Alhamdulillah! Rekap latihan panahan ananda ${this.data.archerName || ''} hari ini: Total Skor ${this.data.totalScore || 0}/${this.data.maxPossible || 300} (Akurasi ${this.data.accuracyPct || 0}%). AN-NAHL ARCHERY CLUB 🎯`
        );
        window.open(`https://api.whatsapp.com/send?text=${text}`, '_blank');
    }
}
