/**
 * Social Authentication Frontend Handler
 *
 * Handles Apple and Google Sign-In on the frontend
 *
 * @package HotSoup
 * @since 0.37
 */

(function($) {
    'use strict';

    // Configuration from WordPress
    const hsAuthConfig = window.hsAuthConfig || {};
    const appleEnabled = hsAuthConfig.appleEnabled || false;
    const googleEnabled = hsAuthConfig.googleEnabled || false;
    const appleClientId = hsAuthConfig.appleClientId || '';
    const googleClientId = hsAuthConfig.googleClientId || '';
    const redirectUri = hsAuthConfig.redirectUri || window.location.origin;

    /**
     * Initialize social authentication
     */
    function initSocialAuth() {
        // Initialize Apple Sign-In
        if (appleEnabled && appleClientId) {
            initAppleSignIn();
        }

        // Initialize Google Sign-In
        if (googleEnabled && googleClientId) {
            initGoogleSignIn();
        }

        // Form validation
        initFormValidation();
    }

    /**
     * Initialize Apple Sign-In
     */
    function initAppleSignIn() {
        // Load Apple Sign-In script
        if (!document.getElementById('appleid-auth')) {
            const script = document.createElement('script');
            script.id = 'appleid-auth';
            script.src = 'https://appleid.cdn-apple.com/appleauth/static/jsapi/appleid/1/en_US/appleid.auth.js';
            script.onload = configureAppleSignIn;
            document.head.appendChild(script);
        }
    }

    /**
     * Configure Apple Sign-In
     */
    function configureAppleSignIn() {
        if (typeof AppleID !== 'undefined') {
            AppleID.auth.init({
                clientId: appleClientId,
                scope: 'name email',
                redirectURI: redirectUri,
                usePopup: true
            });

            // Handle Apple auth responses
            document.addEventListener('AppleIDSignInOnSuccess', handleAppleSignInSuccess);
            document.addEventListener('AppleIDSignInOnFailure', handleAppleSignInFailure);
        }
    }

    /**
     * Initialize Google Sign-In
     */
    function initGoogleSignIn() {
        // Load Google Sign-In script
        if (!document.getElementById('google-signin')) {
            const script = document.createElement('script');
            script.id = 'google-signin';
            script.src = 'https://accounts.google.com/gsi/client';
            script.onload = configureGoogleSignIn;
            script.async = true;
            script.defer = true;
            document.head.appendChild(script);
        }
    }

    /**
     * Configure Google Sign-In
     */
    function configureGoogleSignIn() {
        if (typeof google !== 'undefined' && google.accounts) {
            google.accounts.id.initialize({
                client_id: googleClientId,
                callback: handleGoogleSignInSuccess,
                auto_select: false,
                cancel_on_tap_outside: true
            });

            // Render Google Sign-In button if container exists
            const googleButtonContainer = document.getElementById('hs-google-signin-button');
            if (googleButtonContainer) {
                google.accounts.id.renderButton(
                    googleButtonContainer,
                    {
                        theme: 'outline',
                        size: 'large',
                        width: googleButtonContainer.offsetWidth,
                        text: 'continue_with'
                    }
                );
            }
        }
    }

    /**
     * Handle Apple Sign-In button click
     */
    $(document).on('click', '.hs-apple-signin-btn', function(e) {
        e.preventDefault();

        if (typeof AppleID !== 'undefined') {
            const mode = $(this).data('mode') || 'signin';
            $('.hs-auth-mode').val(mode);

            AppleID.auth.signIn();
        } else {
            showError('Apple Sign-In is not available. Please try again later.');
        }
    });

    /**
     * Handle Google Sign-In button click
     */
    $(document).on('click', '.hs-google-signin-btn', function(e) {
        e.preventDefault();

        if (typeof google !== 'undefined' && google.accounts) {
            const mode = $(this).data('mode') || 'signin';
            $('.hs-auth-mode').val(mode);

            google.accounts.id.prompt();
        } else {
            showError('Google Sign-In is not available. Please try again later.');
        }
    });

    /**
     * Handle successful Apple Sign-In
     */
    function handleAppleSignInSuccess(event) {
        const { authorization, user } = event.detail;
        const idToken = authorization.id_token;
        const mode = $('.hs-auth-mode').val() || 'signin';

        if (mode === 'register') {
            // Show username and ToS form
            showRegistrationForm('apple', idToken, user);
        } else {
            // Directly sign in
            submitAppleSignIn(idToken);
        }
    }

    /**
     * Handle failed Apple Sign-In
     */
    function handleAppleSignInFailure(event) {
        console.error('Apple Sign-In failed:', event.detail);
        showError('Apple Sign-In failed. Please try again.');
    }

    /**
     * Handle successful Google Sign-In
     */
    function handleGoogleSignInSuccess(response) {
        const idToken = response.credential;
        const mode = $('.hs-auth-mode').val() || 'signin';

        if (mode === 'register') {
            // Show username and ToS form
            showRegistrationForm('google', idToken);
        } else {
            // Directly sign in
            submitGoogleSignIn(idToken);
        }
    }

    /**
     * Submit Apple Sign-In request
     */
    function submitAppleSignIn(idToken, userData = null) {
        showLoading('Signing in with Apple...');

        $.ajax({
            url: hsAuthConfig.apiUrl + '/auth/apple/signin',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                id_token: idToken,
                user_data: userData
            }),
            success: function(response) {
                hideLoading();
                if (response.success) {
                    showSuccess('Successfully signed in! Redirecting...');
                    setTimeout(function() {
                        window.location.href = hsAuthConfig.redirectAfterLogin || '/';
                    }, 1000);
                }
            },
            error: function(xhr) {
                hideLoading();
                const error = xhr.responseJSON || {};
                if (xhr.status === 404) {
                    // User not found, switch to registration
                    showError('No account found. Please register first.');
                    $('.hs-auth-mode').val('register');
                    showRegistrationForm('apple', idToken, null);
                } else {
                    showError(error.message || 'Sign-in failed. Please try again.');
                }
            }
        });
    }

    /**
     * Submit Google Sign-In request
     */
    function submitGoogleSignIn(idToken) {
        showLoading('Signing in with Google...');

        $.ajax({
            url: hsAuthConfig.apiUrl + '/auth/google/signin',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                id_token: idToken
            }),
            success: function(response) {
                hideLoading();
                if (response.success) {
                    showSuccess('Successfully signed in! Redirecting...');
                    setTimeout(function() {
                        window.location.href = hsAuthConfig.redirectAfterLogin || '/';
                    }, 1000);
                }
            },
            error: function(xhr) {
                hideLoading();
                const error = xhr.responseJSON || {};
                if (xhr.status === 404) {
                    // User not found, switch to registration
                    showError('No account found. Please register first.');
                    $('.hs-auth-mode').val('register');
                    showRegistrationForm('google', idToken);
                } else {
                    showError(error.message || 'Sign-in failed. Please try again.');
                }
            }
        });
    }

    /**
     * Show registration form with username and ToS
     */
    function showRegistrationForm(provider, idToken, userData = null) {
        // Hide social buttons
        $('.hs-social-buttons').hide();

        // Show registration form
        const form = $('.hs-registration-form');
        form.find('.hs-auth-provider').val(provider);
        form.find('.hs-auth-token').val(idToken);
        if (userData) {
            form.find('.hs-auth-userdata').val(JSON.stringify(userData));
        }
        form.show();

        // Focus on username field
        form.find('#hs-username').focus();
    }

    /**
     * Handle registration form submission
     */
    $(document).on('submit', '.hs-registration-form', function(e) {
        e.preventDefault();

        const provider = $(this).find('.hs-auth-provider').val();
        const idToken = $(this).find('.hs-auth-token').val();
        const username = $(this).find('#hs-username').val().trim();
        const acceptTos = $(this).find('#hs-accept-tos').is(':checked');
        const userData = $(this).find('.hs-auth-userdata').val();

        // Validate
        if (!username) {
            showError('Username is required');
            return;
        }

        if (!acceptTos) {
            showError('You must accept the Terms of Service');
            return;
        }

        // Submit registration
        if (provider === 'apple') {
            submitAppleRegistration(idToken, username, acceptTos, userData);
        } else if (provider === 'google') {
            submitGoogleRegistration(idToken, username, acceptTos);
        }
    });

    /**
     * Submit Apple registration
     */
    function submitAppleRegistration(idToken, username, acceptTos, userData) {
        showLoading('Creating your account...');

        const data = {
            id_token: idToken,
            username: username,
            accept_tos: acceptTos
        };

        if (userData) {
            try {
                data.user_data = JSON.parse(userData);
            } catch (e) {
                // Invalid JSON, ignore
            }
        }

        $.ajax({
            url: hsAuthConfig.apiUrl + '/auth/apple/register',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(data),
            success: function(response) {
                hideLoading();
                if (response.success) {
                    showSuccess('Account created successfully! Redirecting...');
                    setTimeout(function() {
                        window.location.href = hsAuthConfig.redirectAfterLogin || '/';
                    }, 1000);
                }
            },
            error: function(xhr) {
                hideLoading();
                const error = xhr.responseJSON || {};
                showError(error.message || 'Registration failed. Please try again.');
            }
        });
    }

    /**
     * Submit Google registration
     */
    function submitGoogleRegistration(idToken, username, acceptTos) {
        showLoading('Creating your account...');

        $.ajax({
            url: hsAuthConfig.apiUrl + '/auth/google/register',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                id_token: idToken,
                username: username,
                accept_tos: acceptTos
            }),
            success: function(response) {
                hideLoading();
                if (response.success) {
                    showSuccess('Account created successfully! Redirecting...');
                    setTimeout(function() {
                        window.location.href = hsAuthConfig.redirectAfterLogin || '/';
                    }, 1000);
                }
            },
            error: function(xhr) {
                hideLoading();
                const error = xhr.responseJSON || {};
                showError(error.message || 'Registration failed. Please try again.');
            }
        });
    }

    /**
     * Handle email registration
     */
    $(document).on('submit', '.hs-email-registration-form', function(e) {
        e.preventDefault();

        const email = $(this).find('#hs-email').val().trim();
        const password = $(this).find('#hs-password').val();
        const username = $(this).find('#hs-username').val().trim();
        const acceptTos = $(this).find('#hs-accept-tos').is(':checked');

        // Validate
        if (!email || !password || !username) {
            showError('All fields are required');
            return;
        }

        if (!acceptTos) {
            showError('You must accept the Terms of Service');
            return;
        }

        showLoading('Creating your account...');

        $.ajax({
            url: hsAuthConfig.apiUrl + '/auth/email/register',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                email: email,
                password: password,
                username: username,
                accept_tos: acceptTos
            }),
            success: function(response) {
                hideLoading();
                if (response.success) {
                    showSuccess('Account created successfully! Redirecting...');
                    setTimeout(function() {
                        window.location.href = hsAuthConfig.redirectAfterLogin || '/';
                    }, 1000);
                }
            },
            error: function(xhr) {
                hideLoading();
                const error = xhr.responseJSON || {};
                showError(error.message || 'Registration failed. Please try again.');
            }
        });
    });

    /**
     * Check username availability
     */
    let usernameCheckTimeout;
    $(document).on('input', '#hs-username', function() {
        const username = $(this).val().trim();
        const feedback = $('.hs-username-feedback');

        if (!username) {
            feedback.html('').removeClass('available unavailable');
            return;
        }

        clearTimeout(usernameCheckTimeout);
        usernameCheckTimeout = setTimeout(function() {
            $.ajax({
                url: hsAuthConfig.apiUrl + '/auth/check-username',
                method: 'GET',
                data: { username: username },
                success: function(response) {
                    if (response.available) {
                        feedback.html('✓ Username is available').removeClass('unavailable').addClass('available');
                    } else {
                        feedback.html('✗ ' + response.message).removeClass('available').addClass('unavailable');
                    }
                },
                error: function(xhr) {
                    const error = xhr.responseJSON || {};
                    feedback.html('✗ ' + (error.message || 'Error checking username')).removeClass('available').addClass('unavailable');
                }
            });
        }, 500);
    });

    /**
     * Form validation
     */
    function initFormValidation() {
        // Username validation
        $('#hs-username').on('blur', function() {
            const username = $(this).val().trim();
            if (username.length < 3) {
                $(this).addClass('invalid');
            } else {
                $(this).removeClass('invalid');
            }
        });
    }

    /**
     * Show loading message
     */
    function showLoading(message) {
        $('.hs-auth-loading').text(message).show();
        $('.hs-auth-error').hide();
        $('.hs-auth-success').hide();
    }

    /**
     * Hide loading message
     */
    function hideLoading() {
        $('.hs-auth-loading').hide();
    }

    /**
     * Show error message
     */
    function showError(message) {
        $('.hs-auth-error').text(message).show();
        $('.hs-auth-loading').hide();
        $('.hs-auth-success').hide();
    }

    /**
     * Show success message
     */
    function showSuccess(message) {
        $('.hs-auth-success').text(message).show();
        $('.hs-auth-error').hide();
        $('.hs-auth-loading').hide();
    }

    // Initialize on document ready
    $(document).ready(function() {
        initSocialAuth();
    });

})(jQuery);
