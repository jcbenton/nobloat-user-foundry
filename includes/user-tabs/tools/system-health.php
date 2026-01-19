<?php
/**
 * Tools > System Health Tab
 *
 * Cron job verification and template load tests.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * ==========================================================
 * GATHER CRON JOB DATA
 * ==========================================================
 * Uses centralized definitions from NBUF_Cron for single source of truth.
 * Any new cron jobs added to NBUF_Cron::get_cron_definitions() will
 * automatically appear here.
 */
$nbuf_cron_jobs = NBUF_Cron::get_cron_definitions();

/* Get scheduled status for each cron job */
$nbuf_cron_status = array();
$nbuf_crons_array = _get_cron_array();

foreach ( $nbuf_cron_jobs as $nbuf_hook => $nbuf_info ) {
	$nbuf_cron_status[ $nbuf_hook ] = array(
		'scheduled' => false,
		'next_run'  => null,
	);

	if ( ! empty( $nbuf_crons_array ) ) {
		foreach ( $nbuf_crons_array as $nbuf_timestamp => $nbuf_cron ) {
			if ( isset( $nbuf_cron[ $nbuf_hook ] ) ) {
				$nbuf_cron_status[ $nbuf_hook ]['scheduled'] = true;
				$nbuf_cron_status[ $nbuf_hook ]['next_run']  = $nbuf_timestamp;
				break;
			}
		}
	}
}

/*
 * ==========================================================
 * GATHER TEMPLATE DATA
 * ==========================================================
 */
$nbuf_templates = array(
	/* Email Templates */
	'Email Templates' => array(
		'email-verification-html'   => __( 'Email Verification (HTML)', 'nobloat-user-foundry' ),
		'email-verification-text'   => __( 'Email Verification (Text)', 'nobloat-user-foundry' ),
		'welcome-email-html'        => __( 'Welcome Email (HTML)', 'nobloat-user-foundry' ),
		'welcome-email-text'        => __( 'Welcome Email (Text)', 'nobloat-user-foundry' ),
		'password-reset-html'       => __( 'Password Reset (HTML)', 'nobloat-user-foundry' ),
		'password-reset-text'       => __( 'Password Reset (Text)', 'nobloat-user-foundry' ),
		'2fa-email-code-html'       => __( '2FA Email Code (HTML)', 'nobloat-user-foundry' ),
		'2fa-email-code-text'       => __( '2FA Email Code (Text)', 'nobloat-user-foundry' ),
		'expiration-warning-html'   => __( 'Expiration Warning (HTML)', 'nobloat-user-foundry' ),
		'expiration-warning-text'   => __( 'Expiration Warning (Text)', 'nobloat-user-foundry' ),
		'expiration-notice-html'    => __( 'Expiration Notice (HTML)', 'nobloat-user-foundry' ),
		'expiration-notice-text'    => __( 'Expiration Notice (Text)', 'nobloat-user-foundry' ),
		'admin-new-user-html'       => __( 'Admin New User (HTML)', 'nobloat-user-foundry' ),
		'admin-new-user-text'       => __( 'Admin New User (Text)', 'nobloat-user-foundry' ),
		'security-alert-email-html' => __( 'Security Alert (HTML)', 'nobloat-user-foundry' ),
	),
	/* Form Templates */
	'Form Templates'  => array(
		'login-form'         => __( 'Login Form', 'nobloat-user-foundry' ),
		'registration-form'  => __( 'Registration Form', 'nobloat-user-foundry' ),
		'account-page'       => __( 'Account Page', 'nobloat-user-foundry' ),
		'request-reset-form' => __( 'Password Reset Request', 'nobloat-user-foundry' ),
		'reset-form'         => __( 'Password Reset Form', 'nobloat-user-foundry' ),
	),
	/* 2FA Templates */
	'2FA Templates'   => array(
		'2fa-verify'        => __( '2FA Verification', 'nobloat-user-foundry' ),
		'2fa-setup-totp'    => __( '2FA TOTP Setup', 'nobloat-user-foundry' ),
		'2fa-backup-codes'  => __( '2FA Backup Codes', 'nobloat-user-foundry' ),
		'2fa-backup-verify' => __( '2FA Backup Verify', 'nobloat-user-foundry' ),
	),
	/* Page Templates */
	'Page Templates'  => array(
		'public-profile-html'        => __( 'Public Profile', 'nobloat-user-foundry' ),
		'member-directory-html'      => __( 'Member Directory (Grid)', 'nobloat-user-foundry' ),
		'member-directory-list-html' => __( 'Member Directory (List)', 'nobloat-user-foundry' ),
		'account-data-export-html'   => __( 'Account Data Export', 'nobloat-user-foundry' ),
	),
);

/* Test each template */
$nbuf_template_status = array();
foreach ( $nbuf_templates as $nbuf_group => $nbuf_group_templates ) {
	foreach ( $nbuf_group_templates as $nbuf_template_name => $nbuf_label ) {
		$nbuf_content                                = NBUF_Template_Manager::load_template( $nbuf_template_name );
		$nbuf_template_status[ $nbuf_template_name ] = array(
			'loaded' => ! empty( $nbuf_content ) && strpos( $nbuf_content, 'Template not found' ) === false,
			'size'   => strlen( $nbuf_content ),
			'empty'  => empty( trim( $nbuf_content ) ),
		);
	}
}
?>

<h2><?php esc_html_e( 'Cron Job Verification', 'nobloat-user-foundry' ); ?></h2>
<p class="description">
	<?php esc_html_e( 'Verifies that all scheduled maintenance tasks are properly registered with WordPress. These tasks handle cleanup, notifications, and automated account management.', 'nobloat-user-foundry' ); ?>
</p>

<?php
$nbuf_scheduled_count   = 0;
$nbuf_unscheduled_count = 0;
foreach ( $nbuf_cron_status as $cron_item ) {
	if ( $cron_item['scheduled'] ) {
		++$nbuf_scheduled_count;
	} else {
		++$nbuf_unscheduled_count;
	}
}
?>

<div style="margin: 20px 0; padding: 15px; background: <?php echo $nbuf_unscheduled_count > 0 ? '#fff3cd' : '#d4edda'; ?>; border-left: 4px solid <?php echo $nbuf_unscheduled_count > 0 ? '#856404' : '#28a745'; ?>; border-radius: 4px;">
	<strong>
		<?php
		if ( 0 === $nbuf_unscheduled_count ) {
			printf(
				/* translators: %d: number of scheduled cron jobs */
				esc_html__( 'All %d cron jobs are properly scheduled.', 'nobloat-user-foundry' ),
				count( $nbuf_cron_jobs )
			);
		} else {
			printf(
				/* translators: 1: number of scheduled jobs, 2: number of unscheduled jobs */
				esc_html__( '%1$d jobs scheduled, %2$d jobs not scheduled.', 'nobloat-user-foundry' ),
				(int) $nbuf_scheduled_count,
				(int) $nbuf_unscheduled_count
			);
		}
		?>
	</strong>
</div>

<table class="widefat striped" style="margin-top: 15px;">
	<thead>
		<tr>
			<th style="width: 25%;"><?php esc_html_e( 'Cron Job', 'nobloat-user-foundry' ); ?></th>
			<th style="width: 35%;"><?php esc_html_e( 'Description', 'nobloat-user-foundry' ); ?></th>
			<th style="width: 10%;"><?php esc_html_e( 'Schedule', 'nobloat-user-foundry' ); ?></th>
			<th style="width: 15%;"><?php esc_html_e( 'Status', 'nobloat-user-foundry' ); ?></th>
			<th style="width: 15%;"><?php esc_html_e( 'Next Run', 'nobloat-user-foundry' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ( $nbuf_cron_jobs as $nbuf_hook => $nbuf_info ) : ?>
			<?php $nbuf_status = $nbuf_cron_status[ $nbuf_hook ]; ?>
			<tr>
				<td><strong><?php echo esc_html( $nbuf_info['label'] ); ?></strong><br><code style="font-size: 11px; color: #666;"><?php echo esc_html( $nbuf_hook ); ?></code></td>
				<td><?php echo esc_html( $nbuf_info['description'] ); ?></td>
				<td><?php echo esc_html( ucfirst( $nbuf_info['schedule'] ) ); ?></td>
				<td>
					<?php if ( $nbuf_status['scheduled'] ) : ?>
						<span style="color: #28a745; font-weight: 600;"><?php esc_html_e( 'Scheduled', 'nobloat-user-foundry' ); ?></span>
					<?php else : ?>
						<span style="color: #dc3545; font-weight: 600;"><?php esc_html_e( 'Not Scheduled', 'nobloat-user-foundry' ); ?></span>
					<?php endif; ?>
				</td>
				<td>
					<?php if ( $nbuf_status['next_run'] ) : ?>
						<?php
						$nbuf_next_run_human = human_time_diff( time(), $nbuf_status['next_run'] );
						if ( $nbuf_status['next_run'] < time() ) {
							$nbuf_next_run_human = __( 'Overdue', 'nobloat-user-foundry' );
						} else {
							/* translators: %s: time until next run */
							$nbuf_next_run_human = sprintf( __( 'in %s', 'nobloat-user-foundry' ), $nbuf_next_run_human );
						}
						?>
						<span title="<?php echo esc_attr( gmdate( 'Y-m-d H:i:s', $nbuf_status['next_run'] ) ); ?>">
							<?php echo esc_html( $nbuf_next_run_human ); ?>
						</span>
					<?php else : ?>
						<span style="color: #999;">—</span>
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>

<?php if ( $nbuf_unscheduled_count > 0 ) : ?>
	<p class="description" style="margin-top: 15px;">
		<strong><?php esc_html_e( 'Note:', 'nobloat-user-foundry' ); ?></strong>
		<?php esc_html_e( 'Some cron jobs may not be scheduled because their related features are disabled. If you believe a job should be scheduled, try deactivating and reactivating the plugin.', 'nobloat-user-foundry' ); ?>
	</p>
<?php endif; ?>

<hr style="margin: 40px 0;">

<h2><?php esc_html_e( 'Template Load Test', 'nobloat-user-foundry' ); ?></h2>
<p class="description">
	<?php esc_html_e( 'Verifies that all email, form, and page templates can be loaded successfully. Templates are loaded from custom settings if available, otherwise from default files.', 'nobloat-user-foundry' ); ?>
</p>

<?php
$nbuf_templates_ok     = 0;
$nbuf_templates_failed = 0;
foreach ( $nbuf_template_status as $tpl_item ) {
	if ( $tpl_item['loaded'] ) {
		++$nbuf_templates_ok;
	} else {
		++$nbuf_templates_failed;
	}
}
?>

<div style="margin: 20px 0; padding: 15px; background: <?php echo $nbuf_templates_failed > 0 ? '#f8d7da' : '#d4edda'; ?>; border-left: 4px solid <?php echo $nbuf_templates_failed > 0 ? '#dc3545' : '#28a745'; ?>; border-radius: 4px;">
	<strong>
		<?php
		if ( 0 === $nbuf_templates_failed ) {
			printf(
				/* translators: %d: number of templates */
				esc_html__( 'All %d templates loaded successfully.', 'nobloat-user-foundry' ),
				count( $nbuf_template_status )
			);
		} else {
			printf(
				/* translators: 1: number of successful templates, 2: number of failed templates */
				esc_html__( '%1$d templates OK, %2$d templates failed to load.', 'nobloat-user-foundry' ),
				(int) $nbuf_templates_ok,
				(int) $nbuf_templates_failed
			);
		}
		?>
	</strong>
</div>

<?php foreach ( $nbuf_templates as $nbuf_group => $nbuf_group_templates ) : ?>
	<h3 style="margin-top: 25px;"><?php echo esc_html( $nbuf_group ); ?></h3>
	<table class="widefat striped">
		<thead>
			<tr>
				<th style="width: 40%;"><?php esc_html_e( 'Template', 'nobloat-user-foundry' ); ?></th>
				<th style="width: 20%;"><?php esc_html_e( 'Status', 'nobloat-user-foundry' ); ?></th>
				<th style="width: 20%;"><?php esc_html_e( 'Size', 'nobloat-user-foundry' ); ?></th>
				<th style="width: 20%;"><?php esc_html_e( 'Template Key', 'nobloat-user-foundry' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $nbuf_group_templates as $nbuf_template_name => $nbuf_label ) : ?>
				<?php $nbuf_status = $nbuf_template_status[ $nbuf_template_name ]; ?>
				<tr>
					<td><strong><?php echo esc_html( $nbuf_label ); ?></strong></td>
					<td>
						<?php if ( $nbuf_status['loaded'] ) : ?>
							<span style="color: #28a745; font-weight: 600;"><?php esc_html_e( 'OK', 'nobloat-user-foundry' ); ?></span>
						<?php elseif ( $nbuf_status['empty'] ) : ?>
							<span style="color: #ffc107; font-weight: 600;"><?php esc_html_e( 'Empty', 'nobloat-user-foundry' ); ?></span>
						<?php else : ?>
							<span style="color: #dc3545; font-weight: 600;"><?php esc_html_e( 'Failed', 'nobloat-user-foundry' ); ?></span>
						<?php endif; ?>
					</td>
					<td>
						<?php if ( $nbuf_status['size'] > 0 ) : ?>
							<?php echo esc_html( number_format( $nbuf_status['size'] ) ); ?> <?php esc_html_e( 'bytes', 'nobloat-user-foundry' ); ?>
						<?php else : ?>
							<span style="color: #999;">—</span>
						<?php endif; ?>
					</td>
					<td><code style="font-size: 11px;"><?php echo esc_html( $nbuf_template_name ); ?></code></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php endforeach; ?>

<p class="description" style="margin-top: 20px;">
	<strong><?php esc_html_e( 'Note:', 'nobloat-user-foundry' ); ?></strong>
	<?php esc_html_e( 'Templates are loaded from your customized settings first. If no custom template is saved, the default template file is used. Failed templates may indicate missing default files or database issues.', 'nobloat-user-foundry' ); ?>
</p>
