<?php
/**
 * Tools Tab - Bulk User Import
 *
 * @package NoBloat_User_Foundry
 * @since   1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Get current settings */
$import_require_email   = NBUF_Options::get( 'nbuf_import_require_email', true );
$import_send_welcome    = NBUF_Options::get( 'nbuf_import_send_welcome', false );
$import_verify_emails   = NBUF_Options::get( 'nbuf_import_verify_emails', true );
$import_default_role    = NBUF_Options::get( 'nbuf_import_default_role', 'subscriber' );
$import_batch_size      = NBUF_Options::get( 'nbuf_import_batch_size', 50 );
$import_update_existing = NBUF_Options::get( 'nbuf_import_update_existing', false );
?>

<div class="nbuf-settings-section">
	<h2>Bulk User Import (CSV)</h2>
	<p class="description">Import users from a CSV file with automatic field mapping and validation.</p>

	<!-- Import Settings -->
	<div class="nbuf-card" style="margin-top: 20px;">
		<h3>Import Settings</h3>

		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="nbuf_import_require_email">Require Email</label>
				</th>
				<td>
					<label>
						<input type="checkbox"
								name="nbuf_import_require_email"
								id="nbuf_import_require_email"
								value="1"
								<?php checked( $import_require_email ); ?> />
						All users must have valid email addresses
					</label>
					<p class="description">Rows without valid emails will be skipped.</p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="nbuf_import_verify_emails">Require Email Verification</label>
				</th>
				<td>
					<label>
						<input type="checkbox"
								name="nbuf_import_verify_emails"
								id="nbuf_import_verify_emails"
								value="1"
								<?php checked( $import_verify_emails ); ?> />
						Imported users must verify their email
					</label>
					<p class="description">Recommended: ON. Ensures all imported users verify their accounts.</p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="nbuf_import_send_welcome">Send Welcome Email</label>
				</th>
				<td>
					<label>
						<input type="checkbox"
								name="nbuf_import_send_welcome"
								id="nbuf_import_send_welcome"
								value="1"
								<?php checked( $import_send_welcome ); ?> />
						Send welcome email with login credentials
					</label>
					<p class="description">⚠️ Emails will include plain-text passwords. Use with caution.</p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="nbuf_import_update_existing">Update Existing Users</label>
				</th>
				<td>
					<label>
						<input type="checkbox"
								name="nbuf_import_update_existing"
								id="nbuf_import_update_existing"
								value="1"
								<?php checked( $import_update_existing ); ?> />
						Update existing users if email/username matches
					</label>
					<p class="description">If disabled, duplicate users will be skipped.</p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="nbuf_import_default_role">Default Role</label>
				</th>
				<td>
					<select name="nbuf_import_default_role" id="nbuf_import_default_role">
						<?php
						$roles = wp_roles()->roles;
						foreach ( $roles as $role_slug => $role_data ) {
							printf(
								'<option value="%s" %s>%s</option>',
								esc_attr( $role_slug ),
								selected( $import_default_role, $role_slug, false ),
								esc_html( $role_data['name'] )
							);
						}
						?>
					</select>
					<p class="description">Role assigned to users if not specified in CSV.</p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="nbuf_import_batch_size">Batch Size</label>
				</th>
				<td>
					<input type="number"
							name="nbuf_import_batch_size"
							id="nbuf_import_batch_size"
							value="<?php echo esc_attr( $import_batch_size ); ?>"
							min="10"
							max="500"
							step="10" />
					<p class="description">Users processed per batch (default: 50). Lower = safer, higher = faster.</p>
				</td>
			</tr>
		</table>
	</div>

	<!-- CSV Upload Form -->
	<div class="nbuf-card" style="margin-top: 20px;">
		<h3>Upload CSV File</h3>

		<form id="nbuf-import-form" enctype="multipart/form-data">
			<?php wp_nonce_field( 'nbuf_import_nonce', 'nbuf_import_nonce' ); ?>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="csv_file">CSV File</label>
					</th>
					<td>
						<input type="file"
								name="csv_file"
								id="csv_file"
								accept=".csv"
								required />
						<p class="description">Maximum file size: 10MB. Format: UTF-8 CSV with headers.</p>
					</td>
				</tr>
			</table>

			<p>
				<button type="submit" class="button button-primary" id="nbuf-upload-csv">
					Upload &amp; Validate CSV
				</button>
			</p>
		</form>

		<div id="nbuf-import-progress" style="display: none; margin-top: 20px;">
			<h4>Import Progress</h4>
			<div style="background: #f0f0f1; border: 1px solid #c3c4c7; border-radius: 4px; padding: 4px;">
				<div id="nbuf-progress-bar" style="background: #2271b1; height: 24px; border-radius: 2px; width: 0%; transition: width 0.3s;"></div>
			</div>
			<p id="nbuf-progress-text" style="margin-top: 10px;">Initializing...</p>
		</div>

		<div id="nbuf-import-results" style="display: none; margin-top: 20px;">
			<h4>Import Results</h4>
			<div id="nbuf-results-content"></div>
		</div>
	</div>

	<!-- CSV Format Documentation -->
	<div class="nbuf-card" style="margin-top: 20px;">
		<h3>CSV Format &amp; Field Reference</h3>

		<details>
			<summary style="cursor: pointer; font-weight: 600; padding: 10px; background: #f6f7f7; border-radius: 4px;">
				Click to view supported CSV fields and format examples
			</summary>

			<div style="padding: 15px; background: #f9f9f9; margin-top: 10px; border-radius: 4px;">
				<h4>Required Fields</h4>
				<ul>
					<li><code>user_email</code> - User email address (required, must be valid)</li>
					<li><code>user_login</code> - Username (required, must be unique)</li>
				</ul>

				<h4>Core WordPress Fields</h4>
				<ul>
					<li><code>user_pass</code> - Password (auto-generated if not provided)</li>
					<li><code>display_name</code> - Display name</li>
					<li><code>first_name</code> - First name</li>
					<li><code>last_name</code> - Last name</li>
					<li><code>role</code> - User role (subscriber, editor, administrator, etc.)</li>
					<li><code>user_url</code> - Website URL</li>
					<li><code>description</code> - User bio/description</li>
				</ul>

				<h4>Profile Fields (53 fields available)</h4>

				<strong>Personal Information:</strong>
				<ul style="columns: 2;">
					<li><code>bio</code></li>
					<li><code>date_of_birth</code></li>
					<li><code>gender</code></li>
					<li><code>pronouns</code></li>
					<li><code>nationality</code></li>
				</ul>

				<strong>Contact Information:</strong>
				<ul style="columns: 2;">
					<li><code>phone</code></li>
					<li><code>mobile_phone</code></li>
					<li><code>fax</code></li>
					<li><code>address_line_1</code></li>
					<li><code>address_line_2</code></li>
					<li><code>city</code></li>
					<li><code>state</code></li>
					<li><code>postal_code</code></li>
					<li><code>country</code></li>
				</ul>

				<strong>Social Media:</strong>
				<ul style="columns: 2;">
					<li><code>facebook</code></li>
					<li><code>twitter</code></li>
					<li><code>linkedin</code></li>
					<li><code>instagram</code></li>
					<li><code>youtube</code></li>
					<li><code>tiktok</code></li>
					<li><code>github</code></li>
					<li><code>website</code></li>
				</ul>

				<strong>Professional:</strong>
				<ul style="columns: 2;">
					<li><code>company</code></li>
					<li><code>job_title</code></li>
					<li><code>department</code></li>
					<li><code>employee_id</code></li>
					<li><code>education_level</code></li>
					<li><code>school</code></li>
					<li><code>degree</code></li>
					<li><code>graduation_year</code></li>
				</ul>

				<strong>Preferences:</strong>
				<ul style="columns: 2;">
					<li><code>language_preference</code></li>
					<li><code>timezone</code></li>
					<li><code>communication_preference</code></li>
					<li><code>newsletter_opt_in</code></li>
					<li><code>marketing_opt_in</code></li>
				</ul>

				<strong>Emergency Contact:</strong>
				<ul>
					<li><code>emergency_contact_name</code></li>
					<li><code>emergency_contact_phone</code></li>
					<li><code>emergency_contact_relationship</code></li>
				</ul>

				<strong>Legal:</strong>
				<ul>
					<li><code>tax_id</code></li>
					<li><code>government_id</code></li>
				</ul>

				<strong>Custom Fields (1-10):</strong>
				<ul style="columns: 2;">
					<li><code>custom_field_1</code></li>
					<li><code>custom_field_2</code></li>
					<li><code>custom_field_3</code></li>
					<li><code>custom_field_4</code></li>
					<li><code>custom_field_5</code></li>
					<li><code>custom_field_6</code></li>
					<li><code>custom_field_7</code></li>
					<li><code>custom_field_8</code></li>
					<li><code>custom_field_9</code></li>
					<li><code>custom_field_10</code></li>
				</ul>

				<h4>Example CSV Format</h4>
				<pre style="background: #fff; padding: 10px; border: 1px solid #ddd; overflow-x: auto;">user_email,user_login,first_name,last_name,role,company,city
john@example.com,johndoe,John,Doe,subscriber,Acme Corp,Boston
jane@example.com,janedoe,Jane,Doe,editor,Tech Inc,Seattle</pre>

				<h4>Tips</h4>
				<ul>
					<li>✅ First row must contain field names (headers)</li>
					<li>✅ Field names are case-insensitive</li>
					<li>✅ Include only the fields you need</li>
					<li>✅ Empty fields will be skipped</li>
					<li>✅ Use UTF-8 encoding for special characters</li>
					<li>⚠️ Duplicate emails/usernames will be skipped (unless "Update Existing" is enabled)</li>
					<li>⚠️ Invalid data will be reported with line numbers</li>
				</ul>
			</div>
		</details>
	</div>
</div>



