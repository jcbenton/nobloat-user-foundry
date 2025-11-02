# NoBloat User Foundry

**Contributors:** [mailborder](https://github.com/jcbenton)
**Donate link:** [https://donate.stripe.com/14AdRa6XJ1Xn8yT8KObfO00](https://donate.stripe.com/14AdRa6XJ1Xn8yT8KObfO00)
**Tags:** user management, email verification, account expiration, user lifecycle, registration
**Requires at least:** 5.8
**Tested up to:** 6.8
**Requires PHP:** 7.4
**Stable tag:** 1.0.0
**License:** GPL v3 or later
**License URI:** [https://www.gnu.org/licenses/gpl-3.0.html](https://www.gnu.org/licenses/gpl-3.0.html)

Lightweight WordPress user management system - email verification, account expiration, and user lifecycle management without the bloat.

---

## Description

**NoBloat User Foundry** is a comprehensive yet lightweight user management system for WordPress. It provides email verification, account expiration, bulk user management, and complete user lifecycle control without unnecessary bloat.

Perfect for membership sites, WooCommerce stores, and any WordPress site that needs professional user management without the overhead of bloated plugins like Ultimate Member.

### Key Features

- ✅ **Email Verification** - New users must verify their email before logging in
- ✅ **Account Expiration** - Set expiration dates for users with automatic enforcement
- ✅ **User Lifecycle Management** - Complete control over user accounts from registration to expiration
- ✅ **Bulk User Operations** - Manage verification, disabled status, and expiration for multiple users
- ✅ **Advanced Filtering** - Filter users by verification status, account status, and expiration
- ✅ **Custom Verification Pages** - Use your theme's design for verification and password reset pages
- ✅ **WooCommerce Protection** - Optionally prevent expiration for active subscribers and recent customers
- ✅ **Email Notifications** - Automated warning emails before account expiration
- ✅ **Admin Dashboard** - Comprehensive user management interface
- ✅ **Customizable Templates** - Edit all email templates (HTML and plain text)
- ✅ **Automatic Cleanup** - Expired tokens and scheduled enforcement via cron jobs

### How It Works

**Registration & Verification:**
1. User registers via WordPress or WooCommerce
2. Verification email is sent with unique token
3. User clicks verification link in email
4. User account is marked as verified
5. User can now log in normally

**Account Expiration:**
1. Admin sets expiration date on user profile
2. Warning email sent X days before expiration (configurable)
3. On expiration date, account is automatically disabled
4. User cannot log in until expiration is removed

### Admin Features

- **Verified Status Column** - See verification status at a glance in Users list
- **Account Status Column** - View enabled/disabled status
- **Expiration Column** - See expiration dates with visual indicators for expired accounts
- **Filter Dropdowns** - Filter users by verification, status, and expiration with live counts
- **Bulk Actions** - Verify, disable, enable, set/remove expiration for multiple users at once
- **User Profile Meta Box** - Set expiration with datepicker on any user profile
- **Settings Page** - Configure all options with multiple organized tabs
- **Email Template Editor** - Customize all email templates with placeholder support

### Developer Friendly

- **Action Hooks** - `nbuf_user_verified` and `nbuf_user_expired` for custom integrations
- **Clean Code** - Procedural style preferred, OOP only where it makes sense
- **Well Documented** - Extensive section comments for future maintainability
- **Extendable** - Use WordPress hooks and filters to customize behavior
- **Performance** - Custom database table with indexed columns
- **No Bloat** - Minimal CSS/JS, only loads where needed

---

## Installation

### From GitHub

1. Download the latest release from [GitHub](https://github.com/jcbenton/nobloat-user-foundry)
2. Upload the `nobloat-user-foundry` folder to `/wp-content/plugins/`
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Configure settings at **Settings → User Foundry**

### Via Upload

1. Download the plugin ZIP file
2. Navigate to **Plugins → Add New** in your WordPress admin
3. Click **Upload Plugin** and select the ZIP file
4. Click **Install Now**, then **Activate**
5. Configure settings at **Settings → User Foundry**

---

## Quick Start

### Enable Email Verification

1. Go to **Settings → User Foundry → General**
2. Verification is enabled by default
3. Customize email templates under **Templates** tab

### Enable Account Expiration

1. Go to **Settings → User Foundry → Expiration**
2. Check "Enable account expiration feature"
3. Configure warning days (default: 7 days before expiration)
4. Save settings

### Set User Expiration

**Method 1: Individual User**
1. Edit any user profile
2. Find "Account Expiration" meta box
3. Uncheck "Never expires"
4. Select date/time with datepicker
5. Save user

**Method 2: Bulk Action**
1. Go to **Users** list
2. Select multiple users
3. Choose "Set Expiration Date" from bulk actions dropdown
4. Click Apply
5. Enter date/time in modal
6. Submit

---

## Shortcodes

### Verification Page
```
[nbuf_verify_page]
```
Add this to any page to create a custom verification page.

### Password Reset Form
```
[nbuf_reset_form]
```
Add this to any page for a custom password reset form.

---

## WooCommerce Integration

### Protect Active Subscribers
1. Go to **Settings → User Foundry → Expiration**
2. Check "Prevent expiration for active subscriptions"
3. Users with active WooCommerce subscriptions won't expire

### Protect Recent Customers
1. Go to **Settings → User Foundry → Expiration**
2. Check "Prevent expiration for recent orders"
3. Set days to consider "recent" (default: 90)
4. Users with orders in the last X days won't expire

---

## Developer Documentation

### Hooks

**Post-Verification Hook:**
```php
add_action('nbuf_user_verified', function($email, $user_id) {
    // Send welcome email
    // Assign role
    // Grant access
}, 10, 2);
```

**User Expired Hook:**
```php
add_action('nbuf_user_expired', function($user_id) {
    // Log expiration
    // Send notification
    // Revoke access
}, 10, 1);
```

### Check User Status

**Is Verified:**
```php
$verified = NBUF_User_Data::is_verified($user_id);
```

**Is Disabled:**
```php
$disabled = NBUF_User_Data::is_disabled($user_id);
```

**Is Expired:**
```php
$expired = NBUF_User_Data::is_expired($user_id);
```

**Get Expiration Date:**
```php
$expires_at = NBUF_User_Data::get_expiration($user_id);
# Returns datetime string or null
```

### Manage Users Programmatically

**Set Verified:**
```php
NBUF_User_Data::set_verified($user_id);
```

**Set Unverified:**
```php
NBUF_User_Data::set_unverified($user_id);
```

**Disable Account:**
```php
NBUF_User_Data::set_disabled($user_id, 'manual');
# Reason: 'manual', 'expired', etc.
```

**Enable Account:**
```php
NBUF_User_Data::set_enabled($user_id);
```

**Set Expiration:**
```php
NBUF_User_Data::set_expiration($user_id, '2025-12-31 23:59:59');
```

**Remove Expiration:**
```php
NBUF_User_Data::set_expiration($user_id, null);
```

---

## Database Schema

### nbuf_tokens Table
Stores verification tokens with expiration dates.

```sql
{prefix}nbuf_tokens
- id (bigint, primary key)
- user_id (bigint)
- user_email (varchar 255, indexed)
- token (varchar 128, indexed)
- created_at (datetime)
- expires_at (datetime)
- verified (tinyint)
- is_test (tinyint)
```

### nbuf_user_data Table
Stores user verification, disabled status, and expiration data.

```sql
{prefix}nbuf_user_data
- user_id (bigint, primary key)
- is_verified (tinyint, indexed)
- verified_date (datetime)
- is_disabled (tinyint, indexed)
- disabled_reason (varchar 50)
- expires_at (datetime, indexed)
- expiration_warned_at (datetime)
```

---

## Cron Jobs

- **Hourly:** Check for expired accounts and auto-disable
- **Daily:** Send expiration warning emails
- **Daily:** Clean up expired verification tokens

---

## FAQ

### Will existing users need to verify their email?

No, only new users who register after plugin activation need to verify. Existing users are unaffected.

### Can I manually verify users?

Yes, use the "Mark as Verified" bulk action in the Users list, or edit the user profile.

### Does this work with WooCommerce?

Yes, it integrates seamlessly with WooCommerce registration. You can also prevent account expiration for active subscribers and recent customers.

### Are administrators affected by expiration?

No, users with the `manage_options` capability are never disabled or expired.

### What happens when an account expires?

The account is automatically disabled, all sessions are terminated, and the user cannot log in until the expiration is removed or the account is manually enabled.

### Can I bulk-manage users?

Yes! Filter users by verification status, account status, or expiration, then use bulk actions to manage multiple users at once.

---

## Performance

- Custom database tables with indexed columns
- Efficient queries (no N+1 problems)
- Admin classes only load in wp-admin
- Minimal front-end assets (CSS/JS only on verification pages)
- Automatic cleanup prevents database bloat

---

## Security

- Nonce verification on all forms
- Capability checks for admin functions
- Input sanitization and output escaping
- Prepared SQL statements
- One-time use tokens with expiration
- CSRF and XSS protection
- Session destruction on account disable

---

## Changelog

### 1.0.0
* Initial release
* Email verification system
* Account expiration system with automated enforcement
* User lifecycle management
* Custom database tables for performance
* Bulk user management actions
* Advanced user filtering
* WooCommerce integration
* Developer hooks and API
* Security hardened
* Performance optimized

---

## Support

* **GitHub:** [https://github.com/jcbenton/nobloat-user-foundry](https://github.com/jcbenton/nobloat-user-foundry)
* **Website:** [https://www.mailborder.com](https://www.mailborder.com)

---

## Author

Developed by **Jerry Benton** for **Mailborder Systems**
Website: [https://www.mailborder.com](https://www.mailborder.com)
GitHub: [https://github.com/jcbenton](https://github.com/jcbenton)

---

## License

This plugin is licensed under the GNU General Public License v3 or later.
See [https://www.gnu.org/licenses/gpl-3.0.html](https://www.gnu.org/licenses/gpl-3.0.html) for details.
