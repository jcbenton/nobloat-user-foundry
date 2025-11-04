<?php
/**
 * Media Tab
 *
 * Controls image upload optimization, WebP conversion, and file size limits
 * for the NoBloat User Foundry plugin.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Image optimization settings */
$convert_to_webp     = NBUF_Options::get( 'nbuf_convert_images_to_webp', true );
$webp_quality        = NBUF_Options::get( 'nbuf_webp_quality', 85 );
$profile_max_width   = NBUF_Options::get( 'nbuf_profile_photo_max_width', 1024 );
$profile_max_size_mb = NBUF_Options::get( 'nbuf_profile_photo_max_size', 5 );
$cover_max_width     = NBUF_Options::get( 'nbuf_cover_photo_max_width', 1920 );
$cover_max_height    = NBUF_Options::get( 'nbuf_cover_photo_max_height', 600 );
$cover_max_size_mb   = NBUF_Options::get( 'nbuf_cover_photo_max_size', 10 );
$strip_exif          = NBUF_Options::get( 'nbuf_strip_exif_data', true );

/* Check WebP support */
$webp_supported = false;
if ( function_exists( 'imagewebp' ) ) {
	$webp_supported = true;
} elseif ( extension_loaded( 'imagick' ) ) {
	$imagick        = new Imagick();
	$webp_supported = in_array( 'WEBP', $imagick->queryFormats(), true );
}
?>

<form method="post" action="options.php">
	<?php
	settings_fields( 'nbuf_media_group' );
	settings_errors( 'nbuf_media' );
	?>

	<!-- WebP Conversion -->
	<h2><?php esc_html_e( 'Image Optimization', 'nobloat-user-foundry' ); ?></h2>

	<?php if ( ! $webp_supported ) : ?>
		<div class="notice notice-warning inline">
			<p>
				<strong><?php esc_html_e( 'WebP Not Supported', 'nobloat-user-foundry' ); ?></strong><br>
				<?php esc_html_e( 'Your server does not support WebP image conversion. Images will be optimized in their original format (JPG/PNG) instead. Contact your hosting provider to enable GD or Imagick with WebP support for better compression.', 'nobloat-user-foundry' ); ?>
			</p>
		</div>
	<?php endif; ?>

	<table class="form-table">
		<tr>
			<th><?php esc_html_e( 'Convert to WebP', 'nobloat-user-foundry' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="nbuf_convert_images_to_webp" value="1" <?php checked( $convert_to_webp, true ); ?> <?php disabled( ! $webp_supported ); ?>>
					<?php esc_html_e( 'Automatically convert uploaded images to WebP format', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'WebP images are 25-35% smaller than JPG/PNG with identical visual quality. Recommended for better performance and reduced storage.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>

		<tr>
			<th><?php esc_html_e( 'WebP Quality', 'nobloat-user-foundry' ); ?></th>
			<td>
				<input type="number" name="nbuf_webp_quality" value="<?php echo esc_attr( $webp_quality ); ?>" min="1" max="100" class="small-text" <?php disabled( ! $webp_supported ); ?>>
				<span><?php esc_html_e( '(1-100)', 'nobloat-user-foundry' ); ?></span>
				<p class="description">
					<strong><?php esc_html_e( 'Recommended: 85', 'nobloat-user-foundry' ); ?></strong><br>
					<?php esc_html_e( 'Quality Guide:', 'nobloat-user-foundry' ); ?><br>
					• <strong>95-100:</strong> <?php esc_html_e( 'Lossless/Excellent (largest files)', 'nobloat-user-foundry' ); ?><br>
					• <strong>85-90:</strong> <?php esc_html_e( 'Recommended (best balance)', 'nobloat-user-foundry' ); ?><br>
					• <strong>75-85:</strong> <?php esc_html_e( 'Good (smaller files, minimal quality loss)', 'nobloat-user-foundry' ); ?><br>
					• <strong>60-75:</strong> <?php esc_html_e( 'Fair (noticeable compression)', 'nobloat-user-foundry' ); ?><br>
					• <strong>Below 60:</strong> <?php esc_html_e( 'Poor (not recommended)', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>

		<tr>
			<th><?php esc_html_e( 'Strip EXIF Data', 'nobloat-user-foundry' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="nbuf_strip_exif_data" value="1" <?php checked( $strip_exif, true ); ?>>
					<?php esc_html_e( 'Remove EXIF metadata from uploaded images', 'nobloat-user-foundry' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'Removes GPS location, camera model, and other metadata for privacy. Also reduces file size by ~50KB per image. Recommended.', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<!-- Profile Photo Limits -->
	<h2><?php esc_html_e( 'Profile Photo Limits', 'nobloat-user-foundry' ); ?></h2>
	<table class="form-table">
		<tr>
			<th><?php esc_html_e( 'Maximum Width', 'nobloat-user-foundry' ); ?></th>
			<td>
				<input type="number" name="nbuf_profile_photo_max_width" value="<?php echo esc_attr( $profile_max_width ); ?>" min="256" max="4096" class="small-text">
				<span><?php esc_html_e( 'pixels', 'nobloat-user-foundry' ); ?></span>
				<p class="description">
					<?php esc_html_e( 'Profile photos will be automatically resized to this maximum width. Default: 1024px', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>

		<tr>
			<th><?php esc_html_e( 'Maximum File Size', 'nobloat-user-foundry' ); ?></th>
			<td>
				<input type="number" name="nbuf_profile_photo_max_size" value="<?php echo esc_attr( $profile_max_size_mb ); ?>" min="1" max="50" class="small-text">
				<span><?php esc_html_e( 'MB', 'nobloat-user-foundry' ); ?></span>
				<p class="description">
					<?php esc_html_e( 'Reject profile photo uploads larger than this size. Default: 5MB', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<!-- Cover Photo Limits -->
	<h2><?php esc_html_e( 'Cover Photo Limits', 'nobloat-user-foundry' ); ?></h2>
	<table class="form-table">
		<tr>
			<th><?php esc_html_e( 'Maximum Width', 'nobloat-user-foundry' ); ?></th>
			<td>
				<input type="number" name="nbuf_cover_photo_max_width" value="<?php echo esc_attr( $cover_max_width ); ?>" min="800" max="4096" class="small-text">
				<span><?php esc_html_e( 'pixels', 'nobloat-user-foundry' ); ?></span>
				<p class="description">
					<?php esc_html_e( 'Cover photos will be automatically resized to this maximum width. Default: 1920px', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>

		<tr>
			<th><?php esc_html_e( 'Maximum Height', 'nobloat-user-foundry' ); ?></th>
			<td>
				<input type="number" name="nbuf_cover_photo_max_height" value="<?php echo esc_attr( $cover_max_height ); ?>" min="200" max="2048" class="small-text">
				<span><?php esc_html_e( 'pixels', 'nobloat-user-foundry' ); ?></span>
				<p class="description">
					<?php esc_html_e( 'Cover photos will be automatically resized to this maximum height. Default: 600px', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>

		<tr>
			<th><?php esc_html_e( 'Maximum File Size', 'nobloat-user-foundry' ); ?></th>
			<td>
				<input type="number" name="nbuf_cover_photo_max_size" value="<?php echo esc_attr( $cover_max_size_mb ); ?>" min="1" max="50" class="small-text">
				<span><?php esc_html_e( 'MB', 'nobloat-user-foundry' ); ?></span>
				<p class="description">
					<?php esc_html_e( 'Reject cover photo uploads larger than this size. Default: 10MB', 'nobloat-user-foundry' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<?php submit_button(); ?>
</form>
