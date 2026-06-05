=== NoBloat User Foundry ===
Contributors: mailborder
Donate link: https://donate.stripe.com/3cIfZi81NbxX9CX4uybfO01
Tags: user manager, passkey, 2fa, authentication, role manager
Requires at least: 6.2
Tested up to: 7.0
Stable tag: 1.7.25
Requires PHP: 8.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Enterprise-grade user management for WordPress with email verification, 2FA, passkeys, roles, GDPR tools, audit logs, and lifecycle control.

== Description ==

NoBloat User Foundry is a comprehensive yet lightweight user management system for WordPress. It replaces bloated membership plugins with a focused, performant solution for email verification, two-factor authentication, account expiration, user profiles, full audit logs, and GDPR compliance. This plugin was specifically designed to not cause bloat within the WordPress database structure. It uses its own tables for all data except minimal required settings in wp_options. The uninstall options allows for a complete and total clean uninstall. 

= Core Features =

**Clean Structure**

* No extra WordPress pages. All structure is generated within and internal router.
* Clean CSS and JS that is automatically minified and only loaded on relevant pages.
* No third party libraries.
* No external API calls.
* No static images.
* Custom database tables - no wp_usermeta or wp_options bloat.
* Lazy class loading - only loads what's needed per request.
* Complete uninstall - removes all plugin data cleanly.
* Fully compliant with WordPress coding standards.

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

= 1.7.6 — WordPress 7.0 "Armstrong" compatibility =
* Tested against WordPress 7.0 (released 2026-05-20). No code changes required; this release bumps the compatibility header and documents the audit.
* Audit scope vs. WP 7.0 breaking changes: minimum-PHP-7.4 bump (plugin already requires PHP 8.0); HTML5 `script` theme support removal (plugin does not call `add_theme_support()` for `html5`); author-link function signature additions in `get_the_author_link()` / `the_author_link()` (plugin does not call these); Block API v3 iframed-editor enforcement (plugin is not a block plugin); Administrator/Editor removal from the General Settings "new user default role" UI (plugin uses `wp_dropdown_roles()` in its own settings panel — function API is unchanged).
* Real-Time Collaboration interaction: the content-restriction metabox is a classic `add_meta_box()` registration, which by design disables RTC for posts that have the metabox visible. This is the documented WP 7.0 behavior and is not a regression. Posts without restrictions enabled are unaffected.
* New WP 7.0 additive APIs (AI Client, Connectors, Client-Side Abilities, `customCSS`, `textIndent`, dimensions width/height, `autoRegister` PHP blocks) are not consumed by this plugin and require no integration.

= 1.7.5 =
* Bug fix (HIGH): Non-admins were redirected into /wp-admin/ after login despite `nbuf_login_redirect = "account"`. The submit handler only honored the POST'd `redirect_to`, so a `redirect_to` URL parameter inherited from a wp-admin link silently overrode the setting. wp-login.php native flow also ignored the setting (no `login_redirect` filter). New `NBUF_Hooks::sanitize_post_login_redirect()` unconditionally rewrites `/wp-admin/*` redirect targets to the account URL for non-admins (no setting required) — applied at every login redirect site (NBUF form, 2FA, magic-link, passkey, universal-router, ToS post-acceptance, password-expiration). New `login_redirect` filter (priority 999) catches the WP-native flow.
* Security (HIGH): `restrict_admin_access` cap-vs-role bypass (same pattern fixed in ToS gate for 1.7.1). Check is now role-based (`administrator` / multisite super-admin) rather than `manage_options` capability. Note: this gate (which controls whether non-admins may *browse* /wp-admin/ once there) still requires the `nbuf_restrict_admin_access` setting; only the post-login redirect rewrite is unconditional.

= 1.7.4 =
* Bug fix (HIGH): ToS acceptance form was bouncing back to the acceptance page in a loop (1.7.1 regression). The new `admin_init` ToS gate intercepted the form's POST to `/wp-admin/admin-post.php?action=nbuf_accept_tos` before `handle_acceptance` could run. The gate now allowlists that action; the handler still verifies its own nonce, pins to the active version, and refuses impersonated submissions.

= 1.7.3 — Group D forensic audit: 7 HIGH =
* BP migrator: unserialize with `allowed_classes => false` (object-injection / RCE-as-admin).
* Transient increment race: single `INSERT ... ON DUPLICATE KEY UPDATE`.
* Settings save: registry sanitizer identity check (closes tampered checkbox/array overwrite).
* Admin JS XSS via `.html()` — fixed across 6 admin scripts.
* Email subject CR/LF/NULL stripped; config import refuses unknown keys.
* CSS sanitizer decodes hex escapes before pattern matching, iterates to fixed-point.

= 1.7.2 — Group C closure =
* ToS gate fail-open on future-dated effective_date (HIGH).
* Webhook DNS-rebind via IPv6 AAAA — IP pinned via CURLOPT_RESOLVE (HIGH).
* ToS set_active_version: SELECT…FOR UPDATE row lock (HIGH).
* ToS update_version defers activate until row UPDATE commits (HIGH).
* ToS acceptance refused during admin impersonation (MEDIUM).
* Email-restriction rejects RFC 5321 domain-literals (MEDIUM).
* Webhook delivery_id replay nonce + scheme allowlist + batched cleanup (MEDIUM).
* TOTP setup rate-limit; password-change preserves "remember me" (MEDIUM).

= 1.7.1 — Group C: 4 functional fixes + 8 HIGH security =
* Fix: registration broken with antibot; impersonation-end binding double-hash; TOTP retry stale-secret loop; duplicate `nbuf_after_profile_update` fire.
* ToS exemption now ROLE check (closes manage_options-via-custom-role bypass).
* ToS gate covers wp-admin and admin-ajax (heartbeat/logout allowlist).
* Multi-role self-edit and role-manager parent_role respect cap-containment.
* 2FA partial-disable destroys other sessions; state changes audit-log on success + re-auth failure.
* Per-user rate limit on verify_reauth (15min / 10 attempts).
* Audit-log purge logged to immutable admin-audit-log; CSV formula-injection regex standardised.

= 1.7.0 — Group B forensic audit: 10 HIGH =
Multisite cap-meta leak in merger; PHP 8 stdClass regression in merger photo branch; version-history revert split admin/user tiers; GDPR Article-17 erasure removes 2FA material + login-limiting + sessions; public-profile cover-photo via validated path; directory excludes disabled/expired; NBUF_User::to_array applies SENSITIVE_FIELDS denylist; username changer fires profile_update + migrates login-attempts; image fallback always re-encodes (GIF/WebP polyglot + EXIF); delete_user_photo deletes file before DB row.

= 1.6.9 — 5 CRITICAL + 11 HIGH =
WebAuthn UV server-side enforcement; passkey user-binding sentinel; TOTP pinned-secret transient; re-setup preserves backup codes; device-trust rotation no longer fails open; pending-2FA cookie UA-bound; per-IP 2FA lockout; CBOR parser bounds; passkey origin handles subdirectory installs; magic-link IP rate-limit.

= 1.6.8 — 1.6.0 =
Earlier security and forensic-audit releases. Highlights: 1.6.8 verified-passkey skip 2FA toggle; 1.6.7 GDPR/impersonation/ToS-REST hardening; 1.6.6 multisite uninstall + activator gate; 1.6.5 merger PHP 8 + webhook SSRF + GDPR; 1.6.4 ToS active-version pinning (CRITICAL) + auth/sessions/restrictions; 1.6.3 safe-unserialize during merge; 1.6.0 PHP 8.0 baseline + SHA-256 tokens + magic-link/passkey/password-expiration/webhook SSRF hardening.

= Earlier versions =
Full per-version history (1.0.0 - 1.5.7) is in README.md / CHANGELOG.md on GitHub: https://github.com/jcbenton/nobloat-user-foundry

== Upgrade Notice ==

= 1.7.6 =
WordPress 7.0 "Armstrong" compatibility confirmed. No code changes required — the plugin's authentication, REST, shortcode, user, and capability surfaces are unaffected by the WP 7.0 breaking changes. "Tested up to" header bumped to 7.0.

= 1.7.5 =
Bug fix: non-admins were sent to /wp-admin/ after login despite the "After Login Redirect" setting. The post-login /wp-admin/ rewrite for non-admins is now unconditional — no setting required. Also fixes a cap-vs-role bypass in the wp-admin browse restriction.

= 1.7.4 =
Bug fix: ToS acceptance form was bouncing back to the acceptance page in a loop (1.7.1 regression) — non-admin users could not get past the Terms of Service screen. Fixed by allowlisting the acceptance form's POST through the admin_init gate.

= 1.7.3 =
Group D forensic audit closure: 7 HIGH findings (BP migration unserialize, transient race, settings sanitization, admin JS XSS, email header injection, config-import keys) plus CSS sanitizer hardening.

= 1.7.2 =
Group C closure: ToS gate fail-open on future-dated versions, webhook DNS-rebinding via IPv6 AAAA, ToS save races, impersonator-attribution, IP-literal email bypass, webhook replay nonce, password "remember me" preservation, TOTP rate limit.

= 1.7.1 =
Critical fixes: registration broken with antibot, impersonation-end binding, TOTP retry loop. 8 HIGH security findings: ToS gate cap-vs-role chain, role-manager parent_role inheritance, 2FA partial-disable, no rate-limit on password re-auth.

= 1.7.0 =
Group B forensic audit: 10 HIGH findings. Multisite cap-meta leak in merger, GDPR erasure now removes 2FA + login-limiting, version-history self-revert restricted, image fallback re-encodes, member directory excludes disabled.

= 1.6.9 =
5 CRITICAL + 11 HIGH findings in passkey/2FA/TOTP/device-trust. Server-side WebAuthn UV enforcement, TOTP setup no longer trusts client secret, device-trust no longer fails open, pending-2FA cookie UA-bound.

= 1.6.8 =
Verified-passkey logins now skip TOTP/email 2FA by default (passkey is already multi-factor). Toggle "Require 2FA After Passkey" in Security › 2FA Settings to keep both.

= 1.6.7 =
Closes deferred items from 1.6.4-1.6.6: GDPR export directory hardening, impersonation sudo-step, ToS REST gate, webhook payload out of cron args, admin-audit-log column sort.

= 1.6.6 =
Final batch of the full-codebase forensic audit. Multisite uninstall, activator capability gate, migration data-integrity, router redirect/path-traversal hardening.

= 1.6.5 =
Major hardening: merger PHP 8 compat, webhook SSRF, GDPR data exposure, profile-change notification rate limits, version-history allowlist, CSV-escape standardisation, bulk-import password handling.

= 1.6.4 =
Security and hardening release covering auth/sessions/impersonation/restrictions/registration/verification/password policy/ToS. Closes one CRITICAL ToS evidence-fabrication path plus dozens of HIGH/MEDIUM findings.

= 1.6.3 =
Closes PHP object-injection during account merge, hardens config-import deserialization, adds per-target cap + UA binding to impersonation, fixes referer bypass on photo uploads, translates admin strings.

= 1.6.2 =
Clears failed-login records after a successful password reset so users who tripped the rate limiter aren't blocked when logging in with their new password.

= 1.6.1 =
Bug fix release. Corrects login redirect_to propagation, fixes content restriction login redirect, and resolves a fatal in the [nbuf_universal] shortcode.

= 1.6.0 =
Security hardening. Requires PHP 8.0+. Fixes auth bypass in magic link login, account takeover in password expiration, SSRF in webhooks. Tokens stored as SHA-256 hashes.

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
