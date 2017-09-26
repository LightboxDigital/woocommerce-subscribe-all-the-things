<?php
/**
 * Clone Order Functionality.
 *
 */
class LBCloneOrder extends WCS_ATT_Admin {

    /**
	 * Create replicated order post and initiate cloned_order_data function.
	 */
    
     public function clone_order($originalorderid = null, $userId, $outstandingValue){
	    
        $currentUser = wp_get_current_user($userId);
		
		$order_data =  array(
	        'post_type'     => 'shop_order',
	        'post_status'   => 'publish',
	        'ping_status'   => 'closed',
	        'post_author'   => $currentUser->ID,
	        'post_password' => uniqid( 'order_' ),
	    );
	
	    $order_id = wp_insert_post( $order_data, true );

	    if ( is_wp_error( $order_id ) ) {
	        add_action( 'admin_notices', array($this, 'clone__error'));
	    } else {
			$this->cloned_order_data($order_id, $originalorderid, $outstandingValue);		
		}
		
	}
	
	/**
	 * Create new WC_Order and clone all exisiting data
	 */
	
	public function cloned_order_data($order_id, $originalorderid = null, $outstandingValue){
		
		$order = new WC_Order($order_id);
		
		if ($originalorderid != null) {
			$this->original_order_id = $originalorderid;
		} else {
			$this->original_order_id = $_GET['order_id'];
		}
		
		$original_order = new WC_Order($this->original_order_id);
					
		// Check if Sequential Numbering is installed
		
		if ( class_exists( 'WC_Seq_Order_Number_Pro' ) ) {
			
			// Set sequential order number 
			
			$setnumber = new WC_Seq_Order_Number_Pro;
			$setnumber->set_sequential_order_number($order_id);
			
		}
		
		$this->clone_order_header($order_id, $outstandingValue);
		$this->clone_order_billing($order_id);
		$this->clone_order_shipping($order_id);
		
		$this->clone_order_shipping_items($order_id, $original_order);
		$this->clone_order_fees($order, $original_order, $outstandingValue);
		
		$this->clone_order_coupons($order, $original_order);
		
		// $this->clone_order_items($order, $original_order);
		
		update_post_meta( $order_id, '_payment_method', get_post_meta($this->original_order_id, '_payment_method', true) );
		update_post_meta( $order_id, '_payment_method_title', get_post_meta($this->original_order_id, '_payment_method_title', true) );
		
		// Reduce Order Stock
		
		// wc_reduce_stock_levels($order_id);
		
		// POSSIBLE CHANGE? - Set status to on hold as payment is not received
		
		$order->update_status('completed');
		
		// Set order note of original cloned order
		
		$order->add_order_note('Final payment for #'.$this->original_order_id.'');
		
		// Returns success message on clone completion
		
		add_action( 'admin_notices', array($this, 'clone__success'));
		
	}
	
	/**
	 * Duplicate Order Header meta
	 */
	
	public function clone_order_header($order_id, $outstandingValue){

        update_post_meta( $order_id, '_order_shipping', get_post_meta($this->original_order_id, '_order_shipping', true) );
        update_post_meta( $order_id, '_order_discount', get_post_meta($this->original_order_id, '_order_discount', true) );
        update_post_meta( $order_id, '_cart_discount', get_post_meta($this->original_order_id, '_cart_discount', true) );
        update_post_meta( $order_id, '_order_tax', get_post_meta($this->original_order_id, '_order_tax', true) );
        update_post_meta( $order_id, '_order_shipping_tax', get_post_meta($this->original_order_id, '_order_shipping_tax', true) );
        update_post_meta( $order_id, '_order_total', (string) $outstandingValue );

        update_post_meta( $order_id, '_order_key', 'wc_' . apply_filters('woocommerce_generate_order_key', uniqid('order_') ) );
        update_post_meta( $order_id, '_customer_user', get_post_meta($this->original_order_id, '_customer_user', true) );
        update_post_meta( $order_id, '_order_currency', get_post_meta($this->original_order_id, '_order_currency', true) );
        update_post_meta( $order_id, '_prices_include_tax', get_post_meta($this->original_order_id, '_prices_include_tax', true) );
        update_post_meta( $order_id, '_customer_ip_address', get_post_meta($this->original_order_id, '_customer_ip_address', true) );
        update_post_meta( $order_id, '_customer_user_agent', get_post_meta($this->original_order_id, '_customer_user_agent', true) );
		
	}
	
	/**
	 * Duplicate Order Billing meta
	 */
	
	public function clone_order_billing($order_id){

        update_post_meta( $order_id, '_billing_city', get_post_meta($this->original_order_id, '_billing_city', true));
        update_post_meta( $order_id, '_billing_state', get_post_meta($this->original_order_id, '_billing_state', true));
        update_post_meta( $order_id, '_billing_postcode', get_post_meta($this->original_order_id, '_billing_postcode', true));
        update_post_meta( $order_id, '_billing_email', get_post_meta($this->original_order_id, '_billing_email', true));
        update_post_meta( $order_id, '_billing_phone', get_post_meta($this->original_order_id, '_billing_phone', true));
        update_post_meta( $order_id, '_billing_address_1', get_post_meta($this->original_order_id, '_billing_address_1', true));
        update_post_meta( $order_id, '_billing_address_2', get_post_meta($this->original_order_id, '_billing_address_2', true));
        update_post_meta( $order_id, '_billing_country', get_post_meta($this->original_order_id, '_billing_country', true));
        update_post_meta( $order_id, '_billing_first_name', get_post_meta($this->original_order_id, '_billing_first_name', true));
        update_post_meta( $order_id, '_billing_last_name', get_post_meta($this->original_order_id, '_billing_last_name', true));
        update_post_meta( $order_id, '_billing_company', get_post_meta($this->original_order_id, '_billing_company', true));
		
		do_action('clone_extra_billing_fields_hook', $order_id, $this->original_order_id);
		
	}
	
	/**
	 * Duplicate Order Shipping meta
	 */
	
	public function clone_order_shipping($order_id){

        update_post_meta( $order_id, '_shipping_country', get_post_meta($this->original_order_id, '_shipping_country', true));
        update_post_meta( $order_id, '_shipping_first_name', get_post_meta($this->original_order_id, '_shipping_first_name', true));
        update_post_meta( $order_id, '_shipping_last_name', get_post_meta($this->original_order_id, '_shipping_last_name', true));
        update_post_meta( $order_id, '_shipping_company', get_post_meta($this->original_order_id, '_shipping_company', true));
        update_post_meta( $order_id, '_shipping_address_1', get_post_meta($this->original_order_id, '_shipping_address_1', true));
        update_post_meta( $order_id, '_shipping_address_2', get_post_meta($this->original_order_id, '_shipping_address_2', true));
        update_post_meta( $order_id, '_shipping_city', get_post_meta($this->original_order_id, '_shipping_city', true));
        update_post_meta( $order_id, '_shipping_state', get_post_meta($this->original_order_id, '_shipping_state', true));
        update_post_meta( $order_id, '_shipping_postcode', get_post_meta($this->original_order_id, '_shipping_postcode', true));
        
        do_action('clone_extra_shipping_fields_hook', $order_id, $this->original_order_id);
	
	}
	
	
	/**
	 * Duplicate Order Fees
	 */
	
	public function clone_order_fees($order, $original_order){

        $fee_items = $original_order->get_fees();
 
        if (empty($fee_items)) {
	        
        } else {
	        
	        foreach($fee_items as $fee_key => $fee_value){
		        
		        $fee_item  = new WC_Order_Item_Fee();

		        $fee_item->set_props( array(
					'name'        => $fee_item->get_name(),
					'tax_class'   => $fee_value['tax_class'],
					'tax_status'  => $fee_value['tax_status'],
					'total'       => $outstandingValue,
					'total_tax'   => $outstandingValue * 1.2,
					'taxes'       => $fee_value['taxes'],
				) );

		        $order->add_item( $fee_item );	 
		        
	        }
	        
        }
   
	}
	
	/**
	 * Duplicate Order Coupon
	 */
	
	public function clone_order_coupons($order, $original_order){

        $coupon_items = $original_order->get_used_coupons();

        if (empty($coupon_items)) {
	        
        } else {
	        
	        foreach($original_order->get_items( 'coupon' ) as $coupon_key => $coupon_values){
		        
		        $coupon_item  = new WC_Order_Item_Coupon();

		        $coupon_item->set_props( array(
					'name'  	   => $coupon_values['name'],
					'code'  	   => $coupon_values['code'],
					'discount'     => $coupon_values['discount'],
					'discount_tax' => $coupon_values['discount_tax'],
				) );

		        $order->add_item( $coupon_item );	 
		        
	        }
	        
        }
   
	}
	
	/**
	 * Clone Items - v 1.3
	 */
	
	// public function clone_order_items($order, $original_order){

	// 	 foreach($original_order->get_items() as $order_key => $values){
			
	// 		if ($values['variation_id'] != 0) {
	// 			$product = new WC_Product_Variation($values['variation_id']);

	// 		} else {
	// 			$product = new WC_Product($values['product_id']);	
	// 		}
			
	// 		$item                       = new WC_Order_Query();
	// 		$item->legacy_values        = $values;
	// 		$item->legacy_cart_item_key = $order_key;
			
	// 		$item->set_props( array(
	// 			'quantity'     => $values['quantity'],
	// 			'variation'    => $values['variation'],
	// 			'subtotal'     => $values['line_subtotal'],
	// 			'total'        => $values['line_total'],
	// 			'subtotal_tax' => $values['line_subtotal_tax'],
	// 			'total_tax'    => $values['line_tax'],
	// 			'taxes'        => $values['line_tax_data'],
	// 		) );
			
	// 		if ( $product ) {
	// 			$item->set_props( array(
	// 				'name'         => $product->get_name(),
	// 				'tax_class'    => $product->get_tax_class(),
	// 				'product_id'   => $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id(),
	// 				'variation_id' => $product->is_type( 'variation' ) ? $product->get_id() : 0,
	// 			) );
	// 		}

	// 		$item->set_backorder_meta();

	// 		$order->add_item( $item );	 
			 
	// 	 }
	// }
	
	/**
	 * Clone success
	 */
	
	function clone__success() {
	
		$class = 'notice notice-success is-dismissible';
		$message = __( 'Order Duplicated.', 'clone-notice' );
	
		printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $message ); 

	}
	
	/**
	 * Clone error
	 */
	
	function clone__error() {
		$class = 'notice notice-error';
		$message = __( 'Duplication Failed an error has occurred.', 'clone-notice' );
	
		printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $message ); 
	}
	
	
	/**
	 * Duplicate Shipping Item Meta
	 * v1.4 - Shipping is added with order items
	 */
	
	public function clone_order_shipping_items($order_id, $original_order){
	 
        $original_order_shipping_items = $original_order->get_items('shipping');

        foreach ( $original_order_shipping_items as $original_order_shipping_item ) {

            $item_id = wc_add_order_item( $order_id, array(
                'order_item_name'       => $original_order_shipping_item['name'],
                'order_item_type'       => 'shipping'
            ) );

            if ( $item_id ) {
                wc_add_order_item_meta( $item_id, 'method_id', $original_order_shipping_item['method_id'] );
                wc_add_order_item_meta( $item_id, 'cost', wc_format_decimal( $original_order_shipping_item['cost'] ) );
            }

        }
	}
}