<?php
/**
 * Email layout. Inline solid colors only, no images.
 *
 * @var string $site_name
 * @var string $subject
 * @var string $greeting Escaped letter salutation.
 * @var string $closing Escaped letter closing HTML.
 * @var string $heading
 * @var string $body   Already escaped/kses body HTML.
 * @var string $cta_url
 * @var string $cta_label
 * @var string $secondary_url
 * @var string $secondary_label
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
<title><?php echo esc_html( $subject ); ?></title>
</head>
<body style="margin:0;padding:0;background-color:#f1efe7;font-family:<?php echo esc_attr( $font ); ?>;color:#393939;-webkit-text-size-adjust:100%;">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;mso-hide:all;"><?php echo esc_html( $heading ? $heading : $subject ); ?></div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f1efe7;">
<tr><td align="center" style="padding:32px 12px;">
<table role="presentation" width="640" cellpadding="0" cellspacing="0" border="0" style="max-width:640px;width:100%;background-color:#ffffff;border:1px solid #e6e4de;border-top:4px solid #393939;">
<tr><td style="padding:28px 28px 24px;border-bottom:1px solid #e6e4de;font-size:20px;font-weight:600;line-height:1.4;color:#393939;"><?php echo esc_html( $site_name ); ?></td></tr>
<tr><td style="padding:28px;font-size:16px;line-height:1.75;color:#393939;overflow-wrap:anywhere;">
<?php if ( $heading ) : ?>
<h1 style="margin:0 0 28px;font-size:24px;line-height:1.35;font-weight:600;color:#393939;"><?php echo esc_html( $heading ); ?></h1>
<?php endif; ?>
<?php if ( $greeting ) : ?>
<p style="margin:0 0 20px;line-height:1.75;"><?php echo $greeting; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in Mailer. ?></p>
<?php endif; ?>
<?php echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
<?php if ( $cta_url && $cta_label ) : ?>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:24px 0 14px;"><tr><td bgcolor="#393939" style="background-color:#393939;border-radius:4px;text-align:center;"><a href="<?php echo esc_url( $cta_url ); ?>" style="display:inline-block;border:1px solid #393939;border-radius:4px;background-color:#393939;color:#ffffff;text-decoration:none;font-weight:600;font-size:15px;line-height:1.4;padding:13px 22px;"><?php echo esc_html( $cta_label ); ?></a></td></tr></table>
<p style="margin:0 0 22px;font-size:12px;line-height:1.6;color:#666666;"><?php esc_html_e( 'If the button does not work, open this link:', 'next-class-services' ); ?><br><a href="<?php echo esc_url( $cta_url ); ?>" style="color:#393939;text-decoration:underline;word-break:break-all;"><?php echo esc_html( $cta_url ); ?></a></p>
<?php endif; ?>
<?php if ( $secondary_url && $secondary_label ) : ?>
<p style="margin:22px 0;padding:16px;border:1px solid #e6e4de;background-color:#faf9f6;font-size:15px;line-height:1.6;"><strong><?php echo esc_html( $secondary_label ); ?></strong><br><a href="<?php echo esc_url( $secondary_url ); ?>" style="color:#393939;text-decoration:underline;word-break:break-all;"><?php echo esc_html( $secondary_url ); ?></a></p>
<?php endif; ?>
<?php if ( $closing ) : ?>
<div style="margin:28px 0 0;line-height:1.75;"><?php echo $closing; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in Mailer. ?></div>
<?php endif; ?>
</td></tr>
<tr><td style="padding:22px 28px;background-color:#faf9f6;font-size:12px;line-height:1.7;color:#666666;border-top:1px solid #e6e4de;">
<?php if ( $footer ) : ?>
<?php echo $footer; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
<?php endif; ?>
<p style="margin:8px 0 0;color:#666666;"><?php echo esc_html( $site_name ); ?><br><a href="<?php echo esc_url( home_url( '/' ) ); ?>" style="color:#666666;text-decoration:underline;"><?php echo esc_html( preg_replace( '#^https?://#', '', untrailingslashit( home_url() ) ) ); ?></a></p>
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
