=== NoBloat User Foundry ===
Contributors: mailborder
Donate link: https://donate.stripe.com/14AdRa6XJ1Xn8yT8KObfO00
Tags: user manager, passkey, 2fa, authentication, role manager
Requires at least: 6.2
Tested up to: 6.9
Stable tag: 1.5.6
Requires PHP: 7.4
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Business focused user management with email verification, 2FA, passkeys, role management, GDPR, auditing, and lifecycle control.

== Description ==

NoBloat User Foundry is a comprehensive yet lightweight user management system for WordPress. It replaces bloated membership plugins with a focused, performant solution for email verification, two-factor authentication, account expiration, user profiles, full audit logs, and GDPR compliance. This plugin was specifically designed to not cause bloat within the Wordpress database structure. It uses its own tables for all data except minimal required settings in wp_options. The uninstall options allows for a complete and total clean uninstall. 

= Core Features =

**Clean Structure**

* No extra Wordpress pages. All structure is generated within and internal router.
* Clean CSS and JS that is automatically minified and only loaded on relevant pages.
* No third party libraries.
* No external API calls.
* No static images.
* Custom database tables - no wp_usermeta or wp_options bloat.
* Lazy class loading - only loads what's needed per request.
* Complete uninstall - removes all plugin data cleanly.
* Fully compliant with Wordpress coding standards.

**Email Verification**

* Automatic verification emails on registration
* Unique token-based verification links
* Customizable email templates (HTML and plain text)
* Manual admin verification and bulk actions
* Token expiration and automatic cleanup

**Two-Factor Authentication (2FA)**

* Email-based 2FA with 6-digit codes
* TOTP authenticator app support (Google Authenticator, Authy, etc.)
* Backup codes for account recovery
* Device trust (remember this device for 30 days)
* Role-based 2FA enforcement with grace periods
* Lockout protection after failed attempts

**Passkeys/WebAuthn**

* Passwordless authentication
* Multiple passkeys per user
* Pure PHP implementation (no external dependencies)
* AJAX-based registration and authentication

**Account Expiration**

* Set expiration dates for user accounts
* Automatic account disabling via scheduled tasks
* Pre-expiration warning emails (configurable days before)
* WooCommerce integration: protect active subscribers
* WooCommerce integration: protect recent customers

**User Profiles**

* Extended profile fields (phone, company, address, bio)
* Profile photos (custom upload, Gravatar, or SVG initials)
* Cover photos
* Privacy controls (public, members-only, private)
* Profile version history with diff comparison
* Revert to previous profile versions

**Member Directory**

* Public-facing member listing
* Search by name, email, or bio
* Filter by role
* Pagination support
* Respects user privacy settings

**GDPR & Privacy**

* User-initiated data export
* Admin-initiated data export
* Account deletion with data anonymization
* Privacy policy management
* WordPress privacy tools integration
* Audit log anonymization on deletion

**Magic Links**

* Passwordless email login links
* Configurable link expiration (default 15 minutes)
* Rate limiting to prevent abuse
* One-time use tokens
* Works with all other security features

**User Impersonation**

* Admins can log in as any user for support
* Full audit trail of impersonation sessions
* Sticky banner showing impersonation status
* One-click return to admin account
* Capability-based access control

**Email Domain Restrictions**

* Whitelist or blacklist email domains for registration
* Wildcard subdomain support (*.example.com)
* Customizable rejection messages
* Security log integration

**IP Restrictions**

* Whitelist or blacklist IP addresses for login
* CIDR notation support (192.168.1.0/24)
* Trusted proxy configuration for load balancers/CDNs
* Works with Cloudflare, AWS, Nginx, and more

**Terms of Service**

* Version-controlled Terms of Service
* Track user acceptance with timestamps
* Require acceptance on login for new versions
* Configurable grace periods
* Export acceptance records to CSV

**Session Management**

* View all active login sessions
* Device and browser detection
* Revoke individual sessions
* "Log out everywhere" option
* Current session protection

**Activity Dashboard**

* Timeline view of account activity
* Security events (logins, password changes, 2FA)
* Admin dashboard widget with site-wide stats
* Paginated activity history

**Security Features**

* Login attempt limiting with IP-based lockouts
* Password strength requirements (length, complexity)
* Password expiration with forced changes
* Anti-bot protection (honeypot, timing, JavaScript validation)
* Application passwords for API access
* Security event logging

**Admin Features**

* Enhanced Users list with status columns
* Bulk actions: verify, disable, enable, set expiration
* Advanced filters with live counts
* User notes/admin comments
* Account merger tool
* Import from Ultimate Member and BuddyPress
* Comprehensive audit logging

**Access Restrictions**

* Menu item visibility by role or login status
* Content restrictions by role
* Widget visibility controls
* Taxonomy/category restrictions
* Hide restricted content from archives

**Webhooks**

* Send HTTP POST notifications on user events
* Configurable events: registration, verification, login, profile updates
* HMAC-SHA256 signature verification
* Webhook delivery logging
* Auto-disable after consecutive failures

**Custom Account Tabs**

* Add custom tabs to the frontend account page
* Shortcode content support (WooCommerce, EDD, etc.)
* Role-based tab visibility
* Optional Dashicon icons
* Drag-and-drop reordering
* Priority-based sorting

**Email System**

* Customizable email templates
* HTML and plain text modes
* Custom sender address and name
* Placeholder support: {site_name}, {username}, {verify_link}, etc.

= Shortcodes =

* `[nbuf_login_form]` - Custom login form
* `[nbuf_registration_form]` - Registration form
* `[nbuf_reset_form]` - Password reset form
* `[nbuf_request_reset_form]` - Request password reset
* `[nbuf_verify_page]` - Email verification page
* `[nbuf_account_page]` - User account dashboard
* `[nbuf_profile]` - Display user profile
* `[nbuf_members]` - Member directory
* `[nbuf_2fa_verify]` - 2FA verification form
* `[nbuf_totp_setup]` - TOTP authenticator setup
* `[nbuf_logout]` - Logout button
* `[nbuf_restrict]` - Restrict content by role/login
* `[nbuf_data_export]` - GDPR data export form
* `[nbuf_magic_link_form]` - Magic link request form

= Universal Router =

Virtual page routing at `/user-foundry/` (configurable URL) for:

* `/user-foundry/login/`
* `/user-foundry/register/`
* `/user-foundry/account/`
* `/user-foundry/profile/`
* `/user-foundry/verify/`
* `/user-foundry/forgot-password/`
* `/user-foundry/reset-password/`
* `/user-foundry/2fa/`
* `/user-foundry/2fa-setup/`
* `/user-foundry/members/`
* `/user-foundry/magic-link/`
* `/user-foundry/accept-tos/`
* `/user-foundry/logout/`

No WordPress pages required - URLs work automatically.

= Developer Features =

* PSR-4 autoloader for optimal performance
* Unified User API with caching (`NBUF_User::get()`)
* Extensive hooks and filters
* Custom database tables with indexed columns
* Isolated options table (no wp_options bloat)
* Well-documented codebase

= Performance =

* Lazy class loading (only loads what's needed)
* Three-tier caching (memory, object cache, database)
* Redis and Memcached compatible (works with popular object cache plugins)
* Single-query option preloading
* Request-level caching to eliminate duplicate queries
* Minified CSS with on-disk caching
* Conditional asset loading

= Security =

* Nonce verification on all forms
* Capability checks for admin functions
* Input sanitization and output escaping
* Prepared SQL statements
* CSRF and XSS protection
* Timing attack prevention
* Brute force protection

== Installation ==

= Automatic Installation =

1. Log in to your WordPress admin panel
2. Navigate to **Plugins > Add New**
3. Search for "NoBloat User Foundry"
4. Click **Install Now**, then **Activate**
5. Configure at **User Foundry > Settings**

= Manual Installation =

1. Download the plugin ZIP file
2. Upload `nobloat-user-foundry` folder to `/wp-content/plugins/`
3. Activate through the Plugins menu
4. Configure at **User Foundry > Settings**

== Frequently Asked Questions ==

= Will existing users need to verify their email? =

No, only new users who register after activation need to verify. Use bulk actions to verify existing users if needed.

= Does this work with WooCommerce? =

Yes, it integrates with WooCommerce registration and can protect active subscribers and recent customers from account expiration.

= Can I customize the emails? =

Yes, all email templates are customizable with support for placeholders like {site_name}, {username}, {verify_link}, etc.

= How does 2FA work? =

Users can enable email-based codes, TOTP authenticator apps, or both. Admins can require 2FA for specific roles with configurable grace periods.

= What happens when an account expires? =

The account is automatically disabled, all sessions are terminated, and the user cannot log in until the expiration is removed.

= Is it GDPR compliant? =

Yes, the plugin includes user data export, account deletion with anonymization, and integrates with WordPress privacy tools.

= Can I migrate from Ultimate Member or Buddypress? =

Yes, there's a built-in migration tool that imports users, profile fields, and access restrictions.

== Documentation ==

Full documentation:
https://docs.mailborder.com/nobloat-user-foundry

Configuration guides, troubleshooting, and examples are available online.

== Screenshots ==

1. Users list with verification, status, and expiration columns
2. Two-factor authentication setup
3. Member directory with search
4. User profile with privacy controls
5. Settings: Security configuration
6. Email template editor
7. Webhook configuration
8. GDPR data export

== Changelog ==

= 1.5.6 =
* Added: Password Reset email templates to editor (replaces WordPress default)
* Added: Admin New User Notification templates to editor
* Added: Account Expiration Notice templates to editor
* Added: Security Alert email template to editor
* Improved: All template subtab files now use Template Manager for consistent DB-first loading
* Improved: JavaScript template reset with fallback for simplified form field names
* Fixed: Anti-bot settings link now correctly points to Security > Registration
* Fixed: Added missing `nbuf_is_reserved_username` filter to hooks documentation
* Fixed: Updated example date in API documentation

= 1.5.5 =
* Security: Consolidated IP address handling into new NBUF_IP utility class
* Security: IPv6 normalization prevents rate limit bypass via address variations
* Security: Trusted proxy configuration for load balancer/CDN setups
* Security: MySQL lock cleanup with try-finally pattern
* Security: Fail-safe database query handling in login limiting
* Security: Consistent GMT/UTC timestamp handling throughout
* Improved: Extensive PHPCS compliance updates (80+ files)
* Improved: Return type declarations for better type safety
* Improved: DocBlocks added to anonymous callback functions
* Improved: Code architecture refinements
* Improved: Account page and admin UI styling
* Fixed: Removed hardcoded /contact URL from expiration templates
* Fixed: Grace period logic with write side effects in getter
* Fixed: Alignment warnings in admin list tables

= 1.5.0 =
* Added: Custom account page tabs with shortcode content
* Added: Role-based tab visibility restrictions
* Added: Drag-and-drop tab reordering
* Added: Dashicon support for custom tabs
* Added: Rate limiting for passkey authentication endpoints
* Improved: Template backward compatibility
* Security: Rate limiting on pre-login passkey AJAX endpoints

= 1.4.1 =
* Added: Webhooks for external integrations
* Added: 10 webhook events (registration, login, profile updates, etc.)
* Added: HMAC-SHA256 webhook signatures
* Added: Webhook delivery logging and auto-disable
* Fixed: Plugin validation warnings

= 1.4.0 =
* Added: Profile version history with diff comparison
* Added: Revert to previous profile versions
* Added: Password expiration system
* Added: Multi-role user assignment
* Added: GDPR data export enhancements
* Improved: Admin audit logging

= 1.3.0 =
* Added: Change notifications for profile updates
* Added: Digest mode for admin notifications
* Improved: Email template system

= 1.2.0 =
* Added: Anti-bot protection (honeypot, timing, JavaScript)
* Added: Proof of work challenges
* Improved: Registration security

= 1.1.0 =
* Added: Unified User API with caching
* Added: Batch user loading for admin lists
* Improved: Performance optimizations

= 1.0.0 =
* Initial release
* Email verification system
* Two-factor authentication (email, TOTP, backup codes)
* Passkeys/WebAuthn support
* Account expiration with WooCommerce integration
* User profiles with privacy controls
* Member directory
* Login limiting and security features
* GDPR compliance tools
* Access restrictions (menus, content, widgets)
* Custom database tables
* Universal router for virtual pages

== Upgrade Notice ==

= 1.5.6 =
Adds missing email templates to the editor (Password Reset, Admin Notification, Expiration Notice, Security Alert). No database changes required.

= 1.5.5 =
Security and code quality release. Consolidated IP handling, PHPCS compliance, and various fixes. No database changes required.

= 1.5.0 =
Adds custom account page tabs for integrating third-party plugin content (WooCommerce, EDD, etc.). No database migration required.

= 1.4.1 =
Adds webhook support for external integrations. Database tables are automatically created on upgrade.

== Additional Information ==

= Database Tables =

The plugin creates these custom tables:

* `nbuf_tokens` - Verification tokens
* `nbuf_user_data` - User status and expiration data
* `nbuf_options` - Plugin settings (isolated from wp_options)
* `nbuf_user_profile` - Extended profile fields
* `nbuf_login_attempts` - Login attempt tracking
* `nbuf_user_2fa` - 2FA configuration
* `nbuf_user_passkeys` - WebAuthn passkeys
* `nbuf_user_audit_log` - User activity log
* `nbuf_admin_audit_log` - Admin actions log
* `nbuf_user_notes` - Admin notes per user
* `nbuf_import_history` - Migration/import tracking
* `nbuf_profile_versions` - Profile history snapshots
* `nbuf_security_log` - Security events
* `nbuf_webhooks` - Webhook configuration
* `nbuf_webhook_log` - Webhook delivery log
* `nbuf_menu_restrictions` - Menu visibility rules
* `nbuf_content_restrictions` - Content visibility rules
* `nbuf_user_roles` - Custom role management
* `nbuf_tos_versions` - Terms of Service versions
* `nbuf_tos_acceptances` - User ToS acceptance records

= Hooks =

**Actions:**

* `nbuf_user_verified` - User email verified
* `nbuf_user_expired` - Account expired
* `nbuf_user_disabled` - Account disabled
* `nbuf_user_enabled` - Account enabled
* `nbuf_user_approved` - Account approved
* `nbuf_2fa_enabled` - 2FA enabled
* `nbuf_2fa_disabled` - 2FA disabled

**Filters:**

* `nbuf_verification_email_subject`
* `nbuf_verification_email_message`
* `nbuf_password_requirements`
* `nbuf_profile_fields`

= Uninstall =

When deleted, the plugin removes:

* All custom database tables
* All plugin options
* Scheduled cron jobs

User accounts are preserved but plugin data is removed.

= Support =

* GitHub: [https://github.com/jcbenton/nobloat-user-foundry](https://github.com/jcbenton/nobloat-user-foundry)

== Privacy Policy ==

NoBloat User Foundry stores user data for management functionality:

* Email verification status and dates
* Account status (enabled, disabled, expired)
* Profile information (phone, company, address, bio)
* Security data (2FA settings, login attempts)
* Audit logs (actions and events)

Data is stored in custom database tables and can be exported or deleted via GDPR tools. The plugin does not share data with third parties or external services (except user-configured webhooks).
