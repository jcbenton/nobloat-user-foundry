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
	gap: 40px;
	max-width: 1200px;
}
.nbuf-about-main {
	flex: 1;
	min-width: 0;
}
.nbuf-about-sidebar {
	width: 300px;
	flex-shrink: 0;
}

/* Hero Section */
.nbuf-about-wrap .nbuf-hero {
	display: block;
	box-sizing: border-box;
	background: linear-gradient(135deg, #1d2327 0%, #2c3338 100%) !important;
	border-radius: 12px;
	padding: 32px;
	margin-bottom: 32px;
	color: #fff !important;
	overflow: hidden;
}
.nbuf-about-wrap .nbuf-hero h2,
.nbuf-about-wrap .nbuf-hero h2.wp-heading-inline {
	margin: 0 0 8px 0 !important;
	padding: 0 !important;
	font-size: 1.8em !important;
	font-weight: 600 !important;
	color: #fff !important;
	display: flex !important;
	align-items: center;
	gap: 12px;
	border: none !important;
	background: transparent !important;
	line-height: 1.3;
}
.nbuf-about-wrap .nbuf-version-badge {
	display: inline-block;
	box-sizing: border-box;
	background: rgba(255,255,255,0.15) !important;
	padding: 4px 12px;
	border-radius: 20px;
	font-size: 13px !important;
	font-weight: 400 !important;
	color: rgba(255,255,255,0.9) !important;
	line-height: 1.4;
	flex-shrink: 0;
}
.nbuf-about-wrap .nbuf-hero-tagline {
	font-size: 16px !important;
	line-height: 1.6;
	color: rgba(255,255,255,0.85) !important;
	margin: 0 !important;
	padding: 0 !important;
}

/* Feature Grid */
.nbuf-features {
	display: grid;
	grid-template-columns: repeat(2, 1fr);
	gap: 16px;
	margin-bottom: 32px;
}
.nbuf-feature {
	background: #f6f7f7;
	border: 1px solid #e0e0e0;
	border-radius: 8px;
	padding: 20px;
}
.nbuf-feature-icon {
	width: 40px;
	height: 40px;
	background: #2271b1;
	border-radius: 8px;
	display: flex;
	align-items: center;
	justify-content: center;
	margin-bottom: 12px;
}
.nbuf-feature-icon .dashicons {
	color: #fff;
	font-size: 20px;
	width: 20px;
	height: 20px;
}
.nbuf-feature h4 {
	margin: 0 0 6px 0;
	font-size: 14px;
	font-weight: 600;
	color: #1d2327;
}
.nbuf-feature p {
	margin: 0;
	font-size: 13px;
	line-height: 1.5;
	color: #50575e;
}

/* Section Styling */
.nbuf-section {
	margin-bottom: 28px;
}
.nbuf-section h3 {
	margin: 0 0 14px 0;
	padding-bottom: 10px;
	border-bottom: 2px solid #2271b1;
	font-size: 1.15em;
	font-weight: 600;
	color: #1d2327;
}
.nbuf-section p {
	font-size: 14px;
	line-height: 1.7;
	color: #3c434a;
	margin: 0 0 14px 0;
}

/* Creator Section */
.nbuf-creator {
	background: #fff;
	border: 1px solid #e0e0e0;
	border-radius: 8px;
	padding: 24px;
	margin-bottom: 28px;
}
.nbuf-creator h3 {
	margin: 0 0 14px 0;
	font-size: 1.1em;
	font-weight: 600;
	color: #1d2327;
	border: none;
	padding: 0;
}
.nbuf-creator p {
	font-size: 14px;
	line-height: 1.7;
	color: #3c434a;
	margin: 0 0 12px 0;
}
.nbuf-creator p:last-child {
	margin-bottom: 0;
}

/* Links */
.nbuf-external-link {
	color: #2271b1;
	text-decoration: none;
	font-weight: 500;
}
.nbuf-external-link:hover {
	color: #135e96;
	text-decoration: underline;
}
.nbuf-external-link::after {
	content: "\f504";
	font-family: dashicons;
	font-size: 14px;
	margin-left: 3px;
	vertical-align: middle;
}

/* Resources List */
.nbuf-resources {
	display: grid;
	grid-template-columns: repeat(2, 1fr);
	gap: 12px;
}
.nbuf-resource-link {
	display: flex;
	align-items: center;
	gap: 10px;
	padding: 12px 16px;
	background: #f6f7f7;
	border: 1px solid #e0e0e0;
	border-radius: 6px;
	text-decoration: none;
	color: #1d2327;
	font-size: 14px;
	font-weight: 500;
	transition: all 0.15s ease;
}
.nbuf-resource-link:hover {
	background: #fff;
	border-color: #2271b1;
	color: #2271b1;
}
.nbuf-resource-link .dashicons {
	color: #2271b1;
	font-size: 18px;
	width: 18px;
	height: 18px;
}

/* Sidebar */
.nbuf-donate-box {
	background: linear-gradient(135deg, #2271b1 0%, #135e96 100%);
	border-radius: 12px;
	padding: 28px 24px;
	text-align: center;
	color: #fff;
}
.nbuf-donate-box h3 {
	margin: 0 0 8px 0;
	font-size: 1.3em;
	color: #fff;
	font-weight: 600;
}
.nbuf-donate-free {
	font-size: 14px;
	color: rgba(255,255,255,0.85);
	margin: 0 0 20px 0;
}
.nbuf-donate-button {
	display: block;
	background: #fff;
	color: #135e96 !important;
	font-size: 16px;
	font-weight: 600;
	padding: 14px 24px;
	border-radius: 8px;
	text-decoration: none;
	transition: all 0.2s ease;
}
.nbuf-donate-button:hover {
	background: #f0f6fc;
	color: #135e96 !important;
	text-decoration: none;
	transform: translateY(-1px);
}
.nbuf-donate-note {
	margin: 16px 0 0 0;
	font-size: 12px;
	color: rgba(255,255,255,0.7);
	line-height: 1.5;
}

.nbuf-rate-box {
	background: #fff;
	border: 1px solid #e0e0e0;
	border-radius: 12px;
	padding: 24px;
	text-align: center;
	margin-top: 20px;
}
.nbuf-rate-box h3 {
	margin: 0 0 8px 0;
	font-size: 1.1em;
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
	font-size: 24px;
	color: #ffb900;
	margin: 0 0 16px 0;
	letter-spacing: 2px;
}
.nbuf-rate-button {
	display: block;
	background: #f6f7f7;
	color: #1d2327 !important;
	font-size: 14px;
	font-weight: 500;
	padding: 12px 20px;
	border-radius: 6px;
	border: 1px solid #e0e0e0;
	text-decoration: none;
	transition: all 0.15s ease;
}
.nbuf-rate-button:hover {
	background: #fff;
	border-color: #2271b1;
	color: #2271b1 !important;
	text-decoration: none;
}

/* Philosophy Box */
.nbuf-philosophy {
	background: #f0f6fc;
	border-left: 4px solid #2271b1;
	padding: 16px 20px;
	margin: 20px 0;
	border-radius: 0 8px 8px 0;
}
.nbuf-philosophy p {
	margin: 0;
	font-size: 14px;
	line-height: 1.6;
	color: #1d2327;
	font-style: italic;
}

@media screen and (max-width: 960px) {
	.nbuf-about-wrap {
		flex-direction: column;
	}
	.nbuf-about-sidebar {
		width: 100%;
		order: -1;
	}
	.nbuf-features {
		grid-template-columns: 1fr;
	}
	.nbuf-resources {
		grid-template-columns: 1fr;
	}
}
</style>

<div class="nbuf-about-wrap">

	<!-- Main Content Column -->
	<div class="nbuf-about-main">

		<!-- Hero -->
		<div class="nbuf-hero">
			<h2>
				<?php esc_html_e( 'NoBloat User Foundry', 'nobloat-user-foundry' ); ?>
				<span class="nbuf-version-badge">v<?php echo esc_html( $nbuf_version ); ?></span>
			</h2>
			<p class="nbuf-hero-tagline">
				<?php esc_html_e( 'Enterprise-grade user management for WordPress. Multi-layer security, complete audit trails, GDPR compliance, and zero bloat.', 'nobloat-user-foundry' ); ?>
			</p>
		</div>

		<!-- Feature Grid -->
		<div class="nbuf-features">
			<div class="nbuf-feature">
				<div class="nbuf-feature-icon"><span class="dashicons dashicons-shield-alt"></span></div>
				<h4><?php esc_html_e( 'Multi-Layer Security', 'nobloat-user-foundry' ); ?></h4>
				<p><?php esc_html_e( '2FA (TOTP & email), passkeys, magic links, brute force protection, IP restrictions, and anti-bot measures.', 'nobloat-user-foundry' ); ?></p>
			</div>
			<div class="nbuf-feature">
				<div class="nbuf-feature-icon"><span class="dashicons dashicons-clipboard"></span></div>
				<h4><?php esc_html_e( 'Complete Audit Trail', 'nobloat-user-foundry' ); ?></h4>
				<p><?php esc_html_e( 'Three-tier logging: user activity, admin actions, and security events. Full accountability for compliance.', 'nobloat-user-foundry' ); ?></p>
			</div>
			<div class="nbuf-feature">
				<div class="nbuf-feature-icon"><span class="dashicons dashicons-privacy"></span></div>
				<h4><?php esc_html_e( 'GDPR Ready', 'nobloat-user-foundry' ); ?></h4>
				<p><?php esc_html_e( 'Data export, account deletion with anonymization, privacy controls, and no external service dependencies.', 'nobloat-user-foundry' ); ?></p>
			</div>
			<div class="nbuf-feature">
				<div class="nbuf-feature-icon"><span class="dashicons dashicons-performance"></span></div>
				<h4><?php esc_html_e( 'Zero Bloat', 'nobloat-user-foundry' ); ?></h4>
				<p><?php esc_html_e( 'Custom tables, lazy loading, conditional assets. No wp_options pollution. No external API calls.', 'nobloat-user-foundry' ); ?></p>
			</div>
		</div>

		<!-- Philosophy -->
		<div class="nbuf-philosophy">
			<p><?php esc_html_e( 'Built because existing solutions were either too bloated, too insecure, or not designed for production use. This plugin does what it says, nothing more.', 'nobloat-user-foundry' ); ?></p>
		</div>

		<!-- Creator -->
		<div class="nbuf-creator">
			<h3><?php esc_html_e( 'About the Developer', 'nobloat-user-foundry' ); ?></h3>
			<p>
				<?php
				printf(
					/* translators: %s: Link to Mailborder Systems website */
					esc_html__( 'Developed by Jerry Benton at %s, where the focus is building security infrastructure that handles real-world scale. Mailborder\'s email gateways process millions of messages daily with some of the most powerful filtering technologies available.', 'nobloat-user-foundry' ),
					'<a href="https://mailborder.com" target="_blank" rel="noopener noreferrer" class="nbuf-external-link">Mailborder Systems</a>'
				);
				?>
			</p>
			<p>
				<?php esc_html_e( 'That same approach drives this plugin: security by default, performance under load, and code you can trust in production.', 'nobloat-user-foundry' ); ?>
			</p>
		</div>

		<!-- Resources -->
		<div class="nbuf-section">
			<h3><?php esc_html_e( 'Resources', 'nobloat-user-foundry' ); ?></h3>
			<div class="nbuf-resources">
				<a href="https://docs.mailborder.com/nobloat-user-foundry" target="_blank" rel="noopener noreferrer" class="nbuf-resource-link">
					<span class="dashicons dashicons-book"></span>
					<?php esc_html_e( 'Documentation', 'nobloat-user-foundry' ); ?>
				</a>
				<a href="https://github.com/jcbenton/nobloat-user-foundry" target="_blank" rel="noopener noreferrer" class="nbuf-resource-link">
					<span class="dashicons dashicons-editor-code"></span>
					<?php esc_html_e( 'GitHub', 'nobloat-user-foundry' ); ?>
				</a>
				<a href="https://github.com/jcbenton/nobloat-user-foundry/issues" target="_blank" rel="noopener noreferrer" class="nbuf-resource-link">
					<span class="dashicons dashicons-flag"></span>
					<?php esc_html_e( 'Report Issue', 'nobloat-user-foundry' ); ?>
				</a>
				<a href="https://mailborder.com" target="_blank" rel="noopener noreferrer" class="nbuf-resource-link">
					<span class="dashicons dashicons-admin-site-alt3"></span>
					<?php esc_html_e( 'Mailborder', 'nobloat-user-foundry' ); ?>
				</a>
			</div>
		</div>

	</div>

	<!-- Sidebar Column -->
	<div class="nbuf-about-sidebar">
		<div class="nbuf-donate-box">
			<h3><?php esc_html_e( 'Support Development', 'nobloat-user-foundry' ); ?></h3>
			<p class="nbuf-donate-free"><?php esc_html_e( 'Free and open source. No upsells, no premium tiers.', 'nobloat-user-foundry' ); ?></p>
			<a href="https://donate.stripe.com/3cIfZi81NbxX9CX4uybfO01" target="_blank" rel="noopener noreferrer" class="nbuf-donate-button">
				<?php esc_html_e( 'Make a Donation', 'nobloat-user-foundry' ); ?>
			</a>
			<p class="nbuf-donate-note">
				<?php esc_html_e( 'Helps fund security updates and continued development.', 'nobloat-user-foundry' ); ?>
			</p>
		</div>

		<div class="nbuf-rate-box">
			<h3><?php esc_html_e( 'Find it Useful?', 'nobloat-user-foundry' ); ?></h3>
			<p><?php esc_html_e( 'A review helps others discover the plugin.', 'nobloat-user-foundry' ); ?></p>
			<div class="nbuf-rate-stars">&#9733;&#9733;&#9733;&#9733;&#9733;</div>
			<a href="https://wordpress.org/support/plugin/nobloat-user-foundry/reviews/#new-post" target="_blank" rel="noopener noreferrer" class="nbuf-rate-button">
				<?php esc_html_e( 'Leave a Review', 'nobloat-user-foundry' ); ?>
			</a>
		</div>
	</div>

</div>
