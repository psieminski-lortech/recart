/**
 * ReCart AI - Exit-Intent Popup with FingerprintJS
 *
 * Handles exit-intent detection (desktop + mobile),
 * fingerprint generation, session tracking, and form submission.
 *
 * @package ReCart_AI
 * @version 1.0.0
 */

(function () {
    'use strict';

    // Bail if config not available
    if (typeof recartAiConfig === 'undefined') {
        return;
    }

    const config = recartAiConfig;

    // Don't show on checkout or thank-you pages
    if (config.isCheckout || config.isThankYou) {
        return;
    }

    // Don't show if popup disabled
    if (!config.popupEnabled) {
        return;
    }

    // Don't show if cart is empty
    if (!config.cartItems || config.cartItems.length === 0) {
        return;
    }

    // State
    let fingerprintId = null;
    let sessionStartTime = Date.now();
    let popupShown = false;
    let popupDismissed = false;
    let sessionTimeReported = 0;

    // Session storage key to prevent showing popup multiple times
    const STORAGE_KEY = 'recart_ai_popup_shown';
    const SESSION_KEY = 'recart_ai_session_time';

    /**
     * Initialize FingerprintJS and get visitor ID.
     */
    async function initFingerprint() {
        if (config.gdprMode) {
            // In GDPR mode, use a simple session-based ID
            fingerprintId = getSessionId();
            return;
        }

        try {
            if (typeof FingerprintJS !== 'undefined') {
                const fp = await FingerprintJS.load();
                const result = await fp.get();
                fingerprintId = result.visitorId;
            } else {
                // Fallback if FingerprintJS fails to load
                fingerprintId = generateFallbackId();
            }
        } catch (e) {
            console.warn('ReCart AI: FingerprintJS initialization failed, using fallback.');
            fingerprintId = generateFallbackId();
        }
    }

    /**
     * Generate a fallback ID using available browser signals.
     */
    function generateFallbackId() {
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        ctx.textBaseline = 'top';
        ctx.font = '14px Arial';
        ctx.fillText('ReCartAI', 2, 2);
        const canvasData = canvas.toDataURL();

        const signals = [
            navigator.userAgent,
            navigator.language,
            screen.width + 'x' + screen.height,
            screen.colorDepth,
            new Date().getTimezoneOffset(),
            canvasData.substring(0, 100)
        ].join('|');

        return hashString(signals);
    }

    /**
     * Simple hash function for fallback ID.
     */
    function hashString(str) {
        let hash = 0;
        for (let i = 0; i < str.length; i++) {
            const char = str.charCodeAt(i);
            hash = ((hash << 5) - hash) + char;
            hash = hash & hash;
        }
        return 'fb_' + Math.abs(hash).toString(36);
    }

    /**
     * Get or create a session ID.
     */
    function getSessionId() {
        let sessionId = sessionStorage.getItem('recart_ai_session');
        if (!sessionId) {
            sessionId = 'sess_' + Date.now().toString(36) + '_' + Math.random().toString(36).substring(2, 8);
            sessionStorage.setItem('recart_ai_session', sessionId);
        }
        return sessionId;
    }

    /**
     * Track session time and report to backend.
     */
    function trackSessionTime() {
        setInterval(function () {
            const elapsed = Math.floor((Date.now() - sessionStartTime) / 1000);
            const toReport = elapsed - sessionTimeReported;

            if (toReport >= 10 && fingerprintId) {
                reportSessionTime(toReport);
                sessionTimeReported = elapsed;
            }
        }, 10000); // Every 10 seconds
    }

    /**
     * Report session time to backend.
     */
    function reportSessionTime(seconds) {
        const formData = new FormData();
        formData.append('action', 'recart_ai_update_session');
        formData.append('nonce', config.nonce);
        formData.append('fingerprint_id', fingerprintId);
        formData.append('seconds', seconds);

        fetch(config.ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        }).catch(function () {
            // Silent fail
        });
    }

    /**
     * Setup exit-intent detection for desktop.
     */
    function setupDesktopExitIntent() {
        let lastY = 0;
        let exitIntentTriggered = false;

        document.addEventListener('mousemove', function (e) {
            // Detect cursor moving toward top of viewport (exit intent)
            if (e.clientY < 50 && lastY > e.clientY && !exitIntentTriggered && !popupShown && !popupDismissed) {
                exitIntentTriggered = true;
                triggerPopup('exit_intent_desktop');
            }
            lastY = e.clientY;
        });

        // Also detect tab switching (visibility change)
        document.addEventListener('visibilitychange', function () {
            if (document.hidden && !popupShown && !popupDismissed) {
                // Will show when user returns
                document.addEventListener('visibilitychange', function onReturn() {
                    if (!document.hidden && !popupShown && !popupDismissed) {
                        triggerPopup('tab_return');
                        document.removeEventListener('visibilitychange', onReturn);
                    }
                });
            }
        });
    }

    /**
     * Setup exit-intent detection for mobile.
     */
    function setupMobileExitIntent() {
        if (!config.mobileEnabled) {
            return;
        }

        let touchStartY = 0;
        let scrollTop = 0;

        // Detect back button behavior (scroll to top rapidly)
        document.addEventListener('touchstart', function (e) {
            touchStartY = e.touches[0].clientY;
            scrollTop = window.pageYOffset;
        });

        document.addEventListener('touchend', function (e) {
            const touchEndY = e.changedTouches[0].clientY;
            const deltaY = touchEndY - touchStartY;

            // Rapid scroll up (potential back gesture)
            if (deltaY > 150 && scrollTop < 100 && !popupShown && !popupDismissed) {
                triggerPopup('exit_intent_mobile');
            }
        });

        // Detect when user has been idle for a while then starts scrolling up
        let idleTimer = null;
        let isIdle = false;

        function resetIdleTimer() {
            clearTimeout(idleTimer);
            isIdle = false;
            idleTimer = setTimeout(function () {
                isIdle = true;
            }, 15000); // 15 seconds of inactivity
        }

        document.addEventListener('touchstart', resetIdleTimer);
        document.addEventListener('scroll', function () {
            if (isIdle && window.pageYOffset < 200 && !popupShown && !popupDismissed) {
                triggerPopup('exit_intent_mobile_idle');
            }
        });

        resetIdleTimer();
    }

    /**
     * Trigger the popup display.
     */
    function triggerPopup(trigger) {
        // Check session storage to prevent repeat showing
        if (sessionStorage.getItem(STORAGE_KEY)) {
            return;
        }

        // Apply delay if configured
        const delay = config.popupDelay * 1000;

        setTimeout(function () {
            showPopup(trigger);
        }, delay);
    }

    /**
     * Show the popup.
     */
    function showPopup(trigger) {
        if (popupShown || popupDismissed) {
            return;
        }

        popupShown = true;
        sessionStorage.setItem(STORAGE_KEY, '1');

        const overlay = document.getElementById('recart-ai-popup-overlay');
        if (!overlay) {
            return;
        }

        // Set texts
        document.getElementById('recart-ai-title').textContent = config.texts.title;
        document.getElementById('recart-ai-message').textContent = config.texts.message;
        document.getElementById('recart-ai-button-text').textContent = config.texts.button;
        document.getElementById('recart-ai-dismiss').textContent = config.texts.close;
        document.getElementById('recart-ai-privacy-text').textContent = config.texts.privacy;

        // Set colors
        const popup = document.getElementById('recart-ai-popup');
        popup.style.setProperty('--recart-primary', config.colors.primary);
        popup.style.setProperty('--recart-bg', config.colors.bg);
        popup.style.setProperty('--recart-text', config.colors.text);

        // Show phone field if enabled
        if (config.showPhone) {
            document.getElementById('recart-ai-phone-field').style.display = 'block';
        }

        // Show cart products if enabled
        if (config.showProducts && config.cartItems.length > 0) {
            renderProducts();
        }

        // Show overlay with animation
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');

        requestAnimationFrame(function () {
            overlay.classList.add('recart-ai-visible');
        });

        // Track popup shown event
        trackEvent('popup_shown', { trigger: trigger });

        // Prevent body scroll
        document.body.style.overflow = 'hidden';
    }

    /**
     * Render cart products in popup.
     */
    function renderProducts() {
        const container = document.getElementById('recart-ai-products');
        if (!container) return;

        const maxProducts = 3;
        const items = config.cartItems.slice(0, maxProducts);

        let html = '<div class="recart-ai-products-list">';
        items.forEach(function (item) {
            html += '<div class="recart-ai-product-item">';
            html += '<img src="' + escapeHtml(item.image) + '" alt="' + escapeHtml(item.name) + '" class="recart-ai-product-img" />';
            html += '<div class="recart-ai-product-info">';
            html += '<span class="recart-ai-product-name">' + escapeHtml(item.name) + '</span>';
            html += '<span class="recart-ai-product-price">' + item.quantity + ' × ' + formatPrice(item.price) + '</span>';
            html += '</div>';
            html += '</div>';
        });

        if (config.cartItems.length > maxProducts) {
            html += '<div class="recart-ai-more-items">+' + (config.cartItems.length - maxProducts) + ' ' + 'więcej</div>';
        }

        html += '</div>';
        html += '<div class="recart-ai-cart-total"><strong>Razem: ' + formatPrice(config.cartTotal) + '</strong></div>';

        container.innerHTML = html;
    }

    /**
     * Format price with currency.
     */
    function formatPrice(price) {
        return parseFloat(price).toFixed(2) + ' ' + config.currency;
    }

    /**
     * Escape HTML entities.
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Hide the popup.
     */
    function hidePopup() {
        const overlay = document.getElementById('recart-ai-popup-overlay');
        if (!overlay) return;

        overlay.classList.remove('recart-ai-visible');
        overlay.setAttribute('aria-hidden', 'true');

        setTimeout(function () {
            overlay.style.display = 'none';
        }, 300);

        document.body.style.overflow = '';
        popupDismissed = true;
    }

    /**
     * Handle form submission.
     */
    async function handleSubmit(e) {
        e.preventDefault();

        const emailInput = document.getElementById('recart-ai-email');
        const phoneInput = document.getElementById('recart-ai-phone');
        const submitBtn = document.getElementById('recart-ai-submit');
        const spinner = document.getElementById('recart-ai-spinner');
        const buttonText = document.getElementById('recart-ai-button-text');

        const email = emailInput.value.trim();
        const phone = phoneInput ? phoneInput.value.trim() : '';

        // Validate email
        if (!email || !isValidEmail(email)) {
            emailInput.classList.add('recart-ai-error');
            emailInput.focus();
            return;
        }

        emailInput.classList.remove('recart-ai-error');

        // Show loading state
        submitBtn.disabled = true;
        spinner.style.display = 'inline-block';
        buttonText.style.opacity = '0.5';

        try {
            const formData = new FormData();
            formData.append('action', 'recart_ai_submit_popup');
            formData.append('nonce', config.nonce);
            formData.append('fingerprint_id', fingerprintId);
            formData.append('email', email);
            formData.append('phone', phone);
            formData.append('cart_total', config.cartTotal);
            formData.append('session_id', getSessionId());

            const response = await fetch(config.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });

            const data = await response.json();

            if (data.success) {
                showSuccess(data.data);
            } else {
                showSuccess({ message: config.texts.success });
            }
        } catch (error) {
            console.error('ReCart AI: Form submission error', error);
            showSuccess({ message: config.texts.success });
        } finally {
            submitBtn.disabled = false;
            spinner.style.display = 'none';
            buttonText.style.opacity = '1';
        }
    }

    /**
     * Show success state.
     */
    function showSuccess(data) {
        const formState = document.getElementById('recart-ai-form-state');
        const successState = document.getElementById('recart-ai-success-state');
        const successMessage = document.getElementById('recart-ai-success-message');
        const codeDisplay = document.getElementById('recart-ai-code-display');
        const codeElement = document.getElementById('recart-ai-code');

        formState.style.display = 'none';
        successState.style.display = 'block';

        if (data.code) {
            // Show discount code
            if (data.discount_type === 'free_delivery') {
                successMessage.textContent = config.texts.freeDelivery;
            } else {
                successMessage.textContent = config.texts.codeSuccess;
            }
            codeElement.textContent = data.code;
            codeDisplay.style.display = 'flex';
        } else if (data.action === 'show_contact_message') {
            successMessage.textContent = config.texts.contactUs;
        } else {
            successMessage.textContent = data.message || config.texts.success;
        }

        // Auto-close after 8 seconds
        setTimeout(hidePopup, 8000);
    }

    /**
     * Validate email format.
     */
    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    /**
     * Track event via AJAX.
     */
    function trackEvent(eventType, eventData) {
        const formData = new FormData();
        formData.append('action', 'recart_ai_track_event');
        formData.append('nonce', config.nonce);
        formData.append('fingerprint_id', fingerprintId || '');
        formData.append('event_type', eventType);
        formData.append('event_data', JSON.stringify(eventData || {}));

        fetch(config.ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        }).catch(function () {
            // Silent fail
        });
    }

    /**
     * Copy code to clipboard.
     */
    function copyCode() {
        const code = document.getElementById('recart-ai-code');
        if (!code) return;

        navigator.clipboard.writeText(code.textContent).then(function () {
            const btn = document.getElementById('recart-ai-copy-code');
            btn.textContent = '✓';
            setTimeout(function () {
                btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>';
            }, 2000);
        });
    }

    /**
     * Bind event listeners.
     */
    function bindEvents() {
        // Close button
        const closeBtn = document.getElementById('recart-ai-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', function () {
                hidePopup();
                trackEvent('popup_closed', { method: 'close_button' });
            });
        }

        // Dismiss link
        const dismissBtn = document.getElementById('recart-ai-dismiss');
        if (dismissBtn) {
            dismissBtn.addEventListener('click', function () {
                hidePopup();
                trackEvent('popup_dismissed', { method: 'dismiss_link' });
            });
        }

        // Overlay click
        const overlay = document.getElementById('recart-ai-popup-overlay');
        if (overlay) {
            overlay.addEventListener('click', function (e) {
                if (e.target === overlay) {
                    hidePopup();
                    trackEvent('popup_closed', { method: 'overlay_click' });
                }
            });
        }

        // Form submit
        const form = document.getElementById('recart-ai-form');
        if (form) {
            form.addEventListener('submit', handleSubmit);
        }

        // Copy code button
        const copyBtn = document.getElementById('recart-ai-copy-code');
        if (copyBtn) {
            copyBtn.addEventListener('click', copyCode);
        }

        // Escape key
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && popupShown) {
                hidePopup();
                trackEvent('popup_closed', { method: 'escape_key' });
            }
        });

        // Email input validation feedback
        const emailInput = document.getElementById('recart-ai-email');
        if (emailInput) {
            emailInput.addEventListener('input', function () {
                this.classList.remove('recart-ai-error');
            });
        }
    }

    /**
     * Detect if device is mobile.
     */
    function isMobile() {
        return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)
            || window.innerWidth < 768;
    }

    /**
     * Initialize the plugin.
     */
    async function init() {
        // Wait for DOM
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', setup);
        } else {
            setup();
        }
    }

    async function setup() {
        // Initialize fingerprint
        await initFingerprint();

        // Bind events
        bindEvents();

        // Start session tracking
        trackSessionTime();

        // Register fingerprint with backend
        if (fingerprintId) {
            const formData = new FormData();
            formData.append('action', 'recart_ai_register_visit');
            formData.append('nonce', config.nonce);
            formData.append('fingerprint_id', fingerprintId);
            formData.append('cart_total', config.cartTotal);

            fetch(config.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            }).catch(function () {});
        }

        // Setup exit-intent detection based on device
        if (isMobile()) {
            setupMobileExitIntent();
        } else {
            setupDesktopExitIntent();
        }
    }

    // Start
    init();

})();
