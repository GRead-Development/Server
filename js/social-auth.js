/**
 * Social Authentication JavaScript
 * Handles Apple and Google Sign-In flows
 */

(function($) {
    'use strict';

    // Configuration from WordPress
    const config = window.hsAuthConfig || {};

    // State management
    let authState = {
        mode: 'signin', // 'signin' or 'register'
        provider: null,
        authData: null,
        tosAccepted: false
    };

    /**
     * Initialize Apple Sign-In
     */
    function initAppleSignIn() {
        if (!config.appleEnabled || !config.appleClientId) {
            return;
        }

        // Load Apple Sign-In SDK
        if (typeof AppleID === 'undefined') {
            const script = document.createElement('script');
            script.src = 'https://appleid.cdn-apple.com/appleauth/static/jsapi/appleid/1/en_US/appleid.auth.js';
            script.async = true;
            script.onload = setupAppleSignIn;
            document.head.appendChild(script);
        } else {
            setupAppleSignIn();
        }
    }

    /**
     * Setup Apple Sign-In configuration
     */
    function setupAppleSignIn() {
        if (typeof AppleID === 'undefined') {
            console.error('Apple Sign-In SDK failed to load');
            return;
        }

        try {
            // Use configured redirect URI or fallback to current origin
            const redirectURI = config.redirectUri || window.location.origin + '/';

            AppleID.auth.init({
                clientId: config.appleClientId,
                scope: 'name email',
                redirectURI: redirectURI,
                usePopup: true
            });
        } catch (error) {
            console.error('Failed to initialize Apple Sign-In:', error);
        }
    }

    /**
     * Handle Apple Sign-In button click
     */
    function handleAppleSignIn() {
        const mode = authState.mode;

        // Check ToS acceptance for registration
        if (mode === 'register' && !checkTosAcceptance()) {
            return;
        }

        showLoading('Connecting to Apple...');

        if (typeof AppleID === 'undefined') {
            showError('Apple Sign-In is not available. Please try again.');
            return;
        }

        AppleID.auth.signIn()
            .then(response => {
                handleAppleResponse(response, mode);
            })
            .catch(error => {
                console.error('Apple Sign-In error:', error);
                hideLoading();

                if (error.error === 'popup_closed_by_user') {
                    // User cancelled - don't show error
                    return;
                }

                showError('Apple Sign-In failed. Please try again.');
            });
    }

    /**
     * Handle Apple Sign-In response
     */
    function handleAppleResponse(response, mode) {
        const authData = {
            provider: 'apple',
            id_token: response.authorization.id_token,
            code: response.authorization.code,
            user: response.user || null
        };

        authState.authData = authData;
        authState.provider = 'apple';

        // For registration, show username modal
        if (mode === 'register') {
            hideLoading();
            showUsernameModal(authData);
        } else {
            // For sign-in, send to backend
            sendAuthToBackend(authData, mode);
        }
    }

    /**
     * Initialize Google Sign-In
     */
    function initGoogleSignIn() {
        if (!config.googleEnabled || !config.googleClientId) {
            return;
        }

        // Load Google Sign-In SDK
        if (typeof google === 'undefined') {
            const script = document.createElement('script');
            script.src = 'https://accounts.google.com/gsi/client';
            script.async = true;
            script.defer = true;
            script.onload = setupGoogleSignIn;
            document.head.appendChild(script);
        } else {
            setupGoogleSignIn();
        }
    }

    /**
     * Setup Google Sign-In
     */
    function setupGoogleSignIn() {
        if (typeof google === 'undefined' || !google.accounts) {
            console.error('Google Sign-In SDK failed to load');
            return;
        }

        try {
            google.accounts.id.initialize({
                client_id: config.googleClientId,
                callback: handleGoogleResponse,
                auto_select: false,
                cancel_on_tap_outside: true
            });

            // Render Google button if element exists
            const buttonElement = document.getElementById('hs-google-signin-button');
            if (buttonElement) {
                google.accounts.id.renderButton(
                    buttonElement,
                    {
                        theme: 'outline',
                        size: 'large',
                        width: buttonElement.offsetWidth,
                        text: authState.mode === 'register' ? 'signup_with' : 'signin_with'
                    }
                );
            }
        } catch (error) {
            console.error('Failed to initialize Google Sign-In:', error);
        }
    }

    /**
     * Handle Google Sign-In button click (fallback)
     */
    function handleGoogleSignIn() {
        const mode = authState.mode;

        // Check ToS acceptance for registration
        if (mode === 'register' && !checkTosAcceptance()) {
            return;
        }

        if (typeof google === 'undefined' || !google.accounts) {
            showError('Google Sign-In is not available. Please try again.');
            return;
        }

        google.accounts.id.prompt((notification) => {
            if (notification.isNotDisplayed() || notification.isSkippedMoment()) {
                console.log('Google One Tap not displayed:', notification.getNotDisplayedReason());
            }
        });
    }

    /**
     * Handle Google Sign-In response
     */
    function handleGoogleResponse(response) {
        const mode = authState.mode;

        showLoading('Connecting to Google...');

        const authData = {
            provider: 'google',
            credential: response.credential
        };

        authState.authData = authData;
        authState.provider = 'google';

        // For registration, show username modal
        if (mode === 'register') {
            hideLoading();
            showUsernameModal(authData);
        } else {
            // For sign-in, send to backend
            sendAuthToBackend(authData, mode);
        }
    }

    /**
     * Show username modal for registration
     */
    function showUsernameModal(authData) {
        let modal = $('#hs-username-modal');

        // Create modal if it doesn't exist
        if (modal.length === 0) {
            modal = $('<div id="hs-username-modal" class="hs-username-modal">' +
                '<div class="hs-username-modal-content">' +
                    '<h3>Choose Your Username</h3>' +
                    '<label for="hs-username-input">Username</label>' +
                    '<input type="text" id="hs-username-input" placeholder="Enter username" maxlength="20">' +
                    '<div class="username-hint">3-20 characters, letters, numbers, and underscores only</div>' +
                    '<div class="username-error"></div>' +
                    '<button class="submit-btn">Create Account</button>' +
                    '<button class="cancel-btn">Cancel</button>' +
                '</div>' +
            '</div>');
            $('body').append(modal);
        }

        modal.addClass('active');

        // Clear previous input
        $('#hs-username-input').val('').focus();
        $('.username-error').removeClass('active').text('');

        // Handle submit
        modal.find('.submit-btn').off('click').on('click', function() {
            const username = $('#hs-username-input').val().trim();

            if (!validateUsername(username)) {
                return;
            }

            authData.username = username;
            modal.removeClass('active');
            sendAuthToBackend(authData, 'register');
        });

        // Handle cancel
        modal.find('.cancel-btn').off('click').on('click', function() {
            modal.removeClass('active');
            authState.authData = null;
        });

        // Handle Enter key
        $('#hs-username-input').off('keypress').on('keypress', function(e) {
            if (e.which === 13) {
                modal.find('.submit-btn').click();
            }
        });
    }

    /**
     * Validate username
     */
    function validateUsername(username) {
        const errorElement = $('.username-error');

        if (!username || username.length < 3) {
            errorElement.text('Username must be at least 3 characters').addClass('active');
            return false;
        }

        if (username.length > 20) {
            errorElement.text('Username must be 20 characters or less').addClass('active');
            return false;
        }

        if (!/^[a-zA-Z0-9_]+$/.test(username)) {
            errorElement.text('Username can only contain letters, numbers, and underscores').addClass('active');
            return false;
        }

        errorElement.removeClass('active').text('');
        return true;
    }

    /**
     * Check ToS acceptance
     */
    function checkTosAcceptance() {
        const tosCheckbox = $('#hs-tos-acceptance');

        if (tosCheckbox.length === 0) {
            return true; // No ToS required
        }

        if (!tosCheckbox.is(':checked')) {
            showError('Please accept the Terms of Service to continue.');
            return false;
        }

        authState.tosAccepted = true;
        return true;
    }

    /**
     * Send authentication data to backend
     */
    function sendAuthToBackend(authData, mode) {
        showLoading(mode === 'register' ? 'Creating your account...' : 'Signing you in...');

        const endpoint = mode === 'register' ? '/auth/register' : '/auth/signin';

        $.ajax({
            url: config.apiUrl + endpoint,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(authData),
            success: function(response) {
                hideLoading();

                if (response.success) {
                    showSuccess(response.message || 'Success! Redirecting...');

                    // Redirect after a short delay
                    setTimeout(() => {
                        window.location.href = response.redirect_url || config.redirectAfterLogin;
                    }, 1000);
                } else {
                    showError(response.message || 'Authentication failed. Please try again.');
                }
            },
            error: function(xhr) {
                hideLoading();

                let errorMessage = 'An error occurred. Please try again.';

                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                } else if (xhr.status === 400) {
                    errorMessage = 'Invalid request. Please try again.';
                } else if (xhr.status === 409) {
                    errorMessage = 'An account with this email already exists.';
                }

                showError(errorMessage);
            }
        });
    }

    /**
     * Show loading state
     */
    function showLoading(message) {
        $('.hs-auth-loading').addClass('active').text(message || 'Loading...');
        $('.hs-auth-error').removeClass('active');
        $('.hs-auth-success').removeClass('active');
        $('.hs-social-btn').prop('disabled', true);
    }

    /**
     * Hide loading state
     */
    function hideLoading() {
        $('.hs-auth-loading').removeClass('active');
        $('.hs-social-btn').prop('disabled', false);
    }

    /**
     * Show error message
     */
    function showError(message) {
        $('.hs-auth-error').addClass('active').text(message);
        $('.hs-auth-loading').removeClass('active');
        $('.hs-auth-success').removeClass('active');
    }

    /**
     * Show success message
     */
    function showSuccess(message) {
        $('.hs-auth-success').addClass('active').text(message);
        $('.hs-auth-loading').removeClass('active');
        $('.hs-auth-error').removeClass('active');
    }

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        // Detect auth mode
        const modeElement = $('.hs-auth-mode');
        if (modeElement.length > 0) {
            authState.mode = modeElement.val() || 'signin';
        }

        // Initialize providers
        initAppleSignIn();
        initGoogleSignIn();

        // Bind Apple button click
        $(document).on('click', '.hs-apple-signin-btn', function(e) {
            e.preventDefault();
            authState.mode = $(this).data('mode') || authState.mode;
            handleAppleSignIn();
        });

        // Bind Google button click (fallback)
        $(document).on('click', '.hs-google-signin-btn', function(e) {
            e.preventDefault();
            authState.mode = $(this).data('mode') || authState.mode;
            handleGoogleSignIn();
        });

        // Update Google button on mode change
        $(document).on('change', '.hs-auth-mode', function() {
            authState.mode = $(this).val();
            setupGoogleSignIn(); // Re-render button with new text
        });
    });

})(jQuery);
