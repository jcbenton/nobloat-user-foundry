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
$available_plugins = array(
	'ultimate-member' => array( 'name' => 'Ultimate Member' ),
	'buddypress'      => array( 'name' => 'BuddyPress' ),
);
?>

<div class="nbuf-migration-simple">
	<h2><?php esc_html_e( 'Data Migration Tools', 'nobloat-user-foundry' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Import profile data and settings from other WordPress user management plugins. This will NOT create new users - it migrates data for your existing WordPress users.', 'nobloat-user-foundry' ); ?>
	</p>

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
						<?php foreach ( $available_plugins as $slug => $plugin ) : ?>
							<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $plugin['name'] ); ?></option>
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

		<!-- Roles Mapping Review -->
		<div id="nbuf-roles-mapping-section" class="nbuf-migration-card" style="display:none;">
			<h3><?php esc_html_e( 'Custom Roles Mapping', 'nobloat-user-foundry' ); ?></h3>
			<p class="description">
				<?php esc_html_e( 'Review what custom roles will be migrated.', 'nobloat-user-foundry' ); ?>
			</p>

			<div id="nbuf-roles-preview">
				<!-- Will be populated via JavaScript -->
			</div>
		</div>

		<!-- Migration Actions -->
		<div class="nbuf-migration-card">
			<h3><?php esc_html_e( 'Start Migration', 'nobloat-user-foundry' ); ?></h3>

			<div class="nbuf-migration-warning notice notice-info inline">
				<p>
					<strong><?php esc_html_e( 'Before you start:', 'nobloat-user-foundry' ); ?></strong><br>
					• <?php esc_html_e( 'This will update existing user data in your database', 'nobloat-user-foundry' ); ?><br>
					• <?php esc_html_e( 'Existing NBUF data will be overwritten if conflicts occur', 'nobloat-user-foundry' ); ?><br>
					• <?php esc_html_e( 'It\'s recommended to backup your database first', 'nobloat-user-foundry' ); ?>
				</p>
			</div>

			<button type="button" id="nbuf-start-migration-btn" class="button button-primary button-hero" disabled>
				<span class="dashicons dashicons-database-import"></span>
				<?php esc_html_e( 'Start Migration', 'nobloat-user-foundry' ); ?>
			</button>

			<div id="nbuf-migration-progress" style="display:none; margin-top: 20px;">
				<div class="nbuf-progress-bar">
					<div class="nbuf-progress-fill" style="width: 0%;"></div>
				</div>
				<p class="nbuf-progress-text">
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
		$history = NBUF_Migration::get_import_history( 10 );

		if ( empty( $history ) ) {
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
					<?php foreach ( $history as $record ) : ?>
						<tr>
							<td><?php echo esc_html( gmdate( 'Y-m-d H:i', strtotime( $record->imported_at ) ) ); ?></td>
							<td><?php echo esc_html( $record->source_plugin ); ?></td>
							<td><?php echo esc_html( $record->migration_type ?? 'unknown' ); ?></td>
							<td><?php echo esc_html( $record->successful ); ?> / <?php echo esc_html( $record->total_rows ); ?></td>
							<td>
								<?php if ( $record->failed > 0 ) : ?>
									<span style="color: orange;">⚠ <?php echo esc_html( $record->failed ); ?> errors</span>
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

<style>
.nbuf-migration-simple {
	max-width: 1000px;
}

.nbuf-migration-card {
	background: #fff;
	border: 1px solid #ccd0d4;
	box-shadow: 0 1px 1px rgba(0,0,0,.04);
	padding: 20px;
	margin: 20px 0;
}

.nbuf-migration-card h3 {
	margin-top: 0;
	margin-bottom: 15px;
	padding-bottom: 10px;
	border-bottom: 1px solid #f0f0f1;
}

#nbuf-plugin-status {
	background: #f0f6fc;
	border-left: 4px solid #2271b1;
}

.nbuf-status-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
	gap: 15px;
	margin-top: 15px;
}

.nbuf-status-item {
	background: #fff;
	padding: 12px;
	border-radius: 4px;
	border: 1px solid #ddd;
}

.nbuf-status-item strong {
	display: block;
	color: #666;
	font-size: 11px;
	text-transform: uppercase;
	margin-bottom: 5px;
}

.nbuf-status-item .value {
	font-size: 24px;
	font-weight: 600;
	color: #2271b1;
}

.nbuf-checkbox-group {
	margin: 15px 0;
}

.nbuf-checkbox-group label {
	display: flex;
	align-items: center;
	padding: 12px;
	margin: 8px 0;
	background: #f9f9f9;
	border: 2px solid #ddd;
	border-radius: 4px;
	cursor: pointer;
	transition: all 0.2s;
}

.nbuf-checkbox-group label:hover {
	border-color: #2271b1;
	background: #f0f6fc;
}

.nbuf-checkbox-group input[type="checkbox"] {
	margin-right: 10px;
	width: 18px;
	height: 18px;
}

.nbuf-checkbox-group .checkbox-label {
	flex: 1;
}

.nbuf-checkbox-group .checkbox-label strong {
	display: block;
	margin-bottom: 3px;
}

.nbuf-checkbox-group .checkbox-label .description {
	font-size: 13px;
	color: #666;
	margin: 0;
}

#nbuf-field-mapping-table {
	overflow-x: auto;
}

.nbuf-mapping-table {
	width: 100%;
	border-collapse: collapse;
	margin-top: 15px;
}

.nbuf-mapping-table th,
.nbuf-mapping-table td {
	padding: 12px;
	text-align: left;
	border: 1px solid #ddd;
}

.nbuf-mapping-table th {
	background: #f0f0f1;
	font-weight: 600;
	position: sticky;
	top: 0;
}

.nbuf-mapping-table tbody tr:hover {
	background: #f9f9f9;
}

.nbuf-mapping-table code {
	background: #f0f0f1;
	padding: 2px 6px;
	border-radius: 3px;
	font-size: 12px;
}

.nbuf-mapping-table select {
	min-width: 200px;
}

.nbuf-mapping-status {
	display: inline-block;
	padding: 3px 8px;
	border-radius: 3px;
	font-size: 11px;
	font-weight: 600;
}

.nbuf-mapping-status.auto {
	background: #d4edda;
	color: #155724;
}

.nbuf-mapping-status.manual {
	background: #fff3cd;
	color: #856404;
}

.nbuf-mapping-status.unmapped {
	background: #f8d7da;
	color: #721c24;
}

.nbuf-progress-bar {
	width: 100%;
	height: 30px;
	background: #f0f0f1;
	border: 1px solid #ccd0d4;
	border-radius: 4px;
	overflow: hidden;
}

.nbuf-progress-fill {
	height: 100%;
	background: linear-gradient(90deg, #2271b1, #135e96);
	transition: width 0.3s ease;
	display: flex;
	align-items: center;
	justify-content: center;
	color: #fff;
	font-weight: 600;
	font-size: 14px;
}

.nbuf-progress-text {
	margin-top: 10px;
	font-size: 14px;
}

.nbuf-migration-warning {
	padding: 12px 15px !important;
}

.button-hero {
	padding: 10px 24px !important;
	height: auto !important;
	line-height: 1.5 !important;
	font-size: 16px !important;
}

.button-hero .dashicons {
	font-size: 20px;
	width: 20px;
	height: 20px;
	margin-right: 5px;
	vertical-align: middle;
}
</style>

