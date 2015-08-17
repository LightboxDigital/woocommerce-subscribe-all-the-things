<?php
/**
 * Cart functionality for converting cart items to subscriptions.
 *
 * @class 	WCCSubs_Cart
 * @version 1.0.0
 */

class WCCSubs_Cart {

	public static function init() {

		// Allow subs to recognize a cart item of any product type as a subscription
		add_filter( 'woocommerce_is_subscription', __CLASS__ . '::is_converted_to_sub', 10, 3 );

		// Add convert-to-sub configuration data to cart items that can be converted
		add_filter( 'woocommerce_add_cart_item', __CLASS__ . '::add_cart_item_convert_to_sub_data', 10, 2 );

		// Load convert-to-sub cart item session data
		add_filter( 'woocommerce_get_cart_item_from_session', __CLASS__ . '::load_convert_to_sub_session_data', 10, 2 );

		// Remove the subs price string suffix from cart items that can be converted
		add_filter( 'woocommerce_subscriptions_product_price_string_inclusions', __CLASS__ . '::convertible_sub_price_string', 10, 2 );

		// Save the convert to sub radio button setting when clicking the 'update cart' button
		add_filter( 'woocommerce_update_cart_action_cart_updated', __CLASS__ . '::update_convert_to_sub_options', 10 );
	}

	/**
	 * Updates the convert-to-sub status of a cart item based on the cart item option.
	 *
	 * @param  boolean $updated
	 * @return boolean
	 */
	public static function update_convert_to_sub_options( $updated ) {

		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {

			if ( ! empty( $cart_item[ 'wccsub_data' ] ) && isset( $_POST[ 'cart' ][ $cart_item_key ][ 'convert_to_sub' ] ) ) {

				WC()->cart->cart_contents[ $cart_item_key ][ 'wccsub_data' ][ 'active_subscription_scheme_id' ] = $_POST[ 'cart' ][ $cart_item_key ][ 'convert_to_sub' ];

				$updated = true;
			}
		}

		return $updated;
	}

	/**
	 * Removes the subs price string suffix from cart items that can be converted to subs.
	 * Not needed, since subscription parameters are displayed in the 'convert_to_sub' radio option.
	 *
	 * @param  array      $inclusions
	 * @param  WC_Product $product
	 * @return array
	 */
	public static function convertible_sub_price_string( $inclusions, $product ) {

		if ( isset( $product->is_converted_to_sub ) ) {

			if ( isset( $product->delete_subscription_price_suffix ) && $product->delete_subscription_price_suffix === 'yes' ) {

				$inclusions[ 'subscription_period' ] = false;
				$inclusions[ 'subscription_length' ] = false;
				$inclusions[ 'sign_up_fee' ]         = false;
				$inclusions[ 'trial_length' ]        = false;
			}
		}

		return $inclusions;
	}

	/**
	 * Add convert-to-sub subscription data to cart items that can be converted.
	 *
	 * @param array $cart_item
	 * @param int   $product_id
	 */
	public static function add_cart_item_convert_to_sub_data( $cart_item, $product_id ) {

		if ( self::is_convertible_to_sub( $cart_item ) ) {

			$cart_item[ 'wccsub_data' ] = array(
				'active_subscription_scheme_id' => WCCSubs_Schemes::get_default_subscription_scheme_id( $cart_item ),
			);
		}

		return $cart_item;
	}

	/**
	 * Load stored convert-to-sub session data.
	 * Cart items are converted to subscriptions here, then Subs code does all the magic.
	 *
	 * @param  array  $cart_item
	 * @param  array  $item_session_values
	 * @return array
	 */
	public static function load_convert_to_sub_session_data( $cart_item, $item_session_values ) {

		if ( isset( $item_session_values[ 'wccsub_data' ] ) ) {

			$cart_item[ 'wccsub_data' ] = $item_session_values[ 'wccsub_data' ];

			if ( $active_subscription_scheme = WCCSubs_Schemes::get_active_subscription_scheme( $cart_item ) ) {

				$cart_item[ 'data' ]->is_converted_to_sub = $cart_item[ 'data' ]->delete_subscription_price_suffix = 'yes';

				$cart_item[ 'data' ]->subscription_period          = $active_subscription_scheme[ 'subscription_period' ];
				$cart_item[ 'data' ]->subscription_period_interval = $active_subscription_scheme[ 'subscription_period_interval' ];
				$cart_item[ 'data' ]->subscription_length          = $active_subscription_scheme[ 'subscription_length' ];

			} else {

				$cart_item[ 'data' ]->is_converted_to_sub = $cart_item[ 'data' ]->delete_subscription_price_suffix = 'no';
			}
		}

		return $cart_item;
	}

	/**
	 * Hooks onto 'woocommerce_is_subscription' to trick Subs into thinking it is dealing with a subscription.
	 * The necessary subscription properties are added to the product in 'load_convert_to_sub_session_data()'.
	 *
	 * @param  boolean    $is
	 * @param  int        $product_id
	 * @param  WC_Product $product
	 * @return boolean
	 */
	public static function is_converted_to_sub( $is, $product_id, $product ) {

		if ( ! $product ) {
			return $is;
		}

		if ( isset( $product->is_converted_to_sub ) && $product->is_converted_to_sub === 'yes' ) {
			$is = true;
		}

		return $is;
	}

	/**
	 * True if a cart item can be converted from a one-shot purchase to a subscription and vice-versa.
	 * Subscription product types can't be converted to non-sub items.
	 *
	 * @param  array  $cart_item
	 * @return boolean
	 */
	public static function is_convertible_to_sub( $cart_item ) {

		$product_id     = $cart_item[ 'product_id' ];
		$is_convertible = true;

		if ( WC_Subscriptions_Product::is_subscription( $product_id ) ) {
			$is_convertible = false;
		}

		return $is_convertible;
	}
}

WCCSubs_Cart::init();
