<?php
/**
 * Version History Viewer Template
 *
 * Displays profile version timeline with diff viewer and revert capabilities.
 * Reusable component included by admin pages, meta boxes, and user account pages.
 *
 * @package NoBloat_User_Foundry
 * @since 1.4.0
 *
 * Variables expected:
 * - $user_id: User ID whose history to display
 * - $context: 'admin', 'metabox', or 'account' (determines available actions)
 * - $can_revert: Boolean indicating if current user can revert
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Ensure user_id is provided */
if ( empty( $user_id ) ) {
	return;
}

/* Get user */
$user = get_userdata( $user_id );
if ( ! $user ) {
	return;
}

/* Default context */
if ( empty( $context ) ) {
	$context = 'admin';
}

/* Check revert permission */
if ( ! isset( $can_revert ) ) {
	$current_user_id   = get_current_user_id();
	$allow_user_revert = NBUF_Options::get( 'nbuf_version_history_allow_user_revert', false );
	$can_revert        = current_user_can( 'manage_options' ) || ( $allow_user_revert && $user_id === $current_user_id );
}

?>

<div class="nbuf-version-history-viewer" data-user-id="<?php echo esc_attr( $user_id ); ?>" data-context="<?php echo esc_attr( $context ); ?>">

	<!-- Header -->
	<div class="nbuf-vh-header">
		<h3>
			<?php
			printf(
				/* translators: %s: User display name */
				esc_html__( 'Profile History: %s', 'nobloat-user-foundry' ),
				esc_html( $user->display_name )
			);
			?>
		</h3>
		<p class="description">
			<?php esc_html_e( 'Complete timeline of all profile changes. Click any version to view details or compare changes.', 'nobloat-user-foundry' ); ?>
		</p>
	</div>

	<!-- Loading State -->
	<div class="nbuf-vh-loading" style="text-align: center; padding: 40px;">
		<span class="spinner is-active"></span>
		<p><?php esc_html_e( 'Loading version history...', 'nobloat-user-foundry' ); ?></p>
	</div>

	<!-- Timeline Container (populated via AJAX) -->
	<div class="nbuf-vh-timeline" style="display: none;">
		<!-- Timeline items will be inserted here via JavaScript -->
	</div>

	<!-- Pagination -->
	<div class="nbuf-vh-pagination" style="display: none; margin-top: 20px; text-align: center;">
		<button class="button nbuf-vh-prev-page" disabled>
			<?php esc_html_e( '« Previous', 'nobloat-user-foundry' ); ?>
		</button>
		<span class="nbuf-vh-page-info" style="margin: 0 15px;">
			<?php esc_html_e( 'Page 1', 'nobloat-user-foundry' ); ?>
		</span>
		<button class="button nbuf-vh-next-page" disabled>
			<?php esc_html_e( 'Next »', 'nobloat-user-foundry' ); ?>
		</button>
	</div>

	<!-- Empty State -->
	<div class="nbuf-vh-empty" style="display: none; text-align: center; padding: 40px; background: #f9f9f9; border: 1px dashed #ddd; border-radius: 4px;">
		<p style="font-size: 16px; color: #666;">
			<?php esc_html_e( '📝 No version history found.', 'nobloat-user-foundry' ); ?>
		</p>
		<p class="description">
			<?php esc_html_e( 'This user has no recorded profile changes yet. Changes will appear here as the user updates their profile.', 'nobloat-user-foundry' ); ?>
		</p>
	</div>

	<!-- Diff Modal (hidden by default) -->
	<div class="nbuf-vh-diff-modal" style="display: none;">
		<div class="nbuf-vh-diff-overlay"></div>
		<div class="nbuf-vh-diff-content">
			<div class="nbuf-vh-diff-header">
				<h3><?php esc_html_e( 'Version Comparison', 'nobloat-user-foundry' ); ?></h3>
				<button class="nbuf-vh-close-diff button">
					<?php esc_html_e( '× Close', 'nobloat-user-foundry' ); ?>
				</button>
			</div>
			<div class="nbuf-vh-diff-body">
				<div class="nbuf-vh-diff-loading" style="text-align: center; padding: 40px;">
					<span class="spinner is-active"></span>
					<p><?php esc_html_e( 'Comparing versions...', 'nobloat-user-foundry' ); ?></p>
				</div>
				<div class="nbuf-vh-diff-result" style="display: none;">
					<!-- Diff content inserted via JavaScript -->
				</div>
			</div>
		</div>
	</div>

</div>

<!-- Timeline Item Template (JavaScript uses this) -->
<script type="text/template" id="nbuf-vh-timeline-item-template">
	<div class="nbuf-vh-item" data-version-id="{{version_id}}">
		<div class="nbuf-vh-item-icon">
			<span class="dashicons dashicons-{{icon}}"></span>
		</div>
		<div class="nbuf-vh-item-content">
			<div class="nbuf-vh-item-header">
				<div class="nbuf-vh-item-meta">
					<span class="nbuf-vh-item-date">{{date}}</span>
					<span class="nbuf-vh-item-time">{{time}}</span>
					<span class="nbuf-vh-item-type nbuf-vh-type-{{change_type}}">{{change_type_label}}</span>
				</div>
				<div class="nbuf-vh-item-user">
					{{changed_by}}
				</div>
			</div>
			<div class="nbuf-vh-item-details">
				<p class="nbuf-vh-item-fields">
					<strong><?php esc_html_e( 'Fields changed:', 'nobloat-user-foundry' ); ?></strong>
					{{fields_changed}}
				</p>
				{{#ip_address}}
				<p class="nbuf-vh-item-ip">
					<strong><?php esc_html_e( 'IP Address:', 'nobloat-user-foundry' ); ?></strong>
					{{ip_address}}
				</p>
				{{/ip_address}}
			</div>
			<div class="nbuf-vh-item-actions">
				<button class="button button-small nbuf-vh-view-details" data-version-id="{{version_id}}">
					<?php esc_html_e( 'View Snapshot', 'nobloat-user-foundry' ); ?>
				</button>
				{{#has_previous}}
				<button class="button button-small nbuf-vh-compare" data-version-id="{{version_id}}" data-previous-id="{{previous_id}}">
					<?php esc_html_e( 'Compare Changes', 'nobloat-user-foundry' ); ?>
				</button>
				{{/has_previous}}
				{{#can_revert}}
				<button class="button button-primary button-small nbuf-vh-revert" data-version-id="{{version_id}}">
					<?php esc_html_e( '⟲ Revert to This Version', 'nobloat-user-foundry' ); ?>
				</button>
				{{/can_revert}}
			</div>
		</div>
	</div>
</script>

<!-- JavaScript -->
