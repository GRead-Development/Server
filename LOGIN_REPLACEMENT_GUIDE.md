# WordPress Login Replacement Guide

## Quick Start - Replace WordPress Login with Social Auth

This guide shows you how to replace the default WordPress login (`wp-login.php`) with your new custom login/registration pages that include Apple and Google Sign-In.

---

## Method 1: Automatic Redirect (Recommended)

This method automatically redirects users from `wp-login.php` to your custom pages with social authentication.

### Step 1: Create Your Pages

**Create Login Page:**
1. In WordPress admin, go to **Pages** → **Add New**
2. Title: "Login"
3. In the content area, add the shortcode: `[hs_signin_form]`
4. Publish the page
5. Note the URL (e.g., `https://gread.fun/login`)

**Create Registration Page:**
1. Go to **Pages** → **Add New**
2. Title: "Register"
3. In the content area, add the shortcode: `[hs_registration_form]`
4. Publish the page
5. Note the URL (e.g., `https://gread.fun/register`)

### Step 2: Configure Login Integration

1. Go to **WordPress Admin** → **Settings** → **Social Auth**
2. Click on the **"Login Integration"** tab
3. Configure the following settings:

   **Replace WordPress Login:**
   - ☑️ Enable "Replace WordPress Login"
   - Enter your custom login page URL: `https://gread.fun/login`

   **Replace WordPress Registration:**
   - ☑️ Enable "Replace WordPress Registration"
   - Enter your custom registration page URL: `https://gread.fun/register`

   **After Login Redirect:**
   - Enter where users should go after login: `https://gread.fun/` (or your preferred page)

   **After Logout Redirect:**
   - Enter where users should go after logout: `https://gread.fun/`

4. Click **Save Settings**

### Step 3: Test It!

1. Open a private/incognito browser window
2. Visit: `https://gread.fun/wp-login.php`
3. You should be automatically redirected to your custom login page with Apple and Google sign-in buttons! ✅

---

## Method 2: Add Social Auth to Default wp-login.php

If you prefer to keep the default WordPress login page but add social authentication buttons to it:

1. Go to **Settings** → **Social Auth** → **Login Integration**
2. ☑️ Enable "Add Social Auth to wp-login.php"
3. **Uncheck** "Replace WordPress Login" (if you don't want full redirect)
4. Click **Save Settings**
5. Visit `wp-login.php` - you'll see Apple and Google buttons above the email/password form

---

## What Gets Replaced

### Before (WordPress Default)
```
wp-login.php
├── Username field
├── Password field
├── Remember Me checkbox
└── Log In button
```

### After (With Social Auth)
```
Your Custom Login Page
├── Sign in with Apple button
├── Sign in with Google button
├── "or" divider
└── Link to email/password login
```

---

## Login Flow Explained

### User Visits wp-login.php
```
1. User navigates to https://gread.fun/wp-login.php
2. HotSoup detects this and redirects to custom login page
3. User sees social auth buttons
4. User clicks "Sign in with Apple" or "Sign in with Google"
5. Provider authenticates user
6. User is logged into WordPress
7. User is redirected to home page (or custom URL)
```

### User Visits wp-login.php?action=register
```
1. User navigates to WordPress registration URL
2. HotSoup redirects to custom registration page
3. User sees all registration options:
   - Sign up with Apple
   - Sign up with Google
   - Sign up with Email
4. User completes registration (chooses username, accepts ToS)
5. Account is created and user is logged in
6. User is redirected to home page
```

---

## Additional Features

### Prevent Admin Access for Subscribers

The login integration automatically:
- ✅ Hides the WordPress admin bar for non-administrators
- ✅ Prevents subscribers from accessing `/wp-admin`
- ✅ Redirects subscribers away from admin dashboard

### Custom Login Logo

You can replace the WordPress logo on the login page:

1. Go to **Settings** → **Social Auth** → **Login Integration**
2. Enter your logo URL in "Custom Login Logo URL"
3. Recommended size: 320px wide × 80px tall
4. Save settings

### BuddyPress Integration

If you're using BuddyPress, the login integration also:
- Redirects BuddyPress registration to your custom page
- Works seamlessly with BuddyPress profiles
- Maintains compatibility with BuddyPress login widgets

---

## Troubleshooting

### I'm being redirected to the wrong page
**Solution:**
1. Check the URLs in **Settings** → **Social Auth** → **Login Integration**
2. Make sure the URLs exactly match your page URLs (copy/paste from browser)
3. Clear your browser cache and try again

### Users can still access wp-login.php directly
**Solution:**
1. Verify "Replace WordPress Login" is checked
2. Make sure the plugin is activated
3. Check for plugin conflicts (deactivate other login/security plugins temporarily)

### Social buttons don't appear on custom pages
**Solution:**
1. Verify the shortcode is correct: `[hs_signin_form]` or `[hs_registration_form]`
2. Check that Apple/Google is enabled in **Settings** → **Social Auth**
3. Make sure you've entered API credentials
4. Check browser console for JavaScript errors

### Login redirects to home but I want a different page
**Solution:**
1. Go to **Settings** → **Social Auth** → **Login Integration**
2. Change "After Login Redirect" to your desired URL
3. Example: `https://gread.fun/dashboard` or `https://gread.fun/my-books`

---

## Advanced Configuration

### Redirect Different User Roles Differently

By default:
- **Administrators** → WordPress Dashboard (`/wp-admin`)
- **Subscribers** → Home page (or custom URL you set)

To customize this, edit `includes/login_integration.php` around line 133:
```php
function hs_login_redirect($redirect_to, $request, $user) {
    // Add your custom logic here
}
```

### Disable Login Integration

To temporarily disable the login integration without deleting pages:

1. Go to **Settings** → **Social Auth** → **Login Integration**
2. Uncheck "Replace WordPress Login"
3. Uncheck "Replace WordPress Registration"
4. Save settings

Users will now see the default WordPress login again.

---

## WordPress Menu Integration

### Add Login/Register Links to Your Menu

1. Go to **Appearance** → **Menus**
2. Click **Custom Links**
3. Add Login link:
   - **URL:** `https://gread.fun/login`
   - **Link Text:** "Login"
4. Add Register link:
   - **URL:** `https://gread.fun/register`
   - **Link Text:** "Sign Up"
5. Add these to your menu
6. Save menu

### Show Login Link Only to Logged Out Users

You can use a plugin like "If Menu" or add custom code to show/hide menu items based on login status.

---

## Security Notes

1. **HTTPS Required:** Social authentication requires HTTPS in production
2. **Session Security:** WordPress sessions are used for authentication
3. **Admin Protection:** Non-administrators cannot access `/wp-admin`
4. **Password Security:** Social auth users get secure random passwords
5. **Token Verification:** All social tokens are verified before creating/logging in users

---

## Complete Setup Checklist

- [ ] Configure Apple Sign-In credentials (if using Apple)
- [ ] Configure Google Sign-In credentials (if using Google)
- [ ] Create Login page with `[hs_signin_form]` shortcode
- [ ] Create Register page with `[hs_registration_form]` shortcode
- [ ] Enable "Replace WordPress Login" in settings
- [ ] Enable "Replace WordPress Registration" in settings
- [ ] Set login/logout redirect URLs
- [ ] Test login flow in incognito browser
- [ ] Test registration flow
- [ ] Test logout redirect
- [ ] Add login/register links to site menu
- [ ] Verify HTTPS is enabled
- [ ] Test on mobile devices

---

## Support

If you encounter issues:

1. Check this guide first
2. Review `SOCIAL_AUTH_SETUP.md` for social auth configuration
3. Check browser console for JavaScript errors
4. Verify all settings in **Settings** → **Social Auth**
5. Test with social auth providers enabled
6. Check PHP error logs for server-side issues

---

## Files Modified

This login integration system consists of:

- `includes/login_integration.php` - Main login override logic
- `includes/admin/social_auth_settings.php` - Settings page with Login Integration tab
- `includes/shortcodes/registration_form.php` - Shortcodes for forms
- `js/social-auth.js` - Frontend JavaScript
- `css/social-auth.css` - Styling

---

## Version

Login Integration added in **HotSoup! v0.37**

---

**Ready to go?** Follow the Quick Start guide above and you'll have social authentication replacing your WordPress login in just a few minutes! 🚀
