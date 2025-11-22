# Social Authentication Setup Guide

## Overview

HotSoup! now supports social authentication with **Apple Sign-In** and **Google Sign-In**, in addition to traditional email registration. This allows users to create accounts and sign in using their existing Apple or Google accounts.

## Features

- **Apple Sign-In**: Users can register and sign in using their Apple ID
- **Google Sign-In**: Users can register and sign in using their Google account
- **Email Registration**: Traditional email and password registration
- **Username Selection**: All users must choose a unique username (cannot be changed later)
- **Terms of Service**: Users must accept the ToS with a link to gread.fun/tos
- **Secure**: Token verification, secure password generation for social auth users

## Installation & Configuration

### Step 1: Configure Apple Sign-In

1. Go to the [Apple Developer Portal](https://developer.apple.com)
2. Create an **App ID** for your application
3. Enable "Sign in with Apple" capability for your App ID
4. Create a **Services ID**
5. Configure the Services ID:
   - **Domains**: Add your domain (e.g., `gread.fun`)
   - **Return URLs**: Add your site URL (e.g., `https://gread.fun`)
6. Create a **Private Key** for Sign in with Apple
7. Download the `.p8` private key file
8. Note your **Team ID**, **Key ID**, and **Services ID** (Client ID)

### Step 2: Configure Google Sign-In

1. Go to the [Google Cloud Console](https://console.cloud.google.com)
2. Create a new project or select an existing one
3. Enable the **Google+ API**
4. Go to **Credentials** → **Create Credentials** → **OAuth 2.0 Client ID**
5. Choose **Web application** as the application type
6. Configure authorized origins and redirect URIs:
   - **Authorized JavaScript origins**: `https://gread.fun`
   - **Authorized redirect URIs**: `https://gread.fun/wp-json/gread/v1/auth/google/callback`
7. Copy your **Client ID** and **Client Secret**

### Step 3: Configure in WordPress Admin

1. Log in to your WordPress admin panel
2. Go to **Settings** → **Social Auth**
3. Configure **Apple Sign-In**:
   - Enable Apple Sign-In
   - Enter your **Client ID** (Services ID)
   - Enter your **Team ID**
   - Enter your **Key ID**
   - Paste your **Private Key** (.p8 file contents)
4. Configure **Google Sign-In**:
   - Enable Google Sign-In
   - Enter your **Client ID**
   - Enter your **Client Secret**
5. Configure **General Settings**:
   - Set Terms of Service URL (default: `https://gread.fun/tos`)
   - Username requirements are automatically enforced
6. Click **Save Settings**

### Step 4: Add Registration/Sign-In Forms

Use the following shortcodes to add authentication forms to your pages:

#### Registration Form
```
[hs_registration_form]
```

Options:
- `redirect`: URL to redirect after successful registration (default: home)
- `show_email`: Show email registration option (default: true)

Example:
```
[hs_registration_form redirect="/welcome" show_email="true"]
```

#### Sign-In Form
```
[hs_signin_form]
```

Options:
- `redirect`: URL to redirect after successful sign-in (default: home)

Example:
```
[hs_signin_form redirect="/dashboard"]
```

## Usage

### For Users

1. **Registration**:
   - Visit the registration page
   - Choose to sign up with Apple, Google, or Email
   - If using social auth, click the respective button
   - Choose a unique username (3-20 characters, letters/numbers/underscores only)
   - **Important**: Username cannot be changed after registration
   - Accept the Terms of Service
   - Complete registration

2. **Sign-In**:
   - Visit the sign-in page
   - Click "Sign in with Apple" or "Sign in with Google"
   - Or use email/password if registered with email

### For Developers

#### API Endpoints

The following REST API endpoints are available:

**Apple Sign-In**
- `POST /wp-json/gread/v1/auth/apple/signin`
- `POST /wp-json/gread/v1/auth/apple/register`

**Google Sign-In**
- `POST /wp-json/gread/v1/auth/google/signin`
- `POST /wp-json/gread/v1/auth/google/register`

**Email Registration**
- `POST /wp-json/gread/v1/auth/email/register`

**Username Check**
- `GET /wp-json/gread/v1/auth/check-username?username=example`

#### Registration Flow

1. User clicks social auth button
2. Social provider authenticates user and returns ID token
3. Frontend sends ID token to backend API
4. Backend verifies token with provider
5. If user exists: log in
6. If new user: show username/ToS form
7. Submit registration with username and ToS acceptance
8. Backend creates WordPress user with social provider ID
9. User is logged in and redirected

#### User Metadata

The following user metadata is stored:

- `apple_user_id`: Apple user identifier (for Apple auth)
- `google_user_id`: Google user identifier (for Google auth)
- `tos_accepted`: Boolean indicating ToS acceptance
- `tos_accepted_date`: Timestamp of ToS acceptance
- `registration_method`: 'apple', 'google', or 'email'
- `user_points`: User points (initialized to 0)
- `hs_completed_books_count`: Books completed (initialized to 0)
- `hs_total_pages_read`: Total pages read (initialized to 0)
- `hs_books_added_count`: Books added (initialized to 0)

#### Username Validation

Usernames must meet the following requirements:
- 3-20 characters long
- Only letters, numbers, and underscores
- Cannot be changed after registration
- Must be unique
- Reserved usernames: admin, administrator, root, system, hotsoup, gread

#### Hooks

**Actions:**
- `hs_user_registered_apple` - Fired when user registers with Apple
- `hs_user_registered_google` - Fired when user registers with Google
- `hs_user_registered_email` - Fired when user registers with email

Example:
```php
add_action('hs_user_registered_apple', function($user_id) {
    // Custom logic after Apple registration
    error_log("New user registered with Apple: " . $user_id);
});
```

## Security Considerations

1. **Token Verification**: All ID tokens are verified with the respective providers
2. **HTTPS Required**: Social authentication requires HTTPS for security
3. **Secure Credentials**: Keep your private keys and client secrets secure
4. **Username Immutability**: Usernames cannot be changed to prevent impersonation
5. **Password Generation**: Social auth users get secure random passwords
6. **ToS Acceptance**: Required for all registration methods

## Troubleshooting

### Apple Sign-In Issues

**Problem**: "Invalid token" error
- **Solution**: Verify your Client ID, Team ID, and Key ID are correct
- Check that your private key is properly formatted (include BEGIN/END markers)
- Ensure your domain is configured in Apple Developer Portal

**Problem**: Apple button doesn't appear
- **Solution**: Check that Apple Sign-In is enabled in settings
- Verify your Client ID is saved
- Check browser console for JavaScript errors

### Google Sign-In Issues

**Problem**: "Invalid token" error
- **Solution**: Verify your Client ID and Client Secret are correct
- Ensure redirect URIs match exactly in Google Cloud Console

**Problem**: Google button doesn't appear
- **Solution**: Check that Google Sign-In is enabled in settings
- Verify your Client ID is saved
- Check for JavaScript errors in browser console

### General Issues

**Problem**: "Username already exists"
- **Solution**: Choose a different username

**Problem**: Registration form doesn't appear
- **Solution**: Verify the shortcode is correctly placed on the page
- Check that you're not already logged in
- Clear browser cache

**Problem**: After clicking social button, nothing happens
- **Solution**: Check browser console for errors
- Verify JavaScript file is loaded (check Network tab)
- Ensure cookies are enabled

## File Structure

```
/includes/
├── api/
│   ├── auth.php                    # Main authentication API handlers
│   └── social-providers.php        # Token verification utilities
├── admin/
│   └── social_auth_settings.php    # Admin settings page
└── shortcodes/
    └── registration_form.php       # Registration/sign-in forms

/js/
└── social-auth.js                  # Frontend JavaScript handler

/css/
└── social-auth.css                 # Styling for auth forms
```

## Requirements

- WordPress 5.0 or higher
- BuddyPress (for user profiles)
- PHP 7.4 or higher
- HTTPS enabled on production
- OpenSSL extension (for token verification)

## Version History

- **0.37**: Initial release of social authentication feature
  - Apple Sign-In support
  - Google Sign-In support
  - Email registration with username selection
  - Terms of Service acceptance
  - Username immutability enforcement

## Support

For issues or questions:
1. Check the Setup Guide in WordPress Admin (Settings → Social Auth → Setup Guide)
2. Review this documentation
3. Check browser console for JavaScript errors
4. Verify API credentials are correct
5. Contact support with error details

## License

This feature is part of HotSoup! plugin.
Copyright © Bryce Davis, Daniel Teberian
