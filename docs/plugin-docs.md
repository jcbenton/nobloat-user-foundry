# NoBloat Email Verification - Documentation

Welcome to NoBloat Email Verification! This lightweight plugin adds email verification to your WordPress site without bloat or complexity.

---

## Quick Start

### Installation

**Method 1: WordPress Plugin Directory** (Recommended)  
1. Log in to your WordPress admin panel  
2. Navigate to **Plugins → Add New**  
3. Search for "NoBloat Email Verification"  
4. Click **Install Now**, then **Activate**  
5. Two pages are auto-created: `/verify` and `/password-reset`  
6. Email templates are automatically loaded

**Method 2: GitHub Download**  
1. Download the latest release from [https://github.com/jcbenton/nobloat-email-verification](https://github.com/jcbenton/nobloat-email-verification)  
2. Extract the ZIP file on your computer  
3. Upload the `nobloat-email-verification` folder to `/wp-content/plugins/` via FTP/SFTP  
4. Navigate to **Plugins** in WordPress admin  
5. Find "NoBloat Email Verification" and click **Activate**  
6. Two pages are auto-created: `/verify` and `/password-reset`  
7. Email templates are automatically loaded

**Method 3: Direct Upload**  
1. Download the plugin ZIP file  
2. Log in to your WordPress admin panel  
3. Navigate to **Plugins → Add New → Upload Plugin**  
4. Click **Choose File** and select the downloaded ZIP  
5. Click **Install Now**, then **Activate**  
6. Two pages are auto-created: `/verify` and `/password-reset`  
7. Email templates are automatically loaded

### First Steps

1. Go to **Settings → Email Verification**
2. Review the **General** tab settings
3. Customize email templates in the **Templates** tab
4. Send a test email from the **Email Test** tab
5. Register a test user to verify everything works

---

## Features

### Core Functionality

**Email Verification on Registration**
New users receive a verification email and cannot log in until verified.

**Login Blocking**
Unverified users are blocked at login with a clear error message.

**Custom Pages**
Use your theme's design for verification and password reset pages.

**Admin Management**
Manage user verification status from the Users list with bulk actions.

**Automatic Cleanup**
Expired tokens are automatically deleted daily via cron job.

**Test Email Utility**
Send test verification emails to confirm your email configuration works.

---

## Shortcodes

### `[nobloat_ev_verify_page]`

Displays the email verification page. Place this shortcode on any page to handle email verification with your theme's design.

**Usage:**
```
[nobloat_ev_verify_page]
```

**When to use:**
- You want verification to use your theme's header/footer
- You need custom styling around the verification message
- You want verification on a specific page URL

**Auto-created page:** `/verify`

---

### `[nobloat_ev_reset_form]`

Displays the password reset form. Place this shortcode on any page to handle password resets with your theme's design.

**Usage:**
```
[nobloat_ev_reset_form]
```

**Optional attributes:**
```
[nobloat_ev_reset_form 
  wrapper_class="my-reset-wrapper"
  form_class="my-reset-form"
  input_class="my-input"
  button_class="my-button"
  title="Create New Password"]
```

**Auto-created page:** `/password-reset`

---

## Settings Panel

Navigate to **Settings → Email Verification** to configure the plugin.

### General Tab

**Verification URL**
- Default: `/verify`
- The page where users verify their email
- Must be a simple slug like `/verify` or `/confirm-email`
- If a page exists with this slug, add the `[nobloat_ev_verify_page]` shortcode

**Password Reset URL**
- Default: `/password-reset`
- The page where users reset their password
- Must be a simple slug like `/password-reset` or `/reset`
- If a page exists with this slug, add the `[nobloat_ev_reset_form]` shortcode

**Registration Hooks**
- **user_register:** Default WordPress registration
- **woocommerce_created_customer:** WooCommerce registration
- **Custom Hook:** Enter any action name that fires with a user ID

**Re-verification on Email Change**
- When checked, users must re-verify if they change their email address
- Recommended for security

**Uninstall Cleanup**
- Choose what data to remove when plugin is deleted
- Options: tokens, settings, templates, user meta

---

### Templates Tab

Customize the verification email sent to users.

**Available Placeholders:**
- `{site_name}` - Your site name
- `{site_url}` - Your site URL
- `{user_email}` - User's email address
- `{display_name}` - User's display name
- `{username}` - User's login name
- `{verify_link}` - Verification URL with token
- `{verification_url}` - Same as verify_link

**HTML Template**
Used when sending HTML emails (recommended for modern email clients).

**Plain Text Template**
Used when sending text-only emails (fallback for older email clients).

**Reset to Default**
Click "Reset HTML Template" or "Reset Text Template" to restore the original template.

---

### Email Test Tab

Send a test verification email to confirm your email configuration works correctly.

**Sender Email**
The "From" address for the test email. Pre-filled with your admin email.

**Recipient Email**
Where to send the test email. Use your own email address.

**What happens:**
1. A test token is generated
2. Test email is sent with verification link
3. Token is marked as "test" in database
4. Clicking the link shows "Test verification successful"
5. Test token is automatically deleted

**Troubleshooting:**
- If email doesn't arrive, check your WordPress email configuration
- Test with an SMTP plugin like WP Mail SMTP
- Check spam/junk folders
- Verify your server can send email

---

## User Management

### Users List

Navigate to **Users → All Users** to see verification status.

**Verified Column**
- Shows verification date/time for verified users
- Shows "Unverified" for users who haven't verified

**Resend Verification**
- Click "Resend Verification" on any unverified user
- Generates a new token and sends a new email
- Old tokens are automatically deleted

**Bulk Actions**
- Select multiple users
- Choose "Mark as Verified" from bulk actions
- Instantly verify multiple users without sending emails

---

## How It Works

### Registration Flow

1. User registers via WordPress or WooCommerce
2. Plugin generates a unique verification token
3. Token is stored in database with 24-hour expiration
4. Verification email is sent with token link
5. User is redirected to login page
6. Login is blocked until email is verified

### Verification Flow

1. User clicks verification link in email
2. Token is validated against database
3. Token expiration is checked
4. User meta is updated: `nobloat_ev_verified = 1`
5. Verification date is stored
6. Token is deleted (one-time use)
7. Success message is displayed
8. User can now log in

### Login Flow

1. User enters credentials at login
2. WordPress authenticates username/password
3. Plugin checks `nobloat_ev_verified` user meta
4. If `0` or not set: login blocked with error message
5. If `1`: login proceeds normally
6. Admins always bypass verification check

---

## Integration

### WooCommerce

**Automatic Integration**
Enable the "woocommerce_created_customer" hook in settings.

**What happens:**
- Users who register during checkout receive verification email
- They can complete their purchase
- They must verify email before logging in later

### Custom Registration Forms

**Add verification to any form:**

```php
// After creating user
do_action('user_register', $user_id);
```

**Or use the custom hook:**
1. Go to Settings → Email Verification → General
2. Check "Enable custom hook listener"
3. Enter your hook name (e.g., `my_custom_registration`)
4. Fire your hook after user creation:

```php
// In your registration code
do_action('my_custom_registration', $user_id);
```

### Manual Verification

**Mark a user as verified programmatically:**

```php
update_user_meta($user_id, 'nobloat_ev_verified', 1);
update_user_meta($user_id, 'nobloat_ev_verified_date', current_time('mysql'));
```

### Check Verification Status

**Check if a user is verified:**

```php
$verified = get_user_meta($user_id, 'nobloat_ev_verified', true);
if ($verified) {
    // User is verified
} else {
    // User is not verified
}
```

### Post-Verification Actions

**Run code after verification:**

```php
add_action('nobloat_ev_user_verified', function($email, $user_id) {
    // Send welcome email
    // Assign user role
    // Log event
    // Whatever you need
}, 10, 2);
```

---

## Hooks & Filters

### Actions

**nobloat_ev_user_verified**
Fires after a user successfully verifies their email.

Parameters:
- `$email` (string) - User's email address
- `$user_id` (int) - User ID

Example:
```php
add_action('nobloat_ev_user_verified', function($email, $user_id) {
    error_log("User $user_id verified: $email");
}, 10, 2);
```

### Filters

The plugin uses these WordPress filters internally:

- `authenticate` - Blocks unverified users at login
- `retrieve_password_message` - Rewrites password reset URLs
- `wp_send_new_user_notifications` - Disables default WP emails

---

## Database

### Tokens Table

**Table name:** `wp_nobloat_ev_tokens` (prefix may vary)

**Structure:**
- `id` - Auto-increment primary key
- `user_id` - WordPress user ID (0 for test emails)
- `user_email` - User's email address
- `token` - Unique verification token
- `created_at` - When token was generated
- `expires_at` - When token expires (24 hours)
- `verified` - Whether token was used (0/1)
- `is_test` - Whether this is a test token (0/1)

**Indexes:**
- Primary key on `id`
- Index on `token` for fast lookups
- Index on `user_email` for searches

**Cleanup:**
Tokens are automatically deleted when:
- User verifies email (immediate)
- Token expires (daily cron job)
- User requests new verification (old tokens deleted)

### User Meta

**nobloat_ev_verified**
- Value: `0` (unverified) or `1` (verified)
- Set on successful verification
- Checked at login

**nobloat_ev_verified_date**
- Value: MySQL datetime (e.g., `2025-01-15 14:30:00`)
- Set on successful verification
- Displayed in admin Users list

---

## Troubleshooting

### Emails Not Sending

**Check WordPress email configuration:**
1. Install WP Mail SMTP plugin
2. Configure SMTP settings
3. Send test email from Email Test tab

**Common issues:**
- Server doesn't support `mail()` function
- SPF/DKIM records not configured
- Email caught by spam filters
- Hosting provider blocks outbound email

**Solutions:**
- Use SMTP plugin (recommended)
- Configure DNS records properly
- Whitelist your IP with email providers
- Contact hosting support

---

### Users Can't Verify

**Token expired:**
- Tokens expire after 24 hours
- Click "Resend Verification" from Users list
- User receives new token

**Token already used:**
- Verification tokens are one-time use
- Click "Resend Verification" to generate new token

**Wrong URL:**
- Verify Settings → General → Verification URL matches page slug
- Ensure page has `[nobloat_ev_verify_page]` shortcode
- Check for URL rewrites or redirects

---

### Login Still Blocked

**User verified but can't log in:**
1. Go to Users → All Users
2. Check if user shows as "Verified"
3. If not, click "Resend Verification"
4. Or manually mark as verified with bulk action

**Admins are blocked:**
- Admins should never be blocked
- Check if user actually has `manage_options` capability
- Verify admin role is properly assigned

---

### Templates Not Working

**Templates are blank:**
1. Go to Settings → Email Verification → Templates
2. Click "Reset HTML Template"
3. Click "Reset Text Template"
4. Save changes

**Placeholders not replaced:**
- Ensure placeholders use curly braces: `{site_name}`
- Check for typos in placeholder names
- Verify template was saved after editing

---

## Security

### Token Security

**Random generation:**
Tokens are generated using `wp_generate_password(32, false)` for cryptographic randomness.

**One-time use:**
Tokens are deleted immediately after successful verification.

**Expiration:**
Tokens expire after 24 hours. Expired tokens are automatically cleaned up.

**SQL injection prevention:**
All database queries use prepared statements with proper escaping.

### Form Security

**Nonce verification:**
All forms include WordPress nonces to prevent CSRF attacks.

**Capability checks:**
Admin functions check for `manage_options` capability.

**Input sanitization:**
All user input is sanitized using WordPress functions:
- `sanitize_email()` for emails
- `sanitize_text_field()` for text
- `absint()` for user IDs

**Output escaping:**
All output is escaped to prevent XSS:
- `esc_html()` for text
- `esc_attr()` for attributes
- `esc_url()` for URLs

---

## Performance

### Optimization

**Minimal queries:**
- Indexed database lookups
- Single query per verification
- Bulk operations for admin actions

**Conditional loading:**
- Admin classes load only in wp-admin
- Front-end CSS loads only on verification/reset pages
- No JavaScript required

**Automatic cleanup:**
- Daily cron job removes expired tokens
- Keeps database table small
- Prevents bloat over time

### Cron Job

**Schedule:** Daily at midnight
**Hook:** `nobloat_ev_cleanup_cron`
**Function:** Deletes expired and verified tokens

**Manual trigger:**
```php
Nobloat_EV_Database::cleanup_expired();
```

---

## Uninstall

### Clean Removal

Configure what to delete in **Settings → General → Uninstall Cleanup**.

**Options:**
1. **Delete all verification tokens** - Removes tokens table
2. **Delete plugin settings** - Removes all settings
3. **Delete templates** - Removes custom email templates
4. **Delete user verification meta** - Removes user verification data

**What's kept by default:**
- Pages created during activation
- User accounts

**Manual cleanup:**
- Delete `/verify` and `/password-reset` pages manually if desired
- Remove SMTP plugin if it was only for this plugin

---

## FAQ

**Q: Will existing users need to verify?**  
A: No, only new users who register after plugin activation.


**Q: Can I manually verify users?**  
A: Yes, use the "Mark as Verified" bulk action in Users list.


**Q: Does this work with WooCommerce?**  
A: Yes, enable the WooCommerce hook in settings.


**Q: Can I customize the verification email?**  
A: Yes, edit templates in Settings → Email Verification → Templates.


**Q: How long are tokens valid?**  
A: 24 hours by default. Expired tokens are auto-deleted.


**Q: Can I change the verification page URL?**  
A: Yes, in Settings → General → Verification URL.


**Q: Are admins required to verify?**  
A: No, users with `manage_options` capability bypass verification.


**Q: Can users receive multiple verification emails?**  
A: Yes, click "Resend Verification" to send a new email.


**Q: What happens to old tokens when resending?**  
A: Old tokens are automatically deleted when new ones are generated.


**Q: Does this work with custom registration forms?**  
A: Yes, fire `do_action('user_register', $user_id)` after creating users.


---

## Support

### Getting Help

**Documentation:** You're reading it!

**Settings Panel:** Settings → Email Verification

**Test Email:** Use Email Test tab to diagnose email issues

**Debug Mode:** Enable WP_DEBUG to see detailed error logs

### Reporting Issues

When reporting issues, please include:
1. WordPress version
2. PHP version
3. Active plugins list
4. Error messages (if any)
5. Steps to reproduce

---

## Credits

**Developed by:** NoBloat (Jerry Benton)
**License:** GPL-3.0-or-later

Thank you for using NoBloat Email Verification!