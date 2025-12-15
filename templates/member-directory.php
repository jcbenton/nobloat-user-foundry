<?php
/**
 * Member Directory Template - Grid View
 *
 * Displays members in a grid layout with search and filters.
 * Can be overridden by theme: nbuf-templates/member-directory.php
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
?>

<div class="nbuf-member-directory" data-view="grid">

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
							$nbuf_roles = wp_roles()->get_names();
							foreach ( $nbuf_roles as $nbuf_role_slug => $nbuf_role_name ) :
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
		<div class="nbuf-members-grid">
			<?php foreach ( $members as $nbuf_member ) : ?>
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_member_card() returns escaped HTML.
				echo NBUF_Member_Directory::get_member_card( $nbuf_member );
				?>
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
