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

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
// phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange

/**
 * Direct database access is architectural for this plugin.
 * Custom tables (nbuf_tokens, nbuf_user_data, nbuf_options, etc.) cannot use
 * WordPress's standard meta/options APIs. Caching is not implemented as data
 * changes frequently and caching would introduce stale data issues.
 * Schema changes occur only during plugin activation/updates.
 */

/**
 * Class NBUF_Database
 *
 * Handles database operations for custom tables.
 */
class NBUF_Database {


	/**
	 * Table name cache.
	 *
	 * @var string
	 */
	private static $table_name;

	/**
	 * Initialize database.
	 *
	 * Ensure the table name is initialized with the WP prefix.
	 *
	 * @return void
	 */
	public static function init(): void {
		global $wpdb;
		self::$table_name = $wpdb->prefix . 'nbuf_tokens';
	}

	/**
	==========================================================
	COLUMN EXISTS CHECK (PERFORMANCE HELPER)
	----------------------------------------------------------
	Efficiently check if a column exists using INFORMATION_SCHEMA.
	Faster than SHOW COLUMNS as it returns boolean instead of
	loading all column metadata.
	 *
		@param  string $table_name  Table name to check.
	@param  string $column_name Column name to check.
	@return bool True if column exists.
	==========================================================
	 */
	private static function column_exists( $table_name, $column_name ) {
		global $wpdb;

		/* Use INFORMATION_SCHEMA for single query check */
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*)
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                 AND TABLE_NAME = %s
                 AND COLUMN_NAME = %s',
				$table_name,
				$column_name
			)
		);

		/* SECURITY: Explicit integer comparison to prevent type juggling */
		return (int) $exists > 0;
	}

	/**
	 * Create tokens table.
	 *
	 * Runs on activation to ensure schema exists.
	 *
	 * @return void
	 */
	public static function create_table(): void {
		global $wpdb;
		self::init();

		$charset_collate = $wpdb->get_charset_collate();
		$table_name      = self::$table_name;

		$sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            user_email VARCHAR(255) NOT NULL,
            token VARCHAR(128) NOT NULL,
            type VARCHAR(32) NOT NULL DEFAULT 'verification',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NOT NULL,
            verified TINYINT(1) NOT NULL DEFAULT 0,
            is_test TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY token (token),
            KEY user_id (user_id),
            KEY user_email (user_email),
            KEY type (type),
            KEY cleanup (is_test, verified, expires_at)
        ) {$charset_collate};";

		include_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Create user data table.
	 *
	 * Creates table for user verification and expiration data.
	 *
	 * @return void
	 */
	public static function create_user_data_table(): void {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'nbuf_user_data';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
            user_id BIGINT(20) UNSIGNED NOT NULL,
            is_verified TINYINT(1) NOT NULL DEFAULT 0,
            verified_date DATETIME NULL,
            requires_approval TINYINT(1) NOT NULL DEFAULT 0,
            is_approved TINYINT(1) NOT NULL DEFAULT 1,
            approved_by BIGINT(20) UNSIGNED NULL,
            approved_date DATETIME NULL,
            approval_notes TEXT NULL,
            is_disabled TINYINT(1) NOT NULL DEFAULT 0,
            disabled_reason VARCHAR(50) NULL,
            expires_at DATETIME NULL,
            expiration_warned_at DATETIME NULL,
            weak_password_flagged_at DATETIME NULL,
            password_changed_at DATETIME NULL,
            PRIMARY KEY (user_id),
            KEY is_verified (is_verified),
            KEY is_approved (is_approved),
            KEY is_disabled (is_disabled),
            KEY expires_at (expires_at),
            KEY pending_approval (requires_approval, is_approved, is_verified),
            KEY unverified_cleanup (is_verified, is_disabled),
            KEY warning_check (expires_at, expiration_warned_at, is_disabled)
        ) {$charset_collate};";

		include_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Create options table.
	 *
	 * Creates custom options table to isolate plugin settings
	 * from WordPress wp_options table. Only loads when plugin
	 * is actively being used, reducing bloat on other pages.
	 *
	 * @return void
	 */
	public static function create_options_table(): void {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'nbuf_options';
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

		include_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Create user profile table.
	 *
	 * Creates table for extended user profile fields (phone,
	 * company, address, etc.). Separate from user_data which
	 * handles verification and expiration.
	 *
	 * @return void
	 */
	public static function create_user_profile_table(): void {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'nbuf_user_profile';
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

		include_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Create login attempts table.
	 *
	 * Creates table to track failed login attempts for rate
	 * limiting and account protection.
	 *
	 * @return void
	 */
	public static function create_login_attempts_table(): void {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'nbuf_login_attempts';
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

		include_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Create user 2FA table.
	 *
	 * Creates table for two-factor authentication data.
	 * Replaces wp_usermeta storage to eliminate bloat.
	 *
	 * @return void
	 */
	public static function create_user_2fa_table(): void {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'nbuf_user_2fa';
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

		include_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Create user passkeys table.
	 *
	 * Creates table for storing WebAuthn passkey credentials.
	 * Enables passwordless authentication via biometrics or
	 * security keys. Stores COSE public keys and credential IDs.
	 *
	 * @return void
	 */
	public static function create_user_passkeys_table(): void {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'nbuf_user_passkeys';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            credential_id VARBINARY(255) NOT NULL,
            public_key BLOB NOT NULL,
            sign_count INT(10) UNSIGNED NOT NULL DEFAULT 0,
            transports VARCHAR(255) DEFAULT NULL,
            aaguid BINARY(16) DEFAULT NULL,
            device_name VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_used DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY credential_id (credential_id),
            KEY user_id (user_id),
            KEY user_device (user_id, device_name)
        ) {$charset_collate};";

		include_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Create user audit log table.
	 *
	 * Creates table for tracking user activity and security events.
	 * Tracks authentication, verification, password changes, 2FA,
	 * and other user-centric actions.
	 *
	 * @return void
	 */
	public static function create_user_audit_log_table(): void {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'nbuf_user_audit_log';
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

		include_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Create admin audit log table.
	 *
	 * Separate table for admin actions on users and system settings.
	 * GDPR: Different purpose and retention policy from user-initiated actions.
	 *
	 * @since 1.4.0
	 * @return void
	 */
	public static function create_admin_audit_log_table(): void {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'nbuf_admin_audit_log';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            admin_id BIGINT(20) UNSIGNED NOT NULL,
            admin_username VARCHAR(60) NOT NULL,
            target_user_id BIGINT(20) UNSIGNED DEFAULT NULL,
            target_username VARCHAR(60) DEFAULT NULL,
            action_type VARCHAR(50) NOT NULL,
            action_status VARCHAR(20) NOT NULL,
            action_message TEXT DEFAULT NULL,
            metadata TEXT DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY admin_id (admin_id),
            KEY target_user_id (target_user_id),
            KEY action_type (action_type),
            KEY action_status (action_status),
            KEY created_at (created_at),
            KEY admin_actions (admin_id, created_at),
            KEY user_modifications (target_user_id, created_at),
            KEY cleanup (created_at, action_type),
            FULLTEXT KEY search_message (action_message)
        ) {$charset_collate};";

		include_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	==========================================================
	INSERT TOKEN
	----------------------------------------------------------
	Stores a new verification token.
	 *
		@param  int    $user_id    User ID.
	@param  string $email      User email address.
	@param  string $token      Verification token string.
	@param  mixed  $expires_at Expiration timestamp or date string.
	@param  int    $is_test    Whether this is a test token (default 0).
	@return bool True on success.
	==========================================================
	 */
	public static function insert_token( $user_id, $email, $token, $expires_at, $is_test = 0 ) {
		global $wpdb;
		self::init();

		/* CONSISTENCY: Use current_time('mysql', true) for UTC instead of gmdate() */
		$data = array(
			'user_id'    => (int) $user_id,
			'user_email' => sanitize_email( $email ),
			'token'      => sanitize_text_field( $token ),
			'expires_at' => is_numeric( $expires_at ) ? gmdate( 'Y-m-d H:i:s', $expires_at ) : gmdate( 'Y-m-d H:i:s', strtotime( $expires_at ) ),
			'verified'   => 0,
			'is_test'    => (int) $is_test,
		);

		$format = array( '%d', '%s', '%s', '%s', '%d', '%d' );

		return (bool) $wpdb->insert( self::$table_name, $data, $format );
	}

	/**
	==========================================================
	INSERT TOKEN ATOMICALLY (RACE-SAFE)
	----------------------------------------------------------
	Inserts a verification token only if no valid (unexpired,
	unverified) token exists for the email. Uses a single
	INSERT ... SELECT query to prevent race conditions.

	@param  int    $user_id    User ID.
	@param  string $email      User email address.
	@param  string $token      Verification token string.
	@param  mixed  $expires_at Expiration timestamp or date string.
	@param  int    $is_test    Whether this is a test token (default 0).
	@return bool True if token was inserted, false if token already existed.
	==========================================================
	 */
	public static function insert_token_atomic( $user_id, $email, $token, $expires_at, $is_test = 0 ) {
		global $wpdb;
		self::init();

		$sanitized_email   = sanitize_email( $email );
		$sanitized_token   = sanitize_text_field( $token );
		$expires_formatted = is_numeric( $expires_at )
			? gmdate( 'Y-m-d H:i:s', $expires_at )
			: gmdate( 'Y-m-d H:i:s', strtotime( $expires_at ) );
		$now               = gmdate( 'Y-m-d H:i:s' );

		/*
		 * Atomic INSERT ... SELECT that only inserts if no valid token exists.
		 * This prevents race conditions where two concurrent requests both
		 * check for existing tokens and then both insert.
		 *
		 * The WHERE NOT EXISTS subquery ensures the insert only happens if
		 * no unexpired, unverified token exists for this email.
		 */
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic token insert requires direct query.
		$result = $wpdb->query(
			$wpdb->prepare(
				'INSERT INTO %i (user_id, user_email, token, expires_at, verified, is_test)
				SELECT %d, %s, %s, %s, 0, %d
				WHERE NOT EXISTS (
					SELECT 1 FROM %i
					WHERE user_email = %s
					AND verified = 0
					AND expires_at > %s
					LIMIT 1
				)',
				self::$table_name,
				(int) $user_id,
				$sanitized_email,
				$sanitized_token,
				$expires_formatted,
				(int) $is_test,
				self::$table_name,
				$sanitized_email,
				$now
			)
		);

		/* $result is the number of rows affected (1 if inserted, 0 if skipped) */
		return ( 1 === $result );
	}

	/**
	==========================================================
	GET TOKEN
	----------------------------------------------------------
	Retrieves a single token record by string.
	 *
		@param  string $token Token string to retrieve.
	@return object|null Token object or null if not found.
	==========================================================
	 */
	public static function get_token( $token ) {
		global $wpdb;
		self::init();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Query prepared above.
		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE token = %s LIMIT 1',
				self::$table_name,
				$token
			)
		);
	}

	/**
	==========================================================
	MARK VERIFIED
	----------------------------------------------------------
	Sets verified flag for a given token.
	 *
		@param  string $token Token string to mark as verified.
	@return bool True on success.
	==========================================================
	 */
	public static function mark_verified( $token ) {
		global $wpdb;
		self::init();

		return (bool) $wpdb->update(
			self::$table_name,
			array( 'verified' => 1 ),
			array( 'token' => sanitize_text_field( $token ) ),
			array( '%d' ),
			array( '%s' )
		);
	}

	/**
	 * Cleanup expired tokens.
	 *
	 * Deletes expired or verified tokens (excluding test entries).
	 *
	 * @return void
	 */
	public static function cleanup_expired(): void {
		global $wpdb;
		self::init();

		$now = gmdate( 'Y-m-d H:i:s' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cleanup operation on custom table.
		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE (expires_at < %s OR verified = 1) AND is_test = 0',
				$wpdb->prefix . 'nbuf_tokens',
				$now
			)
		);
	}

	/**
	==========================================================
	DELETE USER TOKENS
	----------------------------------------------------------
	Removes all tokens associated with a specific user ID.
	 *
		@param  int $user_id User ID.
	@return bool True on success.
	==========================================================
	 */
	public static function delete_user_tokens( $user_id ) {
		global $wpdb;
		self::init();

		return (bool) $wpdb->delete(
			self::$table_name,
			array( 'user_id' => (int) $user_id ),
			array( '%d' )
		);
	}

	/**
	==========================================================
	GET VALID TOKEN BY EMAIL
	----------------------------------------------------------
	Check if user has a valid (unexpired, unverified) token.
	Used to avoid sending duplicate verification emails.

	@param  string $email User email address.
	@return object|null Token row if exists, null otherwise.
	==========================================================
	 */
	public static function get_valid_token( $email ) {
		global $wpdb;
		self::init();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE user_email = %s AND verified = 0 AND expires_at > %s LIMIT 1',
				self::$table_name,
				sanitize_email( $email ),
				current_time( 'mysql', true )
			)
		);
	}

	/**
	 * Create user notes table.
	 *
	 * Creates table for storing admin notes about users.
	 * Allows admins to track important information, support
	 * history, or other relevant details about each user.
	 *
	 * @return void
	 */
	public static function create_user_notes_table(): void {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'nbuf_user_notes';
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

		include_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Create import history table.
	 *
	 * Creates table for tracking migration/import history from
	 * other plugins. Stores import statistics, errors, and
	 * allows rollback functionality.
	 *
	 * @return void
	 */
	public static function create_import_history_table(): void {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'nbuf_import_history';
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

		include_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Create menu restrictions table.
	 *
	 * Creates table for storing menu item visibility restrictions.
	 * Controls which menu items are visible based on login status
	 * and user roles.
	 *
	 * @return void
	 */
	public static function create_menu_restrictions_table(): void {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'nbuf_menu_restrictions';
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

		include_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Create content restrictions table.
	 *
	 * Creates table for storing post/page access restrictions.
	 * Controls who can view content and what happens when access
	 * is denied (message, redirect, or 404).
	 *
	 * @return void
	 */
	public static function create_content_restrictions_table(): void {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'nbuf_content_restrictions';
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

		include_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Create user roles table.
	 *
	 * Creates table for custom user roles with caching support.
	 * Lightweight alternative to options table bloat.
	 *
	 * @return void
	 */
	public static function create_user_roles_table(): void {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'nbuf_user_roles';
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

		include_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Update user data table for privacy.
	 *
	 * Adds privacy control columns to user_data table for
	 * member directory and profile visibility features.
	 * Adds: profile_privacy, show_in_directory, privacy_settings
	 *
	 * @return void
	 */
	public static function update_user_data_table_for_privacy(): void {
		global $wpdb;
		$table_name = $wpdb->prefix . 'nbuf_user_data';

		/* PERFORMANCE: Use helper method instead of SHOW COLUMNS */
		$needs_update = false;

		/* Add profile_privacy column */
		if ( ! self::column_exists( $table_name, 'profile_privacy' ) ) {
			$wpdb->query( $wpdb->prepare( "ALTER TABLE %i ADD COLUMN profile_privacy VARCHAR(20) DEFAULT 'private' AFTER password_changed_at", $table_name ) );
			$needs_update = true;
		}

		/* Add show_in_directory column */
		if ( ! self::column_exists( $table_name, 'show_in_directory' ) ) {
			$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD COLUMN show_in_directory TINYINT(1) DEFAULT 0 AFTER profile_privacy', $table_name ) );
			$needs_update = true;
		}

		/* Add privacy_settings column */
		if ( ! self::column_exists( $table_name, 'privacy_settings' ) ) {
			$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD COLUMN privacy_settings LONGTEXT DEFAULT NULL AFTER show_in_directory', $table_name ) );
			$needs_update = true;
		}

		/* Add visible_fields column for user-selected public profile fields */
		if ( ! self::column_exists( $table_name, 'visible_fields' ) ) {
			$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD COLUMN visible_fields TEXT DEFAULT NULL AFTER privacy_settings', $table_name ) );
			$needs_update = true;
		}

		/* Add composite index for directory queries if we made updates */
		if ( $needs_update ) {
			/* Check if index already exists */
			$indexes = $wpdb->get_results( $wpdb->prepare( "SHOW INDEX FROM %i WHERE Key_name = 'idx_directory'", $table_name ) );
			if ( empty( $indexes ) ) {
				$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD INDEX idx_directory (show_in_directory, profile_privacy)', $table_name ) );
			}
		}
	}

	/**
	 * Update user_data table for profile/cover photos.
	 *
	 * Adds columns for profile photo, cover photo, and Gravatar preference.
	 * Safe to run multiple times (only adds columns if they don't exist).
	 *
	 * @return void
	 */
	public static function update_user_data_table_for_photos(): void {
		global $wpdb;
		$table_name = $wpdb->prefix . 'nbuf_user_data';

		/* PERFORMANCE: Use helper method instead of SHOW COLUMNS */

		/* Add profile_photo_url column */
		if ( ! self::column_exists( $table_name, 'profile_photo_url' ) ) {
			$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD COLUMN profile_photo_url VARCHAR(500) DEFAULT NULL AFTER privacy_settings', $table_name ) );
		}

		/* Add profile_photo_path column */
		if ( ! self::column_exists( $table_name, 'profile_photo_path' ) ) {
			$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD COLUMN profile_photo_path VARCHAR(500) DEFAULT NULL AFTER profile_photo_url', $table_name ) );
		}

		/* Add cover_photo_url column */
		if ( ! self::column_exists( $table_name, 'cover_photo_url' ) ) {
			$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD COLUMN cover_photo_url VARCHAR(500) DEFAULT NULL AFTER profile_photo_path', $table_name ) );
		}

		/* Add cover_photo_path column */
		if ( ! self::column_exists( $table_name, 'cover_photo_path' ) ) {
			$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD COLUMN cover_photo_path VARCHAR(500) DEFAULT NULL AFTER cover_photo_url', $table_name ) );
		}

		/* Add use_gravatar column */
		if ( ! self::column_exists( $table_name, 'use_gravatar' ) ) {
			$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD COLUMN use_gravatar TINYINT(1) DEFAULT 0 AFTER cover_photo_path', $table_name ) );
		}
	}

	/**
	 * Create profile versions table.
	 *
	 * Creates table for tracking profile change history.
	 * Stores complete snapshots of user profiles over time
	 * with metadata about who changed what and when.
	 *
	 * @return void
	 */
	public static function create_profile_versions_table(): void {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'nbuf_profile_versions';
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

		include_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Update user_data table for password expiration.
	 *
	 * Adds columns for password expiration tracking:
	 * - password_expires_at: When password expires (auto-calculated)
	 * - force_password_change: Flag for admin-forced password changes
	 *
	 * Safe to run multiple times (only adds columns if they don't exist).
	 *
	 * @return void
	 */
	public static function update_user_data_table_for_password_expiration(): void {
		global $wpdb;
		$table_name = $wpdb->prefix . 'nbuf_user_data';

		/* PERFORMANCE: Use helper method instead of SHOW COLUMNS */

		/* Add password_expires_at column */
		if ( ! self::column_exists( $table_name, 'password_expires_at' ) ) {
			$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD COLUMN password_expires_at DATETIME DEFAULT NULL AFTER password_changed_at', $table_name ) );
		}

		/* Add force_password_change column */
		if ( ! self::column_exists( $table_name, 'force_password_change' ) ) {
			$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD COLUMN force_password_change TINYINT(1) DEFAULT 0 AFTER password_expires_at', $table_name ) );
		}

		/* Add index for password expiration queries */
		$indexes = $wpdb->get_results( $wpdb->prepare( "SHOW INDEX FROM %i WHERE Key_name = 'idx_password_expiration'", $table_name ) );
		if ( empty( $indexes ) ) {
			$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD INDEX idx_password_expiration (password_expires_at, force_password_change)', $table_name ) );
		}
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
	 *
	 * @return void
	 */
	public static function update_user_profile_table_for_account_merging(): void {
		global $wpdb;
		$table_name = $wpdb->prefix . 'nbuf_user_profile';

		/* PERFORMANCE: Use helper method instead of SHOW COLUMNS */

		/* Add tertiary_email column after secondary_email */
		if ( ! self::column_exists( $table_name, 'tertiary_email' ) ) {
			$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD COLUMN tertiary_email VARCHAR(255) DEFAULT NULL AFTER secondary_email', $table_name ) );
		}
	}

	/**
	 * Update user_data table for last login tracking.
	 *
	 * Adds last_login_at column to track when users last logged in.
	 * Enables member directory sorting by last login and admin visibility.
	 *
	 * Safe to run multiple times (only adds column if it doesn't exist).
	 *
	 * @return void
	 */
	public static function update_user_data_table_for_last_login(): void {
		global $wpdb;
		$table_name = $wpdb->prefix . 'nbuf_user_data';

		/* Add last_login_at column after password_changed_at */
		if ( ! self::column_exists( $table_name, 'last_login_at' ) ) {
			$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD COLUMN last_login_at DATETIME DEFAULT NULL AFTER password_changed_at', $table_name ) );
		}

		/* Add index for sorting by last login */
		$indexes = $wpdb->get_results( $wpdb->prepare( "SHOW INDEX FROM %i WHERE Key_name = 'idx_last_login'", $table_name ) );
		if ( empty( $indexes ) ) {
			$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD INDEX idx_last_login (last_login_at)', $table_name ) );
		}
	}

	/**
	==========================================================
	MIGRATE AUDIT LOG INDEXES
	----------------------------------------------------------
	Adds search optimization indexes to existing audit log table.
		New indexes added in v1.4.0:
	- username: Optimize username searches
	- ip_address: Optimize IP searches
	- search_message: FULLTEXT for event message searches
		This migration is idempotent - safe to run multiple times.
	Only runs once per installation using option flag.
	 *
		@return bool True on success, false on failure.
	==========================================================
	 */
	public static function migrate_audit_log_indexes() {
		global $wpdb;

		$table_name    = $wpdb->prefix . 'nbuf_user_audit_log';
		$migration_key = 'nbuf_audit_log_indexes_v1';

		/* Check if migration already run */
		if ( NBUF_Options::get( $migration_key ) ) {
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
			NBUF_Options::update( $migration_key, time(), false, 'system' );
			return true;
		}

		/* Get existing indexes to avoid duplication errors */
		$existing_indexes = $wpdb->get_results(
			$wpdb->prepare( 'SHOW INDEX FROM %i', $table_name ),
			ARRAY_A
		);
		$index_names      = wp_list_pluck( $existing_indexes, 'Key_name' );

		$migration_success = true;
		$errors            = array();

		/* Add username index if not exists */
		if ( ! in_array( 'username', $index_names, true ) ) {
			$result = $wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD KEY username (username)', $table_name ) );
			if ( false === $result ) {
				$migration_success = false;
				$errors[]          = 'Failed to add username index: ' . $wpdb->last_error;
			}
		}

		/* Add ip_address index if not exists */
		if ( ! in_array( 'ip_address', $index_names, true ) ) {
			$result = $wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD KEY ip_address (ip_address)', $table_name ) );
			if ( false === $result ) {
				$migration_success = false;
				$errors[]          = 'Failed to add ip_address index: ' . $wpdb->last_error;
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
			$result = $wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD FULLTEXT KEY search_message (event_message)', $table_name ) );
			if ( false === $result ) {
				$migration_success = false;
				$errors[]          = 'Failed to add FULLTEXT index: ' . $wpdb->last_error;
			}
		}

		/* Log errors and mark complete if successful */
		if ( $migration_success ) {
			NBUF_Options::update( $migration_key, time(), false, 'system' );
		} else {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Logging actual migration failures for troubleshooting.
			error_log( '[NoBloat User Foundry] Audit log indexes migration failed: ' . implode( '; ', $errors ) );
			/* Don't mark as complete if migration failed - allow retry */
		}

		return $migration_success;
	}

	/**
	==========================================================
	MIGRATE TOKENS TABLE INDEXES
	----------------------------------------------------------
	Adds user_email index to tokens table for faster lookups
	in get_valid_token() queries.

	This migration is idempotent - safe to run multiple times.
	Only runs once per installation using option flag.

	@since 1.5.0
	@return bool True on success, false on failure.
	==========================================================
	 */
	public static function migrate_tokens_indexes() {
		global $wpdb;
		self::init();

		$migration_key = 'nbuf_tokens_indexes_v1';

		/* Check if migration already run */
		if ( NBUF_Options::get( $migration_key ) ) {
			return true;
		}

		/* Verify table exists before attempting migration */
		$table_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				self::$table_name
			)
		);

		if ( ! $table_exists ) {
			/* Table doesn't exist yet - indexes will be created during table creation */
			NBUF_Options::update( $migration_key, time(), false, 'system' );
			return true;
		}

		/* Get existing indexes to avoid duplication errors */
		$existing_indexes = $wpdb->get_results(
			$wpdb->prepare( 'SHOW INDEX FROM %i', self::$table_name ),
			ARRAY_A
		);
		$index_names      = wp_list_pluck( $existing_indexes, 'Key_name' );

		$migration_success = true;

		/* Add user_email index if not exists */
		if ( ! in_array( 'user_email', $index_names, true ) ) {
			$result = $wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD KEY user_email (user_email)', self::$table_name ) );
			if ( false === $result ) {
				$migration_success = false;
				error_log( '[NoBloat User Foundry] Tokens index migration failed: ' . $wpdb->last_error ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Migration error logging only.
			}
		}

		/* Mark migration complete */
		if ( $migration_success ) {
			NBUF_Options::update( $migration_key, time(), false, 'system' );
		}

		return $migration_success;
	}

	/**
	 * Migrate tokens table type column.
	 *
	 * Adds type column to tokens table to support different
	 * token types (verification, magic_link, etc.).
	 *
	 * @return bool True on success or already migrated.
	 */
	public static function migrate_tokens_type_column(): bool {
		global $wpdb;
		self::init();

		$migration_key = 'nbuf_tokens_type_column_v1';

		/* Check if migration already run */
		if ( NBUF_Options::get( $migration_key ) ) {
			return true;
		}

		/* Add type column if not exists */
		if ( ! self::column_exists( self::$table_name, 'type' ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Migration adding column.
			$result = $wpdb->query( $wpdb->prepare( "ALTER TABLE %i ADD COLUMN type VARCHAR(32) NOT NULL DEFAULT 'verification' AFTER token", $wpdb->prefix . 'nbuf_tokens' ) );
			if ( false === $result ) {
				error_log( '[NoBloat User Foundry] Tokens type column migration failed: ' . $wpdb->last_error ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Migration error logging only.
				return false;
			}

			/*
			 * Add index for type column.
			 */
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Migration adding index.
			$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD KEY type (type)', $wpdb->prefix . 'nbuf_tokens' ) );
		}

		NBUF_Options::update( $migration_key, time(), false, 'system' );
		return true;
	}

	/**
	 * Create webhooks table.
	 *
	 * Creates table for storing webhook configurations.
	 * Enables sending HTTP POST notifications to external
	 * services when user events occur.
	 *
	 * @return void
	 */
	public static function create_webhooks_table(): void {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'nbuf_webhooks';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            url VARCHAR(2048) NOT NULL,
            secret VARCHAR(255) DEFAULT NULL,
            events TEXT NOT NULL COMMENT 'JSON array of event types',
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            last_triggered DATETIME DEFAULT NULL,
            last_status INT(3) DEFAULT NULL COMMENT 'HTTP response code',
            failure_count INT(10) UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY enabled (enabled),
            KEY enabled_events (enabled)
        ) {$charset_collate};";

		include_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Create webhook log table.
	 *
	 * Creates table for logging webhook delivery attempts.
	 * Useful for debugging and monitoring webhook reliability.
	 *
	 * @return void
	 */
	public static function create_webhook_log_table(): void {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'nbuf_webhook_log';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            webhook_id BIGINT(20) UNSIGNED NOT NULL,
            event_type VARCHAR(50) NOT NULL,
            payload TEXT NOT NULL COMMENT 'JSON payload sent',
            response_code INT(3) DEFAULT NULL,
            response_body TEXT DEFAULT NULL,
            duration_ms INT(10) UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY webhook_id (webhook_id),
            KEY event_type (event_type),
            KEY created_at (created_at),
            KEY webhook_time (webhook_id, created_at)
        ) {$charset_collate};";

		include_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Create ToS versions table.
	 *
	 * Creates table for storing Terms of Service version history.
	 * Supports multiple versions with effective dates for compliance.
	 *
	 * @return void
	 */
	public static function create_tos_versions_table(): void {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'nbuf_tos_versions';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            version VARCHAR(20) NOT NULL,
            title VARCHAR(255) NOT NULL,
            content LONGTEXT NOT NULL,
            effective_date DATETIME NOT NULL,
            created_by BIGINT(20) UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            is_active TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY version (version),
            KEY is_active (is_active),
            KEY effective_date (effective_date),
            KEY active_effective (is_active, effective_date)
        ) {$charset_collate};";

		include_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Create ToS acceptances table.
	 *
	 * Creates table for tracking user ToS acceptances.
	 * Records which version each user accepted and when.
	 *
	 * @return void
	 */
	public static function create_tos_acceptances_table(): void {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'nbuf_tos_acceptances';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            tos_version_id BIGINT(20) UNSIGNED NOT NULL,
            accepted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent VARCHAR(500) DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY user_version (user_id, tos_version_id),
            KEY user_id (user_id),
            KEY tos_version_id (tos_version_id),
            KEY accepted_at (accepted_at)
        ) {$charset_collate};";

		include_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Get table name with WordPress prefix.
	 *
	 * Helper method to get full table name with WordPress prefix.
	 *
	 * @param string $table Short table name (e.g., 'user_data').
	 * @return string Full table name with prefix.
	 */
	public static function get_table_name( string $table ): string {
		global $wpdb;
		return $wpdb->prefix . 'nbuf_' . $table;
	}

	/**
	 * Get expected tables.
	 *
	 * Returns table names that SHOULD exist (for repair/validation).
	 * Used to identify missing tables that need to be created.
	 *
	 * @return array<string, string> Associative array of table_key => full_table_name.
	 */
	public static function get_expected_tables(): array {
		global $wpdb;

		return array(
			'tokens'               => $wpdb->prefix . 'nbuf_tokens',
			'user_data'            => $wpdb->prefix . 'nbuf_user_data',
			'user_2fa'             => $wpdb->prefix . 'nbuf_user_2fa',
			'user_passkeys'        => $wpdb->prefix . 'nbuf_user_passkeys',
			'user_profile'         => $wpdb->prefix . 'nbuf_user_profile',
			'profile_versions'     => $wpdb->prefix . 'nbuf_profile_versions',
			'login_attempts'       => $wpdb->prefix . 'nbuf_login_attempts',
			'options'              => $wpdb->prefix . 'nbuf_options',
			'user_audit_log'       => $wpdb->prefix . 'nbuf_user_audit_log',
			'admin_audit_log'      => $wpdb->prefix . 'nbuf_admin_audit_log',
			'security_log'         => $wpdb->prefix . 'nbuf_security_log',
			'user_notes'           => $wpdb->prefix . 'nbuf_user_notes',
			'user_roles'           => $wpdb->prefix . 'nbuf_user_roles',
			'import_history'       => $wpdb->prefix . 'nbuf_import_history',
			'menu_restrictions'    => $wpdb->prefix . 'nbuf_menu_restrictions',
			'content_restrictions' => $wpdb->prefix . 'nbuf_content_restrictions',
			'webhooks'             => $wpdb->prefix . 'nbuf_webhooks',
			'webhook_log'          => $wpdb->prefix . 'nbuf_webhook_log',
			'tos_versions'         => $wpdb->prefix . 'nbuf_tos_versions',
			'tos_acceptances'      => $wpdb->prefix . 'nbuf_tos_acceptances',
		);
	}

	/**
	 * Get all tables (database introspection).
	 *
	 * Discovers all nbuf_ tables that actually exist in database.
	 * Compares against expected tables to provide complete picture.
	 *
	 * @return array{expected: array<string, string>, existing: array<string, string>, missing: array<string, string>, unexpected: array<string, string>, all: array<string, string>} Array with 'expected', 'existing', 'missing', 'unexpected', 'all' keys.
	 */
	public static function get_all_tables(): array {
		global $wpdb;

		$expected_tables = self::get_expected_tables();

		/*
		 * Query database for all nbuf_ tables that actually exist.
		 */
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing_in_db = $wpdb->get_col(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$wpdb->prefix . 'nbuf_%'
			)
		);

		/* Build result arrays */
		$existing   = array();
		$missing    = array();
		$unexpected = array();

		/* Check expected tables - which exist, which are missing */
		foreach ( $expected_tables as $key => $table_name ) {
			if ( in_array( $table_name, $existing_in_db, true ) ) {
				$existing[ $key ] = $table_name;
			} else {
				$missing[ $key ] = $table_name;
			}
		}

		/* Check for unexpected tables (exist in DB but not in expected list) */
		$expected_names = array_values( $expected_tables );
		foreach ( $existing_in_db as $table_name ) {
			if ( ! in_array( $table_name, $expected_names, true ) ) {
				/* Extract key from table name (remove prefix + nbuf_) */
				$key                = str_replace( $wpdb->prefix . 'nbuf_', '', $table_name );
				$unexpected[ $key ] = $table_name;
			}
		}

		return array(
			'expected'   => $expected_tables,
			'existing'   => $existing,
			'missing'    => $missing,
			'unexpected' => $unexpected,
			'all'        => array_merge( $existing, $missing, $unexpected ),
		);
	}

	/**
	 * Repair all tables.
	 *
	 * Creates all missing database tables.
	 * Safe to run multiple times (uses CREATE TABLE IF NOT EXISTS).
	 * Called by diagnostics repair button.
	 *
	 * @return void
	 */
	public static function repair_all_tables(): void {
		/* Create all core tables */
		self::create_table();
		self::create_user_data_table();
		self::create_options_table();
		self::create_user_profile_table();
		self::create_login_attempts_table();
		self::create_user_2fa_table();
		self::create_user_passkeys_table();
		self::create_user_audit_log_table();
		self::create_admin_audit_log_table();
		self::create_user_notes_table();
		self::create_import_history_table();
		self::create_menu_restrictions_table();
		self::create_content_restrictions_table();
		self::create_user_roles_table();
		self::create_profile_versions_table();
		self::create_webhooks_table();
		self::create_webhook_log_table();
		self::create_tos_versions_table();
		self::create_tos_acceptances_table();

		/* Run column update migrations */
		self::update_user_data_table_for_privacy();
		self::update_user_data_table_for_photos();
		self::update_user_data_table_for_password_expiration();
		self::update_user_data_table_for_last_login();
		self::update_user_profile_table_for_account_merging();

		/* Create security log table if class exists */
		if ( class_exists( 'NBUF_Security_Log' ) ) {
			NBUF_Security_Log::create_table();
		}

		/* Run user meta migration */
		self::update_tables_for_usermeta_migration();
	}

	/**
	 * Update tables for user meta migration.
	 *
	 * Adds columns to store data that was previously in wp_usermeta.
	 * This consolidates user data into custom tables for better performance.
	 *
	 * New columns:
	 * - nbuf_user_data: pending_email, last_data_export, passkey_prompt_dismissed
	 * - nbuf_user_2fa: totp_grace_start
	 *
	 * Safe to run multiple times (only adds columns if they don't exist).
	 *
	 * @since 1.5.0
	 * @return void
	 */
	public static function update_tables_for_usermeta_migration(): void {
		global $wpdb;

		/* Update nbuf_user_data table */
		$user_data_table = $wpdb->prefix . 'nbuf_user_data';

		/* Add pending_email column for email change verification */
		if ( ! self::column_exists( $user_data_table, 'pending_email' ) ) {
			$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD COLUMN pending_email VARCHAR(100) DEFAULT NULL AFTER last_login_at', $user_data_table ) );
		}

		/* Add last_data_export column for GDPR rate limiting */
		if ( ! self::column_exists( $user_data_table, 'last_data_export' ) ) {
			$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD COLUMN last_data_export DATETIME DEFAULT NULL AFTER pending_email', $user_data_table ) );
		}

		/* Add passkey_prompt_dismissed column for passkey prompt tracking */
		if ( ! self::column_exists( $user_data_table, 'passkey_prompt_dismissed' ) ) {
			$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD COLUMN passkey_prompt_dismissed TEXT DEFAULT NULL AFTER last_data_export', $user_data_table ) );
		}

		/* Update nbuf_user_2fa table */
		$twofa_table = $wpdb->prefix . 'nbuf_user_2fa';

		/* Add totp_grace_start column for TOTP setup grace period */
		if ( ! self::column_exists( $twofa_table, 'totp_grace_start' ) ) {
			$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD COLUMN totp_grace_start DATETIME DEFAULT NULL AFTER forced_at', $twofa_table ) );
		}
	}

	/**
	 * Migrate user meta data from wp_usermeta to custom tables.
	 *
	 * Moves data for these meta keys:
	 * - nbuf_pending_email -> nbuf_user_data.pending_email
	 * - nbuf_last_data_export -> nbuf_user_data.last_data_export
	 * - nbuf_passkey_prompt_dismissed_devices -> nbuf_user_data.passkey_prompt_dismissed
	 * - nbuf_totp_grace_start -> nbuf_user_2fa.totp_grace_start
	 *
	 * After migration, the wp_usermeta entries are deleted.
	 * Safe to run multiple times - only migrates data that exists.
	 *
	 * @since 1.5.0
	 * @return array{migrated: int, deleted: int} Count of migrated and deleted entries.
	 */
	public static function migrate_usermeta_to_custom_tables(): array {
		global $wpdb;

		$migrated = 0;
		$deleted  = 0;

		$user_data_table = $wpdb->prefix . 'nbuf_user_data';
		$twofa_table     = $wpdb->prefix . 'nbuf_user_2fa';

		/* Meta keys to migrate and their target columns */
		$user_data_migrations = array(
			'nbuf_pending_email'                    => 'pending_email',
			'nbuf_last_data_export'                 => 'last_data_export',
			'nbuf_passkey_prompt_dismissed_devices' => 'passkey_prompt_dismissed',
		);

		/* Migrate user_data fields */
		foreach ( $user_data_migrations as $meta_key => $column ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$entries = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT user_id, meta_value FROM %i WHERE meta_key = %s',
					$wpdb->usermeta,
					$meta_key
				)
			);

			foreach ( $entries as $entry ) {
				$user_id = (int) $entry->user_id;
				$value   = $entry->meta_value;

				/* Format value based on column type */
				if ( 'last_data_export' === $column && is_numeric( $value ) ) {
					/* Convert timestamp to datetime */
					$value = gmdate( 'Y-m-d H:i:s', (int) $value );
				}

				/* Update custom table - use NBUF_User_Data::update for proper handling */
				NBUF_User_Data::update( $user_id, array( $column => $value ) );
				++$migrated;

				// Delete from usermeta.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time migration cleanup.
				$wpdb->delete(
					$wpdb->usermeta,
					array(
						'user_id'  => $user_id,
						'meta_key' => $meta_key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					)
				);
				++$deleted;
			}
		}

		// Migrate totp_grace_start to 2FA table.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$grace_entries = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT user_id, meta_value FROM %i WHERE meta_key = %s',
				$wpdb->usermeta,
				'nbuf_totp_grace_start'
			)
		);

		foreach ( $grace_entries as $entry ) {
			$user_id = (int) $entry->user_id;
			$value   = (int) $entry->meta_value;

			/* Convert timestamp to datetime */
			$datetime = gmdate( 'Y-m-d H:i:s', $value );

			// Check if user has 2FA record.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$exists = $wpdb->get_var(
				$wpdb->prepare( 'SELECT user_id FROM %i WHERE user_id = %d', $twofa_table, $user_id )
			);

			if ( $exists ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update( $twofa_table, array( 'totp_grace_start' => $datetime ), array( 'user_id' => $user_id ) );
			} else {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->insert(
					$twofa_table,
					array(
						'user_id'          => $user_id,
						'totp_grace_start' => $datetime,
					)
				);
			}
			++$migrated;

			// Delete from usermeta.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time migration cleanup.
			$wpdb->delete(
				$wpdb->usermeta,
				array(
					'user_id'  => $user_id,
					'meta_key' => 'nbuf_totp_grace_start', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				)
			);
			++$deleted;
		}

		/* Mark migration as complete */
		if ( class_exists( 'NBUF_Options' ) ) {
			NBUF_Options::update( 'nbuf_usermeta_migrated', 1, false, 'system' );
		}

		return array(
			'migrated' => $migrated,
			'deleted'  => $deleted,
		);
	}
}
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
// phpcs:enable WordPress.DB.DirectDatabaseQuery.SchemaChange

/* Auto-init on load */
NBUF_Database::init();
