<?php
/**
 * Tools Tab - Configuration Export/Import
 *
 * @package NoBloat_User_Foundry
 * @since   1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Get current settings */
$nbuf_allow_import     = NBUF_Options::get( 'nbuf_config_allow_import', true );
$nbuf_export_sensitive = NBUF_Options::get( 'nbuf_config_export_sensitive', false );
?>

<div class="nbuf-settings-section">
	<h2>Configuration Export/Import</h2>
	<p class="description">Export and import plugin settings for backups, staging, or site cloning.</p>

	<!-- Export Section -->
	<div class="nbuf-card" style="margin-top: 20px;">
		<h3>Export Configuration</h3>
		<p>Export your plugin settings and templates to a JSON file.</p>

		<form id="nbuf-export-form">
			<?php wp_nonce_field( 'nbuf_config_nonce', 'nbuf_config_nonce' ); ?>

			<table class="form-table">
				<tr>
					<th scope="row">Export Options</th>
					<td>
						<label>
							<input type="checkbox" name="export_settings" id="export_settings" value="1" checked />
							<strong>Settings</strong> - All plugin settings (100+ options)
						</label>
						<br/>
						<label>
							<input type="checkbox" name="export_templates" id="export_templates" value="1" checked />
							<strong>Templates</strong> - Email and CSS templates
						</label>
					</td>
				</tr>

				<tr>
					<th scope="row">Advanced Options</th>
					<td>
						<label>
							<input type="checkbox" name="export_sensitive" id="export_sensitive" value="1" <?php checked( $nbuf_export_sensitive ); ?> />
							Include sensitive data (API keys, tokens)
						</label>
						<p class="description">⚠️ Only enable if you trust the export destination.</p>

						<label>
							<input type="checkbox" name="pretty_print" id="pretty_print" value="1" checked />
							Pretty print JSON (human-readable formatting)
						</label>
						<p class="description">Recommended for readability and version control.</p>
					</td>
				</tr>
			</table>

			<p>
				<button type="button" class="button button-primary" id="nbuf-export-config">
					<span class="dashicons dashicons-download" style="vertical-align: middle;"></span>
					Export Configuration
				</button>
			</p>
		</form>

		<div id="nbuf-export-results" style="display: none; margin-top: 20px;"></div>
	</div>

	<!-- Import Section -->
	<div class="nbuf-card" style="margin-top: 30px;">
		<h3>Import Configuration</h3>

		<?php if ( ! $nbuf_allow_import ) : ?>
			<div class="notice notice-warning">
				<p><strong>Configuration import is currently disabled.</strong> Enable it in settings to import configurations.</p>
			</div>
		<?php else : ?>
			<p>Import plugin settings from a JSON configuration file.</p>

			<form id="nbuf-import-form" enctype="multipart/form-data">
			<?php wp_nonce_field( 'nbuf_config_nonce', 'nbuf_config_import_nonce' ); ?>

				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="config_file">Configuration File</label>
						</th>
						<td>
							<input type="file" name="config_file" id="config_file" accept=".json" required />
							<p class="description">Select a JSON configuration file exported from this plugin.</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="import_mode">Import Mode</label>
						</th>
						<td>
							<select name="import_mode" id="import_mode">
								<option value="overwrite">Overwrite All - Replace existing settings</option>
								<option value="merge">Merge - Keep existing, add new only</option>
							</select>
							<p class="description">
								<strong>Overwrite:</strong> Replaces all matching settings with imported values.<br/>
								<strong>Merge:</strong> Only imports settings that don't already exist.
							</p>
						</td>
					</tr>
				</table>

				<p>
					<button type="submit" class="button button-primary" id="nbuf-validate-config">
						<span class="dashicons dashicons-upload" style="vertical-align: middle;"></span>
						Validate &amp; Preview Import
					</button>
				</p>
			</form>

			<div id="nbuf-import-preview" style="display: none; margin-top: 20px;"></div>
			<div id="nbuf-import-results" style="display: none; margin-top: 20px;"></div>
		<?php endif; ?>
	</div>

	<!-- Settings -->
	<div class="nbuf-card" style="margin-top: 30px;">
		<h3><?php esc_html_e( 'Import/Export Settings', 'nobloat-user-foundry' ); ?></h3>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php NBUF_Settings::settings_nonce_field(); ?>
			<input type="hidden" name="nbuf_active_tab" value="tools">
			<input type="hidden" name="nbuf_active_subtab" value="config-portability">
			<!-- Declare checkboxes for proper unchecked handling -->
			<input type="hidden" name="nbuf_form_checkboxes[]" value="nbuf_config_allow_import">
			<input type="hidden" name="nbuf_form_checkboxes[]" value="nbuf_config_export_sensitive">

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="nbuf_config_allow_import"><?php esc_html_e( 'Allow Configuration Import', 'nobloat-user-foundry' ); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox"
									name="nbuf_config_allow_import"
									id="nbuf_config_allow_import"
									value="1"
									<?php checked( $nbuf_allow_import ); ?> />
							<?php esc_html_e( 'Enable configuration import functionality', 'nobloat-user-foundry' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'For security, you can disable imports when not needed.', 'nobloat-user-foundry' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="nbuf_config_export_sensitive"><?php esc_html_e( 'Export Sensitive Data by Default', 'nobloat-user-foundry' ); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox"
									name="nbuf_config_export_sensitive"
									id="nbuf_config_export_sensitive"
									value="1"
									<?php checked( $nbuf_export_sensitive ); ?> />
							<?php esc_html_e( 'Include sensitive data in exports by default', 'nobloat-user-foundry' ); ?>
						</label>
						<p class="description"><?php esc_html_e( '⚠️ Not recommended. Disable to exclude API keys and tokens.', 'nobloat-user-foundry' ); ?></p>
					</td>
				</tr>
			</table>

			<?php submit_button( __( 'Save Settings', 'nobloat-user-foundry' ) ); ?>
		</form>
	</div>

	<!-- Use Cases Documentation -->
	<div class="nbuf-card" style="margin-top: 30px;">
		<h3>Use Cases &amp; Best Practices</h3>

		<details>
			<summary style="cursor: pointer; font-weight: 600; padding: 10px; background: #f6f7f7; border-radius: 4px;">
				Click to view configuration portability use cases and tips
			</summary>

			<div style="padding: 15px; background: #f9f9f9; margin-top: 10px; border-radius: 4px;">
				<h4>Common Use Cases</h4>

				<strong>1. Staging to Production Deployment</strong>
				<ul>
					<li>Configure plugin on staging site</li>
					<li>Export configuration to JSON</li>
					<li>Import on production site</li>
					<li>Instant deployment without manual reconfiguration</li>
				</ul>

				<strong>2. Site Cloning</strong>
				<ul>
					<li>Export configuration from original site</li>
					<li>Install plugin on new site</li>
					<li>Import configuration</li>
					<li>Identical setup in minutes</li>
				</ul>

				<strong>3. Backup Before Changes</strong>
				<ul>
					<li>Export current configuration</li>
					<li>Make experimental changes</li>
					<li>If something breaks, re-import backup</li>
					<li>Instant rollback capability</li>
				</ul>

				<strong>4. Version Control</strong>
				<ul>
					<li>Export with pretty print enabled</li>
					<li>Commit JSON to Git repository</li>
					<li>Track configuration changes over time</li>
					<li>Team collaboration on settings</li>
				</ul>

				<strong>5. Configuration Templates</strong>
				<ul>
					<li>Create "golden" configurations for common setups</li>
					<li>Share with team or clients</li>
					<li>Rapid deployment of standardized settings</li>
				</ul>

				<h4>Best Practices</h4>
				<ul>
					<li>✅ <strong>Always export before major changes</strong> - Safety first!</li>
					<li>✅ <strong>Use merge mode for safety</strong> - Preserves existing customizations</li>
					<li>✅ <strong>Test imports on staging first</strong> - Never import directly to production</li>
					<li>✅ <strong>Review preview before importing</strong> - Verify what will change</li>
					<li>✅ <strong>Keep backups organized</strong> - Name exports with dates/purposes</li>
					<li>⚠️ <strong>Exclude sensitive data for sharing</strong> - Never share API keys</li>
					<li>⚠️ <strong>Check version compatibility</strong> - Major versions must match</li>
					<li>⚠️ <strong>Disable import when not needed</strong> - Reduces security surface</li>
				</ul>

				<h4>What Gets Exported/Imported</h4>

				<strong>Settings Categories:</strong>
				<ul>
					<li><strong>System:</strong> Master toggle, page IDs, feature toggles</li>
					<li><strong>Users:</strong> Registration fields, profile settings, verification</li>
					<li><strong>Security:</strong> 2FA, login limits, password requirements</li>
					<li><strong>Templates:</strong> Email templates, CSS customizations</li>
					<li><strong>Restrictions:</strong> Content, menu, widget, taxonomy restrictions</li>
					<li><strong>Profiles:</strong> Avatar settings, public profile options</li>
					<li><strong>Integration:</strong> WooCommerce, third-party integrations</li>
					<li><strong>Tools:</strong> Import/export settings, migration options</li>
				</ul>

				<strong>Not Included:</strong>
				<ul>
					<li>User data (accounts, profiles, photos)</li>
					<li>Database tables (structure is created on activation)</li>
					<li>Uploaded files (profile photos, cover photos)</li>
					<li>Audit logs and historical data</li>
					<li>Active sessions and tokens</li>
				</ul>
			</div>
		</details>
	</div>
</div>



