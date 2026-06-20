<?php
/**
 * Template Sync Notice
 *
 * Front-end form/page templates are served from a copy stored in the database
 * (seeded from templates/*.html at first activation), and that DB copy takes
 * priority over the file. So when a plugin update ships a changed template, the
 * change does NOT reach an existing site until the stored copy is refreshed.
 *
 * This surfaces a dismissible admin banner whenever a stored template differs
 * from the default that ships with the current plugin version, offering to load
 * the new default (per template) or dismiss the notice. Detection is by content
 * hash — no per-version bookkeeping is required: if the shipped file differs
 * from the stored copy, it changed.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Detects stale DB-stored templates and renders an actionable admin notice.
 */
class NBUF_Template_Sync_Notice {

	/**
	 * Option holding the per-template acknowledged file-hash map.
	 *
	 * Shape: array( template_name => md5_of_default_file_the_admin_accepted ).
	 * Written when the admin DISMISSES the notice for a template they have
	 * chosen to keep (so the stored copy intentionally differs from the shipped
	 * default). A dismissal silences only the change it was made against — if
	 * the shipped default changes again in a future version its hash no longer
	 * matches and the banner returns. "Load default" needs no entry here: it
	 * writes the raw default, so the stored copy then equals the file and is no
	 * longer flagged.
	 */
	const ACK_OPTION = 'nbuf_template_sync_ack';

	/**
	 * admin-post action name for the load / dismiss buttons.
	 */
	const ACTION = 'nbuf_template_sync';

	/**
	 * User-facing form/page templates surfaced by the banner.
	 *
	 * Keyed by NBUF_Template_Manager template name (matching its option/file
	 * maps) => human label. Email/text templates are intentionally excluded so
	 * the notice stays focused on visible form/page layout changes.
	 *
	 * @return array<string, string>
	 */
	private static function templates(): array {
		return array(
			'account-page'        => __( 'Account page', 'nobloat-user-foundry' ),
			'login-form'          => __( 'Login form', 'nobloat-user-foundry' ),
			'registration-form'   => __( 'Registration form', 'nobloat-user-foundry' ),
			'request-reset-form'  => __( 'Password reset request form', 'nobloat-user-foundry' ),
			'reset-form'          => __( 'Password reset form', 'nobloat-user-foundry' ),
			'2fa-verify'          => __( '2FA verification page', 'nobloat-user-foundry' ),
			'2fa-setup-totp'      => __( '2FA setup page', 'nobloat-user-foundry' ),
			'2fa-backup-codes'    => __( '2FA backup codes page', 'nobloat-user-foundry' ),
			'policy-privacy-html' => __( 'Privacy policy', 'nobloat-user-foundry' ),
			'policy-terms-html'   => __( 'Terms of service', 'nobloat-user-foundry' ),
		);
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'admin_notices', array( __CLASS__, 'render' ) );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle' ) );
	}

	/**
	 * Read the acknowledged-hash map as a clean array.
	 *
	 * @return array<string, string>
	 */
	private static function get_ack(): array {
		$ack = NBUF_Options::get( self::ACK_OPTION, array() );
		return is_array( $ack ) ? $ack : array();
	}

	/**
	 * Compute the stale set: stored templates whose DB copy differs from the
	 * current default file and whose current file hash has not been acknowledged.
	 *
	 * @return array<string, array{label:string,file_hash:string}>
	 */
	public static function get_stale(): array {
		static $cache = null;
		if ( null !== $cache ) {
			return $cache;
		}

		if ( ! class_exists( 'NBUF_Template_Manager' ) ) {
			$cache = array();
			return $cache;
		}

		$ack   = self::get_ack();
		$stale = array();

		foreach ( self::templates() as $name => $label ) {
			$stored = NBUF_Template_Manager::get_stored_raw( $name );
			if ( '' === $stored ) {
				/* No DB copy — the file is already authoritative, nothing to sync. */
				continue;
			}

			$default = NBUF_Template_Manager::load_default_file( $name );
			if ( '' === $default ) {
				/* No shipped file to compare against. */
				continue;
			}

			$file_hash = md5( $default );

			if ( md5( $stored ) === $file_hash ) {
				continue; /* Stored copy already equals the shipped default. */
			}

			if ( isset( $ack[ $name ] ) && (string) $ack[ $name ] === $file_hash ) {
				continue; /* Admin dismissed this exact change (kept their copy). */
			}

			$stale[ $name ] = array(
				'label'     => $label,
				'file_hash' => $file_hash,
			);
		}

		$cache = $stale;
		return $cache;
	}

	/**
	 * Whether the banner should appear on the current admin screen.
	 *
	 * Limited to the plugin's own pages, the Dashboard, and the Plugins screen
	 * so it is seen right after an update without nagging on every admin page.
	 *
	 * @return bool
	 */
	private static function is_relevant_screen(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen routing, no state change.
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		if ( '' !== $page && false !== strpos( $page, 'nobloat' ) ) {
			return true;
		}

		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			$id     = $screen ? $screen->id : '';
			if ( 'dashboard' === $id || 'plugins' === $id || ( '' !== $id && false !== strpos( $id, 'nobloat' ) ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Render the admin notice (and any post-action confirmation).
	 *
	 * @return void
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		/* Post-action confirmation flash. */
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only confirmation flag set by our own redirect.
		$done = isset( $_GET['nbuf_tmpl_done'] ) ? sanitize_key( wp_unslash( $_GET['nbuf_tmpl_done'] ) ) : '';
		if ( 'loaded' === $done ) {
			echo '<div class="notice notice-success is-dismissible"><p>'
				. esc_html__( 'Template updated to the latest default.', 'nobloat-user-foundry' )
				. '</p></div>';
		} elseif ( 'dismissed' === $done ) {
			echo '<div class="notice notice-success is-dismissible"><p>'
				. esc_html__( 'Template update notice dismissed. It will return if these templates change again in a future update.', 'nobloat-user-foundry' )
				. '</p></div>';
		}

		if ( ! self::is_relevant_screen() ) {
			return;
		}

		$stale = self::get_stale();
		if ( empty( $stale ) ) {
			return;
		}

		$action_url = admin_url( 'admin-post.php' );
		$version    = defined( 'NBUF_VERSION' ) ? NBUF_VERSION : '';
		?>
		<div class="notice notice-warning">
			<p style="margin-bottom: 6px;">
				<strong><?php esc_html_e( 'NoBloat User Foundry — template update available', 'nobloat-user-foundry' ); ?></strong>
			</p>
			<p style="margin-top: 0;">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: number of templates that differ from the shipped default. */
						_n(
							'%d page/form template differs from the version that ships with this plugin update. Your site is still using your saved copy, so the new layout will not appear until you load the updated default. Loading a default overwrites any manual edits you made to that template.',
							'%d page/form templates differ from the versions that ship with this plugin update. Your site is still using your saved copies, so the new layouts will not appear until you load the updated defaults. Loading a default overwrites any manual edits you made to that template.',
							count( $stale ),
							'nobloat-user-foundry'
						),
						count( $stale )
					)
				);
				?>
			</p>
			<ul style="list-style: disc; margin: 8px 0 12px 22px;">
				<?php foreach ( $stale as $name => $info ) : ?>
					<li style="margin-bottom: 6px;">
						<span style="display: inline-block; min-width: 220px;"><?php echo esc_html( $info['label'] ); ?></span>
						<form method="post" action="<?php echo esc_url( $action_url ); ?>" style="display: inline;">
							<?php wp_nonce_field( self::ACTION ); ?>
							<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
							<input type="hidden" name="nbuf_sync_do" value="load">
							<input type="hidden" name="nbuf_sync_template" value="<?php echo esc_attr( $name ); ?>">
							<button type="submit" class="button button-small">
								<?php esc_html_e( 'Load updated default', 'nobloat-user-foundry' ); ?>
							</button>
						</form>
					</li>
				<?php endforeach; ?>
			</ul>
			<div style="margin: 0 0 4px;">
				<form method="post" action="<?php echo esc_url( $action_url ); ?>" style="display: inline;">
					<?php wp_nonce_field( self::ACTION ); ?>
					<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
					<input type="hidden" name="nbuf_sync_do" value="dismiss">
					<button type="submit" class="button-link" style="color: #646970; text-decoration: underline;">
						<?php esc_html_e( 'Dismiss this notice', 'nobloat-user-foundry' ); ?>
					</button>
				</form>
				<span style="color: #646970;">
					&nbsp;&middot;&nbsp;
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: plugin version. */
							__( 'Detected after updating to v%s. You can also edit templates manually in the plugin settings.', 'nobloat-user-foundry' ),
							$version
						)
					);
					?>
				</span>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle the load / dismiss admin-post submission.
	 *
	 * @return void
	 */
	public static function handle(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'nobloat-user-foundry' ) );
		}
		check_admin_referer( self::ACTION );

		$do     = isset( $_POST['nbuf_sync_do'] ) ? sanitize_key( wp_unslash( $_POST['nbuf_sync_do'] ) ) : '';
		$result = '';
		$ack    = self::get_ack();

		if ( 'load' === $do ) {
			$name      = isset( $_POST['nbuf_sync_template'] ) ? sanitize_text_field( wp_unslash( $_POST['nbuf_sync_template'] ) ) : '';
			$templates = self::templates();
			if ( isset( $templates[ $name ] ) && class_exists( 'NBUF_Template_Manager' ) ) {
				/*
				 * Write the RAW shipped default (matching the activator's install
				 * seeding), NOT save_template() — that would run plugin-trusted
				 * markup through the editor's kses sanitizer and could strip tags
				 * the template needs. A raw write makes the stored copy equal the
				 * file, so get_stale() clears it with no acknowledged-hash entry.
				 */
				if ( NBUF_Template_Manager::restore_default( $name ) ) {
					$result = 'loaded';
				}
			}
		} elseif ( 'dismiss' === $do ) {
			foreach ( self::get_stale() as $name => $info ) {
				$ack[ $name ] = $info['file_hash'];
			}
			NBUF_Options::update( self::ACK_OPTION, $ack, false, 'system' );
			$result = 'dismissed';
		}

		$redirect = wp_get_referer();
		if ( ! $redirect ) {
			$redirect = admin_url();
		}
		if ( '' !== $result ) {
			$redirect = add_query_arg( 'nbuf_tmpl_done', $result, $redirect );
		}
		wp_safe_redirect( $redirect );
		exit;
	}
}
