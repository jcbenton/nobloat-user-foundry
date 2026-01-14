<?php
/**
 * About > Info Tab
 *
 * Information about the plugin, its creator, and support options.
 *
 * @package NoBloat_User_Foundry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$nbuf_version = defined( 'NBUF_VERSION' ) ? NBUF_VERSION : '1.0.0';
?>

<style>
.nbuf-about-wrap {
	display: flex;
	gap: 30px;
	max-width: 1200px;
}
.nbuf-about-main {
	flex: 1;
	min-width: 0;
}
.nbuf-about-sidebar {
	width: 280px;
	flex-shrink: 0;
}
.nbuf-about-section {
	margin-bottom: 28px;
}
.nbuf-about-section h2 {
	margin: 0 0 12px 0;
	padding-bottom: 10px;
	border-bottom: 1px solid #c3c4c7;
	font-size: 1.3em;
	color: #1d2327;
}
.nbuf-about-section p {
	font-size: 14px;
	line-height: 1.7;
	color: #50575e;
	margin: 0 0 12px 0;
}
.nbuf-about-section ul {
	margin: 12px 0 0 20px;
	padding: 0;
}
.nbuf-about-section li {
	font-size: 14px;
	line-height: 1.8;
	color: #50575e;
	margin-bottom: 8px;
}
.nbuf-about-section li strong {
	color: #1d2327;
}
.nbuf-external-link {
	color: #2271b1;
	text-decoration: none;
}
.nbuf-external-link:hover {
	color: #135e96;
	text-decoration: underline;
}
.nbuf-external-link::after {
	content: "\f504";
	font-family: dashicons;
	font-size: 12px;
	margin-left: 4px;
	vertical-align: middle;
}
.nbuf-donate-box {
	background: #f6f7f7;
	border: 1px solid #c3c4c7;
	border-radius: 8px;
	padding: 28px 24px;
	text-align: center;
	margin-top: 20px;
	position: sticky;
	top: 32px;
}
.nbuf-donate-box h3 {
	margin: 0 0 6px 0;
	font-size: 1.3em;
	color: #1d2327;
	font-weight: 600;
}
.nbuf-donate-free {
	font-size: 13px;
	color: #50575e;
	margin: 0 0 20px 0;
}
.nbuf-donate-box .nbuf-donate-amount {
	font-size: 2.2em;
	font-weight: 600;
	color: #1d2327;
	margin: 0 0 20px 0;
}
.nbuf-donate-button {
	display: block;
	background: #5469d4;
	color: #fff !important;
	font-size: 17px;
	font-weight: 500;
	padding: 14px 24px;
	border-radius: 6px;
	text-decoration: none;
	transition: background 0.2s ease;
}
.nbuf-donate-button:hover {
	background: #4354a4;
	color: #fff !important;
	text-decoration: none;
}
.nbuf-donate-note {
	margin: 16px 0 0 0;
	font-size: 12px;
	color: #787c82;
	line-height: 1.5;
}
.nbuf-rate-box {
	background: #f6f7f7;
	border: 1px solid #c3c4c7;
	border-radius: 8px;
	padding: 24px;
	text-align: center;
	margin-top: 20px;
}
.nbuf-rate-box h3 {
	margin: 0 0 8px 0;
	font-size: 1.2em;
	color: #1d2327;
	font-weight: 600;
}
.nbuf-rate-box p {
	font-size: 13px;
	color: #50575e;
	margin: 0 0 16px 0;
	line-height: 1.5;
}
.nbuf-rate-stars {
	font-size: 28px;
	color: #ffb900;
	margin: 0 0 16px 0;
	letter-spacing: 4px;
}
.nbuf-rate-button {
	display: block;
	background: #2271b1;
	color: #fff !important;
	font-size: 15px;
	font-weight: 500;
	padding: 12px 20px;
	border-radius: 6px;
	text-decoration: none;
	transition: background 0.2s ease;
}
.nbuf-rate-button:hover {
	background: #135e96;
	color: #fff !important;
	text-decoration: none;
}
.nbuf-version-badge {
	display: inline-block;
	background: #f0f0f1;
	padding: 4px 10px;
	border-radius: 4px;
	font-size: 13px;
	color: #50575e;
	margin-left: 8px;
	vertical-align: middle;
}
@media screen and (max-width: 960px) {
	.nbuf-about-wrap {
		flex-direction: column;
	}
	.nbuf-about-sidebar {
		width: 100%;
		order: -1;
	}
	.nbuf-donate-box {
		position: static;
	}
}
</style>

<div class="nbuf-about-wrap">

	<!-- Main Content Column -->
	<div class="nbuf-about-main">

		<!-- Plugin Info -->
		<div class="nbuf-about-section">
			<h2>
				<?php esc_html_e( 'NoBloat User Foundry', 'nobloat-user-foundry' ); ?>
				<span class="nbuf-version-badge">v<?php echo esc_html( $nbuf_version ); ?></span>
			</h2>
			<p>
				<?php esc_html_e( 'NoBloat User Foundry is a comprehensive user management system for WordPress, designed from the ground up with enterprise and business environments in mind.', 'nobloat-user-foundry' ); ?>
			</p>
		</div>

		<!-- Why This Plugin -->
		<div class="nbuf-about-section">
			<h2><?php esc_html_e( 'Why NoBloat User Foundry?', 'nobloat-user-foundry' ); ?></h2>
			<p>
				<?php esc_html_e( 'After years of working with WordPress in enterprise and business environments, it became clear that existing user management solutions for Wordpress were either too bloated, too insecure, or simply not designed for serious production use. NoBloat User Foundry was created to address these shortcomings.', 'nobloat-user-foundry' ); ?>
			</p>
			<ul>
				<li>
					<strong><?php esc_html_e( 'Enterprise and Business Focus', 'nobloat-user-foundry' ); ?></strong> &mdash;
					<?php esc_html_e( 'Built for organizations that require robust user management, audit logging, compliance features, and reliable performance under load.', 'nobloat-user-foundry' ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'E-commerce Integration', 'nobloat-user-foundry' ); ?></strong> &mdash;
					<?php esc_html_e( 'Designed specifally to support e-commerce solutions such as Woocommerce, Easy Digital Downloads, or any other plugin or independent solution to give improved capabilities for customers.', 'nobloat-user-foundry' ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'Lightweight Architecture', 'nobloat-user-foundry' ); ?></strong> &mdash;
					<?php esc_html_e( 'Custom database tables eliminate wp_options and wp_usermeta bloat. Autoloading ensures only required code is loaded. No unnecessary dependencies. No global loading of CSS or JS assets.', 'nobloat-user-foundry' ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'Security First', 'nobloat-user-foundry' ); ?></strong> &mdash;
					<?php esc_html_e( 'Every feature is built with security as the primary concern. Prepared statements, cryptographically secure tokens, rate limiting, and comprehensive input validation throughout.', 'nobloat-user-foundry' ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'No Bloat', 'nobloat-user-foundry' ); ?></strong> &mdash;
					<?php esc_html_e( 'No upsells, no tracking, no external API calls without your consent. Just clean, well-documented code that does what it says.', 'nobloat-user-foundry' ); ?>
				</li>
			</ul>
		</div>

		<!-- About the Creator -->
		<div class="nbuf-about-section">
			<h2><?php esc_html_e( 'About the Creator', 'nobloat-user-foundry' ); ?></h2>
			<p>
				<?php
				printf(
					/* translators: %s: Link to Mailborder Systems website */
					esc_html__( 'NoBloat User Foundry is developed and maintained by Jerry Benton at %s, a software development company specializing in security-focused solutions for email gateways, email infrastructure and web applications.', 'nobloat-user-foundry' ),
					'<a href="https://mailborder.com" target="_blank" rel="noopener noreferrer" class="nbuf-external-link">Mailborder Systems</a>'
				);
				?>
			</p>
			<p>
				<?php esc_html_e( 'With decades of experience in systems administration, security engineering, and enterprise software development, the goal is simple: build tools that work reliably, perform efficiently, and keep your data secure.', 'nobloat-user-foundry' ); ?>
			</p>
		</div>

		<!-- Links -->
		<div class="nbuf-about-section">
			<h2><?php esc_html_e( 'Resources', 'nobloat-user-foundry' ); ?></h2>
			<ul>
				<li>
					<a href="https://docs.mailborder.com/nobloat-user-foundry" target="_blank" rel="noopener noreferrer" class="nbuf-external-link">
						<?php esc_html_e( 'Documentation', 'nobloat-user-foundry' ); ?>
					</a>
				</li>
				<li>
					<a href="https://github.com/jcbenton/nobloat-user-foundry" target="_blank" rel="noopener noreferrer" class="nbuf-external-link">
						<?php esc_html_e( 'GitHub Repository', 'nobloat-user-foundry' ); ?>
					</a>
				</li>
				<li>
					<a href="https://github.com/jcbenton/nobloat-user-foundry/issues" target="_blank" rel="noopener noreferrer" class="nbuf-external-link">
						<?php esc_html_e( 'Report an Issue', 'nobloat-user-foundry' ); ?>
					</a>
				</li>
				<li>
					<a href="https://mailborder.com" target="_blank" rel="noopener noreferrer" class="nbuf-external-link">
						<?php esc_html_e( 'Mailborder Systems', 'nobloat-user-foundry' ); ?>
					</a>
				</li>
			</ul>
		</div>

	</div>

	<!-- Sidebar Column -->
	<div class="nbuf-about-sidebar">
		<div class="nbuf-donate-box">
			<h3><?php esc_html_e( 'Support Development', 'nobloat-user-foundry' ); ?></h3>
			<p class="nbuf-donate-free"><?php esc_html_e( 'This plugin is free and open source.', 'nobloat-user-foundry' ); ?></p>
			<div class="nbuf-donate-amount">$25</div>
			<a href="https://donate.stripe.com/3cIfZi81NbxX9CX4uybfO01" target="_blank" rel="noopener noreferrer" class="nbuf-donate-button">
				<?php esc_html_e( 'Donate', 'nobloat-user-foundry' ); ?>
			</a>
			<p class="nbuf-donate-note">
				<?php esc_html_e( 'Your support helps fund continued development, security updates, and new features.', 'nobloat-user-foundry' ); ?>
			</p>
		</div>

		<div class="nbuf-rate-box">
			<h3><?php esc_html_e( 'Enjoying the Plugin?', 'nobloat-user-foundry' ); ?></h3>
			<p><?php esc_html_e( 'Help others discover NoBloat User Foundry by leaving a review.', 'nobloat-user-foundry' ); ?></p>
			<div class="nbuf-rate-stars">&#9733;&#9733;&#9733;&#9733;&#9733;</div>
			<a href="https://wordpress.org/support/plugin/nobloat-user-foundry/reviews/#new-post" target="_blank" rel="noopener noreferrer" class="nbuf-rate-button">
				<?php esc_html_e( 'Rate on WordPress.org', 'nobloat-user-foundry' ); ?>
			</a>
		</div>
	</div>

</div>
