<p align="center">
  <picture>
    <source media="(min-width: 768px)" srcset="wporg-assets/banner-1544x500.png">
    <img src="wporg-assets/banner-772x250.png" alt="TokenLink – Menu Permissions" width="100%">
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
- Fully compliant with Wordpress coding standards.

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
- PHP 7.4 or higher

## Support

- **GitHub:** [https://github.com/jcbenton/nobloat-user-foundry](https://github.com/jcbenton/nobloat-user-foundry)

## License

GPLv3 - See [LICENSE](https://www.gnu.org/licenses/gpl-3.0.html) for details.
