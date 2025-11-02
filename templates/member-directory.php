<?php
/**
 * Member Directory Template - Grid View
 *
 * Displays members in a grid layout with search and filters.
 * Can be overridden by theme: nbuf-templates/member-directory.php
 *
 * Available variables:
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

$current_search = isset( $_GET['member_search'] ) ? sanitize_text_field( $_GET['member_search'] ) : '';
$current_role = isset( $_GET['member_role'] ) ? sanitize_text_field( $_GET['member_role'] ) : '';
?>

<div class="nbuf-member-directory" data-view="grid">

	<?php if ( $show_search || $show_filters ) : ?>
		<div class="nbuf-directory-controls">
			<form method="get" action="" class="nbuf-directory-form">
				<?php /* Preserve existing query vars */ ?>
				<?php foreach ( $_GET as $key => $value ) : ?>
					<?php if ( ! in_array( $key, array( 'member_search', 'member_role', 'member_page' ) ) ) : ?>
						<input type="hidden" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>">
					<?php endif; ?>
				<?php endforeach; ?>

				<?php if ( $show_search ) : ?>
					<div class="nbuf-directory-search">
						<input
							type="text"
							name="member_search"
							placeholder="<?php esc_attr_e( 'Search members...', 'nobloat-user-foundry' ); ?>"
							value="<?php echo esc_attr( $current_search ); ?>"
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
							$roles = wp_roles()->get_names();
							foreach ( $roles as $role_slug => $role_name ) :
								?>
								<option value="<?php echo esc_attr( $role_slug ); ?>" <?php selected( $current_role, $role_slug ); ?>>
									<?php echo esc_html( $role_name ); ?>
								</option>
							<?php endforeach; ?>
						</select>

						<button type="submit" class="nbuf-filter-button">
							<?php esc_html_e( 'Filter', 'nobloat-user-foundry' ); ?>
						</button>

						<?php if ( $current_search || $current_role ) : ?>
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
			<?php foreach ( $members as $member ) : ?>
				<?php echo NBUF_Member_Directory::get_member_card( $member ); ?>
			<?php endforeach; ?>
		</div>

		<?php if ( $total_pages > 1 ) : ?>
			<div class="nbuf-directory-pagination">
				<?php
				/* Build pagination links */
				$base_url = remove_query_arg( 'member_page' );

				if ( $current_page > 1 ) :
					$prev_url = add_query_arg( 'member_page', $current_page - 1, $base_url );
					?>
					<a href="<?php echo esc_url( $prev_url ); ?>" class="nbuf-page-prev">
						<?php esc_html_e( '&laquo; Previous', 'nobloat-user-foundry' ); ?>
					</a>
				<?php endif; ?>

				<span class="nbuf-page-numbers">
					<?php
					/* Show page numbers */
					for ( $i = 1; $i <= $total_pages; $i++ ) :
						if ( $i === $current_page ) :
							?>
							<span class="nbuf-page-number current"><?php echo (int) $i; ?></span>
						<?php else : ?>
							<a href="<?php echo esc_url( add_query_arg( 'member_page', $i, $base_url ) ); ?>" class="nbuf-page-number">
								<?php echo (int) $i; ?>
							</a>
							<?php
						endif;
					endfor;
					?>
				</span>

				<?php if ( $current_page < $total_pages ) : ?>
					<?php $next_url = add_query_arg( 'member_page', $current_page + 1, $base_url ); ?>
					<a href="<?php echo esc_url( $next_url ); ?>" class="nbuf-page-next">
						<?php esc_html_e( 'Next &raquo;', 'nobloat-user-foundry' ); ?>
					</a>
				<?php endif; ?>
			</div>
		<?php endif; ?>

	<?php else : ?>
		<div class="nbuf-no-members">
			<p><?php esc_html_e( 'No members found.', 'nobloat-user-foundry' ); ?></p>
			<?php if ( $current_search || $current_role ) : ?>
				<p>
					<a href="?"><?php esc_html_e( 'Clear filters and show all members', 'nobloat-user-foundry' ); ?></a>
				</p>
			<?php endif; ?>
		</div>
	<?php endif; ?>

</div>
