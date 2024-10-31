<?php

/**
 * PIS Tag Manager
 *
 *
 * @link              https://pisoftek.com
 * @since             1.0.0
 * @package           PIS_Tag_Manager
 *
 * Plugin Name:       PIS Tag Manager
 * Plugin URI:        http://wordpress.org/plugins/pis-tag-manager/
 * Description:       This plugin is used to enhanced commerce activity by PI Softek Limited
 * Version:           1.0.2
 * Author:            PI Softek Limited
 * Author URI:        https://pisoftek.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       pis-tag-manager
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'PIS_TAG_MANAGER_VERSION', '1.0.2' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-settings-page-activator.php
 */
function activate_settings_page() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-pis-tag-manager-activator.php';
	PIS_Tag_Manager_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-settings-page-deactivator.php
 */
function deactivate_settings_page() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-pis-tag-manager-deactivator.php';
	PIS_Tag_Manager_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_settings_page' );
register_deactivation_hook( __FILE__, 'deactivate_settings_page' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-pis-tag-manager.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_pis_tag_manager() {

	$plugin = new PIS_Tag_Manager();
	$plugin->run();

}
run_pis_tag_manager();

// Add hooks
add_action( 'init', 'pis_product_feed_init' );

// Define product feed data
function pis_product_feed_get_data( $product ) {
    $terms = get_the_terms($product->get_id(), 'product_brand' );
    foreach ( $terms as $term ){
        if ( @$term->parent == 0 ) {
            $brand_name=  @$term->slug;
        }
    }

    $terms =  get_the_terms ($product->get_id(), 'product_cat' );
    $categories=array();
    foreach($terms as $item){
        $categories[]=$item->name;
    }

    $data = array(
        'id'        => $product->get_id(),
        'sku'        => $product->get_sku(),
        'name'        => $product->get_name(),
        'price'       => $product->get_price(),
        'brand'       => $brand_name,
        'category'    => $categories[0],
        'categories'  => $categories,
        'detail'      => $product->get_description(),
        'imageURL'   => wp_get_attachment_url( $product->get_image_id() ),

    );
    $data["stock"]["inStock"]=($product->get_stock_status())?'true':'false';
    $data["stock"]["stockLevel"]=($product->get_stock_status())?'Many':'Single';
    $data["stock"]["stockAmount"]=$product->get_stock_quantity();
    $data["promotion"]["isOnPromotion"]=($product->get_regular_price()>$product->get_sale_price() && (!empty($product->get_sale_price())))?'true':'false';
    $data["promotion"]["promotionPrice"]=number_format((float)$product->get_sale_price(), 2, '.', '');
    $data["promotion"]["originalPrice"]=number_format((float)$product->get_regular_price(), 2, '.', '');
    $data["promotion"]["promotionName"]='';

    return $data;
}

// Generate product feed
function pis_product_feed_generate() {

    // Get current page number
    $page = max(1, get_query_var('paged'));

    // Set number of products per page
    $products_per_page = 10;

    // Calculate offset
    $offset = ( $page - 1 ) * $products_per_page;
    
    // Create XML document
    $xml = new SimpleXMLElement('<products/>');

    // Get total number of products
    $total_products = count( get_posts( array(
        'post_type'   => 'product',
        'numberposts' => -1,
    ) ) );

     // Calculate total number of pages
    $total_pages = ceil( $total_products / $products_per_page );

    // Iterate through products for current page
    $products = get_posts( array(
        'post_type'   => 'product',
        'numberposts' => $products_per_page,
        'offset'      => $offset,
    ) );

    foreach ( $products as $product ) {
        $product = wc_get_product( $product->ID );
        $data    = pis_product_feed_get_data( $product );

        // Add product node to XML document
        $product_node = $xml->addChild('product');
        $product_node->addChild('id', $data['id']);
        $product_node->addChild('sku', $data['sku']);
        $product_node->addChild('name', $data['name']);
        $product_node->addChild('price', $data['price']);
        $product_node->addChild('brand', $data['brand']);
        $product_node->addChild('category', $data['category']);
        $categories_node=$product_node->addChild('categories');
        foreach($data['categories'] as $categories){
          
          $categories_node->addChild('category',$categories); 
        }
        $product_node->addChild('detail', $data['detail']);
        $product_node->addChild('imageURL', $data['imageURL']);
        
        $stock_node=$product_node->addChild('stock');
        $stock_node->addChild('inStock',$data['stock']['inStock']);
        $stock_node->addChild('stockLevel',$data['stock']['stockLevel']);
        $stock_node->addChild('stockAmount',$data['stock']['stockAmount']);

        $promotion_node=$product_node->addChild('promotion');
        $promotion_node->addChild('isOnPromotion',$data['promotion']['isOnPromotion']);
        $promotion_node->addChild('promotionPrice',$data['promotion']['promotionPrice']);
        $promotion_node->addChild('promotionName',$data['promotion']['promotionName']);

        
    }

    // Output XML
    header('Content-Type: text/xml');
    echo $xml->asXML();
    exit();
}

// Generate product feed on init
function pis_product_feed_init() {

    add_feed('pis-product-feed', 'pis_product_feed_generate');
    //add_rewrite_rule('^pis-product-feed/?', 'index.php?feed=custom-product-feed', 'top');
    add_rewrite_rule('^pis-product-feed/page/([^/]+)/?$', 'index.php?feed=pis-product-feed&paged=$matches[1]', 'top'); 
}


// Add rewrite rule for custom product feed URL
function pis_product_feed_rewrite_rule() {
    //add_rewrite_rule('^pis-product-feed/?', 'index.php?feed=pis-product-feed', 'top');
    add_rewrite_rule('^pis-product-feed/page/([^/]+)/?$', 'index.php?feed=pis-product-feed&paged=$matches[1]', 'top'); 
    
}
add_action('init', 'pis_product_feed_rewrite_rule');
