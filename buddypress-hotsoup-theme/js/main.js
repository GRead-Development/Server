/**
 * BuddyPress HotSoup Theme - Main JavaScript
 *
 * @package BP_HotSoup_Theme
 */

(function($) {
    'use strict';

    /**
     * Mobile Navigation Toggle
     */
    function initMobileNav() {
        const navToggle = '<button class="nav-toggle" aria-label="Toggle navigation"><span></span><span></span><span></span></button>';
        $('.main-navigation').before(navToggle);

        $('.nav-toggle').on('click', function() {
            $(this).toggleClass('active');
            $('.main-navigation').toggleClass('active');
        });
    }

    /**
     * Smooth Scroll for Anchor Links
     */
    function initSmoothScroll() {
        $('a[href*="#"]:not([href="#"])').on('click', function(e) {
            if (location.pathname.replace(/^\//, '') === this.pathname.replace(/^\//, '') && location.hostname === this.hostname) {
                const target = $(this.hash);
                const $target = target.length ? target : $('[name=' + this.hash.slice(1) + ']');

                if ($target.length) {
                    e.preventDefault();
                    $('html, body').animate({
                        scrollTop: $target.offset().top - 80
                    }, 800);
                }
            }
        });
    }

    /**
     * Add loading state to forms
     */
    function initFormLoading() {
        $('form').on('submit', function() {
            const $submit = $(this).find('input[type="submit"], button[type="submit"]');
            $submit.prop('disabled', true).addClass('loading');
        });
    }

    /**
     * Enhance HotSoup Book Progress Updates
     */
    function enhanceProgressUpdates() {
        // Add visual feedback for progress updates
        $(document).on('hs_progress_updated', function(e, data) {
            if (data.completed) {
                // Show celebration animation
                showCompletionCelebration();
            }
        });
    }

    /**
     * Show completion celebration
     */
    function showCompletionCelebration() {
        const $celebration = $('<div class="completion-celebration">🎉 Book Completed! 🎉</div>');
        $('body').append($celebration);

        setTimeout(function() {
            $celebration.addClass('show');
        }, 100);

        setTimeout(function() {
            $celebration.removeClass('show');
            setTimeout(function() {
                $celebration.remove();
            }, 500);
        }, 3000);
    }

    /**
     * Lazy Load Images
     */
    function initLazyLoad() {
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver(function(entries, observer) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.dataset.src;
                        img.classList.remove('lazy');
                        imageObserver.unobserve(img);
                    }
                });
            });

            document.querySelectorAll('img.lazy').forEach(function(img) {
                imageObserver.observe(img);
            });
        }
    }

    /**
     * Add "Back to Top" button
     */
    function initBackToTop() {
        const $backToTop = $('<button class="back-to-top" aria-label="Back to top">↑</button>');
        $('body').append($backToTop);

        $(window).on('scroll', function() {
            if ($(this).scrollTop() > 300) {
                $backToTop.addClass('visible');
            } else {
                $backToTop.removeClass('visible');
            }
        });

        $backToTop.on('click', function() {
            $('html, body').animate({ scrollTop: 0 }, 600);
        });
    }

    /**
     * Accessibility: Skip to content link
     */
    function initAccessibility() {
        const $skipLink = $('<a href="#main" class="skip-to-content">Skip to content</a>');
        $('body').prepend($skipLink);
    }

    /**
     * Initialize all functions
     */
    $(document).ready(function() {
        initMobileNav();
        initSmoothScroll();
        initFormLoading();
        enhanceProgressUpdates();
        initLazyLoad();
        initBackToTop();
        initAccessibility();
    });

})(jQuery);
