<?php
/**
 * @package AWS Price Calculator
 * @author Enrico Venezia
 * @copyright (C) Altos Web Solutions Italia
 * @license GNU/GPL v2 http://www.gnu.org/licenses/gpl-2.0.html
**/

namespace WSF\Helper;

/*AWS_PHP_HEADER*/

use WSF\Helper\FrameworkHelper;

class EcommerceHelper {
    
    var $wsf;

    public function __construct(FrameworkHelper $wsf) {
        $this->wsf = $wsf;
        
        $this->databaseHelper    = $this->wsf->get('\\WSF\\Helper', true, 'awsframework/Helper', 'DatabaseHelper', array($this->wsf));
        
        if($this->getTargetEcommerce() == "hikashop"){
            if(!@include_once(rtrim(JPATH_ADMINISTRATOR,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'components'.DIRECTORY_SEPARATOR.'com_hikashop'.DIRECTORY_SEPARATOR.'helpers'.DIRECTORY_SEPARATOR.'helper.php')){ return false; }
        }
    }


    /**
     * Format Price.
     *
     * Return the price in Woocommerce format.
     *
     * @param string $price the price in the actual format
     * @return string
     */
    public function get_price($price){
        if($this->getTargetEcommerce() == "woocommerce"){
            return wc_price($price);
        }else if($this->getTargetEcommerce() == "hikashop"){
            $currencyHelper = hikashop_get('class.currency');
            $mainCurrency   = $currencyHelper->mainCurrency();

            return $currencyHelper->format($price, $mainCurrency);
        }
        
    }

    /**
     * Get currency.
     *
     * Return the currency used.
     *
     * @return string
     */
    public function get_currency_symbol(){
            return html_entity_decode(get_woocommerce_currency_symbol());
    }



    /**
     * Get price format.
     *
     * Return the price format used.
     *
     * @return string
     */
    public function get_price_format(){
            return get_woocommerce_price_format();
    }

    /**
     * Return the id of the current cart.
     *
     * @return string
     */
    public function get_current_cartid(){
            global $woocommerce;
            $items = $woocommerce->cart->get_cart();

            foreach($items as $key => $value){
                    return $key;
            }
    }


    public function getPriceFormatForJs(){
        return utf8_encode(htmlentities($this->get_price(9999.1111111111)));
    }


    public function getPageType(){
        if($this->isCart() == true){
            return "cart";
        }

        if($this->isProduct() == true){
            return "product";
        }

        return null;
    }


    /**
     * Return the Woocommerce product.
     *
     * @param string $product_id the product identification number
     * @return array
     */
    public function getProductById($product_id){
        if($this->getTargetEcommerce() == "woocommerce"){
            $obj_product = new \WC_Product($product_id);
            return $this->getProductArrayFromWooCommerce($obj_product);
        }else if($this->getTargetEcommerce() == "hikashop"){
            $productClass        = \hikashop_get('class.product');
            return $this->getProductArrayFromHikashop($productClass->getProduct($product_id));
        }
    }

    /**
     * Return the Woocommerce products.
     *
     * @param array $productIds the products identification numbers
     * @return array
     */
    public function getProductsByIds($productIds = array()){
        
        $products   = array();
        
        foreach($productIds as $productId){
            try{
                $products[$productId]     = $this->getProductById($productId);
            
            /* If products were deleted by WooCommerce an 'Invalid product' exception will be generated */
            }catch(\Exception $ex){
                
            }
        }
        
        return $products;
    }

    /**
     * Return a list of all the Woocommerce products.
     *
     * @param int $productsPerPage  number of product to return
     * @param int $start offset to start
     * @param string $orderBy type of order
     * @param string $orderDir the directory order
     * @param string $search
     * @return array
     */
    public function getProducts($productsPerPage = 10, $start = 0, $orderBy = null, $orderDir = null, $search = null){
        
        $products   = array();
        
        if($this->getTargetEcommerce() == "woocommerce"){
            
            $args = array( 
                'post_type'         => 'product', 
                'posts_per_page'    => $productsPerPage,
                'offset'            => $start,
            );
            
            if($orderBy != null){
                $args['orderby']    = $orderBy;
                $args['order']      = $orderDir;
            }else{
                $args['orderby']    = 'id';
                $args['order']      = 'DESC';
            }
            
            if($search != null){
                $args['s']          = $search;
            }
            
            $loop               = new \WP_Query($args);
            $totalProducts      = $loop->found_posts;
            $count              = $loop->post_count;
            
            while ( $loop->have_posts() ) : $loop->the_post(); 
                global $product; 
                $products[] = $this->getProductArrayFromWooCommerce($product);
                
            endwhile; 
                wp_reset_query();
               
       }else if($this->getTargetEcommerce() == "hikashop"){
           $productClass        = \hikashop_get('class.product');      
           $productClass->getProducts(null, 'id');

           $bufferedProducts	= array();
           foreach($productClass->products as $bufferedProduct){
                $bufferedProducts[]		= $this->getProductArrayFromHikashop($bufferedProduct);
           }
			
           for($i = $start; $i < $start + $productsPerPage; $i++){
                $products[]      = $bufferedProducts[$i];
            }

           $count               = count($products);
           $totalProducts       = count($bufferedProducts);
        }
       
        return array(
            'products'          => $products,
            'totalProducts'     => $totalProducts,
            'count'             => $count,
        );
        
    }



    /**
     * Convert the HikaShop product into a universally understandable product.
     *
     * @param object $product th product identification number
     * @return array
     */
    public function getProductArrayFromHikashop($product){
        return array(
                   'id'     => $product->product_id,
                   'name'   => $product->product_name,
                   'price'  => $product->product_sort_price,
        );
    }

    /**
     * Convert the WooCommerce product into a universally understandable product.
     *
     * @param object $product th product identification number
     * @return array
     */
    public function getProductArrayFromWooCommerce($product){
        return array(
            'id'        => $product->get_id(),
            'name'      => $product->get_title(),
            'price'     => $product->get_regular_price(),
            'taxes'     => $product->get_tax_class(),
            'sku'       => $product->get_sku()
        );
    }


    /**
     * Generate a product in a array type.
     *
     * @param string $id, the id of product
     * @param string $name, the name of the product
     * @param string $price, the price associated
     * @return array
     */
    public function getProductArray($id, $name, $price){
        return array(
            'id'        => $id,
            'name'      => $name,
            'price'     => $price,
        );
    }
    
    /**
     * Get products categories.
     *
     * @return array
     */
    public function getProductCategories(){

        $result     = array();
        
        if($this->getTargetEcommerce() == "woocommerce"){
            foreach (get_terms('product_cat', array('hide_empty' => 0, 'parent' => 0)) as $each) {
                $result     = $result + $this->getProductCategoriesRecursive($each->taxonomy, $each->term_id);
            }
        }else if($this->getTargetEcommerce() == "hikashop"){
            $categoryClass        = \hikashop_get('class.category');   
            $categoryClass->getCategories(null);
            
            /* TODO */
            return array();
            
            foreach($categoryClass as $category){
                print_r($category);
            }
            
        }
        
        return $result;
    }


    /**
     * Products category.
     *
     * Get the categories of all the products that has a calculator attached.
     *
     * @param string $taxonomy
     * @param int $termId
     * @param string $separator
     * @param bool $parent_shown
     * @return array
     */
    function getProductCategoriesRecursive($taxonomy = '', $termId, $separator='', $parent_shown = true){

        $args   = array(
            'hierarchical'      => 1,
            'taxonomy'          => $taxonomy,
            'hide_empty'        => 0,
            'orderby'           => 'id',
            'parent'            => $termId,
        );
        
        $term           = get_term($termId , $taxonomy); 
        $result         = array();
        
        if ($parent_shown) {
            //$output                 = $term->name . '<br/>'; 
            $result[$term->term_id]    = $term->name;
            $parent_shown           = false;
        }
        
        $terms          = get_terms($taxonomy, $args);
        $separator      .= $term->name . ' > ';  

        if(count($terms) > 0){            
            /*
             * $term->term_id
             * $category->term_id
             */
            foreach ($terms as $term) {
                //$output .=  $separator . $term->name . " " . $term->slug . '<br/>';
                $result[$term->term_id]        = $separator . $term->name;
                
                //$output .=  $this->getProductCategoriesRecursive($taxonomy, $term->term_id, $separator, $parent_shown);
                $result  = $result + $this->getProductCategoriesRecursive($taxonomy, $term->term_id, $separator, $parent_shown);
            }
        }
        
        return $result;
    }

    /**
     * Products category.
     *
     * Get the categories of all the products searching by slug.
     *
     * @param string $productCategoryName the category ofa product
     * @return array
     */
    function getCategoryProductsByCategorySlug($productCategorySlug = null){
        /* Using Raw MySQL Query instead of \WP_Query which throw a "Allowed memory" error */

        $rows = $this->databaseHelper->getResults("SELECT object_id FROM [prefix]terms
                        LEFT JOIN [prefix]term_taxonomy ON [prefix]term_taxonomy.term_id = [prefix]terms.term_id
                        LEFT JOIN [prefix]term_relationships ON [prefix]term_relationships.term_taxonomy_id = [prefix]terms.term_id
                        WHERE slug = :slug
                        AND taxonomy = 'product_cat';", array(
                                'slug'	=> $productCategorySlug,
                        ));

        $products   = array();
        foreach($rows as $row){
                        $products[]		= $row->object_id;
        }

        return $products;
    }

    /**
     * Products category.
     *
     * Get the categories of all the products searching by ID.
     *
     * @param int $categoryId the id of a specific category
     * @return array
     */
    function getCategoryProductsByCategoryId($categoryId = null){
        $term = get_term($categoryId, 'product_cat');
        
        if(empty($term)){
            return array();
        }
        
        if($categoryId == null){
            $slug   = null;
        }else{
            $slug = $term->slug;
        }
        
        return $this->getCategoryProductsByCategorySlug($slug);
    }

    /**
     * Target E-commerce.
     *
     * Return the target e-commerce.
     *
     * @return string
     */
    public function getTargetEcommerce(){
        $license   = file_get_contents($this->wsf->getPluginPath("resources/data/ecommerce.bin", true));

        return trim($license);
    }

    /**
     * Check product page.
     *
     * @return bool
     */
    public function isProduct(){
        if($this->getTargetEcommerce() == "woocommerce"){
            return is_product();
        }else if($this->getTargetEcommerce() == "hikashop"){
            $option     = $this->wsf->requestValue('option');
            $ctrl       = $this->wsf->requestValue('ctrl');
            $task       = $this->wsf->requestValue('task');

            if($option == "com_hikashop" && $ctrl == "product" && $task == "show"){
                return true;
            }
            
            return false;
        }
    }

    /**
     * Check cart page.
     *
     * @return bool
     */
    public function isCart(){
        if($this->getTargetEcommerce() == "woocommerce"){
            return is_cart();
        }else if($this->getTargetEcommerce() == "hikashop"){
            /* TODO: Non riesco ad identificare in maniera ancora più precisa se la pagina contiene il carrello */
            if($this->isProduct() == true){
                return false;
            }
            
            return true;
        }
    }

    /**
     * Empty the cart.
     *
     * @return void
     */
    public function emptyCart(){
        if($this->getTargetEcommerce() == "woocommerce"){
            WC()->cart->empty_cart();
        }else if($this->getTargetEcommerce() == "hikashop"){
            hikashop_nocache();

            $cartClass  = \hikashop_get('class.cart');
            $cart_id    = $cartClass->getCurrentCartId();
            
            $cartClass->delete($cart_id);
        }
    }


    /* TODO-LATER */
    /**
     * Decimal separator.
     *
     * Returns the decimal separator configured in the ecommerce.
     *
     * @return string
     */
    public function getDecimalSeparator(){
        if($this->getTargetEcommerce() == "woocommerce"){
            return wc_get_price_decimal_separator();
        }else if($this->getTargetEcommerce() == "hikashop"){
            
        }
        
    }

    /* TODO-LATER */
    /**
     * Thousand separator.
     *
     * Returns the thousand separator configured in the ecommerce.
     *
     * @return string
     */
    public function getThousandSeparator(){
        
        if($this->getTargetEcommerce() == "woocommerce"){
            return wc_get_price_thousand_separator();
        }else if($this->getTargetEcommerce() == "hikashop"){
            
        }
    }


    /* TODO-LATER */
    /**
     * E-commerce decimal number.
     *
     * Returns the decimal number configured in the ecommerce.
     *
     * @return string
     */
    public function getDecimals(){
        if($this->getTargetEcommerce() == "woocommerce"){
            return wc_get_price_decimals();
        }else if($this->getTargetEcommerce() == "hikashop"){
            
        }
    }
    
    /*
     * Get the cart URL
     */
    public function getCartUrl(){
        return wc_get_cart_url();
    }
    

    
    /**
     * Calculate the total tax rates for a product
     *
     * @param $product The WooCommerce Product
     * @param $calculatorId
     * @param $productsIds
     *
     * @return int The total tax rates
     */
    function calculateTotalTaxRates($product){
        $totalTaxRates = 0;
        if (isset($product['taxes'])) {
            $taxRates      = \WC_Tax::get_rates( $product['taxes'] );


            if ( ! empty( $taxRates ) ) {
                foreach ( $taxRates as $taxRate ) {
                    $totalTaxRates += (int) $taxRate['rate'];
                }
            }

        }
        return $totalTaxRates;
    }
}
