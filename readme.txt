=== NoBloat User Foundry ===
Contributors: mailborder
Donate link: https://donate.stripe.com/3cIfZi81NbxX9CX4uybfO01
Tags: user manager, passkey, 2fa, authentication, role manager
Requires at least: 6.2
Tested up to: 6.9
Stable tag: 1.6.6
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

= 1.6.6 =
* Security (HIGH): Multisite-aware uninstall iterates every site so per-blog tables, options, and cron events are cleaned up (previously left orphaned tables on networks)
* Security (HIGH): Activator gains a defense-in-depth capability gate (manage_network_plugins on multisite, activate_plugins on single-site) to block schema/option mutations when invoked with insufficient privileges
* Security (HIGH): Ultimate Member migrator format array now resolved per-key (was a fixed positional list that silently coerced is_disabled / requires_approval flags during data migration)
* Security: BuddyPress profile migrator filters field_mapping_override target columns through NBUF_Profile_Data field whitelist
* Security: Options migration is now two-phase so partial copy failures retain wp_options copies for retry (no more silent settings loss on activation race)
* Security: nbuf_db_version sentinel now binds to NBUF_VERSION so dbDelta re-runs on every release
* Security: Universal-router custom-redirect strict scheme regex + wp_validate_redirect; redirect_to host validation at wp-login.php intercept; password-reset link rewrite uses preg_replace_callback with single match
* Security: Universal-router path-traversal canonicalisation rejects empty/./../NUL segments
* Security: Diagnostics export and table-repair handlers now write admin-audit-log entries
* Standards: View titles wrapped in __() with wp_strip_all_tags applied to pre_get_document_title

= 1.6.5 =
* Security (CRITICAL): Account merger PHP 8 fatal — array-offset access on stdClass returned by NBUF_User_Data::get() across six call sites silently disabled photo-conflict / MIME-recheck paths
* Security (HIGH): Webhook SSRF AAAA-record validation + IPv6 private/loopback/link-local/unique-local/IPv4-mapped private range coverage
* Security (HIGH): Privacy export file URL leak fixed via realpath containment + extension allowlist + cross-reference recorded photo paths
* Security: Webhook log retention cron added (default 30 days, filterable)
* Security: Webhook secret minimum length enforced (16 bytes)
* Security: Profile-change notification per-user rate limit + plain-text mode + CRLF-stripped subject
* Security: Test-webhook endpoint rate limited per admin (5/min)
* Security: Email-change race window closed (delete prior tokens BEFORE set_pending_email)
* Security: Pending-email change notification sent to old address; password-confirm audit logging
* Security: Data-export rate limit (3/hour by default, filterable)
* Security: nbuf_after_profile_update extension API hardened (sanitized data, not raw $_POST)
* Security: render_profile_page privacy gate; multi-role admin escalation guard for non-manage_options actors
* Security: Username-changer enforces illegal_user_logins blocklist + nicename collision suffix
* Security: NBUF_User user_pass / user_activation_key deny-list on __get
* Security: AJAX directory per-field privacy filter; directory rate-limit + length cap
* Security: CSV escape standardised across audit-log + admin-audit-log (catches `|` pipe + leading-quote/whitespace bypass)
* Security: Version-history revert mass-assignment column allowlist + admin audit log entry; ajax_get_version_diff rate limit; ajax_revert_version cross-checks user_id
* Security: User-notes capability gate aligned (manage_options); printf escape consistency
* Security: handle_bulk_delete defense-in-depth cap recheck
* Security: Settings.php migration uses safe-unserialize; CSS sanitizers via NBUF_CSS_Manager::sanitize_css; role manager native-role guard
* Security: Template-manager email http-equiv removed; page-template style attribute removed
* Security: Bulk-import password C0 control strip + 256-char cap; preview strips plaintext passwords
* Security: Email-restrictions IDN/punycode + trailing-dot canonicalisation
* Standards: Audit-log retention whitelist; numerous i18n / phpcs cleanups

= 1.6.4 =
* Security (CRITICAL): ToS handle_acceptance now pins the posted version_id to the currently active ToS version, blocking fabricated-evidence and stale-form attacks
* Security: ToS handle_acceptance now enforces the affirmative-consent checkbox server-side (was client-side only)
* Security: ToS set_active_version is now transactional with rollback, preventing silent zero-active state from interleaved admin saves
* Security: ToS-admin CSV export escape now blocks leading-whitespace formula-injection bypass
* Security: ToS effective_date validated via DateTime::createFromFormat on create/update so malformed input cannot fatal the public acceptance page
* Security: Passkey verify_authentication now enforces the user-binding transient set at options time, closing a credential-confusion attack on shared devices
* Security: TOTP verify_code returns the matched counter; verify_totp_code records that counter (not the verifier clock) and runs under per-user GET_LOCK to defeat replay within the tolerance window
* Security: Login limiting normalises usernames to lowercase before insert/select so case variation no longer bypasses the per-username distributed brute-force threshold
* Security: 2FA email/TOTP/backup-code lockouts now use method-specific window settings instead of all sharing the email window
* Security: Magic-link verify_magic_link checks delete result before COMMIT and rolls back on DB error or zero-rows
* Security: Magic-link send_magic_link defers SMTP via wp_schedule_single_event so synchronous response time is uniform between real-user and unknown-email submissions
* Security: Magic-link verifier collapses distinct account-state messages to one generic string (specific reason still logged for operators)
* Security: Magic-link verifier lower-cases token before SHA-256 (uppercase paste no longer silently fails)
* Security: Passkey clone-detection security log entry severity raised to critical and argument order corrected (admin alert now fires)
* Security: Passkey ajax_check_user_passkeys now also throttles per-username so IP rotation cannot enumerate the entire user base
* Security: 2FA grace period now starts when role is granted (set_user_role / add_user_role) rather than at the next login
* Security: 2FA pending transient is cleared when user hits the 2FA lockout threshold
* Security: wp_login_failed now fires on 2FA failure so IP-level rate limiter sees them
* Security: Backup-code failures use the generic 2FA lockout window instead of the email window
* Security: Device-trust GET_LOCK is now per-token (not just per-user) with 5-second timeout
* Security: Login-limiting clear_attempts_on_password_reset no longer wipes ALL rows for the requester's IP (previously cleared lockouts on co-victims being attacked from the same IP)
* Security: Login-limiting distinguishes missing-table from query-error: missing table fail-opens with critical log instead of locking every user out site-wide
* Security: TOTP base32_decode rejects invalid input instead of silently dropping characters
* Security: Passkey set_transient failure now surfaces as WP_Error('storage_failed') instead of silent no-op
* Security: Passkey-prompt is_ssl checked before consuming the trigger transient (no more silent UX cycle waste)
* Security: Passkey-prompt AJAX endpoints trust the nbuf_device_id cookie, not POST input (closes self-DoS on dismissed-list)
* Security: Passkeys-login redirect uses strict https?:// regex (rejects httpfoo://evil.example/)
* Security: Passkey ajax_authenticate redirect default uses NBUF_Passkeys_Login::get_redirect_url() instead of admin_url() (correct destination for non-admin users)
* Security: Impersonation can_impersonate_user now uses user_can() per cap loop instead of allcaps array_diff so dynamically-granted caps from user_has_cap filters (membership/multi-role plugins, BuddyPress) are honored
* Security: Impersonation get_impersonation_data requires both IP and User-Agent to be non-empty at start; uses NBUF_IP::get_client_ip(true) for normalised IPv6 comparison
* Security: Impersonation start path captures original_session_token_hash; end path verifies that hash matches an active session of the original user before restoring auth (closes transient-injection -> set_auth_cookie primitive)
* Security: Impersonation end-path capability check moved before wp_clear_auth_cookie so a permission revocation mid-session no longer locks the admin out completely
* Security: Sessions revoke_session and revoke_other_sessions now require ownership or edit_user($user_id) capability; revoke_other_sessions verifies destroy_others actually reduced the count
* Security: IP-restrictions admin_bypass default flipped from true to false (eliminates admin-username-to-role oracle on new installs); timing balanced in the bypass branch
* Security: Restrictions module now hooks delete_post and delete_nav_menu_item to clean up orphaned restriction rows (prevents auto_increment ID collision after DB import rebinding stale restrictions)
* Security: Restriction-content filter_rest_content returns WP_Error 403 instead of just blanking content/excerpt (closes title/meta/ACF/raw-content REST data leak)
* Security: Restriction-content access_denied_message security log gated by is_singular() and uses log_or_update (eliminates per-render log-spam DoS)
* Security: Restriction-taxonomy get_excluded_term_ids SQL filters by visibility allowlist so corrupted/old-format meta values cannot break archive listings
* Security: Restriction-menu two-pass filter walks ancestor chain regardless of item order (block-based menus, custom Walkers, reordered iterators)
* Security: NBUF_IP XFF chain walk skips empty fields
* Security: Restriction-content / restriction-taxonomy redirect URLs use esc_url_raw() (correct for wp_safe_redirect)
* Security: Password-expiration update_password_changed_date no longer auto-clears force_password_change (admin-forced rotations can no longer be silently undone via password_reset hook flows)
* Security: Password-expiration change-token transient cleaned up on the logged-in form path too (closes browser-history replay window)
* Security: Password-validator weak-password "every" timing setting no longer resets the grace clock on every login
* Security: Registration validate_registration_data now enforces the antibot challenge so any caller of register_user (REST/CLI/extensions) is protected
* Security: Shortcodes email-change flow deletes any prior unredeemed email_change tokens before issuing a new one
* Security: Verifier email-change comparison canonicalises via sanitize_email + strtolower; failed wp_update_user now writes an email_change_failed audit row
* Performance: Expiration cron capped to 100 users per run by default (filterable via nbuf_expiration_batch_cap) to prevent SMTP saturation on long-stalled sites

= 1.6.3 =
* Security: Removed maybe_unserialize() of attacker-influenced usermeta during account merge (prevents PHP object instantiation / POP-gadget surface); decoding now uses allowed_classes => false where needed
* Security: Consolidated all visible_fields decoding through a single safe helper that disallows class instantiation (closes inconsistency between public profile, account profile, and profile photo settings call sites)
* Security: NBUF_Options now decodes stored option values via a safe-unserialize helper (allowed_classes => false), and the config importer rejects pre-serialized strings to prevent stored object-injection
* Security: force_logout_all_devices AJAX handler now requires per-target edit_user capability and blocks non-super-admins from terminating super-admin sessions on multisite
* Security: Bulk import refuses to assign the administrator role on multisite unless the importer is a network super admin (covers both the explicit-role and default-role code paths)
* Security: Config importer now also rejects pre-serialized template payloads, matching the rejection already applied to settings
* Security: Impersonation session now validates the bound User-Agent (in addition to IP) using hash_equals, matching the documented behavior
* Security: Profile and cover photo upload referer check now compares hostnames exactly (was a strpos prefix check that could be bypassed by `victim.com.attacker.tld`)
* Standards: Wrapped 30+ user-facing AJAX/wp_die/WP_Error strings in __() / esc_html__() with the nobloat-user-foundry text domain
* Standards: Donate link, license declaration, and trademark casing synchronized between readme.txt and the main plugin header
* Standards: Sanitized $_SERVER['REQUEST_METHOD'] reads in 2FA login and password expiration handlers
* Cleanup: Fixed dead echo in docs overview tab; corrected indentation and block-comment style on several phpcs:ignore lines

= 1.6.2 =
* Fix: Failed login attempts are now cleared after a successful password reset, so users who tripped the rate limiter while forgetting their password can log in immediately with the new password instead of being blocked by the brute-force lockout

= 1.6.1 =
* Fix: Login redirect_to parameter now preserved when wp-login.php intercept redirects to NoBloat login page
* Fix: Login form reads redirect_to URL parameter so users return to their intended page after login (content restrictions, bookmarks)
* Fix: Content restriction redirect uses NoBloat login URL instead of wp-login.php when NoBloat login is active
* Fix: NBUF_URL::is_universal_mode() method was undefined, causing a fatal error when the [nbuf_universal] shortcode ran

= 1.6.0 =
* **Breaking:** Minimum PHP version raised from 7.4 to 8.0 (PHP 7.4 reached EOL November 2022)
* Security: All verification and magic link tokens now stored as SHA-256 hashes (DB read no longer yields replayable credentials)
* Security: Encryption hard-fails when OpenSSL unavailable instead of silently storing plaintext
* Security: 2FA enable/disable/backup code regeneration now requires password re-authentication
* Security: Magic link login enforces disabled/expired/unverified/2FA checks (previously bypassed all login protections)
* Security: Passkey login enforces admin-mandated 2FA policy via should_challenge()
* Security: Password expiration form uses cryptographic token instead of predictable user_id (prevents account takeover)
* Security: Webhook delivery protected against SSRF (private IP blocking + wp_safe_remote_post for DNS rebinding)
* Security: Content restrictions enforced on REST API responses (previously bypassed via /wp-json/)
* Security: X-Forwarded-For parsed right-to-left to prevent IP spoofing behind trusted proxies
* Security: All IP retrieval consolidated to NBUF_IP::get_client_ip() (audit log, admin audit, ToS, version history, magic links)
* Security: Role editor restricts capability assignment to caps the admin user holds
* Security: Impersonation compares full capability sets, blocks nested impersonation, destroys target session on end
* Security: Antibot challenge tokens consumed after use to prevent replay attacks
* Security: TOTP replay protection via last-used counter tracking
* Security: Registration timing-attack dummy hash moved to correct branch
* Security: Config import/export uses allowed_classes=false on unserialize, validates nbuf_ prefix
* Security: CSV formula injection protection on admin audit log and ToS acceptance exports
* Security: Encryption fallback key uses random bytes instead of guessable siteurl+admin_email
* Security: Image upload rejects decompression bombs (>25 megapixel limit)
* Security: GDPR export uses user ID in filesystem paths, hash_equals for token comparison, symlink protection
* Security: Admin audit log metadata modal HTML-encoded to prevent stored XSS
* Security: Multi-role save and render filter against get_editable_roles() to prevent privilege escalation
* Security: Magic link verification uses FOR UPDATE transaction to prevent TOCTOU token replay
* Security: Content restrictions enforced on RSS feeds, excerpts, and all configured custom post types
* Security: Member directory AJAX response strips user_email to prevent information disclosure
* Security: Backup code verification enforces lockout and records failed attempts (consistent with email/TOTP 2FA)
* Security: Backup code mark-used wrapped in SELECT...FOR UPDATE transaction to prevent parallel reuse
* Security: Bulk import role assignment filtered against editable_roles (prevents importing administrators)
* Security: GDPR export download validates file path against exports directory via realpath()
* Security: GDPR export ZIP filename and subdirectory include random suffix to prevent enumeration
* Security: GDPR export rate limit set before generation to prevent parallel request bypass
* Security: GDPR export download token scoped to requesting user to prevent cross-admin collision
* Security: Config import applies settings sanitization registry to prevent bypass of validation rules
* Security: Admin user search CSV export protected against formula injection
* Security: Bulk actions handler requires manage_options capability (previously relied on WP hook gating only)
* Security: Account merger blocks administrator accounts from being used as secondary (deleted) account
* Security: Passkey rename/delete admin override requires manage_options instead of edit_users
* Security: Passkey prompt device cookie set to httponly=true (value rendered server-side, not read via JS)
* Security: Passkey login redirect URL validated with wp_validate_redirect() to prevent open redirect
* Security: maybe_unserialize() on visible_fields replaced with allowed_classes=false to block object injection
* Security: Image processor delete_photo() validates path is within uploads directory via realpath()
* Security: Privacy manager can_view_profile/can_view_field: guard against 0===0 guest bypass
* Security: Privacy settings validated against public/members_only/private allowlist
* Security: Impersonation end verifies original user retains impersonation capability before restoring session
* Security: 2FA device trust rotation reordered to add-then-remove (partial failure leaves two tokens, not zero)
* Security: HTML email template placeholders escaped with esc_html() in HTML context (welcome, reset, admin notification)
* Security: Rejection email uses home_url() instead of leaking admin panel URL
* Security: Settings save only processes POST keys registered in the settings registry
* Security: Antibot validate() enforces session ID hex format (consistent with generation)
* Security: Security log CSV formula check strips leading quotes/backslashes before pattern match
* Security: User notes profile AJAX handlers require manage_options (consistent with dedicated notes page)
* Fixed: Password expiration form: $change_token passed to render method (form submission was silently broken)
* Fixed: Admin profile section: missing wp_nonce_field caused all NoBloat profile saves to silently fail
* Fixed: insert_token_atomic: NOT EXISTS filter includes type column (prevents cross-type token blocking)
* Fixed: Taxonomy restriction cache: invalidation key format matches creation format
* Fixed: can_access_content() API: corrected class name to NBUF_Abstract_Restriction and made check_access public
* Fixed: Passkey login scripts now enqueue on WP-page-based login (corrected option key names)
* Fixed: 2FA login redirect now includes 'account' case (previously fell through to hardcoded path)
* Fixed: 2FA trust cookie upgraded to SameSite=Strict
* Fixed: 2FA "Resend code" link on verification page now functional with rate limiting
* Fixed: Password policy enforced on password reset form (previously skipped)
* Fixed: Password change clears 2FA trusted device cookies
* Fixed: Verifier restricts token lookup to verification/email_change types (prevents magic link cross-consumption)
* Fixed: Universal router requires exact base-slug boundary match
* Fixed: Username changer updates user_nicename and invalidates sessions
* Fixed: Email domain restrictions enforced on email change (not just registration)
* Fixed: Image processor deletes old photos via DB-stored path (prevents orphan files on re-upload)
* Fixed: Registration form preserve key uses random token instead of empty session identifiers
* Fixed: Webhook delivery dispatched asynchronously via wp_schedule_single_event
* Fixed: Transient increment uses correct option_name and %s format for atomic fallback WHERE clause
* Fixed: Bulk import format string order matches data key order
* Fixed: Passkey login fires wp_login hook for third-party plugin compatibility
* Fixed: Passkey login clears existing auth cookie before setting new session
* Fixed: Passkey rename: null passkey check separated from ownership check for correct error message
* Fixed: Bulk expiration transient scoped to current admin user (prevents cross-admin race condition)
* Fixed: User search SQL grouping: profile OR-clauses placed inside parenthetical group (preserves query precedence)
* Fixed: wp_update_user() return value checked in version history revert (prevents silent partial revert)
* Fixed: Version history revert includes $wpdb format specifiers and sanitizes first_name/last_name
* Fixed: Digest notification: transient deleted before email send to prevent losing changes queued during I/O
* Fixed: AJAX action collision: user-notes search renamed to nbuf_notes_search_users (was colliding with account merger)
* Fixed: Options audit log handles non-scalar values with wp_json_encode instead of (string) cast
* Improved: Verification token format validation tightened to hex-only (ctype_xdigit)
* Improved: IP wildcard matching uses preg_quote and validates octet range 0-255
* Improved: IPv6 CIDR mask validated for range 0-128
* Improved: Session AJAX handlers gated on session_management_enabled setting
* Improved: Profile data sanitization covers all social URL fields and secondary_email
* Improved: Registration form_key, bulk import error_key, and config import transient_key validated against expected prefixes
* Improved: Config import mode validated against overwrite/merge allowlist
* Improved: $warning_days, $batch_size, $days options cast to (int) with sane minimums to prevent type confusion
* Improved: Security log page uses absint() instead of intval() for admin notice counts
* Improved: ToS version_id cast to (int) for PHP 8 strict type compatibility
* Improved: Migration sanitize_field() uses null/empty-string check instead of empty() to preserve "0" values
* Improved: Dead code get_token()/mark_verified() removed (incompatible with hashed token storage)
* Improved: Passkey auth session ID uses bin2hex(random_bytes(32)) for consistency
* Improved: Antibot session ID validated with hex regex on both creation and validation paths
* Improved: Security log CSV export primes user cache with cache_users() to prevent N+1 queries
* Improved: Webhook secret uses wp_unslash() instead of sanitize_text_field() (preserves special chars in HMAC secrets)
* Improved: CSV injection regex includes + and - characters across all export paths
* Improved: Uninstall uses recursive directory iteration for nested user photo cleanup
* Improved: PHPDoc blocks updated across codebase for accuracy after security fixes

= 1.5.7 =
* Fixed: IP blacklist now blocks restricted IPs before credential validation on wp-login.php
* Fixed: Security alert email flooding during sustained attacks (configurable cooldown, default 1 hour)
* Fixed: Settings save logging every setting as changed even when unchanged
* Added: Activity summary digest in security alert emails showing all IPs and attempt counts
* Added: Alert Cooldown setting in GDPR > Logging (5 min to 24 hours)
* Added: {recent_activity} placeholder for security alert email template
* Improved: Options update skips DB write and cache invalidation when value unchanged
* Improved: Type-aware value comparison handles int/string mismatches from activation defaults
* Improved: Meta fields (nbuf_form_checkboxes, nbuf_form_arrays) excluded from settings processing loop

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

= 1.6.6 =
Final batch of the full-codebase forensic audit. Multisite uninstall, activator capability gate, migration data-integrity, router redirect/path-traversal hardening. No database changes required.

= 1.6.5 =
Major hardening release covering account merger PHP 8 compatibility, webhook SSRF, GDPR data exposure, profile-change notification rate limits, version-history revert column allowlist, CSV-escape standardisation, bulk-import password handling, and many more. No database changes required.

= 1.6.4 =
Significant security and hardening release covering authentication, sessions, impersonation, restrictions, registration, verification, password policy, and Terms of Service. Closes one CRITICAL ToS evidence-fabrication path plus dozens of HIGH and MEDIUM findings from a full forensic re-audit. No database changes required.

= 1.6.3 =
Security and standards release. Closes a PHP object-injection surface during account merge, hardens config-import / option deserialization, adds per-target capability and User-Agent binding to impersonation, fixes a bypassable referer check on photo uploads, and translates dozens of admin error strings. No database changes required.

= 1.6.2 =
Bug fix release. Clears failed-login records after a successful password reset so users who tripped the rate limiter aren't blocked when logging in with their new password. No database changes required.

= 1.6.1 =
Bug fix release. Corrects login redirect_to propagation so users return to their intended page after login, fixes content restriction login redirect, and resolves a fatal error in the [nbuf_universal] shortcode. No database changes required.

= 1.6.0 =
Major security hardening release. Requires PHP 8.0+. Fixes auth bypass in magic link login, account takeover in password expiration, and SSRF in webhooks. Tokens now stored as SHA-256 hashes. No database changes required.

= 1.5.7 =
Security fix: IP blacklist now blocks before authentication. Alert email throttling and digest summaries. Settings save no longer logs unchanged values. No database changes required.

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
