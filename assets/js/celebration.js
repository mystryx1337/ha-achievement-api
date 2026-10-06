/**
 * HA Achievement API - Celebration & Confetti Engine
 *
 * Leichtgewichtiges Konfetti- und Toast-System ohne externe Abhängigkeiten.
 *
 * @package HA_Achievement_API
 */

(function () {
    'use strict';

    class CelebrationEngine {
        constructor() {
            this.canvas = null;
            this.ctx = null;
            this.particles = [];
            this.animating = false;
        }

        initCanvas() {
            if (this.canvas) return;
            this.canvas = document.createElement('canvas');
            this.canvas.id = 'ha-confetti-canvas';
            this.canvas.style.position = 'fixed';
            this.canvas.style.top = '0';
            this.canvas.style.left = '0';
            this.canvas.style.width = '100vw';
            this.canvas.style.height = '100vh';
            this.canvas.style.pointerEvents = 'none';
            this.canvas.style.zIndex = '9999999';
            document.body.appendChild(this.canvas);
            this.ctx = this.canvas.getContext('2d');
            this.resize();
            window.addEventListener('resize', () => this.resize());
        }

        resize() {
            if (!this.canvas) return;
            this.canvas.width = window.innerWidth;
            this.canvas.height = window.innerHeight;
        }

        burst(count = 80) {
            this.initCanvas();
            const colors = ['#f59e0b', '#3b82f6', '#10b981', '#ec4899', '#8b5cf6', '#ef4444'];
            const centerX = this.canvas.width / 2;
            const centerY = this.canvas.height * 0.4;

            for (let i = 0; i < count; i++) {
                const angle = Math.random() * Math.PI * 2;
                const speed = 4 + Math.random() * 8;
                this.particles.push({
                    x: centerX,
                    y: centerY,
                    vx: Math.cos(angle) * speed,
                    vy: Math.sin(angle) * speed - 3,
                    radius: 3 + Math.random() * 4,
                    color: colors[Math.floor(Math.random() * colors.length)],
                    alpha: 1,
                    decay: 0.01 + Math.random() * 0.015,
                    gravity: 0.18,
                });
            }

            if (!this.animating) {
                this.animating = true;
                this.loop();
            }
        }

        loop() {
            if (!this.particles.length) {
                this.animating = false;
                if (this.ctx) this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
                return;
            }

            this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);

            for (let i = this.particles.length - 1; i >= 0; i--) {
                const p = this.particles[i];
                p.x += p.vx;
                p.y += p.vy;
                p.vy += p.gravity;
                p.alpha -= p.decay;

                if (p.alpha <= 0) {
                    this.particles.splice(i, 1);
                    continue;
                }

                this.ctx.save();
                this.ctx.globalAlpha = p.alpha;
                this.ctx.fillStyle = p.color;
                this.ctx.beginPath();
                this.ctx.arc(p.x, p.y, p.radius, 0, Math.PI * 2);
                this.ctx.fill();
                this.ctx.restore();
            }

            requestAnimationFrame(() => this.loop());
        }

        toast(title, text, icon = '🎉') {
            let container = document.querySelector('.ha-toast-container');
            if (!container) {
                container = document.createElement('div');
                container.className = 'ha-toast-container';
                document.body.appendChild(container);
            }

            const toast = document.createElement('div');
            toast.className = 'ha-toast';
            toast.innerHTML = `
                <span style="font-size: 24px;">${icon}</span>
                <div>
                    <strong style="display:block; font-size: 14px;">${title}</strong>
                    <span style="font-size: 12px; color: #cbd5e1;">${text}</span>
                </div>
            `;

            container.appendChild(toast);

            setTimeout(() => {
                toast.style.transition = 'opacity 0.4s, transform 0.4s';
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(10px)';
                setTimeout(() => toast.remove(), 400);
            }, 4500);
        }
    }

    window.HA_Celebration = new CelebrationEngine();
})();
