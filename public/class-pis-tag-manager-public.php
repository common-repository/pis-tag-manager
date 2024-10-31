<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://pisoftek.com
 * @since      1.0.0
 *
 * @package    PIS_Tag_Manager
 * @subpackage PIS_Tag_Manager/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    PIS_Tag_Manager
 * @subpackage PIS_Tag_Manager/public
 * @author     PI Softek Limited
 */
class PIS_Tag_Manager_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       PIS_Tag_Manager
	 * @param      string    $version    1.0.0
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;
		add_action( 'woocommerce_after_single_product_summary' , [$this,'pis_product_details']);
		add_action( 'woocommerce_review_order_before_payment', [$this,'pis_basket_details'] );
		add_action( 'woocommerce_thankyou', [$this,'pis_sale'] );
		add_action( 'woocommerce_after_cart_totals', [$this,'pis_basket_details']);

	}



	public function get_option( $option, $default = false ) {
		global $wpdb;

		if ( is_scalar( $option ) ) {
			$option = trim( $option );
		}

		if ( empty( $option ) ) {
			return false;
		}

		/*
		 * Until a proper _deprecated_option() function can be introduced,
		 * redirect requests to deprecated keys to the new, correct ones.
		 */
		$deprecated_keys = array(
			'blacklist_keys'    => 'disallowed_keys',
			'comment_whitelist' => 'comment_previously_approved',
		);

		if ( isset( $deprecated_keys[ $option ] ) && ! wp_installing() ) {
			_deprecated_argument(
				__FUNCTION__,
				'5.5.0',
				sprintf(
					/* translators: 1: Deprecated option key, 2: New option key. */
					__( 'The "%1$s" option key has been renamed to "%2$s".' ),
					$option,
					$deprecated_keys[ $option ]
				)
			);
			return get_option( $deprecated_keys[ $option ], $default );
		}

		/**
		 * Filters the value of an existing option before it is retrieved.
		 *
		 * The dynamic portion of the hook name, `$option`, refers to the option name.
		 *
		 * Returning a value other than false from the filter will short-circuit retrieval
		 * and return that value instead.
		 *
		 * @since 1.5.0
		 * @since 4.4.0 The `$option` parameter was added.
		 * @since 4.9.0 The `$default` parameter was added.
		 *
		 * @param mixed  $pre_option The value to return instead of the option value. This differs
		 *                           from `$default`, which is used as the fallback value in the event
		 *                           the option doesn't exist elsewhere in get_option().
		 *                           Default false (to skip past the short-circuit).
		 * @param string $option     Option name.
		 * @param mixed  $default    The fallback value to return if the option does not exist.
		 *                           Default false.
		 */
		$pre = apply_filters( "pre_option_{$option}", false, $option, $default );

		/**
		 * Filters the value of all existing options before it is retrieved.
		 *
		 * Returning a truthy value from the filter will effectively short-circuit retrieval
		 * and return the passed value instead.
		 *
		 * @since 6.1.0
		 *
		 * @param mixed  $pre_option  The value to return instead of the option value. This differs
		 *                            from `$default`, which is used as the fallback value in the event
		 *                            the option doesn't exist elsewhere in get_option().
		 *                            Default false (to skip past the short-circuit).
		 * @param string $option      Name of the option.
		 * @param mixed  $default     The fallback value to return if the option does not exist.
		 *                            Default false.
		 */
		$pre = apply_filters( 'pre_option', $pre, $option, $default );

		if ( false !== $pre ) {
			return $pre;
		}

		if ( defined( 'WP_SETUP_CONFIG' ) ) {
			return false;
		}

		// Distinguish between `false` as a default, and not passing one.
		$passed_default = func_num_args() > 1;

		if ( ! wp_installing() ) {
			// Prevent non-existent options from triggering multiple queries.
			$notoptions = wp_cache_get( 'notoptions', 'options' );

			// Prevent non-existent `notoptions` key from triggering multiple key lookups.
			if ( ! is_array( $notoptions ) ) {
				$notoptions = array();
				wp_cache_set( 'notoptions', $notoptions, 'options' );
			}

			if ( isset( $notoptions[ $option ] ) ) {
				/**
				 * Filters the default value for an option.
				 *
				 * The dynamic portion of the hook name, `$option`, refers to the option name.
				 *
				 * @since 3.4.0
				 * @since 4.4.0 The `$option` parameter was added.
				 * @since 4.7.0 The `$passed_default` parameter was added to distinguish between a `false` value and the default parameter value.
				 *
				 * @param mixed  $default The default value to return if the option does not exist
				 *                        in the database.
				 * @param string $option  Option name.
				 * @param bool   $passed_default Was `get_option()` passed a default value?
				 */
				return apply_filters( "default_option_{$option}", $default, $option, $passed_default );
			}

			$alloptions = wp_load_alloptions();

			if ( isset( $alloptions[ $option ] ) ) {
				$value = $alloptions[ $option ];
			} else {
				$value = wp_cache_get( $option, 'options' );

				if ( false === $value ) {
					$row = $wpdb->get_row( $wpdb->prepare( "SELECT option_value FROM $wpdb->options WHERE option_name = %s LIMIT 1", $option ) );

					// Has to be get_row() instead of get_var() because of funkiness with 0, false, null values.
					if ( is_object( $row ) ) {
						$value = $row->option_value;
						wp_cache_add( $option, $value, 'options' );
					} else { // Option does not exist, so we must cache its non-existence.
						if ( ! is_array( $notoptions ) ) {
							$notoptions = array();
						}

						$notoptions[ $option ] = true;
						wp_cache_set( 'notoptions', $notoptions, 'options' );

						/** This filter is documented in wp-includes/option.php */
						return apply_filters( "default_option_{$option}", $default, $option, $passed_default );
					}
				}
			}
		} else {
			$suppress = $wpdb->suppress_errors();
			$row      = $wpdb->get_row( $wpdb->prepare( "SELECT option_value FROM $wpdb->options WHERE option_name = %s LIMIT 1", $option ) );
			$wpdb->suppress_errors( $suppress );

			if ( is_object( $row ) ) {
				$value = $row->option_value;
			} else {
				/** This filter is documented in wp-includes/option.php */
				return apply_filters( "default_option_{$option}", $default, $option, $passed_default );
			}
		}

		// If home is not set, use siteurl.
		if ( 'home' === $option && '' === $value ) {
			return get_option( 'siteurl' );
		}

		if ( in_array( $option, array( 'siteurl', 'home', 'category_base', 'tag_base' ), true ) ) {
			$value = untrailingslashit( $value );
		}

		/**
		 * Filters the value of an existing option.
		 *
		 * The dynamic portion of the hook name, `$option`, refers to the option name.
		 *
		 * @since 1.5.0 As 'option_' . $setting
		 * @since 3.0.0
		 * @since 4.4.0 The `$option` parameter was added.
		 *
		 * @param mixed  $value  Value of the option. If stored serialized, it will be
		 *                       unserialized prior to being returned.
		 * @param string $option Option name.
		 */
		return apply_filters( "option_{$option}", maybe_unserialize( $value ), $option );
	}


	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in PIS_Tag_Manager_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The PIS_Tag_Manager_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/pis-tag-manager-public.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in PIS_Tag_Manager_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The PIS_Tag_Manager_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/pis-tag-manager-public.js', array( 'jquery' ), $this->version, false);
		wp_register_script( 'pis_script', 'https://beta.ideasoftware.in/analytics.platform/sample_script/getTags.js?site_id='.get_option('pis_tag_manager_website_id'), null,null,true);

	  	wp_enqueue_script( 'pis_script');
	  	// after that set this filter
		
	  	add_filter( 'script_loader_tag', function ( $tag, $handle ) {
			if ( 'pis_script' !== $handle ) {
				return $tag;
			}
			return str_replace( ' id', ' async id', $tag ); // async the script
		}, 10, 2 );
	}

	public function pis_product_details() {
	  global $product;

	  // Check if $product is an instance of WC_Product
    if ( ! $product instanceof WC_Product ) {
        return;
    }

	  $product_arr=array();

	  $product_arr = [
        'id' => $product->get_id(),
        'sku' => $product->get_sku(),
        'name' => $product->get_name(),
        'price' => number_format( $product->get_price(), 2, '.', '' ),
    ];
	  
	  // Get brand
    $brand_term = get_term_by( 'name', 'brand', 'product_cat' );
    if ( $brand_term && ! is_wp_error( $brand_term ) ) {
        $brand = wp_get_post_terms( $product->get_id(), 'product_cat', [ 'parent' => $brand_term->term_id ] );
        $product_arr['brand'] = ! empty( $brand ) ? $brand[0]->name : '';
    }

	  
	  // Get categories
    $categories = wp_get_post_terms( $product->get_id(), 'product_cat' );
    $category_names = array_map( function( $category ) {
        return $category->name;
    }, $categories );
    $product_arr['category'] = ! empty( $category_names ) ? $category_names[0] : '';
    $product_arr['categories'] = $category_names;

     // Get product details
    $product_details = $product->get_data();
    $product_arr['detail'] = wp_strip_all_tags( $product_details['description'] );

	  // Get image URL
    $image_url = wp_get_attachment_image_src( get_post_thumbnail_id( $product->get_id() ), 'single-post-thumbnail' );
    $product_arr['imageURL'] = ! empty( $image_url ) ? $image_url[0] : '';
	  
	  // Get stock status and quantity
    $stock_status = $product->get_stock_status();
    $product_arr['stock'] = [
        'inStock' => $stock_status ? 'true' : 'false',
        'stockLevel' => $stock_status ? 'Many' : 'Single',
        'stockAmount' => $product->get_stock_quantity() ?: '',
    ];

    // Get promotion details
    $regular_price = (float) $product->get_regular_price();
    $sale_price = (float) $product->get_sale_price();
    $product_arr['promotion'] = [
        'isOnPromotion' => $regular_price > $sale_price && $sale_price ? 'true' : 'false',
        'promotionPrice' => number_format( $sale_price, 2, '.', '' ),
        'originalPrice' => number_format( $regular_price, 2, '.', '' ),
        'promotionName' => '',
    ];
	 

	   echo '
        <script>
            window._rl_product_view = ' . wp_json_encode($product_arr) . ';
            window.site_id = "' . get_option("pis_tag_manager_website_id") . '";
            window.platform = "WordPress";
            window.page = "Product View";
            window.ip_address = "' . $_SERVER['SERVER_ADDR'] . '";
        </script>
    ';
	  
	}


	public function pis_basket_details() {
	  $cart = WC()->cart;
	  $products=array();
	  if(!$cart->is_empty()){
	    // Loop over $cart items
	    $item_count=0;
	    foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
	      $product_arr=array();
	      $product = $cart_item['data'];
	      // Get Product ID
	      $product_arr['id']=$product->get_id();
	      $product_arr['sku']=$product->get_sku();
	      $product_arr['name']=$product->get_name();
	      $product_arr['price']=$product->get_price();
	      $product_arr['quantity']=$cart_item['quantity'];
	      
	      $terms = get_the_terms($product->get_id(), 'product_brand' );
	      foreach ( $terms as $term ){
	        if ( @$term->parent == 0 ) {
	            $brand_name=  @$term->slug;
	        }
	      }  
	      $product_arr['brand']=($brand_name)?$brand_name:'';

	      $terms =  get_the_terms ($product->get_id(), 'product_cat' );
	      $categories=array();
	      foreach($terms as $item){
	        $categories[]=$item->name;
	      }
	      $product_arr['category']=$categories[0];
	      $product_arr['categories']=$categories;

	      $product_details = $product->get_data();
	      $product_arr["detail"]=strip_tags($product_details['description']);
	      
	      $image_url=wp_get_attachment_image_src( get_post_thumbnail_id($product->get_id()), 'single-post-thumbnail' );
	      $product_arr["imageURL"]=$image_url[0];
	      
	      $product_arr["stock"]["inStock"]=$product->get_stock_status();
	      $product_arr["stock"]["stockLevel"]="Many";
	      $product_arr["stock"]["stockAmount"]=($product->get_stock_quantity())?$product->get_stock_quantity():'';
	      
	      $product_arr["promotion"]["isOnPromotion"]=($product->get_regular_price()>$product->get_sale_price() && (!empty($product->get_sale_price())))?'true':'false';
	      $product_arr["promotion"]["promotionPrice"]=number_format((float)$product->get_sale_price(), 2, '.', '');
	      $product_arr["promotion"]["originalPrice"]=number_format((float)$product->get_regular_price(), 2, '.', '');
	      $product_arr["promotion"]["promotionName"]='';
	      
	      $item_count+=$product_arr['quantity'];
	      $products['items'][]=$product_arr;
	    }
	    global  $woocommerce;
	    $products['currency']=get_woocommerce_currency_symbol();
	    $products['itemCount']=$item_count;
	    $products["taxRate"]=number_format((float)$cart->get_taxes_total(), 2, '.', '');
	    $products["shipping"]=($cart->get_shipping_total())?number_format((float)$cart->get_shipping_total(), 2, '.', ''):number_format(0, 2, '.', '');
	    $products["basePrice"]=($cart->subtotal_ex_tax)?number_format((float)$cart->subtotal_ex_tax, 2, '.', ''):0;
	    $products["voucherDiscount"]=($cart->get_discount_total())?number_format((float)$cart->get_discount_total(), 2, '.', ''):number_format(0, 2, '.', '');
	    $products["cartTotal"]=number_format((float)$cart->subtotal_ex_tax, 2, '.', '');
	    $products['voucherCode']=implode(',',$cart->get_coupons());
	    $products["priceWithTax"]=number_format((float)($products['cartTotal']+$cart->get_taxes_total()), 2, '.', '');
	    $products["shippingMethod"]='';

	  }
	  echo '
            <script>
                window._rl_basket = '.json_encode($products,JSON_UNESCAPED_SLASHES ).';
                window.site_id = "'.get_option("pis_tag_manager_website_id").'";
                  window.platform = "Wordpress";
                  window.page = "Cart View";
                  window.ip_address = "'.$_SERVER['SERVER_ADDR'].'";
            </script>
        ';
	  
	}


	public function pis_sale() {
		$order_id = get_query_var('order-received'); // Get the ID of the current order page
   		$order = wc_get_order($order_id);
   		
   		//$order_id  = $order->get_id(); // Get the order ID
   		$items = $order->get_items();
   		$products=array();
	    $item_count=0;
   		foreach ( $items as $item_id => $item ) {
   			$product_arr=array();
   			$product = new WC_Product($item->get_product_id());
		    $product_id = $item->get_product_id();
		    $product_name = $item->get_name();
		    $product_price = $item->get_total();
		    $product_quantity = $item->get_quantity();
		    // Use the retrieved product information for enhanced ecommerce tracking
		    $product_arr['id']=$item->get_product_id();
	      	$product_arr['sku']=$product->get_sku();
	      	$product_arr['name']=$item->get_name();
	      	$product_arr['price']=(float)number_format($product->get_price(),2);
	      	$product_arr['quantity']=$item->get_quantity();
	      	$terms = get_the_terms($item->get_product_id(), 'product_brand' );
	      	foreach ( $terms as $term ){
	        	if ( @$term->parent == 0 ) {
	            	$brand_name=  @$term->slug;
	        	}
	      	}  
	      	$product_arr['brand']=($brand_name)?$brand_name:'';

	      	$terms =  get_the_terms ($item->get_product_id(), 'product_cat' );
			$categories=array();
			foreach($terms as $item){
				$categories[]=$item->name;
			}
			$product_arr['category']=$categories[0];
			$product_arr['categories']=$categories;
			
			$image_url=wp_get_attachment_image_src( get_post_thumbnail_id($product->get_id()), 'single-post-thumbnail' );
	      	$product_arr["imageURL"]=$image_url[0];
	      
	      	$product_arr["stock"]["inStock"]=$product->get_stock_status();
	      	$product_arr["stock"]["stockLevel"]="Many";
	      	$product_arr["stock"]["stockAmount"]=($product->get_stock_quantity())?$product->get_stock_quantity():'';
	      
	      	$product_arr["promotion"]["isOnPromotion"]=($product->get_regular_price()>$product->get_sale_price() && (!empty($product->get_sale_price())))?'true':'false';
	      	$product_arr["promotion"]["promotionPrice"]=number_format((float)$product->get_sale_price(), 2, '.', '');
	      	$product_arr["promotion"]["originalPrice"]=number_format((float)$product->get_regular_price(), 2, '.', '');
	      	$product_arr["promotion"]["promotionName"]='';
	      
	      	$item_count+=$product_arr['quantity'];
	      	$products['items'][]=$product_arr;


		}

		global  $woocommerce;
	    $products['currency']=get_woocommerce_currency_symbol();
	    $products['orderID']=$order_id;
	    $products['itemCount']=$item_count;
	    $products["taxRate"]=number_format((float)$order->get_total_tax(), 2, '.', '');
	    $products["shipping"]=number_format((float)$order->get_shipping_total(), 2, '.', '');
	    $products["basePrice"]=($order->get_subtotal())?number_format((float)$order->get_subtotal(), 2, '.', ''):number_format((float)0, 2, ',', '.');
	    $products["voucherDiscount"]=number_format((float)$order->get_discount_total(), 2, '.', '');
	    $products["cartTotal"]=number_format(((float)$products["basePrice"]+(float)$products["basePrice"]+(float)$products["voucherDiscount"]), 2, '.', '');
	    $products['voucherCode']=implode(',',$order->get_coupons());
	    $products["priceWithTax"]=number_format(((float)$products['cartTotal']+(float)$order->get_total_tax()), 2, '.', '');
	    $products["shippingMethod"]='';

	    echo '
            <script>
                window._rl_sale = '.json_encode($products).';
                window.site_id = "'.get_option("pis_tag_manager_website_id").'";
                  window.platform = "Wordpress";
                  window.page = "Order Page";
                  window.ip_address = "'.$_SERVER['SERVER_ADDR'].'";
            </script>
        ';


	}





}
