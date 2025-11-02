# NoBloat User Foundry - Assets Directory

This directory contains all JavaScript and CSS files for the NoBloat User Foundry plugin.

## Directory Structure

```
assets/
├── js/
│   ├── admin/          # Admin-only JavaScript files
│   │   ├── feature-name.js       # Full version (for development)
│   │   └── feature-name.min.js   # Minified version (for production)
│   └── frontend/       # Front-end JavaScript files
│       ├── feature-name.js
│       └── feature-name.min.js
├── css/
│   ├── admin/          # Admin-only CSS files
│   │   ├── feature-name.css
│   │   └── feature-name.min.css
│   └── frontend/       # Front-end CSS files
│       ├── feature-name.css
│       └── feature-name.min.css
└── wp-plugin-images/   # WordPress.org plugin directory assets
```

## File Naming Conventions

- Use lowercase with hyphens: `merge-accounts.js`
- Be descriptive: `password-strength.js` not `pwd.js`
- Always create both regular and minified versions

## WordPress Standards

According to WordPress coding standards, plugins should include both regular and minified versions of JavaScript and CSS files:

- `feature-name.js` - Full, commented version for development
- `feature-name.min.js` - Minified version for production

The plugin will automatically detect and load the minified version if it exists, falling back to the regular version if not.

## Enqueue Pattern

```php
/* Smart loading: Use .min if exists, otherwise regular */
$script_file = file_exists( NBUF_PLUGIN_DIR . 'assets/js/admin/feature.min.js' )
    ? 'assets/js/admin/feature.min.js'
    : 'assets/js/admin/feature.js';

wp_enqueue_script(
    'nbuf-feature',
    NBUF_PLUGIN_URL . $script_file,
    array( 'jquery' ),
    NBUF_VERSION,
    true
);

/* Pass dynamic data via localization */
wp_localize_script( 'nbuf-feature', 'NBUF_Data', array(
    'ajaxurl' => admin_url( 'admin-ajax.php' ),
    'nonce'   => wp_create_nonce( 'nbuf_action' ),
    'i18n'    => array(
        'confirm' => __( 'Are you sure?', 'nobloat-user-foundry' ),
    ),
) );
```

## Migration from ui/ Directory

**Current Status:**
- The `ui/` directory currently contains existing JavaScript and CSS files
- These files will be gradually migrated to `assets/js/` and `assets/css/`
- All **new** files should be created in the `assets/` directory

**Migration Plan:**
1. Copy files from `ui/` to appropriate `assets/` subdirectories
2. Update enqueue paths in PHP files
3. Test thoroughly
4. Remove old `ui/` directory when migration is complete

## Best Practices

### JavaScript
- ✅ Use external files for scripts over 20 lines
- ✅ Use `wp_localize_script()` for dynamic data
- ✅ All user-facing strings must be translatable
- ✅ Proper dependency management (jQuery, etc.)
- ✅ Cache busting via NBUF_VERSION

### CSS
- ✅ Mobile-first responsive design
- ✅ Use WordPress admin color schemes
- ✅ Avoid !important unless necessary
- ✅ Namespace classes: `.nbuf-*`
- ✅ Minify for production

### Performance
- ✅ Only enqueue on pages where needed
- ✅ Load scripts in footer when possible
- ✅ Use minified versions in production
- ✅ Version with NBUF_VERSION for cache busting
- ✅ Combine files when appropriate

## Examples

See these files for reference implementations:
- `assets/js/admin/merge-accounts.js` - Full JavaScript example
- `assets/js/admin/merge-accounts.min.js` - Minified version
- Enqueue example: `includes/user-tabs/tools/merge-tabs/wordpress.php`

---

**Note:** This directory structure follows WordPress plugin development best practices and prepares the plugin for submission to WordPress.org.
