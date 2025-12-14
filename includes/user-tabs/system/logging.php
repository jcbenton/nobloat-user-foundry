<?php
/**
 * Logging Settings Tab
 *
 * Enterprise-grade 3-table GDPR-compliant logging configuration.
 * Separates user activity, admin actions, and security events by legal purpose.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage User_Tabs/System
 * @since      1.4.0
 */

/* Prevent direct access */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Security: Verify user has permission to access this page */
if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'nobloat-user-foundry' ) );
}

/* Define settings fields */
$settings_fields = array(

	array(
		'id'    => 'nbuf_logging_section_master',
		'title' => __( 'Master Logging Toggles', 'nobloat-user-foundry' ),
		'type'  => 'section',
		'desc'  => __( 'Enable or disable entire logging systems. Each system serves a distinct legal purpose under GDPR.', 'nobloat-user-foundry' ),
	),

	array(
		'id'       => 'nbuf_logging_user_audit_enabled',
		'title'    => __( 'Enable User Activity Logging', 'nobloat-user-foundry' ),
		'type'     => 'checkbox',
		'default'  => true,
		'category' => 'logging',
		'desc'     => __( 'Track user-initiated actions (login, logout, password changes, 2FA, profile updates). GDPR Basis: Consent or Legitimate Interest (Article 6(1)(a) or (f)).', 'nobloat-user-foundry' ),
	),

	array(
		'id'       => 'nbuf_logging_admin_audit_enabled',
		'title'    => __( 'Enable Admin Action Logging', 'nobloat-user-foundry' ),
		'type'     => 'checkbox',
		'default'  => true,
		'category' => 'logging',
		'desc'     => __( 'Track administrator actions on users and settings (user deletion, role changes, manual verifications). GDPR Basis: Legitimate Interest - Accountability (Article 6(1)(f)). <strong>Recommended for compliance.</strong>', 'nobloat-user-foundry' ),
	),

	array(
		'id'       => 'nbuf_logging_security_enabled',
		'title'    => __( 'Enable Security Event Logging', 'nobloat-user-foundry' ),
		'type'     => 'checkbox',
		'default'  => true,
		'category' => 'logging',
		'desc'     => __( 'Track security events (file validation failures, CSRF attempts, privilege escalation). GDPR Basis: Legitimate Interest - Security (Article 32). <strong>Recommended for security.</strong>', 'nobloat-user-foundry' ),
	),

	array(
		'id'    => 'nbuf_logging_section_user_audit',
		'title' => __( 'User Activity Log Settings', 'nobloat-user-foundry' ),
		'type'  => 'section',
		'desc'  => __( 'Configure what user-initiated actions are logged. Table: <code>nbuf_user_audit_log</code>', 'nobloat-user-foundry' ),
	),

	array(
		'id'       => 'nbuf_logging_user_audit_categories',
		'title'    => __( 'Log Categories', 'nobloat-user-foundry' ),
		'type'     => 'checkbox_group',
		'default'  => array(
			'authentication' => true,
			'verification'   => true,
			'passwords'      => true,
			'2fa'            => true,
			'account_status' => true,
			'profile'        => false,
		),
		'category' => 'logging',
		'options'  => array(
			'authentication' => __( 'Authentication (login, logout, registration)', 'nobloat-user-foundry' ),
			'verification'   => __( 'Email Verification', 'nobloat-user-foundry' ),
			'passwords'      => __( 'Password Changes (user-initiated)', 'nobloat-user-foundry' ),
			'2fa'            => __( 'Two-Factor Authentication', 'nobloat-user-foundry' ),
			'account_status' => __( 'Account Status (disabled, enabled, expired)', 'nobloat-user-foundry' ),
			'profile'        => __( 'Profile Updates (user-initiated changes)', 'nobloat-user-foundry' ),
		),
		'desc'     => __( 'Select which categories of user actions to log.', 'nobloat-user-foundry' ),
	),

	array(
		'id'       => 'nbuf_logging_user_audit_retention',
		'title'    => __( 'Retention Period', 'nobloat-user-foundry' ),
		'type'     => 'select',
		'default'  => '365',
		'category' => 'logging',
		'options'  => array(
			'30'      => __( '30 days', 'nobloat-user-foundry' ),
			'60'      => __( '60 days', 'nobloat-user-foundry' ),
			'90'      => __( '90 days', 'nobloat-user-foundry' ),
			'180'     => __( '180 days', 'nobloat-user-foundry' ),
			'365'     => __( '1 year (recommended)', 'nobloat-user-foundry' ),
			'730'     => __( '2 years', 'nobloat-user-foundry' ),
			'1095'    => __( '3 years', 'nobloat-user-foundry' ),
			'1825'    => __( '5 years', 'nobloat-user-foundry' ),
			'forever' => __( 'Forever', 'nobloat-user-foundry' ),
		),
		'desc'     => __( 'How long to keep user activity logs. GDPR requires data minimization - only retain as long as necessary.', 'nobloat-user-foundry' ),
	),

	array(
		'id'    => 'nbuf_logging_section_admin_audit',
		'title' => __( 'Admin Action Log Settings', 'nobloat-user-foundry' ),
		'type'  => 'section',
		'desc'  => __( 'Configure what administrator actions are logged. Table: <code>nbuf_admin_audit_log</code>', 'nobloat-user-foundry' ),
	),

	array(
		'id'       => 'nbuf_logging_admin_audit_categories',
		'title'    => __( 'Log Categories', 'nobloat-user-foundry' ),
		'type'     => 'checkbox_group',
		'default'  => array(
			'user_deletion'        => true,
			'role_changes'         => true,
			'settings_changes'     => true,
			'bulk_actions'         => true,
			'manual_verifications' => true,
			'password_resets'      => true,
			'profile_edits'        => true,
		),
		'category' => 'logging',
		'options'  => array(
			'user_deletion'        => __( 'User Deletion (account deletions by admin)', 'nobloat-user-foundry' ),
			'role_changes'         => __( 'Role Changes (role assignments)', 'nobloat-user-foundry' ),
			'settings_changes'     => __( 'Settings Changes (critical configuration)', 'nobloat-user-foundry' ),
			'bulk_actions'         => __( 'Bulk Actions (mass operations)', 'nobloat-user-foundry' ),
			'manual_verifications' => __( 'Manual Verifications (admin verifying users)', 'nobloat-user-foundry' ),
			'password_resets'      => __( 'Password Resets (admin resetting passwords)', 'nobloat-user-foundry' ),
			'profile_edits'        => __( 'Profile Edits (admin editing user profiles)', 'nobloat-user-foundry' ),
		),
		'desc'     => __( 'Select which categories of admin actions to log. <strong>All recommended for compliance.</strong>', 'nobloat-user-foundry' ),
	),

	array(
		'id'       => 'nbuf_logging_admin_audit_retention',
		'title'    => __( 'Retention Period', 'nobloat-user-foundry' ),
		'type'     => 'select',
		'default'  => 'forever',
		'category' => 'logging',
		'options'  => array(
			'365'     => __( '1 year', 'nobloat-user-foundry' ),
			'730'     => __( '2 years', 'nobloat-user-foundry' ),
			'1095'    => __( '3 years', 'nobloat-user-foundry' ),
			'1825'    => __( '5 years', 'nobloat-user-foundry' ),
			'2555'    => __( '7 years', 'nobloat-user-foundry' ),
			'forever' => __( 'Forever (recommended for compliance)', 'nobloat-user-foundry' ),
		),
		'desc'     => __( 'How long to keep admin action logs. Forever is recommended for accountability and compliance requirements.', 'nobloat-user-foundry' ),
	),

	array(
		'id'    => 'nbuf_logging_section_security',
		'title' => __( 'Security Event Log Settings', 'nobloat-user-foundry' ),
		'type'  => 'section',
		'desc'  => __( 'Configure what security events are logged. Table: <code>nbuf_security_log</code>', 'nobloat-user-foundry' ),
	),

	array(
		'id'       => 'nbuf_logging_security_categories',
		'title'    => __( 'Log Categories', 'nobloat-user-foundry' ),
		'type'     => 'checkbox_group',
		'default'  => array(
			'file_operations'      => true,
			'csrf_attempts'        => true,
			'privilege_escalation' => true,
			'login_limiting'       => true,
			'import_errors'        => true,
		),
		'category' => 'logging',
		'options'  => array(
			'file_operations'      => __( 'File Operations (validation, errors)', 'nobloat-user-foundry' ),
			'csrf_attempts'        => __( 'CSRF Attempts (origin mismatches)', 'nobloat-user-foundry' ),
			'privilege_escalation' => __( 'Privilege Escalation (blocked attempts)', 'nobloat-user-foundry' ),
			'login_limiting'       => __( 'Login Limiting (lockouts)', 'nobloat-user-foundry' ),
			'import_errors'        => __( 'Import Errors (CSV injection, validation)', 'nobloat-user-foundry' ),
		),
		'desc'     => __( 'Select which categories of security events to log. <strong>All recommended for security monitoring.</strong>', 'nobloat-user-foundry' ),
	),

	array(
		'id'       => 'nbuf_logging_security_retention',
		'title'    => __( 'Retention Period', 'nobloat-user-foundry' ),
		'type'     => 'select',
		'default'  => '90',
		'category' => 'logging',
		'options'  => array(
			'30'      => __( '30 days', 'nobloat-user-foundry' ),
			'60'      => __( '60 days', 'nobloat-user-foundry' ),
			'90'      => __( '90 days (recommended)', 'nobloat-user-foundry' ),
			'180'     => __( '180 days', 'nobloat-user-foundry' ),
			'365'     => __( '1 year', 'nobloat-user-foundry' ),
			'forever' => __( 'Forever', 'nobloat-user-foundry' ),
		),
		'desc'     => __( 'How long to keep security event logs. 90 days is typically sufficient for incident investigation.', 'nobloat-user-foundry' ),
	),

	array(
		'id'    => 'nbuf_logging_section_privacy',
		'title' => __( 'Privacy Settings (All Logs)', 'nobloat-user-foundry' ),
		'type'  => 'section',
		'desc'  => __( 'Configure privacy options that apply to all logging tables.', 'nobloat-user-foundry' ),
	),

	array(
		'id'       => 'nbuf_logging_anonymize_ip',
		'title'    => __( 'Anonymize IP Addresses', 'nobloat-user-foundry' ),
		'type'     => 'checkbox',
		'default'  => false,
		'category' => 'logging',
		'desc'     => __( 'Anonymize IP addresses before storing (removes last octet for IPv4, last 80 bits for IPv6). Recommended for GDPR compliance in EU.', 'nobloat-user-foundry' ),
	),

	array(
		'id'       => 'nbuf_logging_store_user_agent',
		'title'    => __( 'Store User Agent Strings', 'nobloat-user-foundry' ),
		'type'     => 'checkbox',
		'default'  => true,
		'category' => 'logging',
		'desc'     => __( 'Store browser user agent strings in logs. Useful for identifying browsers/devices but contains identifiable information.', 'nobloat-user-foundry' ),
	),

	array(
		'id'    => 'nbuf_logging_section_gdpr',
		'title' => __( 'GDPR Data Subject Rights', 'nobloat-user-foundry' ),
		'type'  => 'section',
		'desc'  => __( 'Configure how logging data is handled for GDPR compliance.', 'nobloat-user-foundry' ),
	),

	array(
		'id'       => 'nbuf_logging_include_in_export',
		'title'    => __( 'Include Logs in User Data Export', 'nobloat-user-foundry' ),
		'type'     => 'checkbox',
		'default'  => true,
		'category' => 'logging',
		'desc'     => __( 'Include user activity and admin action logs when user requests data export (GDPR Right to Access). Recommended: enabled.', 'nobloat-user-foundry' ),
	),

	array(
		'id'       => 'nbuf_logging_user_deletion_action',
		'title'    => __( 'On User Deletion', 'nobloat-user-foundry' ),
		'type'     => 'radio',
		'default'  => 'anonymize',
		'category' => 'logging',
		'options'  => array(
			'anonymize' => __( 'Anonymize user in logs (recommended)', 'nobloat-user-foundry' ),
			'delete'    => __( 'Delete user logs completely', 'nobloat-user-foundry' ),
		),
		'desc'     => __( 'How to handle log entries when a user is deleted. Anonymizing preserves audit trail while removing identifiable information. Complete deletion may violate compliance requirements.', 'nobloat-user-foundry' ),
	),

	array(
		'id'    => 'nbuf_logging_section_export',
		'title' => __( 'Export & Maintenance', 'nobloat-user-foundry' ),
		'type'  => 'section',
		'desc'  => __( 'Export logs and perform maintenance operations. View logs under <strong>Logs</strong> menu in sidebar.', 'nobloat-user-foundry' ),
	),

	array(
		'id'       => 'nbuf_logging_export_buttons',
		'title'    => __( 'Export Logs', 'nobloat-user-foundry' ),
		'type'     => 'custom',
		'category' => 'logging',
		'callback' => 'nbuf_render_logging_export_buttons',
	),

	array(
		'id'       => 'nbuf_logging_purge_buttons',
		'title'    => __( 'Purge Logs', 'nobloat-user-foundry' ),
		'type'     => 'custom',
		'category' => 'logging',
		'callback' => 'nbuf_render_logging_purge_buttons',
	),

	array(
		'id'       => 'nbuf_logging_statistics',
		'title'    => __( 'Log Statistics', 'nobloat-user-foundry' ),
		'type'     => 'custom',
		'category' => 'logging',
		'callback' => 'nbuf_render_logging_statistics',
	),
);

/**
 * Render export buttons
 *
 * @return void
 */
function nbuf_render_logging_export_buttons() {
	?>
	<div class="nbuf-export-buttons">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=nbuf-user-audit-log&action=export' ) ); ?>" class="button button-secondary">
			<span class="dashicons dashicons-download"></span> <?php esc_html_e( 'Export User Activity Log (CSV)', 'nobloat-user-foundry' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=nbuf-admin-audit-log&action=export' ) ); ?>" class="button button-secondary">
			<span class="dashicons dashicons-download"></span> <?php esc_html_e( 'Export Admin Actions Log (CSV)', 'nobloat-user-foundry' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=nbuf-security-log&action=export' ) ); ?>" class="button button-secondary">
			<span class="dashicons dashicons-download"></span> <?php esc_html_e( 'Export Security Events Log (CSV)', 'nobloat-user-foundry' ); ?>
		</a>
	</div>
	<p class="description">
		<?php esc_html_e( 'Export all log entries to CSV files. These files can be used for compliance reporting, auditing, or importing into analysis tools.', 'nobloat-user-foundry' ); ?>
	</p>
	<?php
}

/**
 * Render purge buttons
 *
 * @return void
 */
function nbuf_render_logging_purge_buttons() {
	?>
	<div class="nbuf-purge-buttons">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=nbuf-user-audit-log&action=purge' ) ); ?>" class="button button-link-delete" onclick="return confirm('⚠️ PERMANENTLY DELETE ALL USER ACTIVITY LOGS?\n\nThis cannot be undone. Type DELETE to confirm.');">
			<span class="dashicons dashicons-trash"></span> <?php esc_html_e( 'Purge User Activity Log', 'nobloat-user-foundry' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=nbuf-admin-audit-log&action=purge' ) ); ?>" class="button button-link-delete" onclick="return confirm('⚠️ PERMANENTLY DELETE ALL ADMIN ACTION LOGS?\n\n⚠️ WARNING: Admin logs may be required for compliance.\n\nThis cannot be undone. Type DELETE to confirm.');">
			<span class="dashicons dashicons-trash"></span> <?php esc_html_e( 'Purge Admin Actions Log', 'nobloat-user-foundry' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=nbuf-security-log&action=purge' ) ); ?>" class="button button-link-delete" onclick="return confirm('⚠️ PERMANENTLY DELETE ALL SECURITY EVENT LOGS?\n\nThis cannot be undone. Type DELETE to confirm.');">
			<span class="dashicons dashicons-trash"></span> <?php esc_html_e( 'Purge Security Events Log', 'nobloat-user-foundry' ); ?>
		</a>
	</div>
	<p class="description" style="color: #d63638;">
		<strong><?php esc_html_e( 'WARNING:', 'nobloat-user-foundry' ); ?></strong> <?php esc_html_e( 'Purging logs permanently deletes all entries. Check with legal/compliance before purging. Consider adjusting retention periods instead.', 'nobloat-user-foundry' ); ?>
	</p>
	<?php
}

/**
 * Render log statistics
 *
 * @return void
 */
function nbuf_render_logging_statistics() {
	/* Get statistics from each log table */
	$user_stats     = NBUF_Audit_Log::get_stats();
	$admin_stats    = class_exists( 'NBUF_Admin_Audit_Log' ) ? NBUF_Admin_Audit_Log::get_stats() : array( 'total' => 0 );
	$security_stats = class_exists( 'NBUF_Security_Log' ) ? NBUF_Security_Log::get_stats() : array( 'total_entries' => 0 );
	?>
	<div class="nbuf-log-stats">
		<div class="nbuf-log-stat-box">
			<h4><?php esc_html_e( 'User Activity Log', 'nobloat-user-foundry' ); ?></h4>
			<div class="nbuf-log-stat-number"><?php echo esc_html( number_format_i18n( $user_stats['total_entries'] ) ); ?></div>
			<div class="nbuf-log-stat-label"><?php esc_html_e( 'Total Entries', 'nobloat-user-foundry' ); ?></div>
			<div class="nbuf-log-stat-detail">
				<div><strong><?php esc_html_e( 'Database Size:', 'nobloat-user-foundry' ); ?></strong> <?php echo esc_html( $user_stats['database_size'] ); ?></div>
				<div><strong><?php esc_html_e( 'Oldest Entry:', 'nobloat-user-foundry' ); ?></strong> <?php echo esc_html( $user_stats['oldest_entry'] ); ?></div>
			</div>
		</div>

		<div class="nbuf-log-stat-box">
			<h4><?php esc_html_e( 'Admin Actions Log', 'nobloat-user-foundry' ); ?></h4>
			<div class="nbuf-log-stat-number"><?php echo esc_html( number_format_i18n( isset( $admin_stats['total'] ) ? $admin_stats['total'] : 0 ) ); ?></div>
			<div class="nbuf-log-stat-label"><?php esc_html_e( 'Total Entries', 'nobloat-user-foundry' ); ?></div>
			<div class="nbuf-log-stat-detail">
				<div><strong><?php esc_html_e( 'Today:', 'nobloat-user-foundry' ); ?></strong> <?php echo esc_html( number_format_i18n( isset( $admin_stats['today'] ) ? $admin_stats['today'] : 0 ) ); ?></div>
				<div><strong><?php esc_html_e( 'This Week:', 'nobloat-user-foundry' ); ?></strong> <?php echo esc_html( number_format_i18n( isset( $admin_stats['week'] ) ? $admin_stats['week'] : 0 ) ); ?></div>
			</div>
		</div>

		<div class="nbuf-log-stat-box">
			<h4><?php esc_html_e( 'Security Events Log', 'nobloat-user-foundry' ); ?></h4>
			<div class="nbuf-log-stat-number"><?php echo esc_html( number_format_i18n( $security_stats['total_entries'] ) ); ?></div>
			<div class="nbuf-log-stat-label"><?php esc_html_e( 'Total Entries', 'nobloat-user-foundry' ); ?></div>
			<div class="nbuf-log-stat-detail">
				<div><strong><?php esc_html_e( 'Database Size:', 'nobloat-user-foundry' ); ?></strong> <?php echo esc_html( $security_stats['database_size'] ); ?></div>
				<div><strong><?php esc_html_e( 'Oldest Entry:', 'nobloat-user-foundry' ); ?></strong> <?php echo esc_html( $security_stats['oldest_entry'] ); ?></div>
			</div>
		</div>
	</div>
	<p class="description" style="margin-top: 20px;">
		<?php esc_html_e( 'Statistics are updated in real-time. To view detailed logs, visit the Logs menu in the sidebar.', 'nobloat-user-foundry' ); ?>
	</p>
	<?php
}

/* Render settings form */
NBUF_Settings_Page::render_settings( $settings_fields, __( 'Logging Settings', 'nobloat-user-foundry' ) );
