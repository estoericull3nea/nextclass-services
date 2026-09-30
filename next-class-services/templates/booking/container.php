<?php
/**
 * Booking container.
 *
 * @var string $dom_id
 * @var array  $config
 * @var bool   $preview
 * @var string $style
 * @var string $font
 *
 * @package NextClassServices
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$first = isset( $config['services'][0] ) ? $config['services'][0] : array();
?>
<div id="<?php echo esc_attr( $dom_id ); ?>" class="ncs-root ncs-booking<?php echo esc_attr( $font ); ?>" style="<?php echo esc_attr( $style ); ?>" data-ncs-booking>
	<?php if ( $preview ) : ?>
		<p class="ncs-preview-banner"><?php esc_html_e( 'Preview mode. Native bookings made here are not saved and no emails are sent.', 'next-class-services' ); ?></p>
	<?php endif; ?>
	<script type="application/json" class="ncs-config"><?php echo \NextClassServices\Helpers::json_for_html( $config ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></script>
	<div class="ncs-app" aria-live="polite">
		<p class="ncs-loading"><?php esc_html_e( 'Loading booking options...', 'next-class-services' ); ?></p>
	</div>
	<noscript>
		<?php if ( ! empty( $first['url'] ) ) : ?>
			<p><a class="ncs-btn ncs-btn-primary" href="<?php echo esc_url( $first['url'] ); ?>" rel="noopener noreferrer" target="_blank"><?php esc_html_e( 'Open the booking page', 'next-class-services' ); ?></a></p>
		<?php else : ?>
			<p class="ncs-notice"><?php esc_html_e( 'JavaScript is required to book online. Please enable JavaScript and reload the page.', 'next-class-services' ); ?></p>
		<?php endif; ?>
	</noscript>
</div>
