<?php
/**
 * Voucher publish email — self-contained.
 *
 * Bypasses WC's shared `email-header.php` / `email-footer.php` so other
 * transactional emails aren't touched. Inline styles only — Gmail strips
 * <style> blocks; table layout because Outlook needs it.
 *
 * Vars in scope (set by NotifEngine::render_template):
 *   $user            \WP_User
 *   $voucher         array<string,mixed>  (crm_vouchers row)
 *   $claim_url       string  permalink to /my-account/crm-vouchers/
 *   $unsubscribe_url string  permalink to /my-account/crm-notifications/
 *   $site_name       string
 */

defined( 'ABSPATH' ) || exit;

$is_percent  = in_array( (string) ( $voucher['discount_type'] ?? '' ), \ZippyCrm\Models\Voucher::PERCENT_DISCOUNT_TYPES, true );
$value       = (float) ( $voucher['discount_value'] ?? 0 );
$value_label = $is_percent
	? round( $value ) . '%'
	: '$' . number_format( $value, 2 );

$expiry_label = ! empty( $voucher['expires_at'] )
	? gmdate( 'F j, Y', strtotime( $voucher['expires_at'] . ' UTC' ) )
	: '';

$min_order = (float) ( $voucher['min_order_amount'] ?? 0 );
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title><?php echo esc_html( $voucher['title'] ?? '' ); ?></title>
</head>
<body style="margin:0;padding:0;background-color:#f4f4f6;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;color:#111;">

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f4f4f6;padding:24px 12px;">
	<tr>
		<td align="center">

			<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;background-color:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e5e5e8;">

				<!-- Header -->
				<tr>
					<td style="padding:28px 32px 8px 32px;">
						<p style="margin:0;font-size:12px;letter-spacing:0.08em;text-transform:uppercase;color:#6b7280;">
							<?php echo esc_html( $site_name ); ?>
						</p>
					</td>
				</tr>

				<!-- Discount block -->
				<tr>
					<td style="padding:8px 32px 24px 32px;">
						<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#111111;border-radius:10px;">
							<tr>
								<td align="center" style="padding:28px 24px;">
									<p style="margin:0;font-size:14px;letter-spacing:0.06em;text-transform:uppercase;color:#9ca3af;">
										<?php esc_html_e( 'New voucher', 'zippy-crm' ); ?>
									</p>
									<p style="margin:6px 0 0 0;font-size:48px;line-height:1;font-weight:700;color:#ffffff;">
										<?php echo esc_html( $value_label ); ?>
									</p>
									<p style="margin:6px 0 0 0;font-size:14px;color:#d1d5db;">
										<?php echo $is_percent ? esc_html__( 'off your next order', 'zippy-crm' ) : esc_html__( 'off your cart', 'zippy-crm' ); ?>
									</p>
								</td>
							</tr>
						</table>
					</td>
				</tr>

				<!-- Title + description -->
				<tr>
					<td style="padding:0 32px 16px 32px;">
						<h1 style="margin:0;font-size:22px;line-height:1.3;font-weight:600;color:#111;">
							<?php echo esc_html( $voucher['title'] ?? '' ); ?>
						</h1>
						<?php if ( ! empty( $voucher['description'] ) ) : ?>
							<p style="margin:12px 0 0 0;font-size:15px;line-height:1.55;color:#4b5563;">
								<?php echo esc_html( $voucher['description'] ); ?>
							</p>
						<?php endif; ?>
					</td>
				</tr>

				<!-- Meta row -->
				<tr>
					<td style="padding:8px 32px 24px 32px;">
						<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:13px;color:#6b7280;">
							<?php if ( $min_order > 0 ) : ?>
								<tr>
									<td style="padding:4px 0;">
										<?php
										printf(
											/* translators: %s: minimum order amount */
											esc_html__( 'Minimum order: %s', 'zippy-crm' ),
											esc_html( '$' . number_format( $min_order, 2 ) )
										);
										?>
									</td>
								</tr>
							<?php endif; ?>
							<?php if ( $expiry_label ) : ?>
								<tr>
									<td style="padding:4px 0;">
										<?php
										printf(
											/* translators: %s: expiry date */
											esc_html__( 'Valid until %s', 'zippy-crm' ),
											esc_html( $expiry_label )
										);
										?>
									</td>
								</tr>
							<?php endif; ?>
						</table>
					</td>
				</tr>

				<!-- CTA button (table-based for Outlook) -->
				<tr>
					<td style="padding:0 32px 32px 32px;">
						<table role="presentation" cellpadding="0" cellspacing="0" border="0">
							<tr>
								<td align="center" style="background-color:#111111;border-radius:8px;">
									<a href="<?php echo esc_url( $claim_url ); ?>"
										style="display:inline-block;padding:14px 28px;font-size:15px;font-weight:600;color:#ffffff;text-decoration:none;border-radius:8px;">
										<?php esc_html_e( 'View & Claim Now', 'zippy-crm' ); ?>
									</a>
								</td>
							</tr>
						</table>
					</td>
				</tr>

				<!-- Footer -->
				<tr>
					<td style="padding:20px 32px 28px 32px;border-top:1px solid #f0f0f3;">
						<p style="margin:0;font-size:12px;line-height:1.6;color:#9ca3af;">
							<?php
							printf(
								/* translators: %s: site name */
								esc_html__( 'You received this because you opted in to voucher updates from %s.', 'zippy-crm' ),
								esc_html( $site_name )
							);
							?>
							<br />
							<a href="<?php echo esc_url( $unsubscribe_url ); ?>" style="color:#6b7280;text-decoration:underline;">
								<?php esc_html_e( 'Manage notification preferences', 'zippy-crm' ); ?>
							</a>
						</p>
					</td>
				</tr>

			</table>

		</td>
	</tr>
</table>

</body>
</html>
