# Changelog

All notable changes to NoBloat User Foundry will be documented in this file.

## [Unreleased]

### Added - 2025-10-31

#### CSS Optimization Enhancements
- Added "Use Minified CSS Files" option in Styles tab
- Added separate control for minified (.min.css) vs unminified (.css) file loading
- Added registration-page.css file (was missing)
- Updated CSS descriptions to clarify combined files can be .css or .min.css
- Updated CSS load option to "Load CSS on NoBloat Forms and Pages" with clearer behavior description

#### WooCommerce Integration Tab (NEW)
- Created dedicated WooCommerce tab to consolidate all WooCommerce-specific settings
- Moved WooCommerce email verification hook from General tab
- Moved WooCommerce expiration integration settings from Expiration tab
- Added helper information section at bottom of tab
- Settings include:
  - Email verification for WooCommerce customers (`woocommerce_created_customer` hook)
  - Prevent expiration for users with active subscriptions
  - Prevent expiration for users with recent orders
  - Configurable recent order threshold (default: 90 days)

#### Tab Name Simplification
- Changed "Registration" → "Register"
- Changed "Documentation" → "Docs"
- Changed "WooCommerce" → "Woo"
- Changed "Expiration" → "Expiry"
- Changed "Email Test" → "Tests"

#### Tests Tab Enhancement
- Renamed file from `email-test.php` to `tests.php` for future expansion
- Added "Email Verification Test" heading with descriptive paragraph
- Prepopulated sender field with WordPress admin email
- Prepopulated recipient field with WordPress admin email
- Added descriptions for both email fields
- Updated all redirect URLs from 'email-test' to 'tests'

#### Admin Notice Improvements
- Success messages now auto-dismiss after 2 seconds with fade animation
- Error and warning messages remain persistent (manual dismiss only)
- Uses CSS transitions for smooth fade-out effect

#### Default WordPress Page Redirects (NEW)
- Added option to redirect default WordPress login page (wp-login.php) to custom NoBloat login page
- Added option to redirect default WordPress registration page to custom NoBloat registration page
- Added option to redirect default WordPress logout to custom NoBloat login page
- NO admin bypass - all users (including administrators) see custom pages when enabled
- Redirect settings located in General tab under "WordPress Page Redirects"
- Intelligent handling that doesn't interfere with actual login attempts
- All settings default to false (opt-in behavior)

### Changed - 2025-10-31

#### Files Modified

**includes/class-nbuf-activator.php**
- Added `nbuf_css_use_minified` default setting (line 114)
- Added `nbuf_redirect_default_login`, `nbuf_redirect_default_register`, `nbuf_redirect_default_logout` defaults (lines 101-103)
- Added migration entries for all new settings (lines 331, 333-335)

**templates/registration-page.css** (NEW FILE)
- Created complete CSS styling for registration page
- Includes responsive grid layout for form fields
- Mobile-friendly with single-column layout below 600px
- Consistent with other page styles

**includes/tabs/styles.php**
- Added "Use Minified CSS Files" checkbox option (lines 135-148)
- Added registration CSS editor section (lines 225-248)
- Updated combined CSS file description to include .min.css option
- Added registration CSS to save logic and combined file generation
- Added registration CSS to default loading logic

**includes/tabs/woocommerce.php** (NEW FILE)
- Created complete WooCommerce integration tab
- Email verification integration section
- Account expiration integration section
- Helper information panel at bottom

**includes/tabs/expiration.php**
- Removed entire WooCommerce Integration section
- Removed WooCommerce variables from save and load logic
- Cleaned up removed settings

**includes/tabs/general.php**
- Removed `woocommerce_created_customer` from hooks array
- Added logout settings variables (lines 34-36)
- Added redirect settings variables (lines 39-41)
- Added "WordPress Page Redirects" section with 3 checkboxes (lines 109-150)

**includes/tabs/tests.php** (renamed from email-test.php)
- Changed heading to "Email Verification Test"
- Added descriptive paragraph explaining the feature
- Prepopulated sender field with admin email
- Prepopulated recipient field with admin email
- Added descriptions for email fields
- Updated hidden field value to "tests"

**includes/class-nbuf-settings.php**
- Updated all tab names in navigation (lines 421, 424-427)
- Updated tab content includes for renamed files (lines 450-456)
- Registered `nbuf_css_use_minified` setting (lines 237-239)
- Registered redirect settings (lines 177-186)

**includes/class-nbuf-test.php**
- Updated redirect URLs from 'email-test' to 'tests' (lines 82, 100)

**includes/class-nbuf-css-manager.php**
- Added minified preference check (lines 197-198)
- Modified file loading logic to respect minified preference (lines 200-207)
- Checks for .min.css files first when minified is enabled
- Falls back to .css files if minified not found

**includes/class-nbuf-hooks.php**
- Added `login_init` action hook (line 27)
- Added `handle_default_wordpress_redirects()` method (lines 275-333)
- Intelligently handles login, register, and logout actions
- Uses `wp_safe_redirect()` for security
- Doesn't interfere with actual login attempts

**ui/admin.js**
- Added auto-dismiss for success notices (lines 131-145)
- Uses setTimeout and CSS transitions
- 2-second delay before 0.5-second fade-out
- Removes notice from DOM after fade completes

### Fixed - 2025-10-31

- Fixed inconsistent tab preservation in form submissions
- Fixed missing registration-page.css file
- Fixed tab names in admin.js to match new shortened names
- Fixed redirect URLs in test functionality

### Notes

- Tab navigation with query string preservation working correctly
- All success notices now auto-dismiss
- CSS optimization settings work independently (load/minify/combine)
- WooCommerce settings properly consolidated in dedicated tab

---

## Upcoming Tasks

### To Review Next Session
1. **Uninstall Options** - Review and potentially enhance cleanup options on uninstall
2. **Password Strength** - Review password strength requirements and indicators
3. **Audit Log** - Review audit logging functionality for security and compliance

---

## Previous Sessions

See `docs/session-logs.md` for detailed session-by-session changelog.
