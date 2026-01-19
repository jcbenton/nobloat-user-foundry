<?php
/**
 * Tools > Migration Tab (Simplified)
 *
 * Data migration tools for importing profile data and settings from other plugins.
 * Simple dropdown-based approach with field mapping review.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Available migration plugins (for dropdown rendering) */
$nbuf_available_plugins = array(
	'ultimate-member' => array( 'name' => 'Ultimate Member' ),
	'buddypress'      => array( 'name' => 'BuddyPress' ),
);
?>

<div class="nbuf-migration-simple">
	<h2><?php esc_html_e( 'Data Migration Tools', 'nobloat-user-foundry' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Import profile data and settings from other WordPress user management plugins. This will NOT create new users - it migrates data for your existing WordPress users.', 'nobloat-user-foundry' ); ?>
	</p>

	<!-- Migration Info Boxes -->
	<div class="nbuf-migration-info-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin: 20px 0;">

		<!-- What Gets Migrated -->
		<div class="nbuf-info-box" style="background: #f0f6fc; border: 1px solid #c3c4c7; border-left: 4px solid #2271b1; padding: 15px; border-radius: 4px;">
			<h4 style="margin: 0 0 10px 0; color: #2271b1;">
				<span class="dashicons dashicons-yes-alt" style="color: #2271b1;"></span>
				<?php esc_html_e( 'What Gets Migrated', 'nobloat-user-foundry' ); ?>
			</h4>
			<ul style="margin: 0; padding-left: 20px; line-height: 1.8;">
				<li><?php esc_html_e( 'Account status (verified, pending, disabled)', 'nobloat-user-foundry' ); ?></li>
				<li><?php esc_html_e( 'Extended profile fields (phone, company, address, bio, social media)', 'nobloat-user-foundry' ); ?></li>
				<li><?php esc_html_e( 'Profile and cover photos', 'nobloat-user-foundry' ); ?></li>
				<li><?php esc_html_e( 'Profile privacy settings', 'nobloat-user-foundry' ); ?></li>
				<li><?php esc_html_e( 'Content restrictions (optional)', 'nobloat-user-foundry' ); ?></li>
			</ul>
		</div>

		<!-- What Does NOT Get Migrated -->
		<div class="nbuf-info-box" style="background: #fcf9e8; border: 1px solid #c3c4c7; border-left: 4px solid #dba617; padding: 15px; border-radius: 4px;">
			<h4 style="margin: 0 0 10px 0; color: #996800;">
				<span class="dashicons dashicons-info" style="color: #996800;"></span>
				<?php esc_html_e( 'What Does NOT Need Migration', 'nobloat-user-foundry' ); ?>
			</h4>
			<p style="margin: 0 0 10px 0; font-size: 13px;">
				<?php esc_html_e( 'WordPress core user fields are already stored in WordPress tables and are automatically available:', 'nobloat-user-foundry' ); ?>
			</p>
			<ul style="margin: 0; padding-left: 20px; line-height: 1.8;">
				<li><code>first_name</code>, <code>last_name</code>, <code>display_name</code></li>
				<li><code>user_email</code>, <code>user_url</code> <?php esc_html_e( '(website)', 'nobloat-user-foundry' ); ?></li>
				<li><code>description</code> <?php esc_html_e( '(biographical info)', 'nobloat-user-foundry' ); ?></li>
				<li><?php esc_html_e( 'User roles and capabilities', 'nobloat-user-foundry' ); ?></li>
			</ul>
		</div>

	</div>

	<!-- How It Works -->
	<div class="notice notice-info inline" style="margin: 0 0 20px 0;">
		<p>
			<strong><?php esc_html_e( 'How it works:', 'nobloat-user-foundry' ); ?></strong>
			<?php esc_html_e( 'Migrations copy plugin-specific data from the source plugin into User Foundry tables. Source plugin data is never modified or deleted. Running a migration twice will overwrite previously migrated User Foundry data.', 'nobloat-user-foundry' ); ?>
		</p>
	</div>

	<!-- Plugin Selection -->
	<div class="nbuf-migration-card">
		<h3><?php esc_html_e( 'Select Source Plugin', 'nobloat-user-foundry' ); ?></h3>

		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="nbuf-source-plugin"><?php esc_html_e( 'Source Plugin', 'nobloat-user-foundry' ); ?></label>
				</th>
				<td>
					<select id="nbuf-source-plugin" class="regular-text">
						<option value=""><?php esc_html_e( '-- Select a plugin to migrate from --', 'nobloat-user-foundry' ); ?></option>
						<?php foreach ( $nbuf_available_plugins as $nbuf_slug => $nbuf_available_plugin ) : ?>
							<option value="<?php echo esc_attr( $nbuf_slug ); ?>"><?php echo esc_html( $nbuf_available_plugin['name'] ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description">
						<?php esc_html_e( 'Choose which plugin to import data from. The plugin does not need to be active.', 'nobloat-user-foundry' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<div id="nbuf-migration-loader" style="display:none; text-align:center; padding: 20px;">
			<span class="spinner is-active" style="float:none; margin:0;"></span>
			<p><?php esc_html_e( 'Loading migration options...', 'nobloat-user-foundry' ); ?></p>
		</div>
	</div>

	<!-- Migration Options (shown after plugin selection) -->
	<div id="nbuf-migration-options" style="display:none;">

		<!-- Plugin Status -->
		<div id="nbuf-plugin-status" class="nbuf-migration-card">
			<!-- Will be populated via JavaScript -->
		</div>

		<!-- What to Migrate -->
		<div id="nbuf-migration-types" class="nbuf-migration-card">
			<h3><?php esc_html_e( 'What to Migrate', 'nobloat-user-foundry' ); ?></h3>
			<div id="nbuf-migration-checkboxes">
				<!-- Will be populated via JavaScript -->
			</div>
		</div>

		<!-- Field Mapping Review -->
		<div id="nbuf-field-mapping-section" class="nbuf-migration-card" style="display:none;">
			<h3><?php esc_html_e( 'Profile Field Mapping', 'nobloat-user-foundry' ); ?></h3>
			<p class="description">
				<?php esc_html_e( 'Review and adjust how fields will be mapped. Auto-mapped fields are pre-selected but can be changed.', 'nobloat-user-foundry' ); ?>
			</p>

			<div id="nbuf-field-mapping-table">
				<!-- Will be populated via JavaScript -->
			</div>

			<p class="description" style="margin-top: 15px;">
				<strong><?php esc_html_e( 'Note:', 'nobloat-user-foundry' ); ?></strong>
				<?php esc_html_e( 'Unmapped fields will be skipped during migration.', 'nobloat-user-foundry' ); ?>
			</p>
		</div>

		<!-- Restrictions Mapping Review -->
		<div id="nbuf-restrictions-mapping-section" class="nbuf-migration-card" style="display:none;">
			<h3><?php esc_html_e( 'Content Restrictions Mapping', 'nobloat-user-foundry' ); ?></h3>
			<p class="description">
				<?php esc_html_e( 'Review what content restrictions will be migrated.', 'nobloat-user-foundry' ); ?>
			</p>

			<div id="nbuf-restrictions-preview">
				<!-- Will be populated via JavaScript -->
			</div>
		</div>

		<!-- Migration Actions -->
		<div class="nbuf-migration-card">
			<h3><?php esc_html_e( 'Start Migration', 'nobloat-user-foundry' ); ?></h3>

			<div class="nbuf-migration-warning" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
				<!-- Safe Actions -->
				<div style="background: #edfaef; border: 1px solid #c3c4c7; border-left: 4px solid #00a32a; padding: 12px; border-radius: 4px;">
					<strong style="color: #00a32a;">
						<span class="dashicons dashicons-shield" style="font-size: 16px; width: 16px; height: 16px;"></span>
						<?php esc_html_e( 'Safe:', 'nobloat-user-foundry' ); ?>
					</strong>
					<ul style="margin: 8px 0 0 0; padding-left: 20px; line-height: 1.6;">
						<li><?php esc_html_e( 'Source plugin data is never modified', 'nobloat-user-foundry' ); ?></li>
						<li><?php esc_html_e( 'WordPress core user data unchanged', 'nobloat-user-foundry' ); ?></li>
						<li><?php esc_html_e( 'Can be run multiple times safely', 'nobloat-user-foundry' ); ?></li>
					</ul>
				</div>
				<!-- Before You Start -->
				<div style="background: #fcf9e8; border: 1px solid #c3c4c7; border-left: 4px solid #dba617; padding: 12px; border-radius: 4px;">
					<strong style="color: #996800;">
						<span class="dashicons dashicons-warning" style="font-size: 16px; width: 16px; height: 16px;"></span>
						<?php esc_html_e( 'Before you start:', 'nobloat-user-foundry' ); ?>
					</strong>
					<ul style="margin: 8px 0 0 0; padding-left: 20px; line-height: 1.6;">
						<li><?php esc_html_e( 'Backup your database first', 'nobloat-user-foundry' ); ?></li>
						<li><?php esc_html_e( 'Existing User Foundry data will be overwritten', 'nobloat-user-foundry' ); ?></li>
						<li><?php esc_html_e( 'Review field mapping before starting', 'nobloat-user-foundry' ); ?></li>
					</ul>
				</div>
			</div>

			<button type="button" id="nbuf-start-migration-btn" class="button button-primary button-hero" disabled>
				<span class="dashicons dashicons-database-import"></span>
				<?php esc_html_e( 'Start Migration', 'nobloat-user-foundry' ); ?>
			</button>

			<div id="nbuf-migration-progress" style="display:none; margin-top: 20px;">
				<div class="nbuf-progress-bar" style="background: #e0e0e0; height: 30px; border-radius: 5px; overflow: hidden; position: relative;">
					<div class="nbuf-progress-fill" style="width: 0%; background: #0073aa; height: 100%; transition: width 0.3s; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;"></div>
				</div>
				<p class="nbuf-progress-text" style="margin-top: 10px;">
					<strong><?php esc_html_e( 'Migrating...', 'nobloat-user-foundry' ); ?></strong>
					<span id="nbuf-progress-status"></span>
				</p>
			</div>

			<div id="nbuf-migration-results" style="display:none; margin-top: 20px;">
				<!-- Results will be shown here -->
			</div>
		</div>

	</div>

	<!-- Migration History -->
	<div class="nbuf-migration-card">
		<h3><?php esc_html_e( 'Migration History', 'nobloat-user-foundry' ); ?></h3>
		<?php
		$nbuf_history = NBUF_Migration::get_import_history( 10 );

		if ( empty( $nbuf_history ) ) {
			?>
			<p><em><?php esc_html_e( 'No migrations have been performed yet.', 'nobloat-user-foundry' ); ?></em></p>
			<?php
		} else {
			?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Date', 'nobloat-user-foundry' ); ?></th>
						<th><?php esc_html_e( 'Source', 'nobloat-user-foundry' ); ?></th>
						<th><?php esc_html_e( 'Type', 'nobloat-user-foundry' ); ?></th>
						<th><?php esc_html_e( 'Records', 'nobloat-user-foundry' ); ?></th>
						<th><?php esc_html_e( 'Status', 'nobloat-user-foundry' ); ?></th>
					</tr>
				</thead>
				<tbody>
			<?php foreach ( $nbuf_history as $nbuf_record ) : ?>
						<tr>
							<td><?php echo esc_html( gmdate( 'Y-m-d H:i', strtotime( $nbuf_record->imported_at ) ) ); ?></td>
							<td><?php echo esc_html( $nbuf_record->source_plugin ); ?></td>
							<td><?php echo esc_html( $nbuf_record->migration_type ?? 'unknown' ); ?></td>
							<td><?php echo esc_html( $nbuf_record->successful ); ?> / <?php echo esc_html( $nbuf_record->total_rows ); ?></td>
							<td>
				<?php if ( $nbuf_record->failed > 0 ) : ?>
									<span style="color: orange;">⚠ <?php echo esc_html( $nbuf_record->failed ); ?> errors</span>
								<?php else : ?>
									<span style="color: green;">✓ Success</span>
								<?php endif; ?>
							</td>
						</tr>
			<?php endforeach; ?>
				</tbody>
			</table>
			<?php
		}
		?>
	</div>

</div>



