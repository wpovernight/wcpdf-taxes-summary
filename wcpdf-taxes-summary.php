<?php
/**
 * Plugin Name:      PDF Invoices & Packing Slips for WooCommerce - Taxes Summary
 * Requires Plugins: woocommerce-pdf-invoices-packing-slips
 * Plugin URI:       http://www.wpovernight.com
 * Description:      Add a taxes summary table to the PDF Invoices & Packing Slips for WooCommerce plugin.
 * Version:          1.0.0
 * Author:           WP Overnight
 * Author URI:       http://www.wpovernight.com
 * License:          GPLv2 or later
 * License URI:      http://www.opensource.org/licenses/gpl-license.php
 */

add_action( 'wpo_wcpdf_after_order_details', 'wpo_wcpdf_display_taxes_summary', 12, 2 );
 
function wpo_wcpdf_display_taxes_summary( $document_type, $order ) {
	$allowed_document_types = apply_filters( 'wpo_wcpdf_taxes_summary_allowed_document_types', array( 'invoice' ) );
	
	if ( ! in_array( $document_type, $allowed_document_types ) || ! $order ) {
		return;
	}
	
	// set to true to separate shipping and product taxes
	$split_product_shipping = apply_filters( 'wpo_wcpdf_taxes_summary_split_product_shipping', false );
	
	// totals
	$order_currency         = array( 'currency' => $order->get_currency() );
	$total_gross            = wc_price( $order->get_total(), $order_currency );
	$total_tax              = wc_price( $order->get_total_tax(), $order_currency );
	$total_net              = wc_price( $order->get_total()-$order->get_total_tax(), $order_currency );
	
	// tax data
	$tax_data               = wpo_wcpdf_taxes_summary_get_tax_data( $order );

	if ( $split_product_shipping ) {
		$tax_type_names = array(
			'product'  => __( 'Products', 'woocommerce-pdf-invoices-packing-slips' ),
			'shipping' => __( 'Shipping', 'woocommerce-pdf-invoices-packing-slips' ),
		);
	} else {
		$tax_type_names = array(
			'combined' => __( 'Including', 'woocommerce-pdf-invoices-packing-slips' ),
		);
	}
	
	// output
	?>
		<table class="tax-summary">
			<thead>
				<tr>
					<td></td>
					<td><?php _e( 'Net amount', 'woocommerce-pdf-invoices-packing-slips' ); ?></td>
					<td><?php _e( 'Tax rate', 'woocommerce-pdf-invoices-packing-slips' ); ?></td>
					<td><?php _e( 'Tax Amount', 'woocommerce-pdf-invoices-packing-slips' ); ?></td>
					<td><?php _e( 'Gross amount', 'woocommerce-pdf-invoices-packing-slips' ); ?></td>
				</tr>
			</thead>
			<tbody>
	<?php
		
				printf( '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>', __( 'Total', 'woocommerce-pdf-invoices-packing-slips' ), $total_net, 'X', $total_tax, $total_gross );
				
				// per tax rate
				foreach ( $tax_data as $tax_type => $taxes ) {
					if ( ! isset( $tax_type_names[ $tax_type ] ) ) {
						continue;
					}
					
					foreach ( $taxes as $tax_id => $tax ) {
						$tax_type_name     = $tax_type_names[ $tax_type ];
						$default_rate_name = apply_filters( 'wpo_wcpdf_taxes_summary_default_rate_name', __( 'VAT', 'woocommerce-pdf-invoices-packing-slips' ) );
						$formatted_rate    = sprintf( '%s %s%%', $default_rate_name, number_format( floatval( $tax['rate'] ), 0, '.', '' ) );
						$base              = wc_price( $tax['base'], $order_currency );
						$amount            = wc_price( $tax['amount'], $order_currency );
						$gross             = wc_price( $tax['gross'], $order_currency );
						
						printf( '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>', $tax_type_name, $base, $formatted_rate, $amount, $gross );
					}
				}
	?>
			</tbody>
		</table>
	<?php
}

function wpo_wcpdf_taxes_summary_get_tax_data( $order ) {
	// first collect rate % data
	$tax_rates = array();
	foreach ( $order->get_items( 'tax' ) as $item_id => $tax ) {
		$tax_rates[ $tax->get_rate_id( )] = $tax->get_rate_percent();
	}

	// then collect the amounts from the order items
	$tax_data = array(
		'product'  => array(),
		'shipping' => array(),
		'combined' => array(),
	);
	
	foreach ( $order->get_items( array( 'line_item', 'fee', 'shipping' ) ) as $item_id => $item ) {
		$tax_type = ( 'shipping' === $item->get_type() ) ? 'shipping' : 'product';
		$taxes    = $item->get_taxes();
		
		if ( $taxes ) {
			foreach ( $taxes['total'] as $tax_id => $tax_amount ) {
				if ( empty( $tax_rates[ $tax_id ] ) ) {
					continue; // tax rate not known
				}
				
				if ( is_null( $tax_amount ) || '' === $tax_amount ) {
					continue; // no tax for this rate
				}

				$item_tax_data = array(
					'rate'   => $tax_rates[ $tax_id ],
					'base'   => $item->get_total(),
					'amount' => floatval( $tax_amount ),
					'gross'  => $item->get_total() + floatval( $tax_amount ),
				);
				
				foreach ( array( $tax_type, 'combined' ) as $_tax_type ) {
					if ( empty( $tax_data[ $_tax_type ][ $tax_id ] ) ) {
						$tax_data[ $_tax_type ][ $tax_id ] = $item_tax_data;
					} else {
						foreach ( $item_tax_data as $item_tax_data_key => $item_tax_data_value ) {
							if ( 'rate' !== $item_tax_data_key ) {
								$tax_data[ $_tax_type ][ $tax_id ][ $item_tax_data_key ] += $item_tax_data_value;
							}
						}
					}
				}
			}
		};
	}
	
	return $tax_data;
}
