<?php
/**
 * NoBloat User Foundry - User Profile Tabs
 *
 * Reorganizes the WordPress user edit page into a tabbed interface.
 *
 * @package    NoBloat_User_Foundry
 * @subpackage NoBloat_User_Foundry/includes
 * @since      1.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * User Profile Tabs Class
 *
 * Dynamically reorganizes user profile sections into tabs.
 */
class NBUF_User_Profile_Tabs {

	/**
	 * Initialize user profile tabs functionality
	 *
	 * @return void
	 */
	public static function init(): void {
		if ( ! is_admin() ) {
			return;
		}

		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue assets for user profile pages
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public static function enqueue_assets( $hook ): void {
		/* Only on user edit pages */
		if ( 'user-edit.php' !== $hook && 'profile.php' !== $hook ) {
			return;
		}

		/* Only for admins */
		if ( ! current_user_can( 'edit_users' ) ) {
			return;
		}

		/* Enqueue the admin CSS that has tab styles */
		wp_enqueue_style(
			'nbuf-admin-css',
			NBUF_PLUGIN_URL . 'assets/css/admin/admin.css',
			array(),
			NBUF_VERSION
		);

		/* Add custom styles for profile tabs - matching Settings page */
		wp_add_inline_style(
			'nbuf-admin-css',
			'
			/* Hide original content until tabs are built */
			#your-profile.nbuf-tabs-loading > * {
				visibility: hidden;
			}
			#your-profile.nbuf-tabs-loading::before {
				content: "Loading...";
				visibility: visible;
				display: block;
				padding: 20px;
				color: #646970;
			}

			/* Profile tabs container */
			.nbuf-profile-tabs-wrap {
				margin-top: 10px;
			}

			/* Tab navigation - matching Settings page outer tabs */
			.nbuf-profile-tabs {
				display: flex;
				flex-wrap: wrap;
				border-bottom: 1px solid #dadadf;
				margin-bottom: 0;
			}

			.nbuf-profile-tab-link {
				padding: 20px 0 17px;
				text-decoration: none;
				font-size: 15px;
				color: #3a3a56;
				margin-right: 30px;
				border-bottom: 3px solid transparent;
				margin-bottom: -1px;
				transition: all 0.2s ease;
				cursor: pointer;
			}

			.nbuf-profile-tab-link:hover {
				color: #09092c;
			}

			.nbuf-profile-tab-link.active {
				color: #09092c;
				font-weight: 500;
				border-color: #1a325e;
			}

			/* Tab content panels */
			.nbuf-profile-tab-content {
				display: none;
				padding: 20px 0;
			}

			.nbuf-profile-tab-content.active {
				display: block;
				animation: nbufFadeIn 0.2s ease-in-out;
			}

			@keyframes nbufFadeIn {
				from { opacity: 0; }
				to { opacity: 1; }
			}

			/* Style form tables within tabs */
			.nbuf-profile-tab-content .form-table {
				margin-top: 0;
			}

			.nbuf-profile-tab-content .form-table th {
				padding-left: 0;
			}

			/* Section headings within tabs */
			.nbuf-profile-tab-content h2 {
				display: none; /* Hide since tab name shows the section */
			}

			.nbuf-profile-tab-content h2:not(:first-child) {
				display: block;
				margin-top: 30px;
				padding-top: 20px;
				border-top: 1px solid #dcdcde;
				font-size: 1.1em;
			}

			/* Submit button area */
			.nbuf-profile-submit {
				margin-top: 20px;
				padding-top: 20px;
				border-top: 1px solid #dadadf;
			}
			'
		);

		/* Add JavaScript to reorganize page into tabs */
		wp_add_inline_script(
			'jquery',
			self::get_tabs_javascript()
		);
	}

	/**
	 * Get the JavaScript for building tabs
	 *
	 * @return string JavaScript code.
	 */
	private static function get_tabs_javascript(): string {
		return "
		jQuery(document).ready(function($) {
			var \$form = $('#your-profile');
			if (\$form.length === 0) return;

			/* Add loading class */
			\$form.addClass('nbuf-tabs-loading');

			/* Find all h2 elements (section headers) */
			var \$headings = \$form.find('> h2');
			if (\$headings.length === 0) {
				\$form.removeClass('nbuf-tabs-loading');
				return;
			}

			/* Build sections array - each section is h2 + content until next h2 */
			var sections = [];
			/* Tab title renames */
			var titleRenames = {
				'Personal Options': 'Options',
				'Name': 'Details',
				'Account Management': 'Account',
				'Extended Profile': 'Profile',
				'User Photos': 'Photos',
				'Two-Factor Authentication': '2FA',
				'Profile History': 'History'
			};

			/* Sections to merge into another tab (key = section to merge, value = target section) */
			var sectionsToMerge = {
				'Contact Info': 'Details',
				'About the user': 'Details',
				'NoBloat User Options': 'Account'
			};

			/* Desired tab order (tabs not listed will appear at end in original order) */
			var tabOrder = ['Options', 'Details', 'Account', 'Profile', 'Roles', '2FA', 'History', 'Photos', 'Notes'];

			\$headings.each(function(index) {
				var \$h2 = $(this);
				var originalTitle = \$h2.text().trim();
				var title = originalTitle;

				/* Apply any title renames */
				if (titleRenames[title]) {
					title = titleRenames[title];
				}

				var id = 'nbuf-profile-tab-' + index;

				/* Get all siblings until next h2 */
				var \$content = \$h2.nextUntil('h2');

				sections.push({
					id: id,
					title: title,
					originalTitle: originalTitle,
					\$heading: \$h2,
					\$content: \$content,
					mergeInto: sectionsToMerge[originalTitle] || null
				});
			});

			/* Process merges - move content from merged sections into target sections */
			var sectionsToRemove = [];
			sections.forEach(function(section) {
				if (section.mergeInto) {
					/* Find target section */
					var targetSection = sections.find(function(s) {
						return s.title === section.mergeInto;
					});

					if (targetSection) {
						/* Store content to append later (heading + content for subsection display) */
						if (!targetSection.mergedContent) {
							targetSection.mergedContent = [];
						}
						targetSection.mergedContent.push({
							\$heading: section.\$heading,
							\$content: section.\$content
						});
						sectionsToRemove.push(section);
					}
				}
			});

			/* Remove merged sections from main sections array */
			sectionsToRemove.forEach(function(sectionToRemove) {
				var idx = sections.indexOf(sectionToRemove);
				if (idx > -1) {
					sections.splice(idx, 1);
				}
			});

			/* Sort sections according to tabOrder */
			sections.sort(function(a, b) {
				var aIndex = tabOrder.indexOf(a.title);
				var bIndex = tabOrder.indexOf(b.title);

				/* If not in tabOrder, place at end */
				if (aIndex === -1) aIndex = 999;
				if (bIndex === -1) bIndex = 999;

				return aIndex - bIndex;
			});

			/* Also grab the submit button */
			var \$submit = \$form.find('p.submit');

			/* Create tabs container */
			var \$tabsWrap = $('<div class=\"nbuf-profile-tabs-wrap\"></div>');
			var \$tabNav = $('<div class=\"nbuf-profile-tabs\"></div>');
			var \$tabPanels = $('<div class=\"nbuf-profile-tab-panels\"></div>');

			/* Build tabs */
			sections.forEach(function(section, index) {
				/* Create tab link */
				var \$tabLink = $('<a></a>')
					.addClass('nbuf-profile-tab-link')
					.attr('href', '#' + section.id)
					.attr('data-tab', section.id)
					.text(section.title);

				if (index === 0) {
					\$tabLink.addClass('active');
				}

				\$tabNav.append(\$tabLink);

				/* Create tab panel */
				var \$tabPanel = $('<div></div>')
					.addClass('nbuf-profile-tab-content')
					.attr('id', section.id)
					.attr('data-tab', section.id);

				if (index === 0) {
					\$tabPanel.addClass('active');
				}

				/* Move content into panel */
				section.\$heading.appendTo(\$tabPanel);
				section.\$content.appendTo(\$tabPanel);

				/* Append any merged content (shows as subsections with visible headings) */
				if (section.mergedContent) {
					section.mergedContent.forEach(function(merged) {
						merged.\$heading.appendTo(\$tabPanel);
						merged.\$content.appendTo(\$tabPanel);
					});
				}

				\$tabPanels.append(\$tabPanel);
			});

			/* Create submit area */
			var \$submitWrap = $('<div class=\"nbuf-profile-submit\"></div>');
			\$submit.appendTo(\$submitWrap);

			/* Assemble and insert */
			\$tabsWrap.append(\$tabNav);
			\$tabsWrap.append(\$tabPanels);
			\$tabsWrap.append(\$submitWrap);

			/* Insert at the beginning of form, before any remaining content */
			\$form.prepend(\$tabsWrap);

			/* Remove loading class */
			\$form.removeClass('nbuf-tabs-loading');

			/* Add Application Passwords heading after Sessions in Account tab */
			/* Try multiple selectors for Sessions button */
			var \$sessionsBtn = \$tabPanels.find('#destroy-sessions, .destroy-sessions, button[name=\"destroy-sessions\"]');
			if (\$sessionsBtn.length) {
				var \$sessionsTable = \$sessionsBtn.closest('table.form-table');
				if (\$sessionsTable.length) {
					\$sessionsTable.after('<h2>Application Passwords</h2>');
				}
			} else {
				/* Fallback: Find by button text containing Log Out */
				\$tabPanels.find('button, input[type=\"button\"], input[type=\"submit\"]').each(function() {
					var btnText = $(this).text() || $(this).val() || '';
					if (btnText.indexOf('Log Out') !== -1 && btnText.indexOf('Everywhere') !== -1) {
						var \$table = $(this).closest('table.form-table');
						if (\$table.length) {
							\$table.after('<h2>Application Passwords</h2>');
						}
						return false;
					}
				});
			}

			/* Add Existing Application Passwords heading above the app passwords table */
			var subheadingStyle = 'margin-top: 30px; padding-top: 20px; border-top: 1px solid #dcdcde; font-size: 1.1em;';
			var \$appPasswordsTable = \$tabPanels.find('#application-passwords-section .wp-list-table, .application-passwords-list-table, table.application-passwords');
			if (\$appPasswordsTable.length) {
				\$appPasswordsTable.first().before('<h2 style=\"' + subheadingStyle + '\">Existing Application Passwords</h2>');
			} else {
				/* Fallback: find table after the new app password form */
				var \$newAppForm = \$tabPanels.find('#new_application_password_form, .create-application-password');
				if (\$newAppForm.length) {
					var \$nextTable = \$newAppForm.nextAll('table.wp-list-table').first();
					if (\$nextTable.length) {
						\$nextTable.before('<h2 style=\"' + subheadingStyle + '\">Existing Application Passwords</h2>');
					}
				}
			}

			/* Tab click handler */
			\$tabNav.on('click', '.nbuf-profile-tab-link', function(e) {
				e.preventDefault();
				var tabId = $(this).data('tab');

				/* Update active states */
				\$tabNav.find('.nbuf-profile-tab-link').removeClass('active');
				$(this).addClass('active');

				\$tabPanels.find('.nbuf-profile-tab-content').removeClass('active');
				\$tabPanels.find('.nbuf-profile-tab-content[data-tab=\"' + tabId + '\"]').addClass('active');

				/* Update URL hash for bookmarking */
				if (history.pushState) {
					history.pushState(null, null, '#' + tabId);
				}
			});

			/* Handle initial hash in URL */
			if (window.location.hash) {
				var hash = window.location.hash.substring(1);
				var \$targetTab = \$tabNav.find('.nbuf-profile-tab-link[data-tab=\"' + hash + '\"]');
				if (\$targetTab.length) {
					\$targetTab.trigger('click');
				}
			}

			/* Preserve tab on form validation errors */
			var errorTab = sessionStorage.getItem('nbuf_profile_active_tab');
			if (errorTab) {
				var \$savedTab = \$tabNav.find('.nbuf-profile-tab-link[data-tab=\"' + errorTab + '\"]');
				if (\$savedTab.length) {
					\$savedTab.trigger('click');
				}
				sessionStorage.removeItem('nbuf_profile_active_tab');
			}

			/* Save active tab before form submit */
			\$form.on('submit', function() {
				var activeTab = \$tabNav.find('.nbuf-profile-tab-link.active').data('tab');
				if (activeTab) {
					sessionStorage.setItem('nbuf_profile_active_tab', activeTab);
				}
			});
		});
		";
	}
}
