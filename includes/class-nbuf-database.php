<?php
/**
 * NoBloat User Foundry - Database Handler
 *
 * Handles inserts, lookups, and cleanup of verification tokens.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NBUF_Database {

    private static $table_name;

    /* ==========================================================
       INIT
       ----------------------------------------------------------
       Ensure the table name is initialized with the WP prefix.
       ========================================================== */
    public static function init() {
        global $wpdb;
        self::$table_name = $wpdb->prefix . 'nbuf_tokens';
    }

    /* ==========================================================
       COLUMN EXISTS CHECK (PERFORMANCE HELPER)
       ----------------------------------------------------------
       Efficiently check if a column exists using INFORMATION_SCHEMA.
       Faster than SHOW COLUMNS as it returns boolean instead of
       loading all column metadata.
       ========================================================== */
    private static function column_exists( $table_name, $column_name ) {
        global $wpdb;

        /* Use INFORMATION_SCHEMA for single query check */
        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*)
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                 AND TABLE_NAME = %s
                 AND COLUMN_NAME = %s",
                $table_name,
                $column_name
            )
        );

        return (bool) $exists;
    }

    /* ==========================================================
       CREATE TABLE
       ----------------------------------------------------------
       Runs on activation to ensure schema exists.
       ========================================================== */
    public static function create_table() {
        global $wpdb;
        self::init();

        $charset_collate = $wpdb->get_charset_collate();
        $table_name = self::$table_name;

        $sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            user_email VARCHAR(255) NOT NULL,
            token VARCHAR(128) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NOT NULL,
            verified TINYINT(1) NOT NULL DEFAULT 0,
            is_test TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY token (token),
            KEY user_id (user_id),
            KEY cleanup (is_test, verified, expires_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /* ==========================================================
       CREATE USER DATA TABLE
       ----------------------------------------------------------
       Creates table for user verification and expiration data.
       ========================================================== */
    public static function create_user_data_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'nbuf_user_data';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
            user_id BIGINT(20) UNSIGNED NOT NULL,
            is_verified TINYINT(1) NOT NULL DEFAULT 0,
            verified_date DATETIME NULL,
            is_disabled TINYINT(1) NOT NULL DEFAULT 0,
            disabled_reason VARCHAR(50) NULL,
            expires_at DATETIME NULL,
            expiration_warned_at DATETIME NULL,
            weak_password_flagged_at DATETIME NULL,
            password_changed_at DATETIME NULL,
            PRIMARY KEY (user_id),
            KEY is_verified (is_verified),
            KEY is_disabled (is_disabled),
            KEY expires_at (expires_at),
            KEY warning_check (expires_at, expiration_warned_at, is_disabled)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /* ==========================================================
       CREATE OPTIONS TABLE
       ----------------------------------------------------------
       Creates custom options table to isolate plugin settings
       from WordPress wp_options table. Only loads when plugin
       is actively being used, reducing bloat on other pages.
       ========================================================== */
    public static function create_options_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'nbuf_options';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
            option_name VARCHAR(191) NOT NULL,
            option_value LONGTEXT NOT NULL,
            option_group VARCHAR(50) NOT NULL DEFAULT 'settings',
            autoload TINYINT(1) NOT NULL DEFAULT 0,
            last_modified DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (option_name),
            KEY autoload (autoload),
            KEY option_group (option_group)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /* ==========================================================
       CREATE USER PROFILE TABLE
       ----------------------------------------------------------
       Creates table for extended user profile fields (phone,
       company, address, etc.). Separate from user_data which
       handles verification and expiration.
       ========================================================== */
    public static function create_user_profile_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'nbuf_user_profile';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
            user_id BIGINT(20) UNSIGNED NOT NULL,

            /* Basic Contact Fields */
            phone VARCHAR(50) DEFAULT NULL,
            mobile_phone VARCHAR(50) DEFAULT NULL,
            work_phone VARCHAR(50) DEFAULT NULL,
            fax VARCHAR(50) DEFAULT NULL,
            preferred_name VARCHAR(255) DEFAULT NULL,
            nickname VARCHAR(100) DEFAULT NULL,
            pronouns VARCHAR(50) DEFAULT NULL,
            gender VARCHAR(50) DEFAULT NULL,
            date_of_birth DATE DEFAULT NULL,
            timezone VARCHAR(100) DEFAULT NULL,
            secondary_email VARCHAR(255) DEFAULT NULL,

            /* Address Fields */
            address VARCHAR(500) DEFAULT NULL,
            address_line1 VARCHAR(255) DEFAULT NULL,
            address_line2 VARCHAR(255) DEFAULT NULL,
            city VARCHAR(100) DEFAULT NULL,
            state VARCHAR(100) DEFAULT NULL,
            postal_code VARCHAR(20) DEFAULT NULL,
            country VARCHAR(100) DEFAULT NULL,

            /* Professional Fields */
            company VARCHAR(255) DEFAULT NULL,
            job_title VARCHAR(255) DEFAULT NULL,
            department VARCHAR(255) DEFAULT NULL,
            division VARCHAR(255) DEFAULT NULL,
            employee_id VARCHAR(100) DEFAULT NULL,
            badge_number VARCHAR(100) DEFAULT NULL,
            manager_name VARCHAR(255) DEFAULT NULL,
            supervisor_email VARCHAR(255) DEFAULT NULL,
            office_location VARCHAR(255) DEFAULT NULL,
            hire_date DATE DEFAULT NULL,
            termination_date DATE DEFAULT NULL,
            work_email VARCHAR(255) DEFAULT NULL,
            employment_type VARCHAR(50) DEFAULT NULL,
            license_number VARCHAR(100) DEFAULT NULL,
            professional_memberships TEXT DEFAULT NULL,
            security_clearance VARCHAR(100) DEFAULT NULL,
            shift VARCHAR(50) DEFAULT NULL,
            remote_status VARCHAR(50) DEFAULT NULL,

            /* Education Fields */
            student_id VARCHAR(100) DEFAULT NULL,
            school_name VARCHAR(255) DEFAULT NULL,
            degree VARCHAR(255) DEFAULT NULL,
            major VARCHAR(255) DEFAULT NULL,
            graduation_year VARCHAR(4) DEFAULT NULL,
            gpa VARCHAR(10) DEFAULT NULL,
            certifications TEXT DEFAULT NULL,

            /* Social Media Fields */
            twitter VARCHAR(255) DEFAULT NULL,
            facebook VARCHAR(255) DEFAULT NULL,
            linkedin VARCHAR(255) DEFAULT NULL,
            instagram VARCHAR(255) DEFAULT NULL,
            github VARCHAR(255) DEFAULT NULL,
            youtube VARCHAR(255) DEFAULT NULL,
            tiktok VARCHAR(255) DEFAULT NULL,
            discord_username VARCHAR(255) DEFAULT NULL,
            whatsapp VARCHAR(50) DEFAULT NULL,
            telegram VARCHAR(255) DEFAULT NULL,
            viber VARCHAR(50) DEFAULT NULL,
            twitch VARCHAR(255) DEFAULT NULL,
            reddit VARCHAR(255) DEFAULT NULL,
            snapchat VARCHAR(255) DEFAULT NULL,
            soundcloud VARCHAR(255) DEFAULT NULL,
            vimeo VARCHAR(255) DEFAULT NULL,
            spotify VARCHAR(255) DEFAULT NULL,
            pinterest VARCHAR(255) DEFAULT NULL,

            /* Personal Fields */
            bio TEXT DEFAULT NULL,
            website VARCHAR(255) DEFAULT NULL,
            nationality VARCHAR(100) DEFAULT NULL,
            languages VARCHAR(255) DEFAULT NULL,
            emergency_contact TEXT DEFAULT NULL,

            /* Timestamps */
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /* ==========================================================
       CREATE LOGIN ATTEMPTS TABLE
       ----------------------------------------------------------
       Creates table to track failed login attempts for rate
       limiting and account protection.
       ========================================================== */
    public static function create_login_attempts_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'nbuf_login_attempts';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            ip_address VARCHAR(100) NOT NULL,
            username VARCHAR(255) NOT NULL,
            attempt_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY ip_address (ip_address),
            KEY username (username),
            KEY attempt_time (attempt_time),
            KEY ip_time (ip_address, attempt_time),
            KEY user_time (username, attempt_time)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /* ==========================================================
       CREATE USER 2FA TABLE
       ----------------------------------------------------------
       Creates table for two-factor authentication data.
       Replaces wp_usermeta storage to eliminate bloat.
       ========================================================== */
    public static function create_user_2fa_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'nbuf_user_2fa';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
            user_id BIGINT(20) UNSIGNED NOT NULL,
            enabled TINYINT(1) NOT NULL DEFAULT 0,
            method VARCHAR(10) DEFAULT NULL,
            totp_secret VARCHAR(255) DEFAULT NULL,
            backup_codes TEXT DEFAULT NULL,
            backup_codes_used TEXT DEFAULT NULL,
            trusted_devices TEXT DEFAULT NULL,
            last_used DATETIME DEFAULT NULL,
            forced_at DATETIME DEFAULT NULL,
            setup_completed TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id),
            KEY enabled (enabled),
            KEY enabled_method (enabled, method)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /* ==========================================================
       CREATE USER AUDIT LOG TABLE
       ----------------------------------------------------------
       Creates table for tracking user activity and security events.
       Tracks authentication, verification, password changes, 2FA,
       and other user-centric actions.
       ========================================================== */
    public static function create_user_audit_log_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'nbuf_user_audit_log';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            username VARCHAR(60) NOT NULL,
            event_type VARCHAR(50) NOT NULL,
            event_status VARCHAR(20) NOT NULL,
            event_message TEXT DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent TEXT DEFAULT NULL,
            metadata TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY event_type (event_type),
            KEY event_status (event_status),
            KEY created_at (created_at),
            KEY user_event (user_id, event_type),
            KEY user_timeline (user_id, created_at),
            KEY cleanup (created_at, event_type),
            KEY username (username),
            KEY ip_address (ip_address),
            FULLTEXT KEY search_message (event_message)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /* ==========================================================
       INSERT TOKEN
       ----------------------------------------------------------
       Stores a new verification token.
       ========================================================== */
    public static function insert_token($user_id, $email, $token, $expires_at, $is_test = 0) {
        global $wpdb;
        self::init();

        $data = [
            'user_id'    => (int) $user_id,
            'user_email' => sanitize_email($email),
            'token'      => sanitize_text_field($token),
            'expires_at' => gmdate('Y-m-d H:i:s', strtotime($expires_at)),
            'verified'   => 0,
            'is_test'    => (int) $is_test,
        ];

        $format = ['%d', '%s', '%s', '%s', '%d', '%d'];

        return (bool) $wpdb->insert(self::$table_name, $data, $format);
    }

    /* ==========================================================
       GET TOKEN
       ----------------------------------------------------------
       Retrieves a single token record by string.
       ========================================================== */
    public static function get_token($token) {
        global $wpdb;
        self::init();

        $sql = $wpdb->prepare(
            "SELECT * FROM " . self::$table_name . " WHERE token = %s LIMIT 1",
            $token
        );

        return $wpdb->get_row($sql);
    }

    /* ==========================================================
       MARK VERIFIED
       ----------------------------------------------------------
       Sets verified flag for a given token.
       ========================================================== */
    public static function mark_verified($token) {
        global $wpdb;
        self::init();

        return (bool) $wpdb->update(
            self::$table_name,
            ['verified' => 1],
            ['token' => sanitize_text_field($token)],
            ['%d'],
            ['%s']
        );
    }

    /* ==========================================================
       CLEANUP EXPIRED
       ----------------------------------------------------------
       Deletes expired or verified tokens (excluding test entries).
       ========================================================== */
    public static function cleanup_expired() {
        global $wpdb;
        self::init();

        $now = gmdate('Y-m-d H:i:s');

        return (bool) $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM " . self::$table_name . "
                 WHERE (expires_at < %s OR verified = 1)
                 AND is_test = 0",
                $now
            )
        );
    }

    /* ==========================================================
       DELETE USER TOKENS
       ----------------------------------------------------------
       Removes all tokens associated with a specific user ID.
       ========================================================== */
    public static function delete_user_tokens($user_id) {
        global $wpdb;
        self::init();

        return (bool) $wpdb->delete(
            self::$table_name,
            ['user_id' => (int) $user_id],
            ['%d']
        );
    }

    /* ==========================================================
       CREATE USER NOTES TABLE
       ----------------------------------------------------------
       Creates table for storing admin notes about users.
       Allows admins to track important information, support
       history, or other relevant details about each user.
       ========================================================== */
    public static function create_user_notes_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'nbuf_user_notes';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            note_content TEXT NOT NULL,
            created_by BIGINT(20) UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY created_by (created_by),
            KEY created_at (created_at),
            KEY user_created (user_id, created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /* ==========================================================
       CREATE IMPORT HISTORY TABLE
       ----------------------------------------------------------
       Creates table for tracking migration/import history from
       other plugins. Stores import statistics, errors, and
       allows rollback functionality.
       ========================================================== */
    public static function create_import_history_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'nbuf_import_history';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            source_plugin VARCHAR(100) NOT NULL,
            imported_by BIGINT(20) UNSIGNED NOT NULL,
            total_rows INT(11) UNSIGNED NOT NULL DEFAULT 0,
            successful INT(11) UNSIGNED NOT NULL DEFAULT 0,
            failed INT(11) UNSIGNED NOT NULL DEFAULT 0,
            skipped INT(11) UNSIGNED NOT NULL DEFAULT 0,
            error_log LONGTEXT NULL,
            imported_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY source_plugin (source_plugin),
            KEY imported_by (imported_by),
            KEY imported_at (imported_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /* ==========================================================
       CREATE MENU RESTRICTIONS TABLE
       ----------------------------------------------------------
       Creates table for storing menu item visibility restrictions.
       Controls which menu items are visible based on login status
       and user roles.
       ========================================================== */
    public static function create_menu_restrictions_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'nbuf_menu_restrictions';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
            menu_item_id BIGINT(20) UNSIGNED NOT NULL,
            visibility VARCHAR(20) NOT NULL DEFAULT 'everyone',
            allowed_roles TEXT,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (menu_item_id),
            KEY visibility (visibility)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /* ==========================================================
       CREATE CONTENT RESTRICTIONS TABLE
       ----------------------------------------------------------
       Creates table for storing post/page access restrictions.
       Controls who can view content and what happens when access
       is denied (message, redirect, or 404).
       ========================================================== */
    public static function create_content_restrictions_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'nbuf_content_restrictions';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
            content_id BIGINT(20) UNSIGNED NOT NULL,
            content_type VARCHAR(20) NOT NULL DEFAULT 'post',
            visibility VARCHAR(20) NOT NULL DEFAULT 'everyone',
            allowed_roles TEXT,
            restriction_action VARCHAR(20) NOT NULL DEFAULT 'message',
            custom_message TEXT,
            redirect_url VARCHAR(255),
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (content_id, content_type),
            KEY visibility (visibility),
            KEY content_type (content_type),
            KEY composite_lookup (content_type, visibility)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /* ==========================================================
       CREATE USER ROLES TABLE
       ----------------------------------------------------------
       Creates table for custom user roles with caching support.
       Lightweight alternative to options table bloat.
       ========================================================== */
    public static function create_user_roles_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'nbuf_user_roles';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            role_key VARCHAR(100) NOT NULL,
            role_name VARCHAR(200) NOT NULL,
            capabilities LONGTEXT NOT NULL,
            parent_role VARCHAR(100) DEFAULT NULL,
            priority INT DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY role_key (role_key),
            KEY parent_role (parent_role),
            KEY priority (priority)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /* ==========================================================
       UPDATE USER DATA TABLE FOR PRIVACY
       ----------------------------------------------------------
       Adds privacy control columns to user_data table for
       member directory and profile visibility features.
       Adds: profile_privacy, show_in_directory, privacy_settings
       ========================================================== */
    public static function update_user_data_table_for_privacy() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'nbuf_user_data';

        /* PERFORMANCE: Use helper method instead of SHOW COLUMNS */
        $needs_update = false;

        /* Add profile_privacy column */
        if ( ! self::column_exists( $table_name, 'profile_privacy' ) ) {
            $wpdb->query( "ALTER TABLE `{$table_name}` ADD COLUMN profile_privacy VARCHAR(20) DEFAULT 'members_only' AFTER password_changed_at" );
            $needs_update = true;
        }

        /* Add show_in_directory column */
        if ( ! self::column_exists( $table_name, 'show_in_directory' ) ) {
            $wpdb->query( "ALTER TABLE `{$table_name}` ADD COLUMN show_in_directory TINYINT(1) DEFAULT 0 AFTER profile_privacy" );
            $needs_update = true;
        }

        /* Add privacy_settings column */
        if ( ! self::column_exists( $table_name, 'privacy_settings' ) ) {
            $wpdb->query( "ALTER TABLE `{$table_name}` ADD COLUMN privacy_settings LONGTEXT DEFAULT NULL AFTER show_in_directory" );
            $needs_update = true;
        }

        /* Add composite index for directory queries if we made updates */
        if ($needs_update) {
            /* Check if index already exists */
            $indexes = $wpdb->get_results( "SHOW INDEX FROM `{$table_name}` WHERE Key_name = 'idx_directory'" );
            if (empty($indexes)) {
                $wpdb->query( "ALTER TABLE `{$table_name}` ADD INDEX idx_directory (show_in_directory, profile_privacy)" );
            }
        }

        return true;
    }

    /**
     * Update user_data table for profile/cover photos
     *
     * Adds columns for profile photo, cover photo, and Gravatar preference.
     * Safe to run multiple times (only adds columns if they don't exist).
     */
    public static function update_user_data_table_for_photos() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'nbuf_user_data';

        /* PERFORMANCE: Use helper method instead of SHOW COLUMNS */

        /* Add profile_photo_url column */
        if ( ! self::column_exists( $table_name, 'profile_photo_url' ) ) {
            $wpdb->query( "ALTER TABLE `{$table_name}` ADD COLUMN profile_photo_url VARCHAR(500) DEFAULT NULL AFTER privacy_settings" );
        }

        /* Add profile_photo_path column */
        if ( ! self::column_exists( $table_name, 'profile_photo_path' ) ) {
            $wpdb->query( "ALTER TABLE `{$table_name}` ADD COLUMN profile_photo_path VARCHAR(500) DEFAULT NULL AFTER profile_photo_url" );
        }

        /* Add cover_photo_url column */
        if ( ! self::column_exists( $table_name, 'cover_photo_url' ) ) {
            $wpdb->query( "ALTER TABLE `{$table_name}` ADD COLUMN cover_photo_url VARCHAR(500) DEFAULT NULL AFTER profile_photo_path" );
        }

        /* Add cover_photo_path column */
        if ( ! self::column_exists( $table_name, 'cover_photo_path' ) ) {
            $wpdb->query( "ALTER TABLE `{$table_name}` ADD COLUMN cover_photo_path VARCHAR(500) DEFAULT NULL AFTER cover_photo_url" );
        }

        /* Add use_gravatar column */
        if ( ! self::column_exists( $table_name, 'use_gravatar' ) ) {
            $wpdb->query( "ALTER TABLE `{$table_name}` ADD COLUMN use_gravatar TINYINT(1) DEFAULT 0 AFTER cover_photo_path" );
        }

        return true;
    }

    /* ==========================================================
       CREATE PROFILE VERSIONS TABLE
       ----------------------------------------------------------
       Creates table for tracking profile change history.
       Stores complete snapshots of user profiles over time
       with metadata about who changed what and when.

       Used for:
       - Version history timeline
       - Diff viewing (before/after comparison)
       - Audit trail
       - Revert capability
       - GDPR compliance (export/erasure)
       ========================================================== */
    public static function create_profile_versions_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'nbuf_profile_versions';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            changed_by BIGINT(20) UNSIGNED NULL COMMENT 'User who made change (NULL = self)',
            change_type VARCHAR(50) NOT NULL COMMENT 'profile_update, admin_update, registration, import, etc.',
            fields_changed TEXT NOT NULL COMMENT 'JSON array of changed field names',
            snapshot_data LONGTEXT NOT NULL COMMENT 'JSON snapshot of entire profile state',
            ip_address VARCHAR(45) NULL COMMENT 'IP address (anonymized or full based on settings)',
            user_agent VARCHAR(500) NULL,
            PRIMARY KEY (id),
            KEY user_timeline (user_id, changed_at),
            KEY changed_at (changed_at),
            KEY change_type (change_type),
            KEY changed_by (changed_by)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Update user_data table for password expiration.
     *
     * Adds columns for password expiration tracking:
     * - password_expires_at: When password expires (auto-calculated)
     * - force_password_change: Flag for admin-forced password changes
     *
     * Safe to run multiple times (only adds columns if they don't exist).
     */
    public static function update_user_data_table_for_password_expiration() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'nbuf_user_data';

        /* PERFORMANCE: Use helper method instead of SHOW COLUMNS */

        /* Add password_expires_at column */
        if ( ! self::column_exists( $table_name, 'password_expires_at' ) ) {
            $wpdb->query( "ALTER TABLE `{$table_name}` ADD COLUMN password_expires_at DATETIME DEFAULT NULL AFTER password_changed_at" );
        }

        /* Add force_password_change column */
        if ( ! self::column_exists( $table_name, 'force_password_change' ) ) {
            $wpdb->query( "ALTER TABLE `{$table_name}` ADD COLUMN force_password_change TINYINT(1) DEFAULT 0 AFTER password_expires_at" );
        }

        /* Add index for password expiration queries */
        $indexes = $wpdb->get_results( "SHOW INDEX FROM `{$table_name}` WHERE Key_name = 'idx_password_expiration'" );
        if (empty($indexes)) {
            $wpdb->query( "ALTER TABLE `{$table_name}` ADD INDEX idx_password_expiration (password_expires_at, force_password_change)" );
        }

        return true;
    }

    /**
     * Update user_profile table for account merging.
     *
     * Adds tertiary_email column to support merging multiple accounts
     * with different email addresses into a single account.
     *
     * Email storage after merge:
     * - Primary: user_email (wp_users table)
     * - Secondary: secondary_email (nbuf_user_profile)
     * - Tertiary: tertiary_email (nbuf_user_profile) - NEW
     *
     * Safe to run multiple times (only adds column if it doesn't exist).
     */
    public static function update_user_profile_table_for_account_merging() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'nbuf_user_profile';

        /* PERFORMANCE: Use helper method instead of SHOW COLUMNS */

        /* Add tertiary_email column after secondary_email */
        if ( ! self::column_exists( $table_name, 'tertiary_email' ) ) {
            $wpdb->query( "ALTER TABLE `{$table_name}` ADD COLUMN tertiary_email VARCHAR(255) DEFAULT NULL AFTER secondary_email" );
        }

        return true;
    }

    /* ==========================================================
       MIGRATE AUDIT LOG INDEXES
       ----------------------------------------------------------
       Adds search optimization indexes to existing audit log table.

       New indexes added in v1.4.0:
       - username: Optimize username searches
       - ip_address: Optimize IP searches
       - search_message: FULLTEXT for event message searches

       This migration is idempotent - safe to run multiple times.
       Only runs once per installation using option flag.

       @return bool True on success, false on failure
       ========================================================== */
    public static function migrate_audit_log_indexes() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'nbuf_user_audit_log';
        $migration_key = 'nbuf_audit_log_indexes_v1';

        /* Check if migration already run */
        if ( get_option( $migration_key ) ) {
            return true;
        }

        /* Verify table exists before attempting migration */
        $table_exists = $wpdb->get_var(
            $wpdb->prepare(
                'SHOW TABLES LIKE %s',
                $table_name
            )
        );

        if ( ! $table_exists ) {
            /* Table doesn't exist yet - indexes will be created during table creation */
            update_option( $migration_key, time() );
            return true;
        }

        /* Get existing indexes to avoid duplication errors */
        $existing_indexes = $wpdb->get_results(
            "SHOW INDEX FROM `{$table_name}`",
            ARRAY_A
        );
        $index_names = wp_list_pluck( $existing_indexes, 'Key_name' );

        $migration_success = true;
        $errors = array();

        /* Add username index if not exists */
        if ( ! in_array( 'username', $index_names, true ) ) {
            $result = $wpdb->query( "ALTER TABLE `{$table_name}` ADD KEY username (username)" );
            if ( false === $result ) {
                $migration_success = false;
                $errors[] = 'Failed to add username index: ' . $wpdb->last_error;
            }
        }

        /* Add ip_address index if not exists */
        if ( ! in_array( 'ip_address', $index_names, true ) ) {
            $result = $wpdb->query( "ALTER TABLE `{$table_name}` ADD KEY ip_address (ip_address)" );
            if ( false === $result ) {
                $migration_success = false;
                $errors[] = 'Failed to add ip_address index: ' . $wpdb->last_error;
            }
        }

        /* Add FULLTEXT search_message index if not exists */
        if ( ! in_array( 'search_message', $index_names, true ) ) {
            /*
             * FULLTEXT indexes require special handling:
             * - Requires InnoDB engine (MySQL 5.6+)
             * - Column must not be NULL
             * - May fail on older MySQL versions
             */
            $result = $wpdb->query( "ALTER TABLE `{$table_name}` ADD FULLTEXT KEY search_message (event_message)" );
            if ( false === $result ) {
                $migration_success = false;
                $errors[] = 'Failed to add FULLTEXT index: ' . $wpdb->last_error;
            }
        }

        /* Log results */
        if ( $migration_success ) {
            error_log( '[NoBloat User Foundry] Audit log indexes migration completed successfully' );
            update_option( $migration_key, time() );
        } else {
            error_log( '[NoBloat User Foundry] Audit log indexes migration failed: ' . implode( '; ', $errors ) );
            /* Don't mark as complete if migration failed - allow retry */
        }

        return $migration_success;
    }

    /* ==========================================================
       GET TABLE NAME
       ----------------------------------------------------------
       Helper method to get full table name with WordPress prefix.

       @param string $table Short table name (e.g., 'user_data')
       @return string Full table name with prefix
       ========================================================== */
    public static function get_table_name($table) {
        global $wpdb;
        return $wpdb->prefix . 'nbuf_' . $table;
    }
}

/* Auto-init on load */
NBUF_Database::init();