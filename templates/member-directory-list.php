<?php
/**
 * Member Directory Template - List View
 *
 * Displays members in a compact list layout with search and filters.
 * Can be overridden by theme: nbuf-templates/member-directory-list.php
 *
 * Available variables:
 *
 * @var array  $members      Array of member objects
 * @var int    $total         Total member count
 * @var int    $per_page      Members per page
 * @var int    $current_page  Current page number
 * @var int    $total_pages   Total number of pages
 * @var bool   $show_search   Whether to show search box
 * @var bool   $show_filters  Whether to show filter dropdowns
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only GET parameters for search/filter.
$nbuf_current_search = isset( $_GET['member_search'] ) ? sanitize_text_field( wp_unslash( $_GET['member_search'] ) ) : '';
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only GET parameters for search/filter.
$nbuf_current_role = isset( $_GET['member_role'] ) ? sanitize_text_field( wp_unslash( $_GET['member_role'] ) ) : '';
$nbuf_viewer_id    = get_current_user_id();
?>

<div class="nbuf-member-directory" data-view="list">

	<?php if ( $show_search || $show_filters ) : ?>
		<div class="nbuf-directory-controls">
			<form method="get" action="" class="nbuf-directory-form">
				<?php /* Preserve existing query vars */ ?>
				<?php
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only GET parameters preservation.
				foreach ( $_GET as $nbuf_key => $nbuf_value ) :
					?>
					<?php if ( ! in_array( $nbuf_key, array( 'member_search', 'member_role', 'member_page' ), true ) ) : ?>
						<input type="hidden" name="<?php echo esc_attr( $nbuf_key ); ?>" value="<?php echo esc_attr( $nbuf_value ); ?>">
					<?php endif; ?>
				<?php endforeach; ?>

				<?php if ( $show_search ) : ?>
					<div class="nbuf-directory-search">
						<input
							type="text"
							name="member_search"
							placeholder="<?php esc_attr_e( 'Search members...', 'nobloat-user-foundry' ); ?>"
							value="<?php echo esc_attr( $nbuf_current_search ); ?>"
							class="nbuf-search-input"
						>
						<button type="submit" class="nbuf-search-button">
							<?php esc_html_e( 'Search', 'nobloat-user-foundry' ); ?>
						</button>
					</div>
				<?php endif; ?>

				<?php if ( $show_filters ) : ?>
					<div class="nbuf-directory-filters">
						<select name="member_role" class="nbuf-filter-select">
							<option value=""><?php esc_html_e( 'All Roles', 'nobloat-user-foundry' ); ?></option>
							<?php
							/* Only show roles that are allowed in directory (security: don't expose admin roles) */
							$nbuf_allowed_roles = NBUF_Options::get( 'nbuf_directory_roles', array( 'author', 'contributor', 'subscriber' ) );
							if ( ! is_array( $nbuf_allowed_roles ) ) {
								$nbuf_allowed_roles = array( 'author', 'contributor', 'subscriber' );
							}
							$nbuf_all_roles = wp_roles()->get_names();
							foreach ( $nbuf_allowed_roles as $nbuf_role_slug ) :
								if ( ! isset( $nbuf_all_roles[ $nbuf_role_slug ] ) ) {
									continue;
								}
								$nbuf_role_name = $nbuf_all_roles[ $nbuf_role_slug ];
								?>
								<option value="<?php echo esc_attr( $nbuf_role_slug ); ?>" <?php selected( $nbuf_current_role, $nbuf_role_slug ); ?>>
									<?php echo esc_html( $nbuf_role_name ); ?>
								</option>
							<?php endforeach; ?>
						</select>

						<button type="submit" class="nbuf-filter-button">
							<?php esc_html_e( 'Filter', 'nobloat-user-foundry' ); ?>
						</button>

						<?php if ( $nbuf_current_search || $nbuf_current_role ) : ?>
							<a href="?" class="nbuf-clear-filters">
								<?php esc_html_e( 'Clear', 'nobloat-user-foundry' ); ?>
							</a>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</form>

			<div class="nbuf-directory-stats">
				<?php
				/* translators: %d: total member count */
				printf( esc_html( _n( '%d member found', '%d members found', $total, 'nobloat-user-foundry' ) ), (int) $total );
				?>
			</div>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $members ) ) : ?>
		<div class="nbuf-members-list">
			<?php foreach ( $members as $nbuf_member ) : ?>
				<div class="nbuf-member-item" data-user-id="<?php echo esc_attr( $nbuf_member->ID ); ?>">
					<div class="nbuf-member-avatar-small">
						<?php echo get_avatar( $nbuf_member->ID, 48 ); ?>
					</div>

					<div class="nbuf-member-details">
						<h4 class="nbuf-member-name">
							<a href="<?php echo esc_url( get_author_posts_url( $nbuf_member->ID ) ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'View %s\'s profile', 'nobloat-user-foundry' ), $nbuf_member->display_name ) ); ?>">
								<?php echo esc_html( $nbuf_member->display_name ); ?>
							</a>
						</h4>

						<div class="nbuf-member-meta-inline">
							<?php if ( NBUF_Privacy_Manager::can_view_field( $nbuf_member->ID, 'location', $nbuf_viewer_id ) ) : ?>
								<?php if ( ! empty( $nbuf_member->city ) || ! empty( $nbuf_member->country ) ) : ?>
									<span class="nbuf-member-location-inline">
										<span class="dashicons dashicons-location"></span>
										<?php
										$nbuf_location_parts = array_filter( array( $nbuf_member->city, $nbuf_member->state, $nbuf_member->country ) );
										echo esc_html( implode( ', ', $nbuf_location_parts ) );
										?>
									</span>
								<?php endif; ?>
							<?php endif; ?>

							<span class="nbuf-member-joined-inline">
								<?php
								/* translators: %s: registration date */
								printf( esc_html__( 'Joined %s', 'nobloat-user-foundry' ), esc_html( date_i18n( get_option( 'date_format' ), strtotime( $nbuf_member->user_registered ) ) ) );
								?>
							</span>
						</div>

						<?php if ( NBUF_Privacy_Manager::can_view_field( $nbuf_member->ID, 'bio', $nbuf_viewer_id ) && ! empty( $nbuf_member->bio ) ) : ?>
							<div class="nbuf-member-bio-inline">
								<?php echo esc_html( wp_trim_words( $nbuf_member->bio, 15 ) ); ?>
							</div>
						<?php endif; ?>
					</div>

					<?php if ( NBUF_Privacy_Manager::can_view_field( $nbuf_member->ID, 'website', $nbuf_viewer_id ) && ! empty( $nbuf_member->website ) ) : ?>
						<div class="nbuf-member-actions">
							<a href="<?php echo esc_url( $nbuf_member->website ); ?>" target="_blank" rel="noopener noreferrer" class="nbuf-member-link">
								<?php esc_html_e( 'Website', 'nobloat-user-foundry' ); ?>
							</a>
						</div>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>

		<?php if ( $total_pages > 1 ) : ?>
			<div class="nbuf-directory-pagination">
				<?php
				/* Build pagination links */
				$nbuf_base_url = remove_query_arg( 'member_page' );

				if ( $current_page > 1 ) :
					$nbuf_prev_url = add_query_arg( 'member_page', $current_page - 1, $nbuf_base_url );
					?>
					<a href="<?php echo esc_url( $nbuf_prev_url ); ?>" class="nbuf-page-prev">
						<?php esc_html_e( '&laquo; Previous', 'nobloat-user-foundry' ); ?>
					</a>
				<?php endif; ?>

				<span class="nbuf-page-numbers">
					<?php
					/* Show page numbers */
					for ( $nbuf_i = 1; $nbuf_i <= $total_pages; $nbuf_i++ ) :
						if ( $current_page === $nbuf_i ) :
							?>
							<span class="nbuf-page-number current"><?php echo (int) $nbuf_i; ?></span>
						<?php else : ?>
							<a href="<?php echo esc_url( add_query_arg( 'member_page', $nbuf_i, $nbuf_base_url ) ); ?>" class="nbuf-page-number">
								<?php echo (int) $nbuf_i; ?>
							</a>
							<?php
						endif;
					endfor;
					?>
				</span>

				<?php if ( $current_page < $total_pages ) : ?>
					<?php $nbuf_next_url = add_query_arg( 'member_page', $current_page + 1, $nbuf_base_url ); ?>
					<a href="<?php echo esc_url( $nbuf_next_url ); ?>" class="nbuf-page-next">
						<?php esc_html_e( 'Next &raquo;', 'nobloat-user-foundry' ); ?>
					</a>
				<?php endif; ?>
			</div>
		<?php endif; ?>

	<?php else : ?>
		<div class="nbuf-no-members">
			<p><?php esc_html_e( 'No members found.', 'nobloat-user-foundry' ); ?></p>
			<?php if ( $nbuf_current_search || $nbuf_current_role ) : ?>
				<p>
					<a href="?"><?php esc_html_e( 'Clear filters and show all members', 'nobloat-user-foundry' ); ?></a>
				</p>
			<?php endif; ?>
		</div>
	<?php endif; ?>

</div>
