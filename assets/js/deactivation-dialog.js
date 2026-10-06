/**
 * HA Achievement API: Deactivation Dialog Handler
 * Intercepts deactivation clicks on wp-admin/plugins.php to allow the administrator
 * to set the uninstall data retention policy.
 */
(function() {
    'use strict';

    const config = window.haAchievementDeactivateConfig;
    if (!config) {
        return;
    }

    let modalOverlay = null;
    let pendingDeactivateUrl = '';

    function createModal() {
        const overlay = document.createElement('div');
        overlay.id = 'ha-achievement-deactivate-modal-overlay';
        overlay.className = 'ha-deactivate-overlay';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-labelledby', 'ha-achievement-deactivate-modal-title');

        const currentVal = String(config.currentSetting || '0');

        overlay.innerHTML = `
            <div class="ha-deactivate-modal">
                <div class="ha-deactivate-header">
                    <h3 class="ha-deactivate-title" id="ha-achievement-deactivate-modal-title">
                        <span class="ha-deactivate-title-icon">🏆</span>
                        <span>${config.i18n.modalTitle}</span>
                    </h3>
                    <button type="button" class="ha-deactivate-close" aria-label="${config.i18n.closeBtn}">✕</button>
                </div>
                <div class="ha-deactivate-body">
                    <p class="ha-deactivate-intro">
                        ${config.i18n.introText}
                    </p>

                    <div class="ha-deactivate-options">
                        <label class="ha-deactivate-option ha-option-safe ${currentVal !== '1' ? 'is-selected' : ''}">
                            <input type="radio" name="ha_achievement_uninstall_choice" value="0" ${currentVal !== '1' ? 'checked' : ''}>
                            <div class="ha-option-text">
                                <div class="ha-option-head">
                                    <span class="ha-option-title">${config.i18n.keepTitle}</span>
                                    <span class="ha-option-badge ha-badge-safe">${config.i18n.recommendedBadge}</span>
                                </div>
                                <div class="ha-option-desc">${config.i18n.keepDesc}</div>
                            </div>
                        </label>

                        <label class="ha-deactivate-option ha-option-danger ${currentVal === '1' ? 'is-selected' : ''}">
                            <input type="radio" name="ha_achievement_uninstall_choice" value="1" ${currentVal === '1' ? 'checked' : ''}>
                            <div class="ha-option-text">
                                <div class="ha-option-head">
                                    <span class="ha-option-title">${config.i18n.deleteTitle}</span>
                                    <span class="ha-option-badge ha-badge-danger">${config.i18n.dangerBadge}</span>
                                </div>
                                <div class="ha-option-desc">${config.i18n.deleteDesc}</div>
                            </div>
                        </label>
                    </div>

                    <div class="ha-deactivate-notice">
                        ${config.i18n.hintText}
                    </div>
                </div>
                <div class="ha-deactivate-footer">
                    <button type="button" class="button button-secondary ha-deactivate-btn-cancel">${config.i18n.cancelBtn}</button>
                    <button type="button" class="button button-primary ha-deactivate-btn-confirm">
                        <span class="ha-btn-text">${config.i18n.confirmBtn}</span>
                        <span class="ha-btn-spinner" style="display: none;">⏳</span>
                    </button>
                </div>
            </div>
        `;

        document.body.appendChild(overlay);

        const closeBtn = overlay.querySelector('.ha-deactivate-close');
        const cancelBtn = overlay.querySelector('.ha-deactivate-btn-cancel');
        const confirmBtn = overlay.querySelector('.ha-deactivate-btn-confirm');
        const radios = overlay.querySelectorAll('input[name="ha_achievement_uninstall_choice"]');
        const optionCards = overlay.querySelectorAll('.ha-deactivate-option');

        radios.forEach(function(radio) {
            radio.addEventListener('change', function() {
                optionCards.forEach(function(card) {
                    card.classList.remove('is-selected');
                });
                const parent = radio.closest('.ha-deactivate-option');
                if (parent) {
                    parent.classList.add('is-selected');
                }
            });
        });

        function closeModal() {
            overlay.classList.remove('is-active');
            pendingDeactivateUrl = '';
        }

        closeBtn.addEventListener('click', closeModal);
        cancelBtn.addEventListener('click', closeModal);

        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) {
                closeModal();
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && overlay.classList.contains('is-active')) {
                closeModal();
            }
        });

        confirmBtn.addEventListener('click', function() {
            const selectedRadio = overlay.querySelector('input[name="ha_achievement_uninstall_choice"]:checked');
            const deleteData = selectedRadio ? selectedRadio.value : '0';

            confirmBtn.disabled = true;
            cancelBtn.disabled = true;
            closeBtn.disabled = true;

            const btnText = confirmBtn.querySelector('.ha-btn-text');
            const btnSpinner = confirmBtn.querySelector('.ha-btn-spinner');
            if (btnText) {
                btnText.textContent = config.i18n.savingText;
            }
            if (btnSpinner) {
                btnSpinner.style.display = 'inline-block';
            }

            function proceed() {
                if (pendingDeactivateUrl) {
                    window.location.href = pendingDeactivateUrl;
                } else {
                    closeModal();
                }
            }

            const fallbackTimer = setTimeout(proceed, 2500);

            const formData = new FormData();
            formData.append('action', config.action);
            formData.append('nonce', config.nonce);
            formData.append('delete_data', deleteData);

            fetch(config.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(function(res) {
                return res.json();
            })
            .then(function() {
                clearTimeout(fallbackTimer);
                proceed();
            })
            .catch(function(err) {
                console.warn('HA Achievement deactivate save notice:', err);
                clearTimeout(fallbackTimer);
                proceed();
            });
        });

        return overlay;
    }

    function openModal(deactivateUrl) {
        if (!modalOverlay) {
            modalOverlay = createModal();
        }
        pendingDeactivateUrl = deactivateUrl;
        window.requestAnimationFrame(function() {
            modalOverlay.classList.add('is-active');
        });
    }

    function init() {
        document.body.addEventListener('click', function(e) {
            const link = e.target.closest('a');
            if (!link) {
                return;
            }

            const isDeactivate = link.closest('.deactivate') || (link.href && link.href.indexOf('action=deactivate') !== -1);
            if (!isDeactivate) {
                return;
            }

            const tr = link.closest('tr');
            const slugMatch = tr && (
                tr.getAttribute('data-slug') === config.slug ||
                (tr.getAttribute('data-plugin') && tr.getAttribute('data-plugin').indexOf(config.slug) !== -1)
            );
            const urlMatch = link.href && (
                link.href.indexOf(encodeURIComponent(config.pluginFile)) !== -1 ||
                link.href.indexOf(config.slug) !== -1
            );

            if (slugMatch || urlMatch) {
                e.preventDefault();
                e.stopPropagation();
                openModal(link.href);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
