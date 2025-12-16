<?php
/**
 * Updated code for /includes/class-nbuf-shortcodes.php
 * Lines 1382-1472: Consolidate Visibility + Directory into Profile Settings subtab
 *
 * INSTRUCTIONS:
 * Replace lines 1387-1410 with the "REPLACEMENT CODE" section below
 * Replace lines 1428-1473 with the "REPLACEMENT SUBTAB CONTENT" section below
 */

// ============================================================================
// REPLACEMENT CODE FOR LINES 1387-1410
// ============================================================================
?>

			/* Consolidate Visibility + Directory into Profile Settings */
			if ( $show_visibility || $show_directory ) {
				$subtabs['profile-settings'] = __( 'Profile Settings', 'nobloat-user-foundry' );
				if ( empty( $first_subtab ) ) {
					$first_subtab = 'profile-settings';
				}
			}
			if ( $show_profile_photo ) {
				$subtabs['profile-photo'] = __( 'Profile Photo', 'nobloat-user-foundry' );
				if ( empty( $first_subtab ) ) {
					$first_subtab = 'profile-photo';
				}
			}
			if ( $show_cover_photo ) {
				$subtabs['cover-photo'] = __( 'Cover Photo', 'nobloat-user-foundry' );
				if ( empty( $first_subtab ) ) {
					$first_subtab = 'cover-photo';
				}
			}

<?php
// ============================================================================
// REPLACEMENT SUBTAB CONTENT FOR LINES 1428-1473
// ============================================================================
?>

			/* Profile Settings sub-tab (consolidated Visibility + Directory) */
			if ( $show_visibility || $show_directory ) {
				ob_start();
				do_action( 'nbuf_account_profile_settings_subtab', $user_id );
				$profile_settings_html = ob_get_clean();
				$is_first         = ( $first_subtab === 'profile-settings' );
				$subtab_contents .= '<div class="nbuf-subtab-content' . ( $is_first ? ' active' : '' ) . '" data-subtab="profile-settings">';
				$subtab_contents .= '<form method="post" action="' . esc_url( self::get_current_page_url() ) . '" class="nbuf-account-form nbuf-profile-tab-form">';
				$subtab_contents .= $profile_tab_nonce;
				$subtab_contents .= '<input type="hidden" name="nbuf_account_action" value="update_profile_tab">';
				$subtab_contents .= '<input type="hidden" name="nbuf_active_tab" value="profile">';
				$subtab_contents .= '<input type="hidden" name="nbuf_active_subtab" value="profile-settings">';
				$subtab_contents .= '<div class="nbuf-profile-subtab-section">' . $profile_settings_html . '</div>';
				$subtab_contents .= '<button type="submit" class="nbuf-button nbuf-button-primary">' . esc_html__( 'Save Settings', 'nobloat-user-foundry' ) . '</button>';
				$subtab_contents .= '</form>';
				$subtab_contents .= '</div>';
			}

<?php
/**
 * IMPORTANT NOTES:
 *
 * 1. Remove the old Visibility subtab code (lines ~1428-1444)
 * 2. Remove the old Directory subtab code (lines ~1446-1473)
 * 3. Add the above Profile Settings subtab code in their place
 * 4. This consolidates both into a single form with one save button
 * 5. The form now submits to 'profile-settings' subtab instead of 'visibility' or 'directory'
 *
 * FORM PROCESSING:
 * The form handler already processes both nbuf_profile_privacy and nbuf_show_in_directory
 * fields, so no changes needed to the form processing logic.
 */
?>