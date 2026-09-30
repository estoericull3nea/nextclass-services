<?php
/**
 * Assessment container.
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
?>
<div id="<?php echo esc_attr( $dom_id ); ?>" class="ncs-root ncs-assessment<?php echo esc_attr( $font ); ?>" style="<?php echo esc_attr( $style ); ?>" data-ncs-assessment="<?php echo esc_attr( $config['id'] ); ?>">
	<?php if ( $preview ) : ?>
		<p class="ncs-preview-banner"><?php esc_html_e( 'Preview mode. Submissions are not saved and no emails are sent.', 'next-class-services' ); ?></p>
	<?php endif; ?>
	<script type="application/json" class="ncs-config"><?php echo \NextClassServices\Helpers::json_for_html( $config ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></script>
	<div class="ncs-app" aria-live="polite">
		<p class="ncs-loading"><?php esc_html_e( 'Loading assessment...', 'next-class-services' ); ?></p>
	</div>
	<noscript>
		<p class="ncs-notice"><?php esc_html_e( 'JavaScript is required to take this assessment. Please enable JavaScript and reload the page.', 'next-class-services' ); ?></p>
	</noscript>
</div>
