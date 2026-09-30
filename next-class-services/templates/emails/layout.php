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
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html( $site_name ); ?></title>
</head>
<body style="margin:0;padding:0;background-color:#f1efe7;font-family:Arial,Helvetica,sans-serif;color:#393939;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f1efe7;">
<tr><td align="center" style="padding:24px 12px;">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background-color:#ffffff;border:1px solid #393939;">
<tr><td style="background-color:#393939;padding:16px 24px;color:#ffffff;font-size:16px;font-weight:bold;"><?php echo esc_html( $site_name ); ?></td></tr>
<tr><td style="border-top:4px solid #ff954f;padding:0;font-size:0;line-height:0;">&nbsp;</td></tr>
<tr><td style="padding:24px;font-size:15px;line-height:1.6;color:#393939;">
<?php if ( $heading ) : ?>
<h1 style="margin:0 0 16px;font-size:20px;line-height:1.3;color:#393939;"><?php echo esc_html( $heading ); ?></h1>
<?php endif; ?>
<?php echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
<?php if ( $cta_url && $cta_label ) : ?>
<p style="margin:24px 0 8px;"><a href="<?php echo esc_url( $cta_url ); ?>" style="display:inline-block;background-color:#ff954f;color:#393939;text-decoration:none;font-weight:bold;padding:12px 24px;border:2px solid #393939;"><?php echo esc_html( $cta_label ); ?></a></p>
<?php endif; ?>
</td></tr>
<?php if ( $footer ) : ?>
<tr><td style="padding:16px 24px;background-color:#f1efe7;font-size:12px;line-height:1.5;color:#393939;border-top:1px solid #393939;"><?php echo $footer; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td></tr>
<?php endif; ?>
</table>
</td></tr>
</table>
</body>
</html>
