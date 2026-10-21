/**
 * assets/js/target_plotter.js
 * Engine Plotting Target Panahan Interaktif (Mengadopsi Core MyTargets)
 * Fitur: Triple Shield Auto-Save (Instant AJAX + LocalStorage Offline Shield),
 * Kaca Pembesar (Magnifier Loupe), Grouping Analytics (Windage & Elevation),
 * dan Kontrol Pembukaan Form Evaluasi Pelatih Khusus Setelah 6 Seri Selesai.
 */

class ArcheryPlotter {
    constructor(canvasId, loupeId, options = {}) {
        this.canvas = document.getElementById(canvasId);
        this.ctx = this.canvas.getContext('2d');
        this.loupe = document.getElementById(loupeId);
        this.loupeCanvas = this.loupe ? this.loupe.querySelector('canvas') : null;
        this.loupeCtx = this.loupeCanvas ? this.loupeCanvas.getContext('2d') : null;
        this.loupeBadge = this.loupe ? this.loupe.querySelector('.magnifier-score-badge') : null;

        this.sessionId = options.sessionId || 0;
        this.archerId = options.archerId || 0;
        this.totalEnds = options.totalEnds || 6;
        this.arrowsPerEnd = options.arrowsPerEnd || 5;
        this.targetType = options.targetType || 'wa_10';
        this.currentEnd = 1;

        // Data tembakan per seri: { [endNum]: [ { x, y, radius, score, is_x, arrow_num } ] }
        this.endsData = options.existingEnds || {};
        for (let i = 1; i <= this.totalEnds; i++) {
            if (!this.endsData[i]) this.endsData[i] = [];
        }

        // LAYER 2: LOCALSTORAGE OFFLINE SHIELD (PULIHKAN JIKA ADA CADANGAN LOKAL)
        this.storageKey = `archery_backup_sess_${this.sessionId}`;
        this.restoreFromLocalStorage();

        this.isInteracting = false;
        this.currentDragPoint = null;
        this.zoomFactor = 2.4;

        this.colors = {
            gold: '#f59e0b',
            red: '#ef4444',
            blue: '#0284c7',
            black: '#1e293b',
            white: '#f8fafc',
            line: '#000000',
            arrow: '#10b981',
            arrowActive: '#38bdf8'
        };

        this.init();
    }

    setTargetType(type) {
        this.targetType = type;
        this.redraw();
        this.updateScorecardUI();
    }

    init() {
        this.resizeCanvas();
        window.addEventListener('resize', () => {
            this.resizeCanvas();
            this.redraw();
        });

        const wrap = this.canvas.parentElement;

        const getPos = (e) => {
            const rect = this.canvas.getBoundingClientRect();
            const clientX = e.touches ? e.touches[0].clientX : e.clientX;
            const clientY = e.touches ? e.touches[0].clientY : e.clientY;
            return {
                x: clientX - rect.left,
                y: clientY - rect.top,
                pageClientX: clientX,
                pageClientY: clientY,
                rect: rect
            };
        };

        const handleStart = (e) => {
            if (this.isCurrentEndFull()) return;
            e.preventDefault();
            this.isInteracting = true;
            const pos = getPos(e);
            this.updatePointFromPixel(pos.x, pos.y);
            this.showLoupe(pos);
            this.redraw();
        };

        const handleMove = (e) => {
            if (!this.isInteracting) return;
            e.preventDefault();
            const pos = getPos(e);
            this.updatePointFromPixel(pos.x, pos.y);
            this.showLoupe(pos);
            this.redraw();
        };

        const handleEnd = (e) => {
            if (!this.isInteracting) return;
            e.preventDefault();
            this.isInteracting = false;
            this.hideLoupe();

            if (this.currentDragPoint) {
                this.commitArrow(this.currentDragPoint);
                this.currentDragPoint = null;
                this.redraw();
                this.saveToLocalStorage(); // Simpan Offline
                this.syncToServer();       // Sync AJAX Instant
            }
        };

        wrap.addEventListener('mousedown', handleStart);
        window.addEventListener('mousemove', handleMove);
        window.addEventListener('mouseup', handleEnd);

        wrap.addEventListener('touchstart', handleStart, { passive: false });
        window.addEventListener('touchmove', handleMove, { passive: false });
        window.addEventListener('touchend', handleEnd, { passive: false });

        this.redraw();
        this.updateScorecardUI();
        this.checkSessionCompletion();
    }

    resizeCanvas() {
        const size = this.canvas.parentElement.clientWidth;
        this.canvas.width = size * window.devicePixelRatio;
        this.canvas.height = size * window.devicePixelRatio;
        this.ctx.scale(window.devicePixelRatio, window.devicePixelRatio);
        this.displaySize = size;
        this.center = size / 2;
        this.radiusTarget = size * 0.46; // 92% diameter target
    }

    updatePointFromPixel(pixelX, pixelY) {
        const normX = (pixelX - this.center) / this.radiusTarget;
        const normY = (pixelY - this.center) / this.radiusTarget;
        const r = Math.sqrt(normX * normX + normY * normY);

        const scoreInfo = this.calculateScore(r, normX, normY);
        this.currentDragPoint = {
            normX: parseFloat(normX.toFixed(4)),
            normY: parseFloat(normY.toFixed(4)),
            radius: parseFloat(r.toFixed(4)),
            score: scoreInfo.score,
            is_x: scoreInfo.is_x,
            pixelX: pixelX,
            pixelY: pixelY
        };

        if (this.loupeBadge) {
            let label = scoreInfo.score;
            if (scoreInfo.is_x) label = 'X (' + scoreInfo.score + ')';
            else if (scoreInfo.score === 0) label = 'M';
            this.loupeBadge.textContent = label;
        }
    }

    calculateScore(r, normX = 0, normY = 0) {
        if (this.targetType === 'wa_6spot') {
            if (r <= 0.05) return { score: 10, is_x: 1 };
            if (r <= 0.10) return { score: 10, is_x: 0 };
            if (r <= 0.20) return { score: 9, is_x: 0 };
            if (r <= 0.30) return { score: 8, is_x: 0 };
            if (r <= 0.40) return { score: 7, is_x: 0 };
            if (r <= 0.50) return { score: 6, is_x: 0 };
            if (r <= 0.60) return { score: 5, is_x: 0 };
            return { score: 0, is_x: 0 }; // Outer 1-4 rings are Miss on 6-spot
        }

        if (this.targetType === 'wa_3spot') {
            // 3 vertical spots: centers at y = -0.58, 0, +0.58; radius scale = 0.26
            const spots = [-0.58, 0, 0.58];
            let closestDist = 999;
            spots.forEach(sy => {
                const d = Math.sqrt(normX * normX + (normY - sy) * (normY - sy));
                if (d < closestDist) closestDist = d;
            });
            const rRel = closestDist / 0.26;
            if (rRel <= 0.10) return { score: 10, is_x: 1 };
            if (rRel <= 0.20) return { score: 10, is_x: 0 };
            if (rRel <= 0.40) return { score: 9, is_x: 0 };
            if (rRel <= 0.60) return { score: 8, is_x: 0 };
            if (rRel <= 0.80) return { score: 7, is_x: 0 };
            if (rRel <= 1.00) return { score: 6, is_x: 0 };
            return { score: 0, is_x: 0 };
        }

        if (this.targetType === 'nfaa_blue') {
            // NFAA Indoor Blue Face: 5-ring face
            if (r <= 0.10) return { score: 5, is_x: 1 };
            if (r <= 0.20) return { score: 5, is_x: 0 };
            if (r <= 0.40) return { score: 4, is_x: 0 };
            if (r <= 0.60) return { score: 3, is_x: 0 };
            if (r <= 0.80) return { score: 2, is_x: 0 };
            if (r <= 1.00) return { score: 1, is_x: 0 };
            return { score: 0, is_x: 0 };
        }

        if (this.targetType === 'wa_field') {
            // WA Field Archery: Black rings 1-4, Gold center 5 and 6/X
            if (r <= 0.10) return { score: 6, is_x: 1 };
            if (r <= 0.20) return { score: 5, is_x: 0 };
            if (r <= 0.40) return { score: 4, is_x: 0 };
            if (r <= 0.60) return { score: 3, is_x: 0 };
            if (r <= 0.80) return { score: 2, is_x: 0 };
            if (r <= 1.00) return { score: 1, is_x: 0 };
            return { score: 0, is_x: 0 };
        }

        // Default: WA 10-Ring Standard (Full Face)
        if (r <= 0.05) return { score: 10, is_x: 1 };
        if (r <= 0.10) return { score: 10, is_x: 0 };
        if (r <= 0.20) return { score: 9, is_x: 0 };
        if (r <= 0.30) return { score: 8, is_x: 0 };
        if (r <= 0.40) return { score: 7, is_x: 0 };
        if (r <= 0.50) return { score: 6, is_x: 0 };
        if (r <= 0.60) return { score: 5, is_x: 0 };
        if (r <= 0.70) return { score: 4, is_x: 0 };
        if (r <= 0.80) return { score: 3, is_x: 0 };
        if (r <= 0.90) return { score: 2, is_x: 0 };
        if (r <= 1.00) return { score: 1, is_x: 0 };
        return { score: 0, is_x: 0 }; // Miss
    }

    showLoupe(pos) {
        if (!this.loupe || !this.loupeCtx) return;
        this.loupe.style.display = 'block';
        this.loupe.style.left = `${pos.x}px`;
        this.loupe.style.top = `${pos.y}px`;

        const lSize = 110;
        if (this.loupeCanvas.width !== lSize) {
            this.loupeCanvas.width = lSize;
            this.loupeCanvas.height = lSize;
        }

        this.loupeCtx.save();
        this.loupeCtx.clearRect(0, 0, lSize, lSize);
        this.loupeCtx.translate(lSize / 2, lSize / 2);
        this.loupeCtx.scale(this.zoomFactor, this.zoomFactor);
        this.loupeCtx.translate(-pos.x, -pos.y);

        this.drawTargetFace(this.loupeCtx);
        this.drawExistingArrows(this.loupeCtx);

        this.loupeCtx.restore();
    }

    hideLoupe() {
        if (this.loupe) this.loupe.style.display = 'none';
    }

    isCurrentEndFull() {
        return (this.endsData[this.currentEnd] || []).length >= this.arrowsPerEnd;
    }

    commitArrow(point) {
        if (!this.endsData[this.currentEnd]) this.endsData[this.currentEnd] = [];
        const arrowNum = this.endsData[this.currentEnd].length + 1;
        this.endsData[this.currentEnd].push({
            arrow_num: arrowNum,
            x: point.normX,
            y: point.normY,
            radius: point.radius,
            score: point.score,
            is_x: point.is_x
        });
        this.updateScorecardUI();
        this.checkSessionCompletion();
    }

    undoLastArrow() {
        if (this.endsData[this.currentEnd] && this.endsData[this.currentEnd].length > 0) {
            this.endsData[this.currentEnd].pop();
            this.redraw();
            this.updateScorecardUI();
            this.saveToLocalStorage();
            this.syncToServer();
            this.checkSessionCompletion();
        }
    }

    switchEnd(endNum) {
        if (endNum >= 1 && endNum <= this.totalEnds) {
            this.currentEnd = endNum;
            this.redraw();
            this.updateScorecardUI();
        }
    }

    drawTargetFace(ctx) {
        const c = this.center;
        const R = this.radiusTarget;

        if (this.targetType === 'wa_6spot') {
            // Base background
            ctx.fillStyle = '#0f172a';
            ctx.beginPath();
            ctx.arc(c, c, R * 1.06, 0, Math.PI * 2);
            ctx.fill();

            // Paper circle
            ctx.fillStyle = '#1e293b';
            ctx.beginPath();
            ctx.arc(c, c, R * 0.64, 0, Math.PI * 2);
            ctx.fill();
            ctx.strokeStyle = '#475569';
            ctx.lineWidth = 1;
            ctx.stroke();

            const rings = [
                { r: 0.60, color: this.colors.blue },
                { r: 0.50, color: this.colors.blue },
                { r: 0.40, color: this.colors.red },
                { r: 0.30, color: this.colors.red },
                { r: 0.20, color: this.colors.gold },
                { r: 0.10, color: this.colors.gold },
                { r: 0.05, color: this.colors.gold }
            ];
            rings.forEach(ring => {
                ctx.fillStyle = ring.color;
                ctx.beginPath();
                ctx.arc(c, c, R * ring.r, 0, Math.PI * 2);
                ctx.fill();
                ctx.strokeStyle = '#000000';
                ctx.lineWidth = 0.8;
                ctx.stroke();
            });

            // Center cross
            ctx.strokeStyle = '#000000';
            ctx.lineWidth = 1;
            ctx.beginPath();
            ctx.moveTo(c - 6, c); ctx.lineTo(c + 6, c);
            ctx.moveTo(c, c - 6); ctx.lineTo(c, c + 6);
            ctx.stroke();
            return;
        }

        if (this.targetType === 'wa_3spot') {
            // Canvas background
            ctx.fillStyle = '#0b1329';
            ctx.beginPath();
            ctx.arc(c, c, R * 1.06, 0, Math.PI * 2);
            ctx.fill();

            const spots = [-0.58, 0, 0.58];
            const sR = R * 0.26;

            spots.forEach((syOffset, idx) => {
                const sCenterY = c + syOffset * R;

                // Spot outer shadow & frame
                ctx.fillStyle = '#1e293b';
                ctx.beginPath();
                ctx.arc(c, sCenterY, sR * 1.08, 0, Math.PI * 2);
                ctx.fill();
                ctx.strokeStyle = '#475569';
                ctx.lineWidth = 1;
                ctx.stroke();

                const rings = [
                    { r: 1.00, color: this.colors.blue },  // 6
                    { r: 0.80, color: this.colors.blue },  // 7
                    { r: 0.60, color: this.colors.red },   // 8
                    { r: 0.40, color: this.colors.red },   // 8/9
                    { r: 0.20, color: this.colors.gold },  // 10
                    { r: 0.10, color: this.colors.gold }   // Inner X
                ];
                rings.forEach(ring => {
                    ctx.fillStyle = ring.color;
                    ctx.beginPath();
                    ctx.arc(c, sCenterY, sR * ring.r, 0, Math.PI * 2);
                    ctx.fill();
                    ctx.strokeStyle = '#000000';
                    ctx.lineWidth = 0.7;
                    ctx.stroke();
                });

                // Spot label (1, 2, 3)
                ctx.fillStyle = 'rgba(255,255,255,0.7)';
                ctx.font = 'bold 10px Outfit, sans-serif';
                ctx.textAlign = 'right';
                ctx.fillText(`Spot ${idx + 1}`, c - sR * 1.15, sCenterY + 3);

                // Center cross
                ctx.strokeStyle = '#000000';
                ctx.lineWidth = 0.8;
                ctx.beginPath();
                ctx.moveTo(c - 4, sCenterY); ctx.lineTo(c + 4, sCenterY);
                ctx.moveTo(c, sCenterY - 4); ctx.lineTo(c, sCenterY + 4);
                ctx.stroke();
            });
            return;
        }

        if (this.targetType === 'nfaa_blue') {
            // NFAA Indoor Blue Face
            ctx.fillStyle = '#0f172a';
            ctx.beginPath();
            ctx.arc(c, c, R * 1.06, 0, Math.PI * 2);
            ctx.fill();

            const rings = [
                { r: 1.00, color: '#1e3a8a', stroke: '#ffffff' }, // 1
                { r: 0.80, color: '#1e3a8a', stroke: '#ffffff' }, // 2
                { r: 0.60, color: '#1d4ed8', stroke: '#ffffff' }, // 3
                { r: 0.40, color: '#1d4ed8', stroke: '#ffffff' }, // 4
                { r: 0.20, color: '#ffffff', stroke: '#1e3a8a' }, // 5
                { r: 0.10, color: '#ffffff', stroke: '#1e3a8a' }  // X
            ];
            rings.forEach(ring => {
                ctx.fillStyle = ring.color;
                ctx.beginPath();
                ctx.arc(c, c, R * ring.r, 0, Math.PI * 2);
                ctx.fill();
                ctx.strokeStyle = ring.stroke;
                ctx.lineWidth = 0.9;
                ctx.stroke();
            });

            // Center X
            ctx.strokeStyle = '#1e3a8a';
            ctx.lineWidth = 1.2;
            ctx.beginPath();
            ctx.moveTo(c - 5, c); ctx.lineTo(c + 5, c);
            ctx.moveTo(c, c - 5); ctx.lineTo(c, c + 5);
            ctx.stroke();
            return;
        }

        if (this.targetType === 'wa_field') {
            // WA Field Archery Face (Black 1-4, Gold 5-6)
            ctx.fillStyle = '#0f172a';
            ctx.beginPath();
            ctx.arc(c, c, R * 1.06, 0, Math.PI * 2);
            ctx.fill();

            const rings = [
                { r: 1.00, color: '#18181b', stroke: '#ffffff' }, // 1
                { r: 0.80, color: '#18181b', stroke: '#ffffff' }, // 2
                { r: 0.60, color: '#27272a', stroke: '#ffffff' }, // 3
                { r: 0.40, color: '#27272a', stroke: '#ffffff' }, // 4
                { r: 0.20, color: '#eab308', stroke: '#000000' }, // 5
                { r: 0.10, color: '#ca8a04', stroke: '#000000' }  // 6 / X
            ];
            rings.forEach(ring => {
                ctx.fillStyle = ring.color;
                ctx.beginPath();
                ctx.arc(c, c, R * ring.r, 0, Math.PI * 2);
                ctx.fill();
                ctx.strokeStyle = ring.stroke;
                ctx.lineWidth = 0.9;
                ctx.stroke();
            });

            // Center cross
            ctx.strokeStyle = '#000000';
            ctx.lineWidth = 1;
            ctx.beginPath();
            ctx.moveTo(c - 6, c); ctx.lineTo(c + 6, c);
            ctx.moveTo(c, c - 6); ctx.lineTo(c, c + 6);
            ctx.stroke();
            return;
        }

        // Default: WA 10-Ring Standard (Full Face)
        const rings = [
            { r: 1.00, color: this.colors.white },
            { r: 0.90, color: this.colors.white },
            { r: 0.80, color: this.colors.black },
            { r: 0.70, color: this.colors.black },
            { r: 0.60, color: this.colors.blue },
            { r: 0.50, color: this.colors.blue },
            { r: 0.40, color: this.colors.red },
            { r: 0.30, color: this.colors.red },
            { r: 0.20, color: this.colors.gold },
            { r: 0.10, color: this.colors.gold },
            { r: 0.05, color: this.colors.gold } // Inner X
        ];

        ctx.fillStyle = '#0f172a';
        ctx.beginPath();
        ctx.arc(c, c, R * 1.06, 0, Math.PI * 2);
        ctx.fill();

        rings.forEach(ring => {
            ctx.fillStyle = ring.color;
            ctx.beginPath();
            ctx.arc(c, c, R * ring.r, 0, Math.PI * 2);
            ctx.fill();
            ctx.strokeStyle = (ring.color === this.colors.black) ? '#475569' : '#000000';
            ctx.lineWidth = 0.8;
            ctx.stroke();
        });

        // Silang Tengah
        ctx.strokeStyle = '#000000';
        ctx.lineWidth = 1;
        ctx.beginPath();
        ctx.moveTo(c - 6, c);
        ctx.lineTo(c + 6, c);
        ctx.moveTo(c, c - 6);
        ctx.lineTo(c, c + 6);
        ctx.stroke();
    }

    drawExistingArrows(ctx) {
        const c = this.center;
        const R = this.radiusTarget;
        const arrows = this.endsData[this.currentEnd] || [];

        arrows.forEach((arr) => {
            const px = c + arr.x * R;
            const py = c + arr.y * R;

            ctx.fillStyle = 'rgba(16, 185, 129, 0.4)';
            ctx.beginPath();
            ctx.arc(px, py, 11, 0, Math.PI * 2);
            ctx.fill();

            ctx.fillStyle = '#10b981';
            ctx.beginPath();
            ctx.arc(px, py, 6, 0, Math.PI * 2);
            ctx.fill();
            ctx.strokeStyle = '#ffffff';
            ctx.lineWidth = 1.5;
            ctx.stroke();

            ctx.fillStyle = '#ffffff';
            ctx.font = 'bold 8px Outfit, sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(arr.arrow_num, px, py);
        });
    }

    redraw() {
        this.ctx.clearRect(0, 0, this.displaySize, this.displaySize);
        this.drawTargetFace(this.ctx);
        this.drawExistingArrows(this.ctx);

        if (this.currentDragPoint) {
            const px = this.currentDragPoint.pixelX;
            const py = this.currentDragPoint.pixelY;

            ctx.fillStyle = 'rgba(56, 189, 248, 0.5)';
            ctx.beginPath();
            ctx.arc(px, py, 14, 0, Math.PI * 2);
            ctx.fill();

            ctx.fillStyle = '#38bdf8';
            ctx.beginPath();
            ctx.arc(px, py, 7, 0, Math.PI * 2);
            ctx.fill();
            ctx.strokeStyle = '#ffffff';
            ctx.lineWidth = 2;
            ctx.stroke();
        }

        this.calculateGroupingAnalytics();
    }

    calculateGroupingAnalytics() {
        const arrows = this.endsData[this.currentEnd] || [];
        const adviceBox = document.getElementById('groupingAdviceBox');
        if (!adviceBox) return;

        if (arrows.length < 2) {
            adviceBox.innerHTML = '<span style="color:var(--text-dim); font-size:0.75rem;">Tembakkan minimal 2 panah untuk melihat analisis sebaran (windage & elevation).</span>';
            return;
        }

        let sumX = 0, sumY = 0;
        arrows.forEach(a => { sumX += a.x; sumY += a.y; });
        const avgX = sumX / arrows.length;
        const avgY = sumY / arrows.length;

        let windage = 'Tengah';
        if (avgX > 0.08) windage = '<b style="color:#ef4444">Kanan</b> (geser pisir ke kanan)';
        else if (avgX < -0.08) windage = '<b style="color:#38bdf8">Kiri</b> (geser pisir ke kiri)';

        let elevation = 'Tengah';
        if (avgY > 0.08) elevation = '<b style="color:#f59e0b">Bawah</b> (naikkan pisir)';
        else if (avgY < -0.08) elevation = '<b style="color:#10b981">Atas</b> (turunkan pisir)';

        adviceBox.innerHTML = `
            <div style="font-size:0.8rem; line-height: 1.4;">
                <div>🎯 <b>Center of Impact Seri ${this.currentEnd}:</b> ${windage} | ${elevation}</div>
                <div style="color:var(--text-dim); font-size:0.72rem; margin-top:2px;">Koreksi setelan pisir (sight mark) berdasarkan sebaran di atas.</div>
            </div>
        `;
    }

    updateScorecardUI() {
        const activeEndBadge = document.getElementById('activeEndBadge');
        if (activeEndBadge) activeEndBadge.textContent = `Seri ${this.currentEnd} / ${this.totalEnds}`;

        const arrowSlots = document.getElementById('arrowSlots');
        if (arrowSlots) {
            arrowSlots.innerHTML = '';
            const currentArrows = this.endsData[this.currentEnd] || [];
            for (let i = 0; i < this.arrowsPerEnd; i++) {
                const arr = currentArrows[i];
                const span = document.createElement('span');
                span.className = 'score-pill ' + (arr ? (arr.is_x ? 's-x' : 's-' + arr.score) : 's-m');
                span.textContent = arr ? (arr.is_x ? 'X' : (arr.score === 0 ? 'M' : arr.score)) : '-';
                arrowSlots.appendChild(span);
            }
        }

        let grandTotal = 0;
        let totalX = 0;
        let totalTen = 0;
        let totalArrowsShot = 0;

        for (let i = 1; i <= this.totalEnds; i++) {
            const arrs = this.endsData[i] || [];
            const endSum = arrs.reduce((acc, a) => acc + a.score, 0);
            grandTotal += endSum;
            totalArrowsShot += arrs.length;
            arrs.forEach(a => {
                if (a.is_x) totalX++;
                if (a.score === 10) totalTen++;
            });

            const endScoreTd = document.getElementById(`endScoreVal_${i}`);
            if (endScoreTd) endScoreTd.textContent = endSum;
        }

        const totalScoreEl = document.getElementById('totalScoreDisplay');
        if (totalScoreEl) totalScoreEl.textContent = grandTotal;

        const maxPerArrow = this.targetType === 'nfaa_blue' ? 5 : (this.targetType === 'wa_field' ? 6 : 10);
        const maxPossible = this.totalEnds * this.arrowsPerEnd * maxPerArrow;
        const accuracyPct = maxPossible > 0 ? ((grandTotal / maxPossible) * 100).toFixed(1) : '0.0';
        const accEl = document.getElementById('accuracyDisplay');
        if (accEl) accEl.textContent = `${accuracyPct}%`;

        const tenXEl = document.getElementById('tenXDisplay');
        if (tenXEl) tenXEl.textContent = `${totalTen} / ${totalX}`;
    }

    // CHECK APAKAH SELURUH 6 SERI SELESAI UNTUK MEMBUKA FORM EVALUASI COACH
    checkSessionCompletion() {
        let allCompleted = true;
        for (let i = 1; i <= this.totalEnds; i++) {
            if (!this.endsData[i] || this.endsData[i].length < this.arrowsPerEnd) {
                allCompleted = false;
                break;
            }
        }

        const evalCard = document.getElementById('coachEvalSection');
        const inProgressNotice = document.getElementById('sessionInProgressNotice');

        if (evalCard && inProgressNotice) {
            if (allCompleted) {
                evalCard.style.display = 'block';
                inProgressNotice.style.display = 'none';
            } else {
                evalCard.style.display = 'none';
                inProgressNotice.style.display = 'block';
            }
        }
    }

    saveToLocalStorage() {
        try {
            localStorage.setItem(this.storageKey, JSON.stringify(this.endsData));
        } catch (e) {
            console.warn('LocalStorage error:', e);
        }
    }

    restoreFromLocalStorage() {
        try {
            const saved = localStorage.getItem(this.storageKey);
            if (saved) {
                const parsed = JSON.parse(saved);
                for (let i = 1; i <= this.totalEnds; i++) {
                    if ((!this.endsData[i] || this.endsData[i].length === 0) && parsed[i] && parsed[i].length > 0) {
                        this.endsData[i] = parsed[i];
                    }
                }
            }
        } catch (e) {
            console.warn('Gagal membaca cadangan localStorage:', e);
        }
    }

    syncToServer() {
        const payload = {
            session_id: this.sessionId,
            archer_id: this.archerId,
            end_number: this.currentEnd,
            arrows: this.endsData[this.currentEnd]
        };

        const syncStatusEl = document.getElementById('syncStatusIndicator');
        if (syncStatusEl) {
            syncStatusEl.innerHTML = '<span style="color:#38bdf8;">⏳ Menyinkronkan nilai...</span>';
        }

        fetch('api/save_shot.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                if (syncStatusEl) {
                    syncStatusEl.innerHTML = `<span style="color:#10b981;">✔ Seri ${this.currentEnd} Tersimpan Aman</span>`;
                }
            } else {
                if (syncStatusEl) {
                    syncStatusEl.innerHTML = `<span style="color:#f59e0b;">⚠️ Cadangan lokal aktif</span>`;
                }
            }
        })
        .catch(err => {
            console.warn('Network offline, data tersimpan aman di HP');
            if (syncStatusEl) {
                syncStatusEl.innerHTML = `<span style="color:#f59e0b;">📶 Offline, nilai aman di HP</span>`;
            }
        });
    }
}
