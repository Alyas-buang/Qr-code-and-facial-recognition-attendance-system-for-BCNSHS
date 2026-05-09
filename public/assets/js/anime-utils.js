/**
 * Anime.js Animation Utilities
 * Enhanced Frontend Animations for DaisyUI Components
 * April 2026
 */

window.AnimeUtils = (() => {
    const animeReady = typeof anime !== 'undefined';

    if (!animeReady) {
        console.warn('anime.js not loaded. Some animations will be disabled.');
    }

    // Animation Presets
    const presets = {
        // Fade animations
        fadeIn: {
            duration: 500,
            opacity: [0, 1],
            easing: 'easeInOutQuad'
        },
        fadeOut: {
            duration: 500,
            opacity: [1, 0],
            easing: 'easeInOutQuad'
        },

        // Slide animations
        slideInLeft: {
            duration: 600,
            translateX: [-50, 0],
            opacity: [0, 1],
            easing: 'easeOutCubic'
        },
        slideInRight: {
            duration: 600,
            translateX: [50, 0],
            opacity: [0, 1],
            easing: 'easeOutCubic'
        },
        slideInUp: {
            duration: 600,
            translateY: [50, 0],
            opacity: [0, 1],
            easing: 'easeOutCubic'
        },
        slideInDown: {
            duration: 600,
            translateY: [-50, 0],
            opacity: [0, 1],
            easing: 'easeOutCubic'
        },

        // Bounce animations
        bounce: {
            duration: 800,
            translateY: [0, -20, 0],
            easing: 'easeInOutQuad'
        },

        // Scale animations
        scaleIn: {
            duration: 500,
            scale: [0.8, 1],
            opacity: [0, 1],
            easing: 'easeOutCubic'
        },
        scaleOut: {
            duration: 500,
            scale: [1, 0.8],
            opacity: [1, 0],
            easing: 'easeInCubic'
        },

        // Rotate animations
        rotateIn: {
            duration: 600,
            rotate: [-45, 0],
            opacity: [0, 1],
            easing: 'easeOutCubic'
        },

        // Pulse animation
        pulse: {
            duration: 1500,
            opacity: [1, 0.5, 1],
            easing: 'easeInOutQuad',
            loop: true
        },

        // Glow effect
        glow: {
            duration: 1500,
            boxShadow: [
                '0 0 0 0 rgba(37, 99, 235, 0.7)',
                '0 0 0 10px rgba(37, 99, 235, 0)',
                '0 0 0 0 rgba(37, 99, 235, 0)'
            ],
            easing: 'easeOutQuad',
            loop: true
        },

        // Wiggle
        wiggle: {
            duration: 400,
            rotate: [0, -2, 2, -2, 2, 0],
            easing: 'easeInOutQuad'
        }
    };

    /**
     * Apply animation to element(s)
     * @param {string|Element} target - CSS selector or element
     * @param {string|object} animation - Animation preset name or custom config
     * @param {object} options - Additional anime options
     * @returns {object} anime instance or null
     */
    function animate(target, animation = 'fadeIn', options = {}) {
        if (!animeReady) return null;

        const preset = typeof animation === 'string' ? presets[animation] : animation;
        if (!preset && typeof animation === 'string') {
            console.warn(`Animation preset '${animation}' not found`);
            return null;
        }

        const config = {
            targets: target,
            ...preset,
            ...options
        };

        return anime(config);
    }

    /**
     * Animate elements in sequence (stagger)
     * @param {string|Element[]} target - CSS selector or elements
     * @param {string|object} animation - Animation preset or config
     * @param {number} delay - Delay between elements (ms)
     * @param {object} options - Additional anime options
     */
    function stagger(target, animation = 'slideInUp', delay = 50, options = {}) {
        if (!animeReady) return null;

        const preset = typeof animation === 'string' ? presets[animation] : animation;
        const config = {
            targets: target,
            ...preset,
            delay: anime.stagger(delay),
            ...options
        };

        return anime(config);
    }

    /**
     * Animate card entrance
     * @param {string|Element} target - CSS selector or element
     * @param {object} options - Additional anime options
     */
    function animateCard(target, options = {}) {
        return animate(target, {
            duration: 800,
            opacity: [0, 1],
            translateY: [30, 0],
            scale: [0.95, 1],
            easing: 'easeOutCubic'
        }, options);
    }

    /**
     * Animate button click/loading state
     * @param {string|Element} button - Button element
     * @param {string} text - Loading text
     * @returns {object} anime instance
     */
    function animateButtonLoading(button, text = 'Loading...') {
        if (!animeReady) return null;

        const originalText = button.textContent;
        button.disabled = true;
        button.textContent = text;

        return animate(button, {
            duration: 600,
            opacity: [0.7, 1],
            scale: [0.98, 1],
            easing: 'easeInOutQuad'
        });
    }

    /**
     * Animate button success state
     * @param {string|Element} button - Button element
     * @param {string} successText - Success message
     * @param {number} duration - How long to show success (ms)
     */
    function animateButtonSuccess(button, successText = 'Success!', duration = 2000) {
        if (!animeReady) return null;

        const originalText = button.textContent;
        button.textContent = successText;
        button.classList.add('btn-success');

        anime({
            targets: button,
            duration: 300,
            opacity: [0.8, 1],
            scale: [0.95, 1],
            easing: 'easeOutCubic'
        });

        setTimeout(() => {
            button.textContent = originalText;
            button.classList.remove('btn-success');
            button.disabled = false;
        }, duration);
    }

    /**
     * Animate modal entrance
     * @param {string|Element} modal - Modal element or selector
     * @param {object} options - Additional anime options
     */
    function animateModalOpen(modal, options = {}) {
        if (!animeReady) return null;

        const backdrop = document.querySelector('.modal-backdrop') || 
                        (modal.parentElement?.querySelector('.modal-backdrop'));

        const backdropAnim = backdrop ? animate(backdrop, {
            duration: 400,
            opacity: [0, 1],
            easing: 'easeOutQuad'
        }) : null;

        const modalAnim = animate(modal, {
            duration: 500,
            opacity: [0, 1],
            scale: [0.85, 1],
            translateY: [-30, 0],
            easing: 'easeOutCubic'
        }, options);

        return { backdrop: backdropAnim, modal: modalAnim };
    }

    /**
     * Animate modal exit
     * @param {string|Element} modal - Modal element or selector
     * @param {object} options - Additional anime options
     */
    function animateModalClose(modal, options = {}) {
        if (!animeReady) return null;

        const backdrop = document.querySelector('.modal-backdrop') || 
                        (modal.parentElement?.querySelector('.modal-backdrop'));

        const backdropAnim = backdrop ? animate(backdrop, {
            duration: 300,
            opacity: [1, 0],
            easing: 'easeInQuad'
        }) : null;

        const modalAnim = animate(modal, {
            duration: 400,
            opacity: [1, 0],
            scale: [1, 0.85],
            translateY: [0, -30],
            easing: 'easeInCubic'
        }, options);

        return { backdrop: backdropAnim, modal: modalAnim };
    }

    /**
     * Animate alert/notification entrance
     * @param {string|Element} alert - Alert element or selector
     * @param {object} options - Additional anime options
     */
    function animateAlert(alert, options = {}) {
        return stagger(alert, {
            duration: 500,
            opacity: [0, 1],
            translateX: [-20, 0],
            easing: 'easeOutCubic'
        }, 50, options);
    }

    /**
     * Animate form input focus
     * @param {string|Element} input - Input element or selector
     */
    function animateInputFocus(input) {
        return animate(input, {
            duration: 300,
            scale: [1, 1.02],
            boxShadow: '0 0 0 3px rgba(37, 99, 235, 0.1)',
            easing: 'easeOutCubic'
        });
    }

    /**
     * Animate number counter (for stats)
     * @param {string|Element} target - Element containing number
     * @param {number} start - Start value
     * @param {number} end - End value
     * @param {object} options - Additional anime options
     */
    function animateCounter(target, start, end, options = {}) {
        if (!animeReady) return null;

        return anime({
            targets: { value: start },
            value: end,
            duration: 2000,
            round: 1,
            easing: 'easeInOutExpo',
            update(anim) {
                const element = typeof target === 'string' 
                    ? document.querySelector(target) 
                    : target;
                if (element) {
                    element.textContent = Math.round(anim.progress * (end - start)) + start;
                }
            },
            ...options
        });
    }

    /**
     * Animate progress bar
     * @param {string|Element} progressBar - Progress bar element or selector
     * @param {number} percentage - Target percentage (0-100)
     * @param {object} options - Additional anime options
     */
    function animateProgress(progressBar, percentage, options = {}) {
        return animate(progressBar, {
            duration: 1000,
            width: `${percentage}%`,
            easing: 'easeOutCubic',
            ...options
        });
    }

    /**
     * Create a timeline of animations
     * @returns {object} anime timeline
     */
    function createTimeline(options = {}) {
        if (!animeReady) return null;
        return anime.timeline(options);
    }

    /**
     * Stop all animations
     */
    function stopAll() {
        if (animeReady) anime.set('*', {});
    }

    /**
     * Get all animation presets
     * @returns {object} presets object
     */
    function getPresets() {
        return presets;
    }

    /**
     * Check if anime.js is ready
     * @returns {boolean}
     */
    function isReady() {
        return animeReady;
    }

    function prefersReducedMotion() {
        return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }

    function animateDashboardStats() {
        const statNumbers = document.querySelectorAll('.stats-grid .text-3xl.font-bold');
        statNumbers.forEach((el, index) => {
            const rawText = (el.textContent || '').replace(/,/g, '').trim();
            const end = Number(rawText);
            if (Number.isFinite(end) && end >= 0) {
                anime({
                    targets: { value: 0 },
                    value: end,
                    duration: 1000 + index * 120,
                    easing: 'easeOutExpo',
                    round: 1,
                    update(anim) {
                        el.textContent = Math.round(anim.animations[0].currentValue).toLocaleString();
                    }
                });
            }
        });
    }

    function initPageAnimations() {
        if (!animeReady || prefersReducedMotion()) return;

        const body = document.body;
        if (!body) return;

        anime({
            targets: 'main, .container, .app-container, .home-card, .login-card',
            opacity: [0, 1],
            translateY: [16, 0],
            duration: 700,
            easing: 'easeOutCubic'
        });

        anime({
            targets: '.navbar',
            opacity: [0, 1],
            translateY: [-12, 0],
            duration: 550,
            easing: 'easeOutQuad'
        });

        anime({
            targets: '.card',
            opacity: [0, 1],
            translateY: [24, 0],
            scale: [0.985, 1],
            delay: anime.stagger(60, { start: 70 }),
            duration: 760,
            easing: 'easeOutCubic'
        });

        anime({
            targets: '.btn',
            opacity: [0, 1],
            translateY: [10, 0],
            delay: anime.stagger(35, { start: 160 }),
            duration: 480,
            easing: 'easeOutQuad'
        });

        anime({
            targets: '.table tbody tr',
            opacity: [0, 1],
            translateX: [-10, 0],
            delay: anime.stagger(20, { start: 210 }),
            duration: 420,
            easing: 'easeOutSine'
        });

        anime({
            targets: '#progress-bar',
            duration: 900,
            easing: 'easeOutExpo',
            boxShadow: [
                '0 0 0 rgba(56, 189, 248, 0)',
                '0 0 20px rgba(56, 189, 248, 0.45)',
                '0 0 0 rgba(56, 189, 248, 0)'
            ],
            loop: true
        });

        if (body.classList.contains('admin-dashboard-page') || body.classList.contains('manage-page')) {
            animateDashboardStats();
        }
    }

    // Public API
    return {
        animate,
        stagger,
        animateCard,
        animateButtonLoading,
        animateButtonSuccess,
        animateModalOpen,
        animateModalClose,
        animateAlert,
        animateInputFocus,
        animateCounter,
        animateProgress,
        createTimeline,
        stopAll,
        getPresets,
        isReady,
        initPageAnimations,
        presets
    };
})();

document.addEventListener('DOMContentLoaded', () => {
    if (!window.AnimeUtils || !AnimeUtils.isReady()) return;

    AnimeUtils.initPageAnimations();

    document.querySelectorAll('.btn').forEach(btn => {
        btn.addEventListener('mouseenter', () => {
            if (btn.disabled) return;
            anime.remove(btn);
            anime({
                targets: btn,
                duration: 220,
                scale: 1.03,
                translateY: -1,
                easing: 'easeOutQuad'
            });
        });

        btn.addEventListener('mouseleave', () => {
            anime.remove(btn);
            anime({
                targets: btn,
                duration: 180,
                scale: 1,
                translateY: 0,
                easing: 'easeOutQuad'
            });
        });
    });

    document.querySelectorAll('.input, .textarea, .select').forEach(field => {
        field.addEventListener('focus', () => {
            anime({
                targets: field,
                duration: 240,
                scale: [1, 1.01],
                easing: 'easeOutCubic'
            });
        });
    });
});
