<p align="center">
  <picture>
    <source media="(min-width: 768px)" srcset="wporg-assets/banner-1544x500.png">
    <img src="wporg-assets/banner-772x250.png" alt="NoBloat User Foundry" width="100%">
  </picture>
</p>

# NoBloat User Foundry

Enterprise-grade, business focused user management for WordPress. Email verification, 2FA, passkeys, account expiration, GDPR compliance, auditing, and complete user lifecycle control - without the bloat.

## Overview

NoBloat User Foundry is a comprehensive yet lightweight user management system for WordPress. It replaces bloated membership plugins with a focused, performant solution for email verification, two-factor authentication, account expiration, user profiles, and GDPR compliance.

## Documentation

[https://docs.mailborder.com/nobloat-user-foundry](https://docs.mailborder.com/nobloat-user-foundry)

## Features

### Clean Structure

- No extra WordPress pages - all structure generated via internal router
- Clean CSS and JS automatically minified and only loaded on relevant pages
- No third party libraries
- No external API calls
- No static images
- Custom database tables - no wp_usermeta or wp_options bloat
- Lazy class loading - only loads what's needed per request
- Complete uninstall - removes all plugin data cleanly
- Fully compliant with WordPress coding standards.

### Email Verification

- Automatic verification emails on registration
- Unique token-based verification links
- Customizable email templates (HTML and plain text)
- Manual admin verification and bulk actions
- Token expiration and automatic cleanup

### Two-Factor Authentication (2FA)

- Email-based 2FA with 6-digit codes
- TOTP authenticator app support (Google Authenticator, Authy, etc.)
- Backup codes for account recovery
- Device trust (remember this device for 30 days)
- Role-based 2FA enforcement with grace periods
- Lockout protection after failed attempts

### Passkeys/WebAuthn

- Passwordless authentication
- Multiple passkeys per user
- Pure PHP implementation (no external dependencies)
- AJAX-based registration and authentication

### Account Expiration

- Set expiration dates for user accounts
- Automatic account disabling via scheduled tasks
- Pre-expiration warning emails (configurable days before)
- WooCommerce integration: protect active subscribers
- WooCommerce integration: protect recent customers

### User Profiles

- Extended profile fields (phone, company, address, bio)
- Profile photos (custom upload, Gravatar, or SVG initials)
- Cover photos
- Privacy controls (public, members-only, private)
- Profile version history with diff comparison
- Revert to previous profile versions

### Member Directory

- Public-facing member listing
- Search by name, email, or bio
- Filter by role
- Pagination support
- Respects user privacy settings

### GDPR & Privacy

- User-initiated data export
- Admin-initiated data export
- Account deletion with data anonymization
- Privacy policy management
- WordPress privacy tools integration
- Audit log anonymization on deletion

### Magic Links

- Passwordless email login links
- Configurable link expiration (default 15 minutes)
- Rate limiting to prevent abuse
- One-time use tokens
- Works with all other security features

### User Impersonation

- Admins can log in as any user for support
- Full audit trail of impersonation sessions
- Sticky banner showing impersonation status
- One-click return to admin account
- Capability-based access control

### Email Domain Restrictions

- Whitelist or blacklist email domains for registration
- Wildcard subdomain support (*.example.com)
- Customizable rejection messages
- Security log integration

### IP Restrictions

- Whitelist or blacklist IP addresses for login
- CIDR notation support (192.168.1.0/24)
- Trusted proxy configuration for load balancers/CDNs
- Works with Cloudflare, AWS, Nginx, and more

### Terms of Service

- Version-controlled Terms of Service
- Track user acceptance with timestamps
- Require acceptance on login for new versions
- Configurable grace periods
- Export acceptance records to CSV

### Session Management

- View all active login sessions
- Device and browser detection
- Revoke individual sessions
- "Log out everywhere" option
- Current session protection

### Activity Dashboard

- Timeline view of account activity
- Security events (logins, password changes, 2FA)
- Admin dashboard widget with site-wide stats
- Paginated activity history

### Security Features

- Login attempt limiting with IP-based lockouts
- Password strength requirements (length, complexity)
- Password expiration with forced changes
- Anti-bot protection (honeypot, timing, JavaScript validation)
- Application passwords for API access
- Security event logging

### Admin Features

- Enhanced Users list with status columns
- Bulk actions: verify, disable, enable, set expiration
- Advanced filters with live counts
- User notes/admin comments
- Account merger tool
- Import from Ultimate Member and BuddyPress
- Comprehensive audit logging

### Access Restrictions

- Menu item visibility by role or login status
- Content restrictions by role
- Widget visibility controls
- Taxonomy/category restrictions
- Hide restricted content from archives

### Webhooks

- Send HTTP POST notifications on user events
- Configurable events: registration, verification, login, profile updates
- HMAC-SHA256 signature verification
- Webhook delivery logging
- Auto-disable after consecutive failures

### Custom Account Tabs

- Add custom tabs to the frontend account page
- Shortcode content support (WooCommerce, EDD, LearnDash, etc.)
- Role-based tab visibility restrictions
- Optional Dashicon icons for visual identification
- Drag-and-drop reordering in admin
- Priority-based sorting

### Email System

- Customizable email templates
- HTML and plain text modes
- Custom sender address and name
- Placeholder support: `{site_name}`, `{username}`, `{verify_link}`, etc.

## Shortcodes

| Shortcode | Description |
|-----------|-------------|
| `[nbuf_login_form]` | Custom login form |
| `[nbuf_registration_form]` | Registration form |
| `[nbuf_reset_form]` | Password reset form |
| `[nbuf_request_reset_form]` | Request password reset |
| `[nbuf_verify_page]` | Email verification page |
| `[nbuf_account_page]` | User account dashboard |
| `[nbuf_profile]` | Display user profile |
| `[nbuf_members]` | Member directory |
| `[nbuf_2fa_verify]` | 2FA verification form |
| `[nbuf_totp_setup]` | TOTP authenticator setup |
| `[nbuf_logout]` | Logout button |
| `[nbuf_restrict]` | Restrict content by role/login |
| `[nbuf_data_export]` | GDPR data export form |
| `[nbuf_magic_link_form]` | Magic link request form |

## Universal Router

Virtual page routing at `/user-foundry/` - no WordPress pages required:

- `/user-foundry/login/`
- `/user-foundry/register/`
- `/user-foundry/account/`
- `/user-foundry/profile/`
- `/user-foundry/verify/`
- `/user-foundry/forgot-password/`
- `/user-foundry/reset-password/`
- `/user-foundry/2fa/`
- `/user-foundry/2fa-setup/`
- `/user-foundry/members/`
- `/user-foundry/magic-link/`
- `/user-foundry/accept-tos/`
- `/user-foundry/logout/`

## Developer Features

- PSR-4 autoloader for optimal performance
- Unified User API with caching (`NBUF_User::get()`)
- Extensive hooks and filters
- Custom database tables with indexed columns
- Isolated options table (no wp_options bloat)

### Actions

| Hook | Description |
|------|-------------|
| `nbuf_user_verified` | User email verified |
| `nbuf_user_expired` | Account expired |
| `nbuf_user_disabled` | Account disabled |
| `nbuf_user_enabled` | Account enabled |
| `nbuf_user_approved` | Account approved |
| `nbuf_2fa_enabled` | 2FA enabled |
| `nbuf_2fa_disabled` | 2FA disabled |

### Filters

| Hook | Description |
|------|-------------|
| `nbuf_verification_email_subject` | Modify verification email subject |
| `nbuf_verification_email_message` | Modify verification email message |
| `nbuf_password_requirements` | Customize password requirements |
| `nbuf_profile_fields` | Modify available profile fields |

### Custom Tabs API

```php
// Get all custom tabs
$tabs = NBUF_Custom_Tabs::get_all();

// Get tabs visible to a specific user
$user_tabs = NBUF_Custom_Tabs::get_for_user($user_id);

// Create a custom tab programmatically
$tab = NBUF_Custom_Tabs::create(array(
    'name'    => 'My Orders',
    'slug'    => 'my-orders',
    'content' => '[woocommerce_my_account]',
    'roles'   => array('customer'),
    'icon'    => 'dashicons-cart',
    'enabled' => true,
));
```

## Database Tables

The plugin creates isolated custom tables (prefixed with `nbuf_`):

| Table | Purpose |
|-------|---------|
| `nbuf_tokens` | Verification tokens |
| `nbuf_user_data` | User status and expiration data |
| `nbuf_options` | Plugin settings (isolated from wp_options) |
| `nbuf_user_profile` | Extended profile fields |
| `nbuf_login_attempts` | Login attempt tracking |
| `nbuf_user_2fa` | 2FA configuration |
| `nbuf_user_passkeys` | WebAuthn passkeys |
| `nbuf_user_audit_log` | User activity log |
| `nbuf_admin_audit_log` | Admin actions log |
| `nbuf_user_notes` | Admin notes per user |
| `nbuf_import_history` | Migration/import tracking |
| `nbuf_profile_versions` | Profile history snapshots |
| `nbuf_security_log` | Security events |
| `nbuf_webhooks` | Webhook configuration |
| `nbuf_webhook_log` | Webhook delivery log |
| `nbuf_menu_restrictions` | Menu visibility rules |
| `nbuf_content_restrictions` | Content visibility rules |
| `nbuf_user_roles` | Custom role management |
| `nbuf_tos_versions` | Terms of Service versions |
| `nbuf_tos_acceptances` | User ToS acceptance records |

## Performance

- Lazy class loading (only loads what's needed)
- Three-tier caching (memory, object cache, database)
- Redis and Memcached compatible (works with popular object cache plugins)
- Single-query option preloading
- Request-level caching to eliminate duplicate queries
- Minified CSS with on-disk caching
- Conditional asset loading

## Security

- Nonce verification on all forms
- Capability checks for admin functions
- Input sanitization and output escaping
- Prepared SQL statements
- CSRF and XSS protection
- Timing attack prevention
- Brute force protection

## Requirements

- WordPress 6.2 or higher
- PHP 8.0 or higher

## Changelog

### 1.7.1 — Forensic-audit Group C: admin / shortcodes / webhooks / ToS / roles

Closes 12 HIGH findings from the Group C audit, including 4 functional regressions discovered in v1.6.9-v1.7.0 that broke default-config flows.

**Functional regressions (HIGH):**
- Registration was broken on every default install — antibot validated twice, the second call fails because transients are consumed by the first.
- "End Impersonation" never worked — binding hash compared against itself double-hashed.
- TOTP setup retry trapped users in an infinite loop after one mistyped digit (regression in v1.6.9 server-bound-secret hardening).
- `nbuf_after_profile_update` fired twice per profile save, duplicating version-history snapshots and change-notification emails.

**Security (HIGH):**
- ToS gate now uses ROLE checks (not `manage_options` cap) — closes a privilege-escalation chain on sites that grant manage_options to custom non-admin roles.
- ToS gate now covers wp-admin and admin-ajax — closes Subscriber-and-up bypass to mutate state without acceptance.
- Multi-role self-edit guard uses ROLE check.
- Role-manager `parent_role` inheritance now respects actor cap-containment.
- 2FA partial-disable destroys other sessions and fires `nbuf_2fa_method_changed`.
- All 2FA-account state changes emit audit-log entries on success AND on reauth failure.
- Per-user rate limit on password re-auth (closes brute-force from stolen session).

**Forensics & standards (MEDIUM):**
- Audit-log + security-log purge writes `logs_purged` to the immutable admin-audit-log.
- Security-log and admin-user-search CSV exports use the standardised formula-injection escape regex.

### 1.7.0 — Forensic-audit Group B: registration / privacy / GDPR / photos

Closes 10 HIGH findings from the registration / activator / merger / GDPR / privacy / version-history / photos / directory audit.

- **Account merger (HIGH):** Multisite per-blog capability keys now in the meta skip list — closes cross-blog privilege escalation via merge.
- **Account merger (HIGH):** PHP 8 stdClass regression in legacy `conflict_selections` photo branch fixed.
- **Version history (HIGH):** Revert allowlist split admin-tier / user-tier — self-revert can no longer restore `is_verified`, `pending_email`, `last_login_at`, or `role`.
- **GDPR (HIGH):** Article-17 erasure now removes 2FA cryptographic material, clears `pending_email`, deletes login-limiting rows, and destroys all active sessions.
- **Public profile (HIGH):** Cover-photo URL goes through validated path helper rather than reading `cover_photo_url` raw from the DB.
- **Member directory (HIGH):** Excludes disabled / expired / `user_status!=0` users; matching gate added to `can_view_profile()`.
- **NBUF_User (HIGH):** `to_array()` / `to_json()` apply the same SENSITIVE_FIELDS denylist as `__get`.
- **Username changer (HIGH):** Fires `profile_update`, invalidates NBUF granular caches, migrates `nbuf_login_attempts` rows on rename.
- **Image processor (HIGH):** GIF/WebP fallback path always re-encodes via `wp_get_image_editor`, never `copy()`. Closes polyglot byte-perfect preservation.
- **Photo deletion (MEDIUM):** File-delete-first ordering with metadata retention on FS error (avoids untrackable orphans).
- **Username changer (MEDIUM):** `user_nicename` collision retry on duplicate-key.

### 1.6.9 — Forensic-audit hardening: passkeys, 2FA, TOTP setup, device trust

Closes 5 CRITICAL and 11 HIGH findings from a fresh forensic audit of the auth subsystems.

- **Passkeys (CRITICAL):** Server-side enforcement of `userVerification: required` per W3C WebAuthn Level 2 §7.1/§7.2 — registration AND verification now reject UV-less assertions when policy demands UV.
- **Passkeys (CRITICAL):** User-binding transient no longer fails open — options endpoint always persists a sentinel ('_discoverable' or user ID), verifier rejects missing entries instead of skipping the cross-user defense.
- **TOTP setup (CRITICAL):** Server-bound secret via per-user transient; `$_POST['secret']` is no longer trusted. Closes the "evil setup" attack path.
- **TOTP setup (CRITICAL):** Existing backup codes are preserved on re-setup; explicit rotation remains via the re-auth-protected "Generate New Codes" action.
- **Device trust (CRITICAL):** Rotation under contention no longer fails open — missing post-lock token returns false rather than re-conferring trust on a rotated cookie.
- **Passkeys (HIGH):** `get_origin()` now strips the URL path for RFC 6454 conformance; subdirectory WordPress installs work again.
- **Passkeys (HIGH):** CBOR collection bounds (4096 elements; 64 KB attestation cap) — closes authenticated DoS.
- **2FA (HIGH):** Pending-2FA cookie `Secure: true` always; transient bound to UA-fingerprint hash.
- **2FA (HIGH):** enable_for_user / disable_for_user destroy other active sessions.
- **Device trust (HIGH):** Absolute lifetime cap of 30 days from creation; rotation no longer slides the window indefinitely.
- **2FA (HIGH):** Per-IP lockout component; absolute (not sliding) attempt window; lockout transient set once per window.
- **2FA (HIGH):** Auto-required email 2FA persists `enabled=1` after first verify so device-trust path becomes reachable.
- **2FA (HIGH):** Role demotion clears `forced_at` so re-promotion starts a fresh grace window.
- **Forensics:** Passkey verification failures (challenge/RP-ID/origin/signature/credential/UV/session) now emit security-log entries.
- **Magic links (MEDIUM):** Per-IP rate-limit charged on every form submission, including invalid-email and rate-limited paths.

### 1.6.8 — Verified Passkey ⇒ Skip 2FA (Default)

A WebAuthn assertion that completed user verification (biometric / PIN) is itself a multi-factor credential. v1.6.8 stops layering TOTP / email 2FA on top by default:

- `verify_authentication()` now returns `{user_id, user_verified}` so callers know whether the assertion's UV flag was set.
- `ajax_authenticate()` skips the 2FA-pending branch when UV is set, unless the new option `nbuf_2fa_require_after_passkey` (Security › 2FA Settings → "Require 2FA After Passkey") is on.
- Passkeys without user verification (rare, possible with some hardware keys configured presence-only) still fall through to the standard 2FA challenge.
- `passkey_auth` audit-log entries now record `user_verified` for forensics.

### 1.6.7 — Deferred Hardening Items

Closes the items left for follow-up in v1.6.4-v1.6.6:

- **GDPR export directory**: protection files (.htaccess + index.html + index.php) refreshed on every cleanup tick; cleanup now recursively removes orphaned per-user subdirectories.
- **Impersonation sudo-step**: admin must re-enter their password within a 10-minute window before impersonation can start. Closes the stolen-session pivot. Window length filterable via `nbuf_impersonation_sudo_seconds`.
- **ToS REST gate**: `rest_authentication_errors` filter rejects logged-in non-admins with un-accepted ToS at the REST layer (template_redirect doesn't fire on /wp-json/).
- **ToS audit-log impersonator stamp**: acceptances recorded during active admin impersonation note the impersonator's user_id; replay attempts on already-accepted versions are logged as info entries.
- **Webhook payload off cron**: payloads stored in a 1-hour transient keyed by a 16-byte token; cron arg becomes the token only. Stops PII payload leakage via wp_options dumps.
- **Admin-audit-log column sort**: get_logs() honours orderby/order with strict allowlist; list-table prepare_items wires the request params through.

### 1.6.6 — Forensic Hardening Release (Pieces 10-12 + Final Pass)

Closing batch of the 12-piece full-codebase audit. Highlights: multisite uninstall, activator capability gate, migration data integrity, router hardening.

### 1.6.5 — Forensic Hardening Release (Pieces 4-9)

Mid-audit batch covering shortcodes, user data layer, admin pages, settings/templates, email/webhooks, and import/export. One CRITICAL (account-merger PHP 8 fatal), 4 HIGH security findings closed.

### 1.6.4 — Forensic Hardening Release (Pieces 1-3)

This release ships fixes from a full-codebase forensic audit covering the authentication core, sessions/impersonation/IP-restrictions, and registration/verification/password-policy/ToS subsystems.

**CRITICAL:**
- ToS `handle_acceptance` now pins the posted `version_id` to the active version, blocking fabricated-evidence and stale-form attacks.

**HIGH (security):**
- Passkey `verify_authentication` enforces the user-binding transient (closes credential-confusion on shared devices).
- TOTP replay protection records the matched counter, not the verifier clock; per-user `GET_LOCK` serializes parallel POSTs.
- Login-limiting normalizes username case before insert/select (closes case-variation bypass of the per-username distributed brute-force threshold).
- 2FA email/TOTP/backup-code lockouts use method-specific window settings.
- Magic-link `verify_magic_link` checks the delete result before COMMIT and rolls back on DB error or zero-rows.
- Magic-link SMTP send deferred via `wp_schedule_single_event` to neutralize timing-based email enumeration.
- Magic-link verifier collapses distinct account-state messages and lower-cases token before SHA-256.
- Passkey clone-detection log severity raised to critical and argument order corrected.
- Impersonation `can_impersonate_user` uses `user_can()` per-cap loop (honors dynamically-granted caps from `user_has_cap` filter).
- Impersonation start binds `original_session_token_hash`; end-path verifies that hash against an active session before restoring auth.
- Impersonation IP/UA bindings required non-empty and use `NBUF_IP::get_client_ip(true)` for normalized comparison.
- Sessions `revoke_session`/`revoke_other_sessions` require ownership or `edit_user`; `revoke_other_sessions` verifies destroy actually reduced the count.
- IP-restrictions admin-bypass default flipped to off (eliminates admin-username-to-role oracle).
- Restriction-content `filter_rest_content` returns `WP_Error 403` (closes title/meta/ACF REST data leak).
- Restriction-content `access_denied_message` log gated by `is_singular()` (eliminates per-render log-spam DoS).
- Restriction-taxonomy `get_excluded_term_ids` SQL filters by visibility allowlist.
- Restriction-menu two-pass filter walks ancestor chain regardless of item order.
- Restrictions module hooks `delete_post`/`delete_nav_menu_item` for orphan-row cleanup.
- ToS `handle_acceptance` enforces the affirmative-consent checkbox server-side.
- ToS `set_active_version` is transactional with rollback.
- ToS-admin CSV escape blocks leading-whitespace formula-injection bypass.
- Password-expiration `update_password_changed_date` no longer auto-clears `force_password_change`.
- Password-expiration change-token cleaned up on logged-in form path.
- Password-validator weak-password "every" timing no longer resets the grace clock on every login.
- Registration `validate_registration_data` enforces the antibot challenge (protects any caller of `register_user`).
- Shortcodes email-change deletes prior unredeemed `email_change` tokens before issuing a new one.
- Verifier canonicalizes emails for comparison; failed `wp_update_user` writes an `email_change_failed` audit row.

**MEDIUM:**
- 2FA grace period starts on `set_user_role`/`add_user_role` (not next login).
- 2FA pending transient cleared on lockout.
- Backup-code failures use generic 2FA lockout window.
- Device-trust GET_LOCK is per-token with 5-second timeout.
- Login-limiting distinguishes missing-table from query-error (missing fail-opens with critical log).
- Login-limiting `clear_attempts_on_password_reset` no longer wipes all IP rows.
- TOTP `base32_decode` rejects invalid input.
- Passkey `set_transient` failure surfaces as `WP_Error('storage_failed')`.
- Passkey-prompt `is_ssl` checked before consuming the trigger transient.
- Passkey-prompt AJAX trusts the cookie, not POST device_id.
- Passkeys-login redirect uses strict `https?://` regex.
- Passkey `ajax_authenticate` redirect default uses `NBUF_Passkeys_Login::get_redirect_url()`.
- Impersonation end-path capability check moved before `wp_clear_auth_cookie`.
- ToS `effective_date` validated via `DateTime::createFromFormat`.
- Expiration cron batched to 100 users/run (filterable).

**LOW:**
- `wp_login_failed` fires on 2FA failure.
- NBUF_IP XFF chain skips empty fields.
- Restriction redirect URLs use `esc_url_raw()`.

**Verified:** php -l clean across all 26 modified files; phpcs WordPress 0 errors / minimal pre-existing warnings.

### 1.6.3 — Security & Standards Release

**Security Fixes:**
- Removed `maybe_unserialize()` on attacker-influenced usermeta during account merge; PHP object-injection / POP-gadget surface eliminated
- Consolidated `visible_fields` decoding through a single safe helper (`allowed_classes => false`)
- `NBUF_Options` decodes stored values via a safe-unserialize helper; config importer refuses pre-serialized strings
- `force_logout_all_devices` AJAX handler now enforces per-target `edit_user` capability and blocks cross-super-admin termination on multisite
- Bulk import rejects `administrator` role rows when the importer is not a network super admin (multisite); covers both explicit-role and default-role paths
- Config importer also rejects pre-serialized template payloads (mirrors the settings-path rejection)
- Impersonation session now validates User-Agent (in addition to IP) with `hash_equals`, matching documented behavior
- Profile / cover photo upload referer check uses exact hostname equality (was a `strpos` prefix check)

**Standards / WP.org Compliance:**
- Donate link, license declaration, and trademark casing synchronized between `readme.txt` and the main plugin header
- Wrapped 30+ user-facing AJAX, `wp_die`, and `WP_Error` strings in `__()` / `esc_html__()` with the `nobloat-user-foundry` text domain
- Sanitized `$_SERVER['REQUEST_METHOD']` reads
- Fixed dead `echo` in docs overview tab; corrected indentation and block-comment style on several `phpcs:ignore` lines

### 1.6.2 — Bug Fix Release

**Bug Fixes:**
- Failed login attempts are now cleared after a successful password reset. Previously, users who tripped the brute-force rate limiter while forgetting their password remained locked out by IP/username after completing a reset, even though the new password was correct. Hooks `after_password_reset` so it covers both the plugin's reset flow and any direct wp-login reset.

### 1.6.1 — Bug Fix Release

**Bug Fixes:**
- Login `redirect_to` parameter now preserved when wp-login.php intercept redirects to NoBloat login page
- Login form reads `redirect_to` URL parameter so users return to their intended page after login
- Content restriction redirect uses NoBloat login URL instead of `wp-login.php` when NoBloat login is active
- `NBUF_URL::is_universal_mode()` method was undefined — fatal error when `[nbuf_universal]` shortcode ran

### 1.6.0 — Security Hardening Release

**Breaking:** Minimum PHP version raised from 7.4 to 8.0.

**Security Fixes:**
- All verification and magic link tokens stored as SHA-256 hashes
- Encryption hard-fails instead of silently storing plaintext when OpenSSL unavailable
- 2FA changes require password re-authentication
- Magic link login enforces disabled/expired/unverified/2FA checks
- Passkey login enforces admin-mandated 2FA policy
- Password expiration form uses cryptographic token (prevents account takeover)
- Webhook SSRF protection (private IP blocking, DNS rebinding prevention)
- Content restrictions enforced on REST API responses
- X-Forwarded-For parsed right-to-left for correct client IP behind proxies
- All IP retrieval consolidated to NBUF_IP utility class
- Role editor restricts capability assignment to admin's own capabilities
- Impersonation: full capability comparison, nested impersonation blocked
- Antibot tokens consumed after use to prevent replay
- TOTP replay protection via last-used counter
- CSV formula injection protection on all log exports
- Image upload decompression bomb protection (25MP limit)
- GDPR export: symlink protection, hash_equals token comparison, user ID paths

**Bug Fixes:**
- Passkey login scripts enqueue on WP-page-based login pages
- 2FA login redirect includes 'account' case
- 2FA "Resend code" link now functional
- Password policy enforced on reset form
- Email domain restrictions enforced on email change
- Username changer updates user_nicename and invalidates sessions
- Webhook delivery dispatched asynchronously
- Image processor deletes old photos via DB path (prevents orphan files)
- Passkey login fires wp_login hook

See [readme.txt](readme.txt) for full changelog.

## Support

- **GitHub:** [https://github.com/jcbenton/nobloat-user-foundry](https://github.com/jcbenton/nobloat-user-foundry)

## License

GPLv3 - See [LICENSE](https://www.gnu.org/licenses/gpl-3.0.html) for details.
