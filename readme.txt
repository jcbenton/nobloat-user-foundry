
=== NoBloat User Foundry ===
Contributors: nobloat
Donate link: https://donate.stripe.com/14AdRa6XJ1Xn8yT8KObfO00
Tags: user management, email verification, account expiration, user lifecycle, registration
Requires at least: 5.8
Tested up to: 6.8
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Lightweight WordPress user management system - email verification, account expiration, and user lifecycle management without the bloat.

== Description ==

NoBloat User Foundry is a comprehensive yet lightweight user management system for WordPress. It provides email verification, account expiration, bulk user management, and complete user lifecycle control without unnecessary bloat.

= Key Features =

* **Email verification** - New users must verify their email before logging in
* **Account expiration** - Set expiration dates for users with automatic enforcement
* **User lifecycle management** - Complete control over user accounts from registration to expiration
* **Bulk user operations** - Manage verification, disabled status, and expiration for multiple users
* **Advanced filtering** - Filter users by verification status, account status, and expiration
* **Custom verification pages** - Use your theme's design for verification and password reset pages
* **WooCommerce protection** - Optionally prevent expiration for active subscribers and recent customers
* **Email notifications** - Automated warning emails before account expiration
* **Admin dashboard** - Comprehensive user management interface
* **Customizable templates** - Edit all email templates (HTML and plain text)
* **Automatic cleanup** - Expired tokens and scheduled enforcement via cron jobs

= How It Works =

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

= Shortcodes =

* `[nbuf_verify_page]` - Email verification page
* `[nbuf_reset_form]` - Password reset form

= Admin Features =

* Verified status column in Users list
* Account status (Enabled/Disabled) column
* Expiration date/time column with visual indicators
* Filter dropdowns with live counts
* Bulk actions: Verify, Disable, Enable, Set/Remove Expiration
* User profile meta box for expiration management
* Settings page with multiple tabs
* Email template editor with live preview

= Developer Friendly =

* Hook: `nbuf_user_verified` - Fires after successful verification
* Hook: `nbuf_user_expired` - Fires when user auto-disabled
* Clean, procedural code (OOP only where necessary)
* Well-documented with section comments
* Extendable via WordPress hooks and filters
* Custom database table for performance

= Security =

* Nonce verification on all forms
* Capability checks for admin functions
* Input sanitization and output escaping
* Prepared SQL statements
* One-time use tokens with expiration
* CSRF and XSS protection
* Session destruction on account disable

= Performance =

* Custom database table with indexed columns
* Efficient queries (no N+1 problems)
* Conditional loading (admin classes only load in wp-admin)
* Minimal front-end JavaScript
* Automatic cleanup prevents database bloat

== Installation ==

= Automatic Installation =

1. Log in to your WordPress admin panel
2. Navigate to **Plugins → Add New**
3. Search for "NoBloat User Foundry"
4. Click **Install Now**, then **Activate**
5. Configure settings at **Settings → User Foundry**

= Manual Installation =

1. Download the plugin ZIP file
2. Upload the `nobloat-user-foundry` folder to `/wp-content/plugins/`
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Configure settings at **Settings → User Foundry**

= GitHub Installation =

Download the latest release from [GitHub](https://github.com/jcbenton/nobloat-user-foundry) and follow the manual installation steps above.

== Frequently Asked Questions ==

= Will existing users need to verify their email? =

No, only new users who register after plugin activation need to verify. Existing users are unaffected.

= Can I manually verify users? =

Yes, use the "Mark as Verified" bulk action in the Users list, or edit the user profile.

= Does this work with WooCommerce? =

Yes, it integrates seamlessly with WooCommerce registration. You can also prevent account expiration for active subscribers and recent customers.

= Can I customize the emails? =

Yes, edit all email templates at **Settings → User Foundry → Templates**. Available placeholders: {site_name}, {display_name}, {verify_link}, {user_email}, {username}, {expires_date}.

= How does account expiration work? =

Set an expiration date on any user profile. The system sends warning emails X days before expiration (configurable), then automatically disables the account on the expiration date. WooCommerce customers can be protected from expiration.

= Can I bulk-manage users? =

Yes! Filter users by verification status, account status, or expiration, then use bulk actions to verify, disable, enable, or set/remove expiration for multiple users at once.

= Are administrators affected by expiration? =

No, users with the `manage_options` capability are never disabled or expired.

= What happens when an account expires? =

The account is automatically disabled, all sessions are terminated, and the user cannot log in until the expiration is removed or the account is manually enabled.

= How long are verification tokens valid? =

Tokens expire after 24 hours. Expired tokens are automatically deleted via daily cron job.

= Does this work with custom registration forms? =

Yes, fire `do_action('user_register', $user_id)` after creating users.

== Screenshots ==

1. Users list with verification, status, and expiration columns
2. Filter dropdowns with live user counts
3. Bulk actions for managing multiple users
4. User profile expiration meta box with datepicker
5. Settings: General tab configuration
6. Settings: Expiration tab with WooCommerce integration
7. Settings: Email templates editor
8. Verification page with theme styling

== Changelog ==

= 1.0.0 =
* Initial release
* Email verification system
* Account expiration system with automated enforcement
* User lifecycle management
* Custom database table for performance
* Bulk user management actions
* Advanced user filtering (verification, status, expiration)
* Expiration date picker on user profiles
* Warning emails before expiration
* WooCommerce integration (protect subscribers and recent customers)
* Admin columns: Verified, Disabled, Expires
* Custom verification and password reset pages
* HTML and plain text email templates
* Automated cleanup via cron jobs
* Security: nonces, capability checks, sanitization, escaping
* Performance: indexed queries, conditional loading
* Developer hooks: nbuf_user_verified, nbuf_user_expired

== Upgrade Notice ==

= 1.0.0 =
Initial release of NoBloat User Foundry - comprehensive user management without bloat.

== Additional Information ==

= Database =

The plugin creates two custom tables:
* `{prefix}nbuf_tokens` - Verification tokens with expiration dates
* `{prefix}nbuf_user_data` - User verification, disabled, and expiration data

Both tables use indexed columns for optimal performance.

= Cron Jobs =

* **Hourly:** Check for expired accounts and auto-disable
* **Daily:** Send expiration warning emails
* **Daily:** Clean up expired verification tokens

= Uninstall =

When you delete the plugin, the following are removed:
* Custom database tables
* All plugin options
* Email templates
* Scheduled cron jobs

No user accounts are deleted, but verification and expiration data is removed.

= Support =

* GitHub: [https://github.com/jcbenton/nobloat-user-foundry](https://github.com/jcbenton/nobloat-user-foundry)
* Website: [https://www.mailborder.com](https://www.mailborder.com)

= Developer Documentation =

**Post-Verification Hook:**
```
add_action('nbuf_user_verified', function($email, $user_id) {
    // Your code here
}, 10, 2);
```

**User Expired Hook:**
```
add_action('nbuf_user_expired', function($user_id) {
    // Your code here
}, 10, 1);
```

**Check Verification Status:**
```
$verified = NBUF_User_Data::is_verified($user_id);
```

**Check if Account Expired:**
```
$expired = NBUF_User_Data::is_expired($user_id);
```

**Manual Verification:**
```
NBUF_User_Data::set_verified($user_id);
```

**Set Expiration:**
```
NBUF_User_Data::set_expiration($user_id, '2025-12-31 23:59:59');
```

== Privacy Policy ==

NoBloat User Foundry stores the following user data:

* Email address (in tokens table temporarily, until verified)
* Verification status and date
* Account disabled status and reason
* Account expiration date and warning status

This data is used solely for user management functionality. Verification tokens are deleted after use or expiration. All data can be removed via plugin deletion.

The plugin does not:
* Share data with third parties
* Track user behavior outside of core functionality
* Set cookies
* Send data to external services

== Credits ==

Developed by Jerry Benton for Mailborder.
