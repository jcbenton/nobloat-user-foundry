<?php
/**
 * Settings Tab: Users → Directory
 *
 * Member directory settings including visibility, search, and display options.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes/user-tabs/users
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Register settings */
add_action( 'nbuf_register_settings', 'nbuf_register_directory_settings' );

/**
 * Register member directory settings
 *
 * @since 1.0.0
 */
function nbuf_register_directory_settings() {
	/*
	* ==========================================================
	* SECTION: Member Directory Settings
	* ==========================================================
	*/

	NBUF_Settings::add_section(
		'users-directory',
		array(
			'title'       => __( 'Member Directory', 'nobloat-user-foundry' ),
			'description' => __( 'Configure the public member directory and privacy controls. Member directories allow users to find and connect with other members on your site.', 'nobloat-user-foundry' ),
		)
	);

	/* Enable member directory */
	NBUF_Settings::add_field(
		'users-directory',
		array(
			'type'        => 'checkbox',
			'id'          => 'nbuf_enable_member_directory',
			'label'       => __( 'Enable Member Directory', 'nobloat-user-foundry' ),
			'description' => __( 'Master toggle for member directory functionality. Disable to hide member directories site-wide.', 'nobloat-user-foundry' ),
			'default'     => false,
		)
	);

	/* Allow users to adjust privacy settings */
	NBUF_Settings::add_field(
		'users-directory',
		array(
			'type'        => 'checkbox',
			'id'          => 'nbuf_allow_user_privacy_control',
			'label'       => __( 'Allow Users to Adjust Privacy Settings', 'nobloat-user-foundry' ),
			'description' => __( 'When enabled, users can change their profile privacy and directory visibility from their account page. When disabled, privacy settings are admin-controlled only.', 'nobloat-user-foundry' ),
			'default'     => false,
		)
	);

	/* Display privacy settings when editing is disabled */
	NBUF_Settings::add_field(
		'users-directory',
		array(
			'type'        => 'checkbox',
			'id'          => 'nbuf_display_privacy_when_disabled',
			'label'       => __( 'Display User Privacy Settings When Editing is Disabled', 'nobloat-user-foundry' ),
			'description' => __( 'When enabled, users will see their current privacy settings (read-only) even when they cannot edit them. When disabled, the privacy section is hidden entirely and full privacy is assumed.', 'nobloat-user-foundry' ),
			'default'     => false,
		)
	);

	/* Default directory view */
	NBUF_Settings::add_field(
		'users-directory',
		array(
			'type'        => 'select',
			'id'          => 'nbuf_directory_default_view',
			'label'       => __( 'Default View', 'nobloat-user-foundry' ),
			'description' => __( 'Choose how members are displayed in the directory by default.', 'nobloat-user-foundry' ),
			'options'     => array(
				'grid' => __( 'Grid View', 'nobloat-user-foundry' ),
				'list' => __( 'List View', 'nobloat-user-foundry' ),
			),
			'default'     => 'grid',
		)
	);

	/* Members per page */
	NBUF_Settings::add_field(
		'users-directory',
		array(
			'type'        => 'number',
			'id'          => 'nbuf_directory_per_page',
			'label'       => __( 'Members Per Page', 'nobloat-user-foundry' ),
			'description' => __( 'Number of members to display per page in the directory.', 'nobloat-user-foundry' ),
			'default'     => 20,
			'min'         => 5,
			'max'         => 100,
		)
	);

	/* Show search box */
	NBUF_Settings::add_field(
		'users-directory',
		array(
			'type'        => 'checkbox',
			'id'          => 'nbuf_directory_show_search',
			'label'       => __( 'Show Search Box', 'nobloat-user-foundry' ),
			'description' => __( 'Allow visitors to search the member directory by name, location, or bio.', 'nobloat-user-foundry' ),
			'default'     => true,
		)
	);

	/* Show filters */
	NBUF_Settings::add_field(
		'users-directory',
		array(
			'type'        => 'checkbox',
			'id'          => 'nbuf_directory_show_filters',
			'label'       => __( 'Show Filter Dropdowns', 'nobloat-user-foundry' ),
			'description' => __( 'Display role and other filter options in the directory.', 'nobloat-user-foundry' ),
			'default'     => true,
		)
	);

	/* Default privacy level for new users */
	NBUF_Settings::add_field(
		'users-directory',
		array(
			'type'        => 'select',
			'id'          => 'nbuf_directory_default_privacy',
			'label'       => __( 'Default Privacy Level', 'nobloat-user-foundry' ),
			'description' => __( 'The default privacy setting for new user registrations. Users can change this in their account settings.', 'nobloat-user-foundry' ),
			'options'     => array(
				'public'       => __( 'Public - Anyone can view', 'nobloat-user-foundry' ),
				'members_only' => __( 'Members Only - Logged in users', 'nobloat-user-foundry' ),
				'private'      => __( 'Private - Hidden from directories', 'nobloat-user-foundry' ),
			),
			'default'     => 'private',
		)
	);

	/* Auto-include in directory */
	NBUF_Settings::add_field(
		'users-directory',
		array(
			'type'        => 'checkbox',
			'id'          => 'nbuf_directory_auto_include',
			'label'       => __( 'Auto-Include New Users', 'nobloat-user-foundry' ),
			'description' => __( 'Automatically opt-in new users to appear in member directories (respecting their privacy level). If disabled, users must manually opt-in from their account settings.', 'nobloat-user-foundry' ),
			'default'     => false,
		)
	);

	/* Searchable fields */
	NBUF_Settings::add_field(
		'users-directory',
		array(
			'type'        => 'checkbox_list',
			'id'          => 'nbuf_directory_searchable_fields',
			'label'       => __( 'Searchable Fields', 'nobloat-user-foundry' ),
			'description' => __( 'Select which profile fields can be searched in the member directory.', 'nobloat-user-foundry' ),
			'options'     => array(
				'display_name' => __( 'Display Name', 'nobloat-user-foundry' ),
				'bio'          => __( 'Biography', 'nobloat-user-foundry' ),
				'city'         => __( 'City', 'nobloat-user-foundry' ),
				'state'        => __( 'State/Province', 'nobloat-user-foundry' ),
				'country'      => __( 'Country', 'nobloat-user-foundry' ),
				'company'      => __( 'Company', 'nobloat-user-foundry' ),
				'job_title'    => __( 'Job Title', 'nobloat-user-foundry' ),
			),
			'default'     => array( 'display_name', 'bio', 'city' ),
		)
	);

	/* Visible profile fields */
	NBUF_Settings::add_field(
		'users-directory',
		array(
			'type'        => 'checkbox_list',
			'id'          => 'nbuf_directory_visible_fields',
			'label'       => __( 'Visible in Member Cards', 'nobloat-user-foundry' ),
			'description' => __( 'Select which profile fields are displayed in member cards (subject to user privacy settings).', 'nobloat-user-foundry' ),
			'options'     => array(
				'bio'       => __( 'Biography', 'nobloat-user-foundry' ),
				'location'  => __( 'Location (City/State/Country)', 'nobloat-user-foundry' ),
				'website'   => __( 'Website', 'nobloat-user-foundry' ),
				'company'   => __( 'Company', 'nobloat-user-foundry' ),
				'job_title' => __( 'Job Title', 'nobloat-user-foundry' ),
				'joined'    => __( 'Join Date', 'nobloat-user-foundry' ),
			),
			'default'     => array( 'bio', 'location', 'website', 'joined' ),
		)
	);

	/* Shortcode usage help */
	NBUF_Settings::add_field(
		'users-directory',
		array(
			'type'  => 'html',
			'id'    => 'nbuf_directory_shortcode_help',
			'label' => __( 'Shortcode Usage', 'nobloat-user-foundry' ),
			'html'  => '<div class="nbuf-info-box">' .
					'<p><strong>' . __( 'To display the member directory, use this shortcode:', 'nobloat-user-foundry' ) . '</strong></p>' .
					'<pre><code>[nbuf_members]</code></pre>' .
					'<p><strong>' . __( 'Available shortcode attributes:', 'nobloat-user-foundry' ) . '</strong></p>' .
					'<ul>' .
					'<li><code>view="grid"</code> - ' . __( 'Display mode: grid or list', 'nobloat-user-foundry' ) . '</li>' .
					'<li><code>per_page="20"</code> - ' . __( 'Number of members per page', 'nobloat-user-foundry' ) . '</li>' .
					'<li><code>show_search="yes"</code> - ' . __( 'Show search box: yes or no', 'nobloat-user-foundry' ) . '</li>' .
					'<li><code>show_filters="yes"</code> - ' . __( 'Show filters: yes or no', 'nobloat-user-foundry' ) . '</li>' .
					'<li><code>roles="subscriber,contributor"</code> - ' . __( 'Limit to specific roles', 'nobloat-user-foundry' ) . '</li>' .
					'<li><code>orderby="display_name"</code> - ' . __( 'Order by: display_name, user_registered, city', 'nobloat-user-foundry' ) . '</li>' .
					'<li><code>order="ASC"</code> - ' . __( 'Sort order: ASC or DESC', 'nobloat-user-foundry' ) . '</li>' .
					'</ul>' .
					'<p><strong>' . __( 'Example:', 'nobloat-user-foundry' ) . '</strong></p>' .
					'<pre><code>[nbuf_members view="list" per_page="30" roles="subscriber"]</code></pre>' .
					'</div>',
		)
	);

	/* Privacy information */
	NBUF_Settings::add_field(
		'users-directory',
		array(
			'type'  => 'html',
			'id'    => 'nbuf_directory_privacy_info',
			'label' => __( 'Privacy & GDPR', 'nobloat-user-foundry' ),
			'html'  => '<div class="nbuf-info-box">' .
					'<p>' . __( 'Member directories respect user privacy settings:', 'nobloat-user-foundry' ) . '</p>' .
					'<ul>' .
					'<li><strong>' . __( 'Opt-in Required:', 'nobloat-user-foundry' ) . '</strong> ' . __( 'Users must explicitly choose to appear in directories.', 'nobloat-user-foundry' ) . '</li>' .
					'<li><strong>' . __( 'Privacy Levels:', 'nobloat-user-foundry' ) . '</strong> ' . __( 'Public profiles are visible to all, Members Only requires login, Private hides from directories.', 'nobloat-user-foundry' ) . '</li>' .
					'<li><strong>' . __( 'Field-Level Privacy:', 'nobloat-user-foundry' ) . '</strong> ' . __( 'Users can control visibility of individual fields (bio, location, etc.).', 'nobloat-user-foundry' ) . '</li>' .
					'<li><strong>' . __( 'GDPR Compliant:', 'nobloat-user-foundry' ) . '</strong> ' . __( 'All privacy settings are exportable and erasable via WordPress privacy tools.', 'nobloat-user-foundry' ) . '</li>' .
					'</ul>' .
					'<p><em>' . __( 'Users can manage their privacy settings from their account page.', 'nobloat-user-foundry' ) . '</em></p>' .
					'</div>',
		)
	);
}
