/**
 * HA Achievement API - Frontend Interactive Logic
 *
 * @package HA_Achievement_API
 */

document.addEventListener('DOMContentLoaded', () => {
    // 0. Dropdown Drawer Toggle (Kompakte Seitenleisten-Ansicht)
    const toggleDashboardDrawer = (container) => {
        if (!container) return;
        const isExpanded = container.classList.toggle('is-expanded');
        const btn = container.querySelector('.ha-dropdown-toggle');
        if (btn) {
            btn.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
            const textSpan = btn.querySelector('.ha-toggle-text');
            if (textSpan) {
                textSpan.textContent = isExpanded 
                    ? (btn.dataset.collapseText || 'Schließen ▴') 
                    : (btn.dataset.expandText || 'Aufgaben ▾');
            }
        }
    };

    const dropdownToggles = document.querySelectorAll('.ha-dropdown-toggle');
    dropdownToggles.forEach((btn) => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            toggleDashboardDrawer(btn.closest('.ha-missions-container'));
        });
    });

    const focusBoxes = document.querySelectorAll('.ha-teaser-focus');
    focusBoxes.forEach((box) => {
        box.addEventListener('click', (e) => {
            // Falls innerhalb des Focus-Boxen kein Link geklickt wurde:
            if (e.target.closest('a')) return;
            toggleDashboardDrawer(box.closest('.ha-missions-container'));
        });
        box.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                toggleDashboardDrawer(box.closest('.ha-missions-container'));
            }
        });
    });

    // 1. Akkordeon-Steuerung für Missionen
    const chapterHeaders = document.querySelectorAll('.ha-chapter-header');

    chapterHeaders.forEach((header) => {
        header.addEventListener('click', () => {
            const block = header.closest('.ha-chapter-block');
            if (block) {
                block.classList.toggle('is-open');
            }
        });
    });

    // Erstes nicht-vollendetes Kapitel standardmäßig öffnen
    const firstPending = document.querySelector('.ha-chapter-block:not(.is-complete)');
    if (firstPending) {
        firstPending.classList.add('is-open');
    } else {
        const first = document.querySelector('.ha-chapter-block');
        if (first) first.classList.add('is-open');
    }

    // 2. Filter-Buttons
    const filterButtons = document.querySelectorAll('.ha-filter-btn');
    filterButtons.forEach((btn) => {
        btn.addEventListener('click', () => {
            filterButtons.forEach((b) => b.classList.remove('active'));
            btn.classList.add('active');

            const filter = btn.dataset.filter; // 'all', 'pending', 'completed'
            const taskItems = document.querySelectorAll('.ha-task-item');

            taskItems.forEach((item) => {
                const status = item.dataset.status; // 'pending' oder 'completed'

                if (filter === 'all') {
                    item.style.display = 'flex';
                } else if (filter === status) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    });

    // 3. Globale Schnittstelle für Event-Benachrichtigungen
    window.addEventListener('ha:achievement:toast', (e) => {
        if (window.HA_Celebration && e.detail) {
            window.HA_Celebration.burst(60);
            window.HA_Celebration.toast(e.detail.title || 'Glückwunsch!', e.detail.text || '', e.detail.icon || '🎉');
        }
    });
});
