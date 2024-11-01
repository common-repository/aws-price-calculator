<?php
/**
 * @package AWS Price Calculator
 * @author Altos Web Solutions Italia
 * @copyright (C) Altos Web Solutions Italia
 * @license GNU/GPL v2 http://www.gnu.org/licenses/gpl-2.0.html
 **/

require_once('admin/resources/lib/eos/Stack.php');
require_once('admin/resources/lib/eos/Parser.php');

/*WPC-PRO*/
require_once('admin/resources/lib/PHPExcel/Classes/PHPExcel.php');
/*/WPC-PRO*/

require_once('admin/awsframework/Helper/FrameworkHelper.php');

class AWSPriceCalculator {

    protected static $_instance = null;

    var $plugin_label           = "Excel Worksheet Price Calculation";
    var $plugin_code            = "excel-worksheet-price-calculation";
    var $plugin_dir             = "excel-worksheet-price-calculation";
    var $plugin_db_version      = null;

    var $view = array();

    var $wsf = null;
    var $db;

    var $fieldHelper;
    var $calculatorHelper;

    var $fieldModel;

    /**
     * Main WPC instance
     */
    public static function instance($v) {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self($v);
		}
		return self::$_instance;
	}

    public function __construct($plugin_db_version){

        global $wpdb;

        $this->wpdb                 = $wpdb;
        $this->plugin_db_version    = $plugin_db_version;

        add_action( 'save_post', array($this, 'save_post'));

        add_action('wp_enqueue_scripts', array($this, 'wp_enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));

        add_action('admin_menu', array( $this, 'register_submenu_page'),99);

        add_action('woocommerce_before_add_to_cart_button', array($this, 'product_meta_end'));

        add_filter('woocommerce_cart_item_price', array($this, 'cartItemPrice'), 1, 3);
        add_filter('woocommerce_cart_item_price_html', array($this, 'woocommerce_cart_item_price_html'), 1, 3);
        add_filter('woocommerce_cart_product_subtotal', array($this, 'woocommerce_cart_product_subtotal'), 10, 4 );

        add_action('woocommerce_before_calculate_totals', array($this, 'woocommerce_before_calculate_totals'), 10, 1);
        add_action('woocommerce_add_to_cart', array($this, 'add_to_cart_callback'), 10, 6);
        add_action('woocommerce_add_cart_item_data', array($this, 'woocommerce_add_cart_item_data'), 10, 3);

        add_action('woocommerce_cart_item_removed', array($this, 'action_woocommerce_cart_item_removed'), 10, 2 );

        add_action('woocommerce_add_order_item_meta', array($this, 'action_woocommerce_add_order_item_meta'), 1, 3 );
        add_action('woocommerce_checkout_update_order_meta', array($this, 'action_woocommerce_checkout_update_order_meta'), 10, 2);
        add_action('woocommerce_checkout_order_processed', array($this, 'action_woocommerce_checkout_order_processed'), 10, 1 );

        add_action( 'add_meta_boxes', array($this, 'order_add_meta_boxes'));

        add_action('wp_ajax_awspricecalculator_ajax_callback', array($this, 'ajax_callback'));
        add_action('wp_ajax_nopriv_awspricecalculator_ajax_callback', array($this, 'ajax_callback'));

        /* Setting a very low priority, because for a tip I need to add to cart the product */
        add_filter('woocommerce_add_to_cart_validation', array($this, 'filter_woocommerce_add_to_cart_validation'), 100, 3);
        add_filter('woocommerce_add_to_cart_redirect', array($this, 'filter_woocommerce_add_to_cart_redirect'));
        add_filter('woocommerce_get_price_html', array($this, 'filter_woocommerce_get_price_html'), 10, 2);
        add_filter('woocommerce_cart_item_name', array($this, 'filter_woocommerce_cart_item_name'), 20, 3);


        /*WPC-PRO*/
        add_filter('woocommerce_checkout_cart_item_quantity', array($this, 'woocommerce_checkout_cart_item_quantity'), 10, 2);
        add_filter('woocommerce_order_item_quantity_html', array($this, 'woocommerce_order_item_quantity_html'), 10, 2);

        add_filter('woocommerce_quantity_input_args', array($this, 'filter_woocommerce_quantity_input_args'), 10, 2);
        add_filter('woocommerce_cart_item_quantity', array($this, 'filter_woocommerce_cart_item_quantity'), 10, 3);
        add_filter('woocommerce_order_item_quantity', array($this, 'filter_woocommerce_order_item_quantity'), 10, 3);
        add_filter('woocommerce_order_item_quantity_html', array($this, 'filter_woocommerce_order_item_quantity_html'), 10, 2);

        /* FedEx WooCommerce Extension Compatibility */
        add_filter('wf_fedex_packages', array($this, 'wf_fedex_packages'), 10);

        /*/WPC-PRO*/



        add_filter( 'woocommerce_loop_add_to_cart_link', array($this, 'woocommerce_loop_add_to_cart_link'), 10, 2 );

        add_action('plugins_loaded', array($this, 'action_plugins_loaded'));

        add_action('init', array($this, 'wp_init'), 1);


        /* Assign calculators from product admin page panel, custom tabs, ajax calls for remove and assign calculator */
        add_filter( 'woocommerce_product_data_tabs', array($this,'wpc_custom_product_tabs'));
        add_action( 'woocommerce_product_data_panels', array($this,'wpc_product_data_panel'));
        add_action('wp_ajax_awspricecalculator_ajax_attach_calculator', array($this,'ajax_attach_calculator'));
        add_action('wp_ajax_awspricecalculator_ajax_remove_calculator', array($this,'ajax_remove_calculator'));

        add_filter('site_transient_update_plugins', array($this, 'site_transient_update_plugins'));

        //NOT WORKING FOR NOW: add_filter('woocommerce_cart_shipping_packages', array($this, 'woocommerce_cart_shipping_packages'));

        // add_filter('admin_footer_text', array($this, 'filter_admin_footer_text'));

        $this->wsf               = new WSF\Helper\FrameworkHelper($this->plugin_dir, plugin_dir_path( __DIR__ ), "wordpress");

        $this->wsf->setVersion($plugin_db_version);

        $this->databaseHelper    = $this->wsf->get('\\WSF\\Helper', true, 'awsframework/Helper', 'DatabaseHelper', array($this->wsf));

        $this->calculatorHelper = $this->wsf->get('\\AWSPriceCalculator\\Helper', true, 'awspricecalculator/Helper', 'CalculatorHelper', array($this->wsf));
        $this->fieldHelper      = $this->wsf->get('\\AWSPriceCalculator\\Helper', true, 'awspricecalculator/Helper', 'FieldHelper', array($this->wsf));
        $this->themeHelper      = $this->wsf->get('\\AWSPriceCalculator\\Helper', true, 'awspricecalculator/Helper', 'ThemeHelper', array($this->wsf));
        $this->cartHelper       = $this->wsf->get('\\AWSPriceCalculator\\Helper', true, 'awspricecalculator/Helper', 'CartHelper', array($this->wsf));
        $this->orderHelper      = $this->wsf->get('\\AWSPriceCalculator\\Helper', true, 'awspricecalculator/Helper', 'OrderHelper', array($this->wsf));
        $this->pluginHelper     = $this->wsf->get('\\AWSPriceCalculator\\Helper', true, 'awspricecalculator/Helper', 'PluginHelper', array($this->wsf));
        $this->productHelper    = $this->wsf->get('\\AWSPriceCalculator\\Helper', true, 'awspricecalculator/Helper', 'ProductHelper', array($this->wsf));
        $this->ecommerceHelper  = $this->wsf->get('\\WSF\\Helper', true, 'awsframework/Helper', 'EcommerceHelper', array($this->wsf));

        $this->fieldModel       = $this->wsf->get('\\AWSPriceCalculator\\Model', true, 'awspricecalculator/Model', 'FieldModel', array($this->wsf));
        $this->calculatorModel  = $this->wsf->get('\\AWSPriceCalculator\\Model', true, 'awspricecalculator/Model', 'CalculatorModel', array($this->wsf));
        $this->settingsModel    = $this->wsf->get('\\AWSPriceCalculator\\Model', true, 'awspricecalculator/Model', 'SettingsModel', array($this->wsf));

        /* Best would be leave it as last thing to do to create objects first */
        $this->pluginHelper->pluginUpgrade($this->plugin_db_version);

        /* The possibility to execute actions before any prinnt setting "raw=1" */
        if($this->wsf->requestValue("page") == "excel-worksheet-price-calculation" && $this->wsf->requestValue("raw") == true){
            $this->wsf->execute("awspricecalculator", true, '\\AWSPriceCalculator\\Controller');
        }
    }

    /**
     * Not used at the moment
     *
     * @param string $html, Order HTML Item Quantity
     * @param array $item, The Item Order
     * @return string
     */
    function filter_woocommerce_order_item_quantity_html($html, $item){
        return $html;
    }

    /**
     * The quantity of order (It is used for example for inventory management)
     *
     * @param int $quantity, Order Quantity
     * @param array $order, The Order
     * @param array $item, The Order Item
     * @return string
     */
    function filter_woocommerce_order_item_quantity($quantity, $order, $item){
        return $quantity;
    }

    /**
     * Change the cart item quantity for WPC
     *
     * @param int $product_quantity, Order Quantity
     * @param string $cartItemKey, The key for the cart item
     * @param array $cartItem, The cart item
     * @return int
     */
    function filter_woocommerce_cart_item_quantity($product_quantity, $cartItemKey, $cartItem){
        global $woocommerce;

        if(!empty($cartItem['simulator_id'])){
            $productId      = $cartItem['product_id'];
            $product        = new WC_Product($productId);
            $calculator     = $this->calculatorHelper->get_simulator_for_product($productId);

            if(!empty($calculator)){
                if(!empty($calculator->overwrite_quantity)){
                    $data    = $cartItem['simulator_fields_data'];
                    return $cartItem['quantity'];
                }
            }
        }

        return $product_quantity;
    }

    /**
     * Change Arguments for the Quantity Field
     *
     * @param array $args
     * @param WC_Product The product
     * @return array
     */
    function filter_woocommerce_quantity_input_args($args, $product){

        $productId                      = $product->get_id();
        $calculator                     = $this->calculatorHelper->get_simulator_for_product($productId);

        if(!empty($calculator)){

            if(!empty($calculator->overwrite_quantity)){
                /* Hide the quantity field */
                $args['max_value'] = 1;
                $args['min_value'] = 1;
            }

        }

        return $args;
    }
    
    /**
     * Start the Session on WP Init
     *
     * @return void
     */
    public function wp_init() {
        //changed to cookies
        // if(!session_id()){
        //     session_start();
        // }
    }

    /**
     * FedEx WooCommerce Extension Compatibility
     *
     * @param array $ships Fedex Packages Data
     * @return array
     */
    function wf_fedex_packages($ships){

        $shipsClone     = $ships;

        foreach (WC()->cart->get_cart() as $cart_item_key => $values){
            if(isset($values['simulator_id'])){
                $productId                      = $values['product_id'];
                $quantity                       = $values['quantity'];

                $calculator                     = $this->calculatorHelper->get_simulator_for_product($productId);

                if(!empty($calculator)){
                    $calculatorFieldsData    = $values['simulator_fields_data'];
                    $this->calculatorHelper->calculate_price($productId, $calculatorFieldsData, false, $calculator->id, $outputResults);

                    foreach($shipsClone as $shipIndex => $shipData){
                        if(
                            $shipData['packed_products'][0]->id == $productId &&
                            $shipData['GroupPackageCount'] == $quantity
                        ){

                            /* The overwrite weight field has been set */
                            if(!empty($calculator->overwrite_weight)){
                                $weight = $outputResults[$calculator->overwrite_weight];
                                $ships[$shipIndex]['Weight']['Value']       = $outputResults[$calculator->overwrite_weight];
                            }

                            /* The overwrite length field has been set */
                            if(!empty($calculator->overwrite_length)){
                                $ships[$shipIndex]['Dimensions']['Length']  = $outputResults[$calculator->overwrite_length];
                            }

                            /* The overwrite width field has been set */
                            if(!empty($calculator->overwrite_width)){
                                $ships[$shipIndex]['Dimensions']['Width']   = $outputResults[$calculator->overwrite_width];
                            }

                            /* The overwrite height field has been set */
                            if(!empty($calculator->overwrite_height)){
                                $ships[$shipIndex]['Dimensions']['Height']  = $outputResults[$calculator->overwrite_height];
                            }

                            unset($shipsClone[$shipIndex]);
                            break;

                        }
                    }
                }


            }
        }

        return $ships;
    }

    /**
     * Performed while saving a post
     *
     * @param string $postId, identification number of a given post
     * @return void
     */
    function save_post($postId) {
        $post       = get_post($postId);

        if($post->post_type == "product"){
            /* Check duplicate Calculators, view error */
        }

    }

    /**
     * Change the display of the Add to cart button in the archive
     *
     * @param string $link
     * @param object $product
     * @return string
     */
    function woocommerce_loop_add_to_cart_link($link, $product){
        $calculator  = $this->calculatorHelper->get_simulator_for_product($product->get_id());

        if(!empty($calculator)){
            $link = sprintf( '<a href="%s" rel="nofollow" data-product_id="%s" data-product_sku="%s" data-quantity="%s" class="button product_type_%s">%s</a>',
                esc_url(get_permalink($product->get_id())),
                esc_attr($product->get_id()),
                esc_attr($product->get_sku()),
                esc_attr(isset( $quantity ) ? $quantity : 1),
                esc_attr($product->get_type()),
                esc_html($this->wsf->mixTrans('ecommerce.shop.choose_an_option'))
            );
        }

        return $link;
    }
    
    /**
     * Loading styles and scripts only on the plugin pages.
     *
     * The suffix hook to look for would be "woocommerce_page_woo-price-calculator"
     * but in some systems with other languages (ex: Jew), the first part (woocommerce) could be different.
     * Loading Wordpress Media Library files .
     *
     * @param string $hookSuffix
     * @return void
     */
    function admin_enqueue_scripts($hookSuffix){
        $this->pluginHelper->adminEnqueueScripts($this->plugin_code, $hookSuffix);
    }
    
    /**
     * Loading styles and scripts.
     *
     * @return void
     */
    function wp_enqueue_scripts(){
        $this->pluginHelper->frontEnqueueScripts($this->plugin_code, get_the_ID());
    }

    /**
     * Change the price on the product page and on the shop pages
     * 
     * See the price at the beginning, and take the default values to calculate the starting price
     *
     * @param string $price, price to change
     * @param object $product, instance of the given product
     * @return string
     */
    function filter_woocommerce_get_price_html($productPrice, $product){

        if($product->post_type == "product"){
            $productId	= $product->get_id();
        }else if($product->post_type == "product_variation"){
            $productId	= $product->get_parent_id();
        }else{
            $productId	= null;
        }

        if(!empty($productId)){
            $simulator  = $this->calculatorHelper->get_simulator_for_product($productId);

            if(!empty($simulator)){
                /* 
                 * I avoid displaying the price in the backend, if there are so many products
                 * the program takes a long time to view the page
                 * But in any case I will display the plugin in case of POST request
                 * because there could be plugins that require the price, for example
                 * YITH WooCommerce Quick View
                 */
                if(!is_admin() || $this->wsf->isPost() == true){
                    try{

                        $outputResults      = null;
                        $conditionalLogic   = null;
                        $errors             = null;
                        $fieldValues        = $this->calculatorHelper->getProductPageUserData($simulator);
                        $checkErrors        = $this->calculatorHelper->hasToCheckErrors($simulator);
                        $page               = $this->ecommerceHelper->getPageType();
                        
                        $price		= $this->calculatorHelper->calculate_price(
                            $productId,
                            $fieldValues,
                            true,
                            $simulator->id,
                            $outputResults,
                            $conditionalLogic,
                            $checkErrors,
                            $errors,
                            $priceRaw, //Not used here
                            $page,
                            0,
                            true                //Add taxes on start
                        );
                                               
                        $price                = apply_filters('awspc_filter_wc_get_price_html', $price, array(
                            'productId'         => $productId,
                            'userData'          => $fieldValues,
                            'calculator'        => $simulator,
                            'outputResults'     => $outputResults,
                            'conditionalLogic'  => $conditionalLogic,
                            'checkErrors'       => $checkErrors,
                            'errors'            => $errors,
                            'productPrice'      => $productPrice,
                            'priceRaw'          => $priceRaw,
                            'page'              => $page,
                        ));

                        //$price              = null;
                    }catch (\Exception $ex) {
                        $price              = "Error: {$ex->getMessage()}";
                    }

                    $price      = $this->calculatorHelper->getPriceWithPrefixAndSuffix($price);
                    
                    return "<span class=\"woocommerce-Price-amount amount\">{$price}</span>";
                }else{
                    return "Calculator Price";
                }
            }
        }

        return $productPrice;
    }

    /**
     * Changes the product name on the cart page
     *
     * @param string $productTitle, string to change
     * @param array $cartItem, item present on the cart page
     * @return string
     */
    function filter_woocommerce_cart_item_name($productTitle, $cartItem, $cartItemKey){

        /*WPC-PRO*/
        if(is_cart() && $this->wsf->getLicense() == true){
            if(isset($cartItem['simulator_id'])){
                $simulatorId                = $cartItem['simulator_id'];

                if(!empty($simulatorId)){
                    $calculator             = $this->calculatorModel->get($simulatorId);
                    $simulatorFieldsIds     = $this->calculatorHelper->get_simulator_fields($simulatorId);
                    $outputFieldsIds        = $this->calculatorHelper->get_simulator_fields($simulatorId, true);

                    $simulatorFields        = $this->fieldHelper->get_fields_by_ids($simulatorFieldsIds);
                    $outputFields           = $this->fieldHelper->get_fields_by_ids($outputFieldsIds);

                    $simulatorFieldsData    = $cartItem['simulator_fields_data'];
                    $productId              = $cartItem['product_id'];

                    $this->calculatorHelper->calculate_price($productId, $simulatorFieldsData, false, $simulatorId, $outputResults, $conditionalLogic);


                    $title = array();

                    /* Input Fields */
                    foreach($simulatorFields as $simulatorKey => $simulatorField){
                        if($conditionalLogic[$simulatorField->id] == true){
                            $fieldId                    = $this->fieldHelper->getFieldName($simulatorField->id);
                            $value                      = (isset($simulatorFieldsData[$fieldId]))?$simulatorFieldsData[$fieldId]:null;
                            $fieldLabel                 = $this->wsf->userTrans($this->fieldHelper->getShortLabel($simulatorField));

                            $htmlElement                = $this->orderHelper->getReviewElement($simulatorField, $value);

                            /* OLD:
                             * $title[] = "<span style=\"white-space: nowrap\">&emsp;&emsp;<b>{$fieldLabel}:</b> {$htmlElement}</span>";
                             */

                            /* Should the element be displayed? */
                            if($this->calculatorHelper->isFieldVisibleOnCart($calculator, $simulatorField, $value) == true){
                                $title[$fieldId]       = array(
                                    'fieldId'   => $fieldId,
                                    'label'     => $fieldLabel,
                                    'html'      => $htmlElement,
                                    'field'     => $simulatorField,

                                );
                            }

                        }
                    }

                    /* Output Fields */
                    foreach($outputFields as $simulatorKey => $simulatorField){

                        if($conditionalLogic[$simulatorField->id] == true) {

                            $fieldId = $this->fieldHelper->getFieldName($simulatorField->id);
                            $value = (isset($outputResults[$simulatorField->id])) ? $outputResults[$simulatorField->id] : null;
                            $fieldLabel = $this->wsf->userTrans($this->fieldHelper->getShortLabel($simulatorField));

                            $htmlElement = $this->fieldHelper->getOutputResult($simulatorField, $value);

                            /* Should the element be displayed? */
                            if ($this->calculatorHelper->isFieldVisibleOnCart($calculator, $simulatorField, $value) == true) {
                                $title[$fieldId] = array(
                                    'fieldId' => $fieldId,
                                    'label' => $fieldLabel,
                                    'html' => $htmlElement,
                                    'field' => $simulatorField,

                                );
                            }
                        }
                    }

                    return $this->wsf->getView('awspricecalculator', 'cart/item.php', true, array(
                        'productTitle'      => $productTitle,
                        'productItems'      => $title,
                    ));

                }
            }
        }
        /*/WPC-PRO*/

        return $productTitle;
    }

    /**
     * Performed in review-order.php to review the order
     *
     * @param string $productTitle, title of the product
     * @param array $cartItem, item present in the cart page
     * @return string
     */
    /*WPC-PRO*/
    function woocommerce_checkout_cart_item_quantity($productTitle, $cartItem){

        if(isset($cartItem['simulator_id'])){
            $simulatorId            = $cartItem['simulator_id'];

            if(!empty($simulatorId)){
                $calculator             = $this->calculatorModel->get($simulatorId);

                $simulatorFieldsIds     = $this->calculatorHelper->get_simulator_fields($simulatorId);
                $outputFieldsIds        = $this->calculatorHelper->get_simulator_fields($simulatorId, true);

                $simulatorFields        = $this->fieldHelper->get_fields_by_ids($simulatorFieldsIds);
                $outputFields           = $this->fieldHelper->get_fields_by_ids($outputFieldsIds);

                $simulatorFieldsData    = $cartItem['simulator_fields_data'];
                $productId              = $cartItem['product_id'];
                $title                  = array();

                $this->calculatorHelper->calculate_price($productId, $simulatorFieldsData, false, $simulatorId, $outputResults, $conditionalLogic);

                /* Input Fields */
                foreach($simulatorFields as $simulatorKey => $simulatorField){
                    if($conditionalLogic[$simulatorField->id] == true){
                        $fieldId                    = $this->fieldHelper->getFieldName($simulatorField->id);

                        $label                      = $this->wsf->userTrans($simulatorField->label);
                        $value                      = (isset($simulatorFieldsData[$fieldId]))?$simulatorFieldsData[$fieldId]:null;

                        $htmlElement                = $this->orderHelper->getReviewElement($simulatorField, $value, true, $cartItem['uploadedFile']);

                        /* Should the element be displayed? */
                        if($this->calculatorHelper->isFieldVisibleOnCheckout($calculator, $simulatorField, $value) == true){
                            /*
                             * OLD:
                             * $title[]                    = "&emsp;&emsp;<b>{$label}:</b> {$htmlElement}";
                             */

                            $title[$fieldId]       = array(
                                'fieldId'   => $fieldId,
                                'label'     => $label,
                                'html'      => $htmlElement,
                                'field'     => $simulatorField,

                            );

                        }

                    }
                }

                /* Output Fields */
                foreach($outputFields as $simulatorKey => $simulatorField){
                    if($conditionalLogic[$simulatorField->id] == true) {
                        $fieldId = $this->fieldHelper->getFieldName($simulatorField->id);

                        $label = $this->wsf->userTrans($simulatorField->label);
                        $value = (isset($outputResults[$simulatorField->id])) ? $outputResults[$simulatorField->id] : null;

                        $htmlElement = $this->fieldHelper->getOutputResult($simulatorField, $value);

                        /* Should the element be displayed? */
                        if ($this->calculatorHelper->isFieldVisibleOnCheckout($calculator, $simulatorField, $value) == true) {
                            $title[$fieldId] = array(
                                'fieldId' => $fieldId,
                                'label' => $label,
                                'html' => $htmlElement,
                                'field' => $simulatorField,

                            );

                        }
                    }
                }

                if(count($title) != 0){
                    return $this->wsf->getView('awspricecalculator', 'checkout/item.php', true, array(
                        'productTitle'      => $productTitle,
                        'productItems'      => $title,
                    ));
                }

            }
        }

        return $productTitle;
    }
    /*/WPC-PRO*/

    /**
     * Executed after purchase, in the order details
     *
     * @param string $productTitle, title of the given product
     * @return string
     */
    /*WPC-PRO*/
    function woocommerce_order_item_quantity_html($productTitle, $orderItem){

//            global $woocommerce;
//
//            $cartItemKey            = $orderItem['item_meta']['_wpc_cart_item_key'][0];
//            $cartItem   = $woocommerce->cart->get_cart_item('078fd750ebc949e92f270fa3c660cb77');
//
//            print_r($cartItem);

        /*$orderId    = get_query_var('order-received');
        if(!empty($orderId)){
            $simulation                 = $this->calculatorModel->getSimulationByOrderId($orderId);

            if(!empty($simulation)){
                $simulationData         = json_decode($simulation->simulation_data, true);

                if(isset($orderItem['item_meta']['_wpc_cart_item_key'][0])){
                    $cartItemKey            = $orderItem['item_meta']['_wpc_cart_item_key'][0];

                    $simulatorId            = $simulationData[$cartItemKey]['simulator_id'];
                    $simulatorFieldsIds     = $this->calculatorHelper->get_simulator_fields($simulatorId);
                    $simulatorFields        = $this->fieldHelper->get_fields_by_ids($simulatorFieldsIds);
                    $simulatorFieldsData    = $simulationData[$cartItemKey]['simulator_fields_data'];

                    foreach($simulatorFields as $simulatorKey => $simulatorField){
                        $fieldId                    = $this->plugin_short_code . '_' . $simulatorField->id;
                        $value                      = $simulatorFieldsData[$fieldId];

                        $htmlElement                = $this->orderHelper->getReviewElement($simulatorField, $value);
                        $title[] = "&emsp;&emsp;<b>{$simulatorField->label}:</b> {$htmlElement}";
                    }

                    return "{$productTitle}<br/><small>" . implode("<br/>", $title) . "</small><br/>";
                }
            }

        }*/

        return $productTitle;
    }
    /*/WPC-PRO*/


    /**
     * Not Used
     *
     * @return void
     */
    function filter_admin_footer_text () {
        echo "";
    }

    /**
     * After a product has been added, it redirects directly at checkout
     *
     * @return string
     */
    function filter_woocommerce_add_to_cart_redirect() {

        $product_id = $this->wsf->requestValue('add-to-cart');
        if(!empty($product_id)){
            $simulator = $this->calculatorHelper->get_simulator_for_product($product_id);

            if(!empty($simulator)){
                if($simulator->redirect == 1){
                    return wc_get_checkout_url();
                }
            }
        }

    }

    /**
     * Activation of internationalization
     *
     * @return void
     */
    function action_plugins_loaded() {
        load_plugin_textdomain($this->plugin_code, false, dirname( plugin_basename(__FILE__) ) . '/lang' );
    }

    /**
     * Validation of the simulator fields to the addition of the product in the cart
     *
     * @param bool $bool, boolean logic
     * @param string $product_id, idetification number of a product
     * @param integer $quantity, quantity of ordered products
     * @return bool
     */
    function filter_woocommerce_add_to_cart_validation($bool, $product_id, $quantity){
        $simulator = $this->calculatorHelper->get_simulator_for_product($product_id);

        /* Changes for ignite, AT863*/
        $sample = $this->wsf->requestValue('sample');
        $woocoommerceAddSample = isset($sample)? true : false;


        if(!empty($simulator)){
            $requestFields      = $this->calculatorHelper->getFieldsFromRequest($product_id, $simulator);

            $this->calculatorHelper->calculate_price(
                $product_id,
                $requestFields['data'],
                false,
                $simulator->id,
                $outputResults,
                $conditionalLogic,
                true,
                $errors,
                $priceRaw,
                "add-to-cart");

            /* Changes for Ignite, AT863*/
            if(count($errors) != 0 && !$woocoommerceAddSample){
                foreach($errors as $fieldId => $fieldErrors){
                    foreach($fieldErrors as $errorMessage){
                        wc_add_notice( $errorMessage, "error");
                    }
                }

                return false;
            }

            /*
             * Add the product. The product inserted by default by WC
             * it will be deleted in the add_to_cart_callback function
             */
            if($bool == true){
                /*
                 * Empty the cart before any other add (If the option is enabled)
                 */
                if($simulator->empty_cart == 1){
                    $this->ecommerceHelper->emptyCart();
                }
            }
        }

        return $bool;
    }


    /**
     * Adds more information in the order that will be useful in the future.
     *
     * I could also directly use this method to save data
     * of the table "woopricesim_simulations" in the order.
     *
     * @param string $item_id , identification number of an ordered item
     * @param array $values , retrieved values from the customer
     * @param string $cart_item_key , key of the item in the cart page
     * @return void
     * @throws Exception
     */
    function action_woocommerce_add_order_item_meta($item_id, $values, $cart_item_key){

        if(isset($values['simulator_id'])){
            /*WPC-PRO*/


            $simulatorId            = $values['simulator_id'];
            $calculator             = $this->calculatorModel->get($simulatorId);
            $simulatorFieldsIds     = $this->calculatorHelper->get_simulator_fields($values['simulator_id']);
            $simulatorFields        = $this->fieldHelper->get_fields_by_ids($simulatorFieldsIds);

            $simulatorFieldsData	= $values['simulator_fields_data'];
            $outputFieldsData       = $values['output_fields_data'];
            $productId              = $values['product_id'];

            $this->calculatorHelper->calculate_price($productId, $simulatorFieldsData, false, $simulatorId, $outputResults, $conditionalLogic);

            foreach($simulatorFields as $simulatorKey => $simulatorField){
                if($conditionalLogic[$simulatorField->id] == true){
                    $fieldId                    = $this->fieldHelper->getFieldName($simulatorField->id);
                    $fieldType                  = $simulatorField->type;
                    $value                      = $simulatorFieldsData[$fieldId];
                    $label                      = strip_tags($this->wsf->userTrans($simulatorField->label));
                    $htmlElement                = $this->orderHelper->getReviewElement($simulatorField, $value, true);

                    /* Should the element be displayed? */
                    if($this->calculatorHelper->isFieldVisibleOnOrderDetails($calculator, $simulatorField, $value) == true){
                        wc_add_order_item_meta($item_id, $label,  $htmlElement);
                    }

                }

            }

            foreach($outputFieldsData as $fieldId => $fieldValue){
                $field                      = $this->fieldModel->get_field_by_id($fieldId);
                $label                      = strip_tags($this->wsf->userTrans($field->label));
                $htmlElement                = $this->orderHelper->getReviewElement($field, $fieldValue, false);

                /* Should the element be displayed? */
                if($this->calculatorHelper->isFieldVisibleOnOrderDetails($calculator, $field, $fieldValue) == true){
                    wc_add_order_item_meta($item_id, $label, $htmlElement);
                }

            }

            /*/WPC-PRO*/

            /* Adding cart item key to the order info */
            wc_add_order_item_meta($item_id, "_wpc_cart_item_key", $cart_item_key);
        }
    }


    /**
     * Performed before checkout
     *
     * It is possible to take the information entered by the user in phase of checkout
     *
     * @param string $orderId, identification number of the order
     * @return void
     */
    function action_woocommerce_checkout_update_order_meta($orderId, $values){
        global $woocommerce;
        /*WPC-PRO*/


        if(!empty($orderId)){
            $simulation                 = $this->calculatorModel->getSimulationByOrderId($orderId);

            if(!empty($simulation)){
                $simulationData         = json_decode($simulation->simulation_data, true);

                if(isset($orderItem['item_meta']['_wpc_cart_item_key'][0])){
                    $cartItemKey            = $orderItem['item_meta']['_wpc_cart_item_key'][0];


                    $simulatorId            = $simulationData[$cartItemKey]['simulator_id'];
                    $simulatorFieldsIds     = $this->calculatorHelper->get_simulator_fields($simulatorId);
                    $simulatorFields        = $this->fieldHelper->get_fields_by_ids($simulatorFieldsIds);
                    $simulatorFieldsData    = $simulationData[$cartItemKey]['simulator_fields_data'];

                    foreach($simulatorFields as $simulatorKey => $simulatorField){
                        $fieldId                    = $this->fieldHelper->getFieldName($simulatorField->id);
                        $value                      = $simulatorFieldsData[$fieldId];
                        $htmlElement                = $this->orderHelper->getReviewElement($simulatorField, $value);

                        update_post_meta($orderId, $this->wsf->userTrans($simulatorField->label), $htmlElement );
                    }

                }
            }

        }
        /*/WPC-PRO*/
    }

    /**
     * Saving the simulation in the database, when the user proceeds to the order
     *
     * @param string $order_id, identification number of the order
     * @return void
     */
    function action_woocommerce_checkout_order_processed($order_id){
        $orderData                  = array();
        $simulatorsDataBackup       = array();
        $foundSimulators            = false;
        $targetPath     = $this->wsf->getUploadPath('docs');
        $tmpPath        = $this->wsf->getUploadPath('tmp_files');


        global $woocommerce;

        foreach (WC()->cart->get_cart() as $cart_item_key => $values){

            $cartItem   = $woocommerce->cart->get_cart_item($cart_item_key);
            if(isset($values['simulator_id'])){
                $foundSimulators                = true;
                $simulatorId                    = $values['simulator_id'];

                $orderData[$cart_item_key]      = $values;


                /* Remove temporary uploaded file if the order has been placed */
                foreach ($cartItem['uploadedFile'] as $uploadId => $file){

                    for ($i = 0; $i < count($file['name']); $i++) {
                        $tmpFile = rtrim($tmpPath, '/') . '/' . $cart_item_key . '_' . $uploadId . '_' . str_replace(' ','',$file['name'][$i]);

                        if (file_exists($tmpFile)) {
                            $orderUploadedFile = rtrim($targetPath, '/') . '/' . $cart_item_key . '_' . $uploadId . '_' . str_replace(' ','',$file['name'][$i]);
                            rename($tmpFile, $orderUploadedFile);
                        }
                    }
                }

                if(!array_key_exists($simulatorId, $simulatorsDataBackup)){
                    $simulatorsDataBackup[$simulatorId]     = $this->calculatorModel->get($simulatorId);
                }
            }
        }

        if($foundSimulators === true){
            $this->calculatorModel->saveSimulation($order_id, $orderData, $simulatorsDataBackup);

        }
    }

    /**
     * Adding a block in Orders
     *
     * @return void
     */
    public function order_add_meta_boxes(){

        add_meta_box(
            'woocommerce-order-my-custom',
            "Price Calculator",
            array($this,'order_simulation'),
            'shop_order',
            'normal',
            'default'
        );
    }


    /**
     * View all the simulations for that order
     *
     * @param object $order, instance of the order
     * @return void
     */
    public function order_simulation($order){

        echo $this->orderHelper->calculatorOrder($order->ID);
    }

    /**
     * Performed when a product is removed from the cart
     *
     * @param string $cart_item_key
     * @param object $instance
     * @return  void
     */
    function action_woocommerce_cart_item_removed($cart_item_key, $instance){

    }

    /**
     * Performed for items in the cart
     *
     * @param string $product_name, name of the product
     * @param array $values, attributes of the given product
     * @param string $cart_item_key, key of the item in the cart page
     * @return string | null
     */
    function cartItemPrice($product_name, $values, $cart_item_key){
        global $woocommerce;

        $product    = $this->ecommerceHelper->getProductById($values['product_id']);
        $cartItem   = $woocommerce->cart->get_cart_item($cart_item_key);

        if(isset($cartItem['simulator_id']) && !$cartItem['sample']){
            $calculatorId           = $cartItem['simulator_id'];
            $fieldsData             = $cartItem['simulator_fields_data'];

            $price                  = $this->calculatorHelper->calculate_price($values['product_id'], $fieldsData, true, $calculatorId, $outputResults, $conditionalLogic);
            $calculator             = $this->calculatorModel->get($calculatorId);
            $simulatorFieldsIds     = $this->calculatorHelper->get_simulator_fields($calculator->id);
            $simulatorFields        = $this->fieldHelper->get_fields_by_ids($simulatorFieldsIds);

            /*
             * check if there is a problem on the computer fields, in case of yes, remove the product from the cart
             */
            foreach($simulatorFields as $simulatorKey => $simulatorField) {

                if ($simulatorField == null) {
                    WC()->cart->remove_cart_item($cart_item_key);
                    return null;
                }
            }


            /* I do not show the edit button in the dropdown cart */
            if(is_cart() == true){
                $calculatorFieldsIds    = $this->calculatorHelper->get_simulator_fields($calculator->id);
                $calculatorFields       = $this->fieldHelper->get_fields_by_ids($calculatorFieldsIds);
                $defaultThemeData       = $this->themeHelper->getDefaultThemeData($calculator, $simulatorFields, $fieldsData);

                return $this->wsf->getView('awspricecalculator', 'cart/edit.php', true, array(
                    'product'               => $product,
                    'cartItemKey'           => $cart_item_key,
                    'price'                 => $price,
                    'cartEditButtonClass'   => $this->settingsModel->getValue("cart_edit_button_class"),
                    'cartEditButtonPosition'=> $this->settingsModel->getValue("cart_edit_button_position"),
                    'cartHideItemPrice'     => $this->settingsModel->getValue("cart_hide_item_price"),
                    'modal'             =>  $this->wsf->getView('awspricecalculator', 'product/product.php', true, array(
                            'product'               => $product,
                            'simulator'             => $calculator,
                            'data'                  => $defaultThemeData,
                            'priceFormat'           => $this->ecommerceHelper->getPriceFormatForJs(),
                            'outputResults'         => $this->calculatorHelper->getOutputResultsPart($calculator, $outputResults),
                            'conditionalLogic'      => $conditionalLogic,
                        )) . $this->wsf->getView("awspricecalculator", "product/footer_data.php", true, array(
                            'product'               => $product,
                            'simulator'             => $calculator,
                            'data'                  => $defaultThemeData,
                            'imagelist_modals'      => $this->wsf->getView("awspricecalculator", 'partial/imagelist_modal.php', true, array(
                                'simulator_fields'  => $calculatorFields,
                                'fieldHelper'       => $this->fieldHelper,
                                'cartItemKey'       => $cart_item_key,
                                'data'              => $defaultThemeData,
                            )),
                        )),
                ));
            }else{
                return $price;
            }

        }

        return $product_name;
    }

    /**
     * Executed for items in cart (HTML version)
     * 
     * This price is also displayed in the drop-down cart
     *
     * @param string $cart_price, price of the product in the cart page
     * @param array $values, attributes of the product in the cart page
     * @param string $cart_item_key,key of the item in the cart page
     * @return string
     */
    function woocommerce_cart_item_price_html($cart_price, $values, $cart_item_key){
        global $woocommerce;
        $product = new \WC_Product($values['product_id']);

        $cartItem   = $woocommerce->cart->get_cart_item($cart_item_key);

        if(isset($cartItem['simulator_id'])){
            $calculatorId   		= $cartItem['simulator_id'];
            $fieldsData     		= $cartItem['simulator_fields_data'];
            $price                  = $this->calculatorHelper->calculate_price($values['product_id'], $fieldsData);
            $calculator             = $this->calculatorModel->get($calculatorId);
            $simulatorFieldsIds     = $this->calculatorHelper->get_simulator_fields($calculator->id);
            $simulatorFields        = $this->fieldHelper->get_fields_by_ids($simulatorFieldsIds);

            return $price;

        }

        return $cart_price;
    }

    /**
     * Executed in the display of the total sub-product in the cart
     *
     * @param string $product_subtotal
     * @return string
     */
    function woocommerce_cart_product_subtotal($product_subtotal, $product, $quantity, $cart_object){

        /* Woo Discount Rules: Does it make sense to create a checkbox in Settings for this? */
        //$this->cartHelper->updateCartByCartObject($cart_object);

        return $product_subtotal;
    }

    /**
     * Performed when adding a product to the cart
     * 
     * |Set the quantity on the cart|
     * $woocommerce->cart->set_quantity($cart_item_key, 100, true);
     * 
     * |Recalculate the totals of the cart|
     * $woocommerce->cart->calculate_totals();
     *
     * @param string $cart_item_key, key of the item in the cart page
     * @param string $product_id, identification number of the product in cart section
     * @return void
     */
    public function add_to_cart_callback($cartItemKey, $productId, $quantity, $variationId, $variation, $cartItem){
        global $woocommerce;

        $targetPath     = $this->wsf->getUploadPath('tmp_files');
        foreach ($_FILES as $uploadId => $file){
            for ($i = 0; $i < count($file['name']); $i++) {
                $targetFile = rtrim($targetPath, '/') . '/' . $cartItemKey . '_' . $uploadId . '_' . str_replace(' ','',$file['name'][$i]);
                move_uploaded_file($file['tmp_name'][$i], $targetFile);
            }
        }


        $calculator = $this->calculatorHelper->get_simulator_for_product($productId);

        if(!empty($calculator)){

            $simulator_fields_ids = $this->calculatorHelper->get_simulator_fields($calculator->id);
            $fields = $this->fieldHelper->get_fields_by_ids($simulator_fields_ids);

            if(!empty($calculator->overwrite_quantity)){
                $data           = $cartItem['simulator_fields_data'];
                $product        = new \WC_Product($productId);

                $quantity       = $this->calculatorHelper->getCalculatorQuantity($calculator, $product, $data);

                $woocommerce->cart->set_quantity($cartItemKey, $quantity, true);
            }

            /*
             * check if there is a problem on the computer fields,
             * in case of yes, I remove the product from the cart
             */
            foreach($fields as $simulatorKey => $simulatorField) {

                if ($simulatorField == null) {
                    WC()->cart->remove_cart_item($cart_item_key);

                }
            }


        }
    }

    /*
     * Adding to the calculator cart item the data of the calculator
     */
    public function woocommerce_add_cart_item_data($cartItemData, $productId, $variationId){

        $sample = $this->wsf->requestValue('sample');
        $woocoommerceAddSample = isset($sample)? true : false;

        // global $woocommerce;
        $calculator     = $this->calculatorHelper->get_simulator_for_product($productId);

        if(!empty($calculator)){
            $requestFields  = $this->calculatorHelper->getFieldsFromRequest($productId, $calculator, false, true);

            /* Calcolo i valori di output */
            $this->calculatorHelper->calculate_price($productId, $requestFields['data'], false, $calculator->id, $outputFieldsData);

            return array_merge($cartItemData, array(
                'simulator_id'              => $calculator->id,
                'simulator_fields_data'     => $requestFields['data'],
                'output_fields_data'        => $outputFieldsData,
                'uploadedFile'              => $_FILES,
                'sample'                    => $woocoommerceAddSample
            ));
        }

        return $cartItemData;

    }

    /**
     * Executed before calculating the total in cart / checkout
     * It allows to calculate and change the weight for each product
     *
     * @param object $cart_object, object present in the cart page
     * @return void
     */
    public function woocommerce_before_calculate_totals($cart_object){

        if (sizeof($cart_object->cart_contents ) > 0) {
            foreach ($cart_object->cart_contents as $cartItemKey => $cartItem) {
                $productId      = $cartItem['product_id'];
                $product        = new \WC_Product($productId);
                $calculator     = $this->calculatorHelper->get_simulator_for_product($productId);

                /* It's a calculator */
                if(!empty($calculator)){
                    $calculatorFieldsData    = $cartItem['simulator_fields_data'];
                    $this->calculatorHelper->calculate_price($productId, $calculatorFieldsData, false, $calculator->id, $outputResults);

                    /* The weight overwrite has been set */
                    if(!empty($calculator->overwrite_weight)){
                        $cartItem['data']->set_weight($outputResults[$calculator->overwrite_weight]);
                    }

                    /* The length has been set */
                    if(!empty($calculator->overwrite_length)){
                        $cartItem['data']->set_length($outputResults[$calculator->overwrite_length]);
                    }

                    /* The width has been set */
                    if(!empty($calculator->overwrite_width)){
                        $cartItem['data']->set_width($outputResults[$calculator->overwrite_width]);
                    }

                    /* The height has been set */
                    if(!empty($calculator->overwrite_height)){
                        $cartItem['data']->set_height($outputResults[$calculator->overwrite_height]);
                    }

                    /*
                    $cartItem['data']->apply_changes();
                     *
                     */
                }
            }
        }

        $this->cartHelper->updateCartByCartObject($cart_object);

        /* Woo Discount Rules: Does it makes sense to add a checkbox in Settings about this? */
        remove_action('woocommerce_before_calculate_totals', array($this, 'woocommerce_before_calculate_totals'), 10);
    }

    /**
     * Function called up via Ajax for real-time calculation of the price
     *
     * @return void
     */
    public function ajax_callback(){
        $this->pluginHelper->ajaxCallback();
    }

    /**
     * Display of the simulator in the product sheet
     *
     * @return void
     */
    public function product_meta_end(){
        echo $this->productHelper->productPage(get_the_ID());
    }

    /**
     * Adds an item to the WooCommerce menu
     *
     * @return void
     */
    public function register_submenu_page() {
        add_submenu_page('woocommerce',
            $this->plugin_label,
            $this->plugin_label,
            'manage_woocommerce',
            $this->plugin_code,
            array($this, 'submenu_callback')
        );
    }

    /*
     * Show the back-end of the plugin
     */
    public function submenu_callback() {
        echo $this->wsf->execute('awspricecalculator', true, '\\AWSPriceCalculator\\Controller');
    }


    /**
     * Woocommerce hook
     *
     * Used to add a custom tab in the product post page || product edit page
     *
     * @param $tabs, the default tabs in the product post page || product edit page
     * @return mixed
     */
    public function wpc_custom_product_tabs( $tabs) {

        $tabs['calculator'] = array(
            'label'    => 'Calculator',
            'target'   => 'calculator_product_data',
            'priority' => 51,
        );

        return $tabs;

    }

    /**
     * Woocommerce hook
     *
     * A hook to be able to add html elements when entered inside the specific tab
     * in the product post page || product edit page
     *
     * @return void
     */
    public function wpc_product_data_panel(){
        $this->wsf->execute('awspricecalculator', true, '\\AWSPriceCalculator\\Controller', 'calculator', 'customTabProductPage');

    }


    /**
     *Function called from ajax to add a new calculator to a product from the product post page
     *
     * @return void
     */
    public function ajax_attach_calculator(){
        $post = $this->wsf->getPost();

        $productId = $this->wsf->requestValue('id');
        $simulatorId = $this->wsf->requestValue('simulatorid');

        $this->calculatorHelper->addAjaxProductToCalculator($productId,$simulatorId,$post['selectedCalculatorProducts']);

    }

    /**
     *Function called from ajax to remove the calculator from a product in the product post page
     *
     * @return void
     */
    public function ajax_remove_calculator(){
        $productId = $this->wsf->requestValue('id');
        $this->calculatorHelper->removeAjaxProductToCalculator($productId);

    }
    
    public function site_transient_update_plugins($value){
        if($this->wsf->getLicense() == true){
            if(isset($value) && is_object($value)) {
                  if (isset($value->response["excel-worksheet-price-calculation/excel-worksheet-price-calculation.php"])){
                    unset($value->response["excel-worksheet-price-calculation/excel-worksheet-price-calculation.php"]);
                  }
            }
        }
    
        return $value;
    }


}