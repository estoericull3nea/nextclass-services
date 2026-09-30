<?php
/**
 * Email layout. Inline solid colors only, no images.
 *
 * @var string $site_name
 * @var string $heading
 * @var string $body   Already escaped/kses body HTML.
 * @var string $cta_url
 * @var string $cta_label
 * @var string $footer Already escaped/kses footer HTML.
 *
 * @package NextClassServices
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$font = "'Segoe UI', Helvetica, Arial, sans-serif";
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html( $site_name ); ?></title>
</head>
<body style="margin:0;padding:0;background-color:#f1efe7;font-family:<?php echo esc_attr( $font ); ?>;color:#393939;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f1efe7;">
<tr><td align="center" style="padding:32px 12px;">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background-color:#ffffff;">
<tr><td style="background-color:#393939;padding:20px 32px;color:#ffffff;font-size:18px;font-weight:bold;letter-spacing:0.02em;"><?php echo esc_html( $site_name ); ?></td></tr>
<tr><td style="padding:32px 32px 8px;font-size:16px;line-height:1.7;color:#393939;">
<?php if ( $heading ) : ?>
<h1 style="margin:0 0 20px;font-size:24px;line-height:1.3;font-weight:bold;color:#393939;"><?php echo esc_html( $heading ); ?></h1>
<?php endif; ?>
<?php echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
<?php if ( $cta_url && $cta_label ) : ?>
<p style="margin:28px 0 8px;"><a href="<?php echo esc_url( $cta_url ); ?>" style="display:inline-block;background-color:#393939;color:#ffffff;text-decoration:none;font-weight:bold;font-size:15px;padding:14px 28px;"><?php echo esc_html( $cta_label ); ?></a></p>
<?php endif; ?>
</td></tr>
<tr><td style="padding:24px 32px 32px;font-size:13px;line-height:1.6;color:#666666;border-top:1px solid #f1efe7;">
<?php if ( $footer ) : ?>
<?php echo $footer; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
<?php endif; ?>
<p style="margin:8px 0 0;color:#666666;"><?php echo esc_html( $site_name ); ?> | <a href="<?php echo esc_url( home_url( '/' ) ); ?>" style="color:#666666;"><?php echo esc_html( preg_replace( '#^https?://#', '', untrailingslashit( home_url() ) ) ); ?></a></p>
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
