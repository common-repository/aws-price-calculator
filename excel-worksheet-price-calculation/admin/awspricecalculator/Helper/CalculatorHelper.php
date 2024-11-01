<?php
/**
 * @package AWS Price Calculator
 * @author Enrico Venezia
 * @copyright (C) Altos Web Solutions Italia
 * @license GNU/GPL v2 http://www.gnu.org/licenses/gpl-2.0.html
**/

namespace AWSPriceCalculator\Helper;

/*AWS_PHP_HEADER*/

use PHPExcel_Calculation;
use WSF\Helper\FrameworkHelper;

class CalculatorHelper {
    
    var $wsf;
    
    var $fieldHelper;
    var $ecommerceHelper;
    
    public function __construct(FrameworkHelper $wsf) {
        $this->wsf = $wsf;
        
        /* HELPERS */
        $this->databaseHelper               = $this->wsf->get('\\WSF\\Helper', true, 'awsframework/Helper', 'DatabaseHelper', array($this->wsf));
        $this->fieldHelper                  = $this->wsf->get('\\AWSPriceCalculator\\Helper', true, 'awspricecalculator/Helper', 'FieldHelper', array($this->wsf));
        $this->ecommerceHelper              = $this->wsf->get('\\WSF\\Helper', true, 'awsframework/Helper', 'EcommerceHelper', array($this->wsf));
        $this->themeHelper                  = $this->wsf->get('\\AWSPriceCalculator\\Helper', true, 'awspricecalculator/Helper', 'ThemeHelper', array($this->wsf));
        
        /* MODELS */
        $this->fieldModel                   = $this->wsf->get('\\AWSPriceCalculator\\Model', true, 'awspricecalculator/Model', 'FieldModel', array($this->wsf));
        $this->calculatorModel              = $this->wsf->get('\\AWSPriceCalculator\\Model', true, 'awspricecalculator/Model', 'CalculatorModel', array($this->wsf));
        $this->settingsModel                = $this->wsf->get('\\AWSPriceCalculator\\Model', true, 'awspricecalculator/Model', 'SettingsModel', array($this->wsf));
    }

    /**
     *
     * Calculates the price using the formulas inserted in the simulator
     *
     * @param $productId : The ID of the product on which to calculate the price
     * @param $data : These are the values of the fields
     * @param $formatPrice : How the price must be formed
     * @param $calculatorId : If the simulator ID is present, it is better to enter it
     * @param $outputResults : For outputs other than price
     * @param $conditionalLogic : Which fields must be hidden and which are displayed
     * @param $errors : If errors are present they are saved here.
     *          If the argument is set, the function returns null in case of errors
     * @param $includeTaxes : If true the taxes will be included
     * @return array
     * @throws \Exception
     */
    public function calculate_price($productId, $data, $formatPrice = true, $calculatorId = null, &$outputResults = null, &$conditionalLogic = null, $checkErrors = false, &$errors = null, &$priceRaw = null, $page = null, $compositeBasePrice = 0, $includeTaxes = false){
        $product        = $this->ecommerceHelper->getProductById($productId);
        
        if(empty($calculatorId)){
            $calculator = $this->get_simulator_for_product($productId);
        }else{
            $calculator = $this->calculatorModel->get($calculatorId);
        }

        $ret            = $this->calculate($calculator, $product, $data, $outputResults, $conditionalLogic, $checkErrors, $errors, $page, $includeTaxes) + $compositeBasePrice;

        $this->setSessionCalculatorProductData($productId, $calculator->id, $data, $outputResults, null);

        $userData       = $this->replaceFieldsData($calculator, $data, $product['price']);
        $userData       = $this->transformUserData($userData, $conditionalLogic);

        $apiParams      = array(
            'errors'        => $errors,
            'priceRaw'      => $ret,
            'product'       => $product,
            'calculator'    => $calculator,
            'data'          => $data,
            'userData'      => $userData,
            'outputResults' => $outputResults,
            'formatPrice'   => $formatPrice,
        );
        
        $errors         = apply_filters('awspc_filter_calculate_price_errors', $errors, $apiParams);

        if($checkErrors == true && count($errors) != 0){
            return null;
        }
        
        /*WPC-PRO*/
        
        /* Access to the API */
        if($this->wsf->getLicense()== 1){  
            if($this->wsf->getTargetPlatform() == "wordpress"){
                $formatPriceFilter	= apply_filters('woo_price_calculator_format_price', $formatPrice, $ret, $product['id'], $calculator->id, $data);
                $ret                    = apply_filters('awspc_filter_calculate_price', $ret, $apiParams);
                $ret                    = apply_filters('woo_price_calculator_calculate_price', $ret, $product['id'], $calculator->id, $data);
            }else if($this->wsf->getTargetPlatform() == "joomla"){
                $dispatcher = \JDispatcher::getInstance();
                        
                $dispatcher->trigger('AwsPriceCalculatorFormatPrice', array(&$formatPriceFilter, $formatPrice, $ret, $product['id'], $calculator->id, $data));
                $dispatcher->trigger('AwsPriceCalculatorCalculatePrice', array(&$ret, $product['id'], $calculator->id, $data));
            }
            
            if($formatPriceFilter !== null){
                $formatPrice	= $formatPriceFilter;
            }
        }
        
        /*/WPC-PRO*/
        
        /* Return of the RAW price */
        $priceRaw   = $ret;
        
        if($formatPrice == true){
            return $this->ecommerceHelper->get_price($ret);
        }else{
            return $ret;
        }
    }

    /**
     * Calculates the price using the formulas inserted in the calculator
     *
     * @param $calculator : The calculator (object)
     * @param $product : Product object
     * @param $data : These are the values of the fields
     * @param $outputResults : For outputs other than price
     * @param $conditionalLogic : Quali campi devono essere nascosti e quali visualizzati
     * @param $errors : If errors are present they are saved here.
     *          If the argument is set, the function returns null in case of errors
     * @param $includeTaxes : If true the taxes will be included
     * @return integer | object | array
     * @throws \Exception
     */
    public function calculate($calculator, $product, $data, &$outputResults = null, &$conditionalLogic = null, $checkErrors = false, &$errors = null, $page = null, $includeTaxes = false){

        $ret                             = 0;
        $eos                             = new \jlawrence\eos\Parser();

        $inputFieldsIds                  = $this->getCalculatorFields($calculator);
        $outputFieldsIds                 = $this->getCalculatorFields($calculator, true);
        
        $fields                          = $this->fieldHelper->get_fields_by_ids($inputFieldsIds);
        $outputFields                    = $this->fieldHelper->get_fields_by_ids($outputFieldsIds);

        $taxRates                        = $this->ecommerceHelper->calculateTotalTaxRates($product);

        $formula                         = $calculator->formula;
        
        $spreadsheetErrors               = array();


        list($vars, $conditionalLogic)   = array_values($this->calculateFieldsData($calculator, $product, $data, $formula));


        if($calculator->type == "simple" || empty($calculator->type)){

            try{
                $ret    = $eos->solveIF($formula, $vars);
                
                if($includeTaxes == true && $calculator->product_page_include_taxes == true){
                    $ret    = $ret+($ret*$taxRates)/100;
                }
                
            }catch(\Exception $ex){
                return $ex;
            }
            
        }else if($calculator->type == "excel"){
            /*WPC-PRO*/
            $arr_fields         = $this->getLoaderCalculatorCells($calculator);
            $objPHPExcel        = $this->getPhpExcelCalculator($calculator);
            $loader_fields      = $this->getCalculatorOptions($calculator);
            
            /* Setting base price for cells selected by user */
            if(!empty($loader_fields['price'])){
                $objPHPExcel->getActiveSheet()->setCellValue($loader_fields['price'], $product['price']);
            }
            
            /* Set the total taxes in the selected cell */
            if(!empty($loader_fields['tax_rate'])){
                if($includeTaxes == true){
                        $objPHPExcel->getActiveSheet()->setCellValue($loader_fields['tax_rate'], $taxRates);
                }else{
                        $objPHPExcel->getActiveSheet()->setCellValue($loader_fields['tax_rate'], 0);
                }
            }

            /* Set the sku of the product in the selected cell */
            if(!empty($loader_fields['sku'])){
                $objPHPExcel->getActiveSheet()->setCellValue($loader_fields['sku'], $product['sku']);
            }


            /* Setting firstly the output fields to be sure the exist for cases that an input fields is conditional on an output field */
            foreach($loader_fields['output'] as $coordinates => $outputFieldId){
                    if(in_array($outputFieldId, $outputFieldsIds) ||
                        ($outputFieldId == $calculator->overwrite_weight) ||
                        ($outputFieldId == $calculator->overwrite_length) ||
                        ($outputFieldId == $calculator->overwrite_width) ||
                        ($outputFieldId == $calculator->overwrite_height)){
                        $outputResults[$outputFieldId]      = $objPHPExcel->getActiveSheet()->getCell($coordinates)->getCalculatedValue();

                        $data['aws_price_calc_'.$outputFieldId] = $outputResults[$outputFieldId] ;
                        list($vars, $conditionalLogic)   = array_values($this->calculateFieldsData($calculator, $product, $data, $formula));
                    }
            }


            //clear cache so after the setting we will be sure that the excel file will recalculate its values
            PHPExcel_Calculation::getInstance($objPHPExcel->getActiveSheet()->getParent())->clearCalculationCache();

            /* TODO: Needs more tests on date problem! */
            foreach($vars as $field_id => $field_value){
                $field_key = str_replace("aws_price_calc_", "", $field_id);
				
                foreach($arr_fields as $coordinates => $fieldId){
                    if($fieldId == $field_key){

                        $field		= $this->fieldModel->get_field_by_id($fieldId);

                        if($field->type == "date" ||
                           $field->type == "datetime" ||
                           $field->type == "time"){

                                $fromFormat	= date_create_from_format($this->fieldHelper->getDateTimeFieldFormat($field), $field_value);

                                if(!empty($fromFormat)){
                                    
                                    if($field->type == "date"){
                                            $toFormat	= "Y-m-d";
                                    }else if($field->type == "datetime"){
                                            $toFormat	= "Y-m-d H:i:s";
                                    }else if($field->type == "time"){
                                            $toFormat	= "H:i:s";
                                    }

                                    $field_value	= date_format($fromFormat, $toFormat);
                                }

                        }
						
                        $objPHPExcel->getActiveSheet()->setCellValue($coordinates, $field_value);
                    }
                }

            }

            /* Adding spreadsheet mapped cell errors */
            if(!empty($loader_fields['error'])){
                foreach($loader_fields['error'] as $coordinates => $fieldId){
                    $spreadsheetError      = $objPHPExcel->getActiveSheet()->getCell($coordinates)->getCalculatedValue();
                    $fieldName             = $this->fieldHelper->getFieldName($fieldId);
					
                    if(!empty($spreadsheetError)){
                        $spreadsheetErrors[$fieldName][]    = $spreadsheetError;
                    }
					
		        }
            }
            
            foreach($loader_fields['output'] as $coordinates => $outputFieldId){
                if($outputFieldId == 'price'){
                    $ret                                = $objPHPExcel->getActiveSheet()->getCell($coordinates)->getCalculatedValue();
                }else{
                    if(in_array($outputFieldId, $outputFieldsIds) ||
                       ($outputFieldId == $calculator->overwrite_weight) ||
                       ($outputFieldId == $calculator->overwrite_length) ||
                       ($outputFieldId == $calculator->overwrite_width) ||
                       ($outputFieldId == $calculator->overwrite_height)){
                        $outputResults[$outputFieldId]      = $objPHPExcel->getActiveSheet()->getCell($coordinates)->getCalculatedValue();

                        $data['aws_price_calc_'.$outputFieldId] = $outputResults[$outputFieldId] ;
                        list($vars, $conditionalLogic)   = array_values($this->calculateFieldsData($calculator, $product, $data, $formula));
                    }
                }
            }
            
            /*/WPC-PRO*/
        }

        /*
        $debugFile  = $this->wsf->getUploadPath('debug/' . date("ymd_his") . ".xls");
        $objWriter  = new \PHPExcel_Writer_Excel2007($objPHPExcel);
        $objWriter->save($debugFile);
        */
        
        /* Checking if $errors is set */
        if($checkErrors == true) {
            $errors                 = $this->checkErrors($calculator, $vars, false, $page, $spreadsheetErrors);

            if(count($errors) != 0){
                return null;
            }
        }
        
        return $ret;
    }
    
    /**
     * Get a formatted date cell
     *
     * @param $phpExcel: PHPExcel Object
     * @param $coordinates: The coordinates of the cell
     * @param $format: The date format, default: Y-m-d
     * @return date
     */
    public function getCellFormattedDate($phpExcel, $coordinates, $format = 'Y-m-d'){
        $cell       = $phpExcel->getActiveSheet()->getCell($coordinates);
        return date($format, \PHPExcel_Shared_Date::ExcelToPHP($cell->getCalculatedValue()));
    }
    
    /**
     * Returns all the options of a calculator
     *
     * @param $calculator : The calculator (object)
     * @return array
     */
    public function getCalculatorOptions($calculator){
        return json_decode($calculator->options, true);
    }
    
    /**
     * Returns the PHPExcel Object from a calculator
     *
     * @param $calculator : The calculator (object)
     * @return object
     */
    public function getPhpExcelCalculator($calculator){
        $loader_fields      = $this->getCalculatorOptions($calculator);

        $filePath       = $this->getSpreadsheetUploadPath($loader_fields['file']);
        $objReader      = \PHPExcel_IOFactory::createReader(\PHPExcel_IOFactory::identify($filePath));
        $objReader->setReadDataOnly(true);
        $objPHPExcel    = $objReader->load($filePath);
        $objWorksheet   = $objPHPExcel->setActiveSheetIndex($loader_fields['worksheet']);

        return $objPHPExcel;
    }

    /**
     * Transforming the user data
     * Sort the variables in descending order of length, for example:
     *
     * Array
     *  (
     *       [woo_price_calc_14] => 1500
     *       [woo_price_calc_1] => 12
     *       [woo_price_calc_5] => 100
     *       [woo_price_calc_6] => 21400
     *       [woo_price_calc_7] => 3300
     *       [price] => 23316
     *   )
     *
     * This will replace the longer to the smaller one because
     * it could happen that part of "woo_price_calc_14" is mistakenly replaced by
     * "woo_price_calc_1"
     *
     * @param array $userData
     * @param array $conditionalLogic
     * @param string $formula
     * @return array
     */
    public function transformUserData($userData, $conditionalLogic, &$formula = null){
        
        /*
         * Sort the variables in descending order of length, for example:
         * 
         * Array
            (
                [woo_price_calc_14] => 1500
                [woo_price_calc_1] => 12
                [woo_price_calc_5] => 100
                [woo_price_calc_6] => 21400
                [woo_price_calc_7] => 3300
                [price] => 23316
            )
         * 
         * This will replace the longer to the smaller one because 
         * it could happen that part of "woo_price_calc_14" is mistakenly replaced by 
         * "woo_price_calc_1"
         */
        uksort($userData, function($a, $b){return strlen($a) < strlen($b);});
        
        foreach($userData as $var_key => $var_value){
            $fieldId                        = str_replace("aws_price_calc_", "", $var_key);
            $field                          = $this->fieldModel->get_field_by_id($fieldId);
            
            if(!empty($field)){
                $fieldOptions                   = json_decode($field->options, true);
            }
            
            $conditionalLogic[$fieldId]     = (isset($conditionalLogic[$fieldId])?$conditionalLogic[$fieldId]:1);
            
            if($conditionalLogic[$fieldId] == 0){
                if($field->type == "numeric"){
                        $value		= (empty($fieldOptions['numeric']['default_value'])?0:$fieldOptions['numeric']['default_value']);
                }else{
                        $value          = 0;
                }
				
            }else if(empty($var_value)){
                $value              = 0;
            }else{
                $value              = $var_value;
            }
            
            $userData[$var_key]         = $value;
            
            /* Replacing only calculable fields */
            if(!empty($field)){
                if(in_array($field->type, $this->fieldHelper->getCalculableFieldTypes())){
                    $formula = str_replace("\${$var_key}", (float)$value, $formula);
                }
            }
        }
        
        return $userData;
    }
    
    /**
     * Execute by an ajax call to render real-time calculation of the price
     *
     * @param string $action , type of action
     * @param string $productId , the identified number of a product
     * @param string $calculatorId , the identified number of a calculator
     * @param string $cartItemKey , the id of an item in the cart
     * @param integer $quantity , quantity of the selected product
     * @return void
     * @throws \Exception
     */
    public function calculatePriceAjax($action, $productId, $calculatorId, $cartItemKey = null, $quantity = null, $page = null, $compositeBasePrice = 0){
        global $woocommerce;
        
        $post               = $this->wsf->getPost();

        if(!empty($productId) && !empty($calculatorId)){
            if($action == 'add_cart_item'){
            
                $this->calculate_price($productId, $post, false, $calculatorId, $outputFieldsData);
                
                $cartData   = array(
                        'simulator_id'              => $calculatorId,
                        'simulator_fields_data'     => $post,
                        'output_fields_data'        => $outputFieldsData,
                );
                                
                $woocommerce->cart->add_to_cart($productId, $quantity, 0, array(), $cartData);
                
                die(json_encode(array('status' => true)));
                
            }else if($action == 'edit_cart_item'){
            
                
                /* Calculates the output values */
                $this->calculate_price($productId, $post, false, $calculatorId, $outputFieldsData);
                
                $cartData   = array(
                        'simulator_id'              => $calculatorId,
                        'simulator_fields_data'     => $post,
                        'output_fields_data'        => $outputFieldsData,
                );
                
                if($this->ecommerceHelper->getTargetEcommerce() == "woocommerce"){
                    $woocommerce->cart->remove_cart_item($cartItemKey);
                    $woocommerce->cart->add_to_cart($productId, $quantity, 0, array(), $cartData);
                }else if($this->ecommerceHelper->getTargetEcommerce() == "hikashop"){
                    $cartClass          = \hikashop_get('class.cart');
                    $cart               = $cartClass->get(null);

                    $cart->cart_products[$cartItemKey]->awspricecalculator    = json_encode($cartData);

                    $cartClass->save($cart);
                }
            
            
            }else{

                $calculator             = $this->calculatorModel->get($calculatorId);
                $simulatorFieldsIds     = $this->get_simulator_fields($calculatorId);
                $fields                 = $this->fieldHelper->get_fields_by_ids($simulatorFieldsIds);
                $price                  = $this->calculate_price($productId, $post, true, $calculatorId, $outputResults, $conditionalLogic, true, $errors, $priceRaw, $page, $compositeBasePrice, true);
                
                $price                  = $this->getPriceWithPrefixAndSuffix($price);

                $outputFields           = $this->getOutputResultsPart($calculator, $outputResults);
                $product                = $this->ecommerceHelper->getProductById($productId);
                $taxRates               = $this->ecommerceHelper->calculateTotalTaxRates($product);
                
                $userData               = $this->replaceFieldsData($calculator, $post, $product['price']);
                $userData               = $this->transformUserData($userData, $conditionalLogic);

                $response               = array(
                    'errorsCount'       => count($errors),
                    'errors'            => $errors,
                    'price'             => utf8_encode(htmlentities($price)),
                    'priceRaw'          => $priceRaw,
                    'outputFields'      => $outputFields,
                    'conditionalLogic'  => $conditionalLogic,
                    'quantity'          => $quantity,
                );
                
                $response               = apply_filters('awspc_filter_calculate_price_ajax_response', $response, array(
                    'productId'         => $productId,
                    'calculator'        => $calculator,
                    'fields'            => $fields,
                    'postData'          => $post,
                    'userData'          => $userData,
                    'conditionalLogic'  => $conditionalLogic,
                    'outputResults'     => $outputResults,
                    'errors'            => $errors,
                    'price'             => $price,
                    'priceRaw'          => $priceRaw,
                    'taxRates'          => $taxRates,
                ));
                
                die(json_encode($response));
            }
        }

        exit(-1);
    }

    /**
     * Return the simulator used for a product
     *
     * @param string $product_id , the identification number of a product
     * @return object | null
     */
    public function get_simulator_for_product($product_id){
        $simulators = $this->calculatorModel->get_list();

        /* Priority to individual selected products (have precedence) */
        foreach($simulators as $simulator){
            $products               = json_decode($simulator->products, true);
            
            /* Check if that specific product has been selected */
            if(!empty($products)){
                
                if(in_array($product_id, $products)){
                    return $simulator;
                }

            }
        }
        
        /* Evaluating the product categories */
        foreach($simulators as $simulator){

            $productCategories      = json_decode($simulator->product_categories, true);

            /* Check if a category is selected that contains the product */
            if(!empty($productCategories)){
                $terms      = get_the_terms($product_id, 'product_cat');
                $terms      = (empty($terms))?array():$terms;

                
                foreach ($terms as $term) {

                    if(in_array($term->term_id, $productCategories)){
                        return $simulator;
                    }

                    /* Check all sub-categories */
                    
                    foreach($productCategories as $productCategoryId){
                        if(term_is_ancestor_of($productCategoryId, $term->term_id, 'product_cat') == true){
                            return $simulator;
                        }
                    }
                }
                
            }
        
        }

        return null;
    }
    
    /**
     * Returns all the Fields as Object of a calculator
     *
     * @param $calculator : The calculator (object)
     * @param $outputFields : If should returns output fields or input fields
     * @param $type : Type of the fields to return
     * @return array
     */
    public function getCalculatorFieldEntities($calculator, $outputFields = false, $type = null){
        $calculatorFieldIds         = $this->getCalculatorFields($calculator, $outputFields);
        $calculatorFieldEntities    = array();
        
        foreach($calculatorFieldIds as $calculatorFieldId){
            $fieldEntity        = $this->fieldModel->get_field_by_id($calculatorFieldId);
            
            if($type === null){
                $calculatorFieldEntities[]      = $fieldEntity;
            }else{
                if($fieldEntity->type == $type){
                    $calculatorFieldEntities[]      = $fieldEntity;
                }
            }
        }
        
        return $calculatorFieldEntities;
    }
    
    /**
     * Return the fields used by a calculator
     *
     * @param object $calculator, a specific calculator
     * @param bool $outputFields, demonstrate if a calculator has output fields
     * @return array | JSON
     */
    public function getCalculatorFields($calculator, $outputFields = false){
        if($outputFields === true){
            return json_decode($calculator->output_fields, true);
        }else{
            if($calculator->type == "simple" || empty($calculator->type)){
                return json_decode($calculator->fields, true);
            }else if($calculator->type == "excel"){
                /*WPC-PRO*/
                $ret = array();

                $fields         = json_decode($calculator->fields, true);

                //PMR: to supress a warning.
                if($fields === NULL){
                    return $ret; 
                }
                //
                foreach($fields as $coordinates => $fieldId){
                    if(!in_array($fieldId, $ret)){
                        $ret[] = $fieldId;
                    }
                }

                return $ret;
                /*/WPC-PRO*/
            }
        }
    }
    
    /**
     * Returns the fields used by a simulator
     * 
     * DEPRECATED
     * @param string $calculatorId, the identification number of a specific calculator
     * @param bool $outputFields, demonstrate the existence of output fields
     * @return array | JSON
     */
    public function get_simulator_fields($calculatorId, $outputFields = false){
        $calculator = $this->calculatorModel->get($calculatorId);

        return $this->getCalculatorFields($calculator, $outputFields);
    }

    /**
     * Return the selected products in the simulator
     *
     * @param string $simulator_id, the identification number of a specific simulator
     * @return array
     */
    public function get_simulator_products($simulator_id){
        $simulator = $this->calculatorModel->get($simulator_id);

        return json_decode($simulator->products);
    }
    
    /**
     * Returns the fields used by a simulator
     *
     * @param object $calculator
     * @return array
     */
    public function getLoaderCalculatorCells($calculator){
        $ret            = array();
        $loaderFields   = json_decode($calculator->options, true);
        
        //PMR: to supress a warning.
        if($loaderFields['input'] === NULL){
        	return $ret;
        }
        //
        foreach($loaderFields['input'] as $coordinates => $fieldId){
            $ret[$coordinates]      = $fieldId;
        }
        
        return $ret;
    }

    /**
     * Download the uploaded file from the order
     *
     * @param $fileName: The name of the file to download
     * @param $filePath: The filepath of the file to download
     * @return void
     */
    public function getUploadedFileOrder($fileName, $filePath){
        if(file_exists($filePath)){
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header("Content-Disposition: attachment; filename=\"{$fileName}\"");
            header('Expires: 0');

            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Pragma: public');
            header('Content-Length: ' . filesize($filePath));
            ob_clean();
            flush();
            readfile($filePath);
            exit;
        }

        die();

    }


    /**
     * Download spreadsheet
     *
     * Used to give the possibility to the admin to download an used spreadsheet
     *
     * @param string $simulatorId
     * @return void
     */
    public function downloadSpreadsheet($simulatorId){
        
        /*
        if (!current_user_can('manage_options')){
            die("WPC: Access denied!");
        }
        */
        $calculator        = $this->calculatorModel->get($simulatorId);
        
        if($calculator->type == 'excel'){
            $calculatorOptions  = json_decode($calculator->options, true);
            $file               = $calculatorOptions['file'];
            $filename           = $calculatorOptions['filename'];
            $filePath           = $this->getSpreadsheetUploadPath($file);
            
            if(file_exists($filePath)){
                header('Content-Description: File Transfer');
                header('Content-Type: application/octet-stream');
                header("Content-Disposition: attachment; filename=\"{$filename}\"");
                header('Expires: 0');
                header('Cache-Control: must-revalidate');
                header('Pragma: public');
                header('Content-Length: ' . filesize($filePath));
                readfile($filePath);
                exit;
            }
        }
        
        die("WPC: Nothing to do");
    }
    
    /**
     * Returns the path of the spreadsheet
     *
     * @param string $file
     * @return string
     */
    public function getSpreadsheetUploadPath($file){
        return $this->wsf->getUploadPath("docs/{$file}");
    }
    
    /**
     * Check if calculator errors should be checked or not
     *
     * @param $field : The field (object)
     * @param $conditionalLogic : Conditional logic for the field
     * @param $page : Current page the user is surfing
     * @return bool
     * @throws \Exception
     */
    public function hasToCheckFieldErrors($field, $conditionalLogic, $page){

        if($conditionalLogic[$field->id] == true){
            if(empty($field->check_errors) || $field->check_errors == "always"){
                return true;
            }else if($field->check_errors == "add-to-cart"){
                if($page == "product"){
                    return false;
                }else{
                    return true;
                }
            }
        }else{
            return false;
        }

    }
    
    /**
     * Checks the simulator errors
     *
     * @param object $calculator , the specific given calculator
     * @param array $fieldValues , the values contained in the fields
     * @param bool $replaceFieldsData
     * @return array
     * @throws \Exception
     */
    public function checkErrors($calculator, $fieldValues = array(), $replaceFieldsData = false, $page = null, $additionalErrors = array()){

        $errors                         = array();
        $values                         = array();
        
        $fieldIds                       = array_merge($this->getCalculatorFields($calculator), $this->getCalculatorFields($calculator, true));
        $fields                         = $this->fieldHelper->get_fields_by_ids($fieldIds);
       
        if($replaceFieldsData == true){
            $replacedValues       = $this->replaceFieldsData($calculator, $fieldValues);
        }

        foreach($fields as $field_key => $field_value){
            $fieldId                            = $this->fieldHelper->getFieldName($field_value->id);
            
            if($replaceFieldsData == false){
                $values[$fieldId]           = (isset($fieldValues[$fieldId]))?$fieldValues[$fieldId]:null;
            }else{
                $values[$fieldId]           = $replacedValues[$fieldId];
            }   
        }
        
        $conditionalLogic               = $this->calculateConditionalLogic($calculator, $fields, $values);

        foreach($fields as $field_key => $field_value){
            $fieldId                            = $this->fieldHelper->getFieldName($field_value->id);
            $options                            = json_decode($field_value->options, true);
            $value                              = $values[$fieldId];

            /* Only checking errors if field is displayed */
            if($this->hasToCheckFieldErrors($field_value, $conditionalLogic, $page) == true){
                
                /* Additional Errors */
                if(isset($additionalErrors[$fieldId])){
                    foreach($additionalErrors[$fieldId] as $additionalFieldError){
                        $errors[$fieldId][]     = $additionalFieldError;
                    }
                }

                /* CAMPO OBBLIGATORIO? */
                if($field_value->required == true){
                    if($value === "" || $value === 0 || $value === "0"){

                        /* Visualizzazione di un messaggio di default */
                        if(empty($field_value->required_error_message)){
                            $errors[$fieldId][]       = $this->wsf->mixTrans('aws.field.error_message.required_error_message', array(
                                'fieldLabel'    => $field_value->label
                            ));
                        }else{
                            $errors[$fieldId][]       = $this->wsf->userTrans($field_value->required_error_message, array(
                                'fieldLabel'    => $field_value->label
                            ));
                        }
                    }
                }

                /* Checking data */
                if($field_value->type == "text"){
                    if(!empty($options['text']['regex'])){
                        preg_match($options['text']['regex'], $value, $matches);

                        if(count($matches) == 0){

                            /* Visualizzazione di un messaggio di default */
                            if(empty($options['text']['regex_error'])){
                                $errors[$fieldId][]       = $this->wsf->trans('aws.field.error_message.regex_error', array(
                                    'fieldLabel'    => $field_value->label
                                ));
                            }else{
                                $errors[$fieldId][]       = $this->wsf->userTrans($options['text']['regex_error'], array(
                                    'fieldLabel'    => $field_value->label
                                ));
                            }

                        }
                    }
                    
                }else if($field_value->type == "numeric"){
                    /* MAX VALUE can be also equal 0, so "empty" is not ok */
                    if($options['numeric']['max_value'] != ""){
                        if($value > $options['numeric']['max_value']){

                            /* Showing default message */
                            if(empty($options['numeric']['max_value_error'])){
                                $errors[$fieldId][]       = $this->wsf->trans('aws.field.error_message.max_value_error', array(
                                    'fieldLabel'    => $field_value->label,
                                    'maxValue'      => $options['numeric']['max_value'],
                                ));
                            }else{
                                $errors[$fieldId][]       = $this->wsf->userTrans($options['numeric']['max_value_error'], array(
                                    'fieldLabel'    => $field_value->label,
                                    'maxValue'      => $options['numeric']['max_value'],
                                ));
                            }

                        }
                    }

                    /* MIN VALUE can be also equal to 0, so "empty" is not ok */
                    if($options['numeric']['min_value'] != ""){
                        if($value < $options['numeric']['min_value']){

                            /* Visualizzazione di un messaggio di default */
                            if(empty($options['numeric']['min_value_error'])){
                                $errors[$fieldId][]       = $this->wsf->trans('aws.field.error_message.min_value_error', array(
                                    'fieldLabel'    => $field_value->label,
                                    'minValue'      => $options['numeric']['min_value'],
                                ));
                            }else{
                                $errors[$fieldId][]       = $this->wsf->userTrans($options['numeric']['min_value_error'], array(
                                    'fieldLabel'    => $field_value->label,
                                    'minValue'      => $options['numeric']['min_value'],
                                ));
                            }

                        }
                    }
                }
            }
        }
        
        return $errors;
    }
    
    /**
     * Check that if the price in the selected products is null, 
     * displays a warning message that says that
     * you need to enter a price to display the simulator
     *
     * @param string $productIds, the id of the specific product
     * @return array
     */
    public function checkProductPrices($productIds){
        $warnings   = array();
        
        foreach($productIds as $productId){
            $product            = $this->ecommerceHelper->getProductById($productId);
            $price              = $product['price'];
            $title              = $product['name'];
            
            if($price == ''){
                $warnings[]         = $this->wsf->trans("wpc.calculator.form.price.warning", array(
                    'productTitle'      => $title,
                ));
            }

        }

        return $warnings;
    }
    
    /**
     * Returns the list of fields ordered by user selection
     *
     * @param array $selectedFields, list of the selected fields
     * @param string $mode
     * @return array
     */
    public function orderFields($selectedFields, $mode = '', $fields = null){
        $orderedFields      = array();
        
        if($fields === null){
            $fields             = $this->fieldModel->get_field_list($mode);
        }
        
        if(!empty($selectedFields)){
            foreach($selectedFields as $fieldId){
                $orderedFields[]        = $this->fieldModel->get_field_by_id($fieldId);
            }
        }

        foreach($fields as $field){
            if(!in_array($field, $orderedFields)){
                $orderedFields[]    = $field;
            }
        }
        
        return $orderedFields;
    }
    
    /**
     * TODO : Verify that it works correctly with both Joomla and Wordpress
     * Check that two or more calculators are not assigned to the same product
     *
     * @param array $record
     * @param string $excludeId
     * @return array
     */
    public function checkCalculatorDuplicate($record, $excludeId){
        $errors           = array();
        
        $check_simulators                   = $this->calculatorModel->get_list();
        $productCategories                  = $this->ecommerceHelper->getProductCategories();
        
        $checkCalculatorProducts            = array();
        $checkCalculatorProductCategories   = array();
        
        /* Make the list of all products */
        foreach($check_simulators as $check_simulator){
            if($check_simulator->id != $excludeId){
                
                $calculatorProducts                 = json_decode($this->wsf->isset_or($check_simulator->products, "{}"), true);
                $calculatorProductCategories        = json_decode($this->wsf->isset_or($check_simulator->product_categories, "{}"), true);
                
                foreach($calculatorProductCategories as $productCategoryId){
                    $checkCalculatorProductCategories = array_merge($checkCalculatorProductCategories, $this->ecommerceHelper->getCategoryProductsByCategoryId($productCategoryId));
                }
                
                $checkCalculatorProducts            = array_merge($checkCalculatorProducts, $calculatorProducts);
            }
        }

        $checkCalculatorProducts            = array_unique($checkCalculatorProducts);
        $checkCalculatorProductCategories   = array_unique($checkCalculatorProductCategories);
        
        /* END */
               
        
        /*
         * Check if the simulators used are among the selected products and categories of the simulator
         */
        if(!empty($checkCalculatorProducts) || !empty($checkCalculatorProductCategories)){

            $checkReqProducts               = $record['products'];
            $checkReqProductCategories      = array();
            
            foreach($record['product_categories'] as $productCategoryId){
                $checkReqProductCategories = array_merge($checkReqProductCategories, $this->ecommerceHelper->getCategoryProductsByCategoryId($productCategoryId));
            }
            
            $checkReqProducts           = array_unique($checkReqProducts);
            $checkReqProductCategories  = array_unique($checkReqProductCategories);
            
            if(!empty($checkReqProducts) || !empty($checkReqProductCategories)){
                
                /* Product control */
                foreach($checkReqProducts as $check_req_product){
                    if(in_array($check_req_product, $checkCalculatorProducts)){
                        $check_product      = $this->ecommerceHelper->getProductById($check_req_product);
                        $checkProductTitle  = $check_product['name'];
                        
                        $errors[]           = $this->wsf->trans("calculator.form.error.product_duplicate", array(
                            'productTitle'   => $checkProductTitle,
                        ));
                    }
                }
                
                /* Control of the products of the categories */
                foreach($checkReqProductCategories as $check_req_product){
                    if(in_array($check_req_product, $checkCalculatorProductCategories)){

                        $errors[]           = $this->wsf->trans("calculator.form.error.categories_duplicate");
                        
                        break;
                    }
                }
            }
        }
        
        return $errors;
    }
    
    /**
     * It takes the values of the fields entered by the customers
     *
     * @param object $calculator
     * @param bool $replaceFieldsData
     * @return array
     */
    public function getFieldsFromRequest($productId, $calculator, $replaceFieldsData = false, $tryInSessionAlso = false){
        $simulator_fields_ids           = $this->get_simulator_fields($calculator->id);
        
        $fields                         = $this->fieldHelper->get_fields_by_ids($simulator_fields_ids);
        $fieldsData                     = array();
        
        $sessionCalculatorProductData   = $this->getSessionCalculatorProductData($productId);
        
        foreach($fields as $field_key => $field_value){
            $fieldRequestKey                    = "aws_price_calc_{$field_value->id}";
            $options                            = json_decode($field_value->options, true);

            if($tryInSessionAlso === true && !isset($_POST[$fieldRequestKey])){
                $value    = $sessionCalculatorProductData['simulator_fields_data'][$fieldRequestKey];
            }else{
                $value    = $this->wsf->requestValue($fieldRequestKey);
            }

            /* Adjusting values */
            $fieldsData[$fieldRequestKey] = $value;
        }
        
        if($replaceFieldsData == true){
            $fieldsData     = $this->replaceFieldsData($calculator, $fieldsData);
        }
        
        return array(
            'fields'            => $fields,
            'data'              => $fieldsData,
        );
    }

    /**
     * Replace the value of the field
     *
     * @param object $fieldEntity , the attributes of an input field
     * @param parent $rawValue, the new value to be assigned
     * @return parent
     * @throws \Exception
     */
    public function replaceFieldValue($calculatorEntity, $fieldEntity, $rawValue){

        if($fieldEntity->mode == 'input'){
            $options    = json_decode($fieldEntity->options, true);
            $value      = $this->wsf->isset_or($rawValue, 0);

            if($fieldEntity->type == "checkbox"){
                if($value === "on" || $value == 1){
                    return $options['checkbox']['check_value'];
                }else{
                    return $options['checkbox']['uncheck_value'];
                }
            }else if($fieldEntity->type == "numeric"){
                $value = str_replace($options['numeric']['decimal_separator'], ".", $value);

                //If field is empty, then set it as 0
                if(empty($value)){
                    return 0;
                }else{
                    return $value;
                }

            }else if($fieldEntity->type == "radio"){
                $itemsData         = json_decode($options['radio']['radio_items'], true);

                if(!empty($itemsData)){
                    foreach($itemsData as $index => $item){
                        if($item['id'] == $value){
                            return $item['value'];
                        }
                    }
                }
            }else if($fieldEntity->type == "imagelist"){
                $itemsData         = json_decode($options['imagelist']['imagelist_items'], true);

                if(!empty($itemsData)){
                    foreach($itemsData as $index => $item){
                        if($item['id'] == $value){
                            return $item['value'];
                        }
                    }
                }
            }else if($fieldEntity->type == "picklist"){
                $itemsData         = json_decode($options['picklist_items'], true);

                if(!empty($itemsData)){
                    foreach($itemsData as $index => $item){
                        if($item['id'] == $value){
                            return $item['value'];
                        }
                    }
                }

            }else if($fieldEntity->type == "videolist"){
                $itemsData         = json_decode($options['videolist']['videolist_items'], true);

                if(!empty($itemsData)){
                    foreach($itemsData as $index => $item){
                        if($item['id'] == $value){
                            return $item['value'];
                        }
                    }
                }

            }else if($fieldEntity->type == "upload"){

                return $value;

            }else if($fieldEntity->type == "text" ||
                     $fieldEntity->type == "date" ||
                     $fieldEntity->type == "time" ||
                     $fieldEntity->type == "datetime"){

                return $rawValue;

            }else{
                throw new \Exception("CalculatorHelper::replaceFieldValue field type {$fieldEntity->type} not supported!");
            }
        }
        
        return $rawValue;
    }
    
    /*
     * Replace the value for each field
     *
     * @param object $calculator
     * @param array $data
     * @param integer $productPrice
     * @return array
     * @throws \Exception
     */
    public function replaceFieldsData($calculator, $data, $productPrice = null){

        $vars                   = array();
        $fieldIds               = array_merge($this->getCalculatorFields($calculator), $this->getCalculatorFields($calculator, true));
        $fields                 = $this->fieldHelper->get_fields_by_ids($fieldIds);

        if($productPrice !== null){
            $vars['price']      = $productPrice;
        }




        foreach($fields as $field_key => $field_value){

            if(!empty($field_value)){
                $fieldId    = $this->fieldHelper->getFieldName($field_value->id);
                
                if(!isset($data[$fieldId])){
                    $data[$fieldId]     = null;
                }
                $value      = $this->replaceFieldValue($calculator, $field_value, $data[$fieldId]);


                $vars[$fieldId] = $value;
            }
        }

        return $vars;
    }

    /**
     * Calculates the field's data
     *
     * @param object $calculator, the calculator used in the given product
     * @param object $product, the product in which we want to calculate the price
     * @param array $data, the data inserted by the customer
     * @param string $formula, the formula used
     * @return array
     * @throws \Exception
     */
    public function calculateFieldsData($calculator, $product, $data, &$formula = null){



        $inputFieldsIds         = array_merge($this->getCalculatorFields($calculator), $this->getCalculatorFields($calculator, true));
        $fields                 = $this->fieldHelper->get_fields_by_ids($inputFieldsIds);
        $userData               = $this->replaceFieldsData($calculator, $data, $product['price']);
        $conditionalLogic       = $this->calculateConditionalLogic($calculator, $fields, $userData);
        $userData               = $this->transformUserData($userData, $conditionalLogic, $formula);


        return array(
            'data'              => $userData,
            'conditionalLogic'  => $conditionalLogic,
        );
    }
    
    /**
     * Calculate what field should be displayed or hidden
     *
     * @param object $calculator, the calculator used int the given product
     * @param array $calculatorFields, the input fileds used by tha specific calculator
     * @param array $fieldValues, the values that the input fields holds
     * @return array
     */
    public function calculateConditionalLogic($calculator, $calculatorFields, $fieldValues){

        $calculatorConditionalLogic     = json_decode($calculator->conditional_logic, true);
        $conditionalFieldsLogic         = array();

        /*WPC-PRO*/
        if($this->wsf->getLicense() == 1){
            if($calculatorConditionalLogic['enabled'] == true){
                $hideFields         = (empty($calculatorConditionalLogic['hide_fields']))?array():$calculatorConditionalLogic['hide_fields'];
                $fieldFiltersSql    = $calculatorConditionalLogic['field_filters_sql'];

                foreach($calculatorFields as $calculatorField){
                    $calculatorFieldId     = $calculatorField->id;

                    $displayField          = false;
                    if(!in_array($calculatorFieldId, $hideFields)){
                        $displayField   = true;
                    }

                    $displayField           = (int)$displayField;
                    $inverseDisplayField    = (int)!$displayField;

                    $fieldFiltersSql[$calculatorFieldId]            = str_replace('aws_price_calc_', ':aws_price_calc_', $fieldFiltersSql[$calculatorFieldId]);
                    
                    /*
                     * Preparing the query for MySQL. If it has decimal values I convert the string from:
                     * :aws_price_calc_31 >= '100'
                     * To:
                     * :aws_price_calc_31 >= 100
                     */
                    $fieldFiltersSql[$calculatorFieldId]            = preg_replace("/:aws_price_calc_([0-9]+) ([!|=|<|>]+) '([0-9]+\.?[0-9]?)'$/", ':aws_price_calc_$1 $2 $3', $fieldFiltersSql[$calculatorFieldId], -1);

                    //echo "{$calculatorFieldId}: SELECT IF({$fieldFiltersSql[$calculatorFieldId]}, {$displayField}, {$inverseDisplayField}) AS query_result\n";

                    if(!empty($fieldFiltersSql[$calculatorFieldId])){
                        $test   = "SELECT IF({$fieldFiltersSql[$calculatorFieldId]}, {$inverseDisplayField}, {$displayField}) AS query_result";
                        $queryCalculateConditionalLogic                 = $this->databaseHelper->getRow("SELECT IF({$fieldFiltersSql[$calculatorFieldId]}, {$inverseDisplayField}, {$displayField}) AS query_result", $fieldValues);
                        $conditionalFieldsLogic[$calculatorFieldId]     = $queryCalculateConditionalLogic->query_result;
                    }else{
                        $conditionalFieldsLogic[$calculatorFieldId]     = $displayField;
                    }

                }
            }else{
                foreach($calculatorFields as $calculatorField){
                    $conditionalFieldsLogic[$calculatorField->id] = true;
                }
            }
        }
        /*/WPC-PRO*/

        /* WPC-FREE */
        if($this->wsf->getLicense() == 0){
            foreach($calculatorFields as $calculatorField){
                $conditionalFieldsLogic[$calculatorField->id] = true;
            }
        }
        /* /WPC-FREE */
        
        return $conditionalFieldsLogic;
    }
    
    /**
     * Returns the default values of all fields in a calculator
     *
     * @param string $calculatorId, identification number of the calculator
     * @param bool $returnKey
     * @return array
     */
    public function getFieldsDefaultValue($calculator, $returnKey = false){
        $simulatorFieldsIds                     = $this->get_simulator_fields($calculator->id);
        return $this->getFieldsDefaultValueByFieldIds($calculator, $simulatorFieldsIds, $returnKey);
    }
    

    /**
     * Returns the default values from the IDs of the individual fields
     *
     * @param string $fieldIds, identification number of the field
     * @param bool $returnKey
     * @return array
     */
    public function getFieldsDefaultValueByFieldIds($calculator, $fieldIds, $returnKey = false){
        $simulatorFields                        = $this->fieldHelper->get_fields_by_ids($fieldIds);
        $fieldsData                             = array();

        foreach($simulatorFields as $fieldKey => $field){
            if(!empty($field)){
                $fieldId    			= $this->fieldHelper->getFieldName($field->id);
                $defaultValue			= $this->fieldHelper->getFieldDefaultValue($field, $calculator, $returnKey);

                $fieldsData[$fieldId]	= $defaultValue;
            }
        }
        return $fieldsData;
    }
    
    /**
     * Returns the values of the fields that the calculator should have 
     * if no parameters have yet been entered by the visitor
     *
     * @param string $calculatorId, identification number of a specific calculator
     * @return array
     */
    public function getStartupFieldValues($calculator){
        $simulatorFieldsIds                     = $this->get_simulator_fields($calculator->id);
        $simulatorFields                        = $this->fieldHelper->get_fields_by_ids($simulatorFieldsIds);
        $fieldsData				= array();

        foreach($simulatorFields as $fieldKey => $field){
            if(!empty($field)){
                $fieldId    			= $this->fieldHelper->getFieldName($field->id);
                $fieldOptions                   = json_decode($field->options, true);

                $startupValue			= $this->fieldHelper->getFieldDefaultValue($field, $calculator);
                
                if($field->type == 'numeric'){
                    if(!empty($fieldOptions['numeric']['min_value']))
                        $startupValue               = $fieldOptions['numeric']['min_value'];
                }

                $fieldsData[$fieldId]	= $startupValue;
            }
        }
        
        return $fieldsData;
    }
        
    /**
     * Get the output fields
     *
     * @param object $calculator, the calculator attached to the current product
     * @param array $outputResults, output results
     * @return string
     */
    public function getOutputResultsPart($calculator, $outputResults){
        $calculatorOutputFields = json_decode($calculator->output_fields, true);            
        $outputFields           = array();
        
        if(!empty($outputResults)){
            foreach($outputResults as $fieldId => $fieldResult){
                if(in_array($fieldId, $calculatorOutputFields)){
                    $field                          = $this->fieldModel->get_field_by_id($fieldId);
                    $fieldName                      = $this->fieldHelper->getOutputFieldName($fieldId);
                    

                    $isFieldVisibleOnProductPage    = $this->isFieldVisibleOnProductPage($calculator, $field, null);
                    $value                          = $this->fieldHelper->getOutputResult($field, $fieldResult);

                    if($isFieldVisibleOnProductPage == true){
                        $outputFields[$fieldId]         = array(
                            'fieldName'     => $fieldName,
                            'field'         => $field,
                            'value'         => $value,
                            'fieldResult'   => $fieldResult,
                        );
                    }
                }
            }
        }
        
        return $outputFields;
    }    
    
    /**
     * Generate the name of the random file for Excel sheets
     *
     * @return string
     */
    public function generateFileName(){
       return md5('unique_salt' . time()); 
    }
    
    /**
     * Update the Json filters of the conditional logic with new field IDs
     *
     * @param string $item
     * @param string $key
     * @param array $params
     * @return void
     */
    public function updateConditionalLogicJsonFilters(&$item, $key, $params){
        $fieldMappingIds        = $params['fieldMappingIds'];
  
        if($key == 'id'){
            $item       = $fieldMappingIds[$item];
        }else if($key == 'field'){
            $fieldId    = str_replace("aws_price_calc_", "", $item);
            $item       = "aws_price_calc_{$fieldMappingIds[$fieldId]}";
        }

    }
    
    /**
     * Update the Sql filters of the conditional logic with new field IDs
     *
     * @param array $sqlFieldFilters
     * @param array $fieldMappingIds
     * @return array
     */
    public function updateConditionalLogicSqlFilters($sqlFieldFilters, $fieldMappingIds){
        $retSqlFieldFilters     = array();
        
        /* Avoid replacement bugs */
        foreach($sqlFieldFilters as $fieldId => $sqlFieldFilter){
            $sqlFieldFilters[$fieldId]      = str_replace("aws_price_calc_", "tmp_", $sqlFieldFilter);
        }
        
        /* Replace the ID of the old field with the new one in every single filter */
        foreach($sqlFieldFilters as $fieldId => $sqlFieldFilter){
            $newFieldFilterId                         = $fieldMappingIds[$fieldId];
            $retSqlFieldFilters[$newFieldFilterId]    = $sqlFieldFilter;
            
            foreach($fieldMappingIds as $oldFieldMappingId => $newFieldMappingId){
                $retSqlFieldFilters[$newFieldFilterId]    = str_replace("tmp_{$oldFieldMappingId}", "aws_price_calc_{$newFieldMappingId}", $retSqlFieldFilters[$newFieldFilterId]);
            }
            

        }
        
        return $retSqlFieldFilters;
    }
    
    /**
     * Check that the import ZIP is compatible with the current version
     *
     * @param string $filePath
     * @return array | false
     */
    public function checkImportZipVersion($filePath){
        $zip                 = zip_open($filePath);
        
        $versionFileFound   = false;
        $currentVersion     = $this->wsf->getVersion();
        $zipVersion         = null;
        $ret                = '=';
        
        if(is_resource($zip)){
            while($entry = zip_read($zip)){
                $path           = zip_entry_name($entry);

                if($path    == "version.data"){
                    $zipVersion           = trim(zip_entry_read($entry, zip_entry_filesize($entry)));
                    $versionFileFound     = true;
                    
                    if(version_compare($currentVersion, $zipVersion, '>')){
                        $ret    = '>';
                    }else if(version_compare($currentVersion, $zipVersion, '<')){
                        $ret    = '<';
                    }
                }

            }

            zip_close($zip);

            if($versionFileFound == false){
                return false;
            }
            
            return array(
                'currentVersion'    => $currentVersion,
                'zipVersion'        => $zipVersion,
                'comparison'        => $ret,           
            );
        }

        return false;

    }
    
    /**
     * Upload the importing ZIP  by inserting the calculators,
     * fields and document maps into memory. 
     * It also deals with moving documents into docs with a new name
     *
     * @param string $filePath, path of the zip file
     * @param array $calculators, calculators used
     * @param array $fields, fields used
     * @param array $docsMapping, mapping
     * @param array $themes, used themes
     * @return bool
     */
    public function loadZip($filePath, &$calculators, &$fields, &$docsMapping, &$themes){
        $zip                 = zip_open($filePath);
        $calculators         = array();
        $fields              = array();
        $themes              = array();
        $docsMapping         = array();

        if(is_resource($zip)){
                        
            while($entry = zip_read($zip)){
                $path           = zip_entry_name($entry);
                $directory      = dirname($path);
                $filename       = basename($path);
                
                $read           = zip_entry_read($entry, zip_entry_filesize($entry));

                if($directory == "calculators"){
                    $calculatorId                    = basename($path, ".json");
                    $calculators[$calculatorId]      = json_decode($read, true);
                }else if($directory == "fields"){
                    $fieldId                         = basename($path, ".json");
                    $fields[$fieldId]                = json_decode($read, true);
                    $fields[$fieldId]['options']     = json_decode($fields[$fieldId]['options'], true);
                }else if($directory == "docs"){
                    /* Copying the Excel file from the ZIP to the docs folder */
                    $spreadsheetFileName        = $this->generateFileName();
                    $docsMapping[$filename]     = $spreadsheetFileName;
                    
                    $spreadsheetPath            = $this->wsf->getUploadPath("docs/{$spreadsheetFileName}");
                    file_put_contents($spreadsheetPath, $read);
                }else if($directory == "themes"){
                    $themeFileName              = basename($path, ".php");
                    
                    $themes[$themeFileName]     = $read;
                }
            }
            
            zip_close($zip);
            
            return true;
            
        } else {
            return false;
        }
    }
    
    /**
     * Loading fields
     * 
     * @param array $fields            List of fields to be imported
     * @param array $fieldMapping       Save the mapping result from the import in the format
     *                      [input/output][vecchio ID] = [nuovo ID]
     * @param array $fieldMappingIds    Similar to the previous one but does not take into account input / output
     *                      [vecchio ID] = [nuovo ID]
     * @param array $newFields          New fields that have been created
     * @param array $mappedFields       Except for fields that have not been created, but have only been mapped
     * @return void
     */
    public function importFields($fields, &$fieldMapping, &$fieldMappingIds, &$newFields, &$mappedFields){
        
        $newFields           = array();
        $mappedFields        = array();
        
        $fieldMappingIds     = array();
        $fieldMapping        = array(
            'input'     => array(),
            'output'    => array(),
        );
        
        foreach($fields as $fieldId => $field){
            $findField      = $this->fieldHelper->findField($field);

            if($findField == false){
                /* Il campo non è stato trovato, quindi ne devo creare uno nuovo */
                $field['id']                                = null;
                $newFieldId                                 = $this->fieldModel->save($field);

                $fieldMapping[$field['mode']][$fieldId]     = $newFieldId;
                $fieldMappingIds[$fieldId]                  = $newFieldId;

                $newFields[]                                = $field;     
            }else{
                $fieldMapping[$field['mode']][$fieldId]     = $findField->id;
                $fieldMappingIds[$fieldId]                  = $findField->id;

                $mappedFields[]                             = $findField;
            }
        }

        /* Sort the variables in descending order of length 
         * it will be useful to avoid bugs in substitutions 
         */
        uksort($fieldMappingIds, function($a, $b){return strlen($a) < strlen($b);});
    }
    
    /**
     * Import themes by converting any field IDs
     * 
     * @param array $themes              List of themes in the format
     *                      [Nome tema] => [Contenuto file]
     * 
     * @param array $fieldMappingIds     $fieldMappingIds obtained from the importFields
     * @param array $themesMapping      Result of the import
     * @return void
     */
    public function importThemes($themes, $fieldMappingIds, &$themesMapping){
        
        $themesMapping       = array(
            'all'       => array(),
            'created'   => array(),
            'mapped'    => array(),
        );            
        
        /* Converting the field IDs inside a theme  */
        foreach($themes as $themeFileName => $themeContent){
            /* Avoid bugs due to replaces */
            $themeContent       = str_replace("aws_price_calc_", "#tmp_price_calc_", $themeContent);
                   
            foreach($fieldMappingIds as $oldFieldId => $newFieldId){
                $themeContent       = str_replace("#tmp_price_calc_{$oldFieldId}", "aws_price_calc_{$newFieldId}", $themeContent);
            }
            
            /* Replacing not replaced strings (Maybe was without ID or CSS class) */
            $themeContent       = str_replace("#tmp_price_calc_", "aws_price_calc_", $themeContent);
            
            $theme                = $this->themeHelper->findTheme($themeContent);
            $date                 = date('Ymd_His');
            $filename             = "{$themeFileName}.php";
            
            if($theme == false){
                $newThemeFileName                       = "{$themeFileName}_{$date}.php";
                $themePath                              = $this->wsf->getUploadPath("themes/{$newThemeFileName}");

                $themesMapping['all'][$filename]        = $newThemeFileName;
                $themesMapping['created'][$filename]    = $themesMapping['all'][$filename];

                file_put_contents($themePath, $themeContent);
            }else{
                $themesMapping['all'][$filename]       = $theme['filename'];
                $themesMapping['mapped'][$filename]    = $themesMapping['all'][$filename];
            }
        }
    }

    /**
     * Import a calculator 
     * 
     * @param string $filePath, the path of the zip file
     * @return array | false
     */
    public function import($filePath){

        if($this->loadZip($filePath, $calculators, $fields, $docsMapping, $themes)){

            $this->importFields($fields, $fieldMapping, $fieldMappingIds, $newFields, $mappedFields);
            $this->importThemes($themes, $fieldMappingIds, $themesMapping);
            
            /* Loading computers */
            foreach($calculators as $calculatorIndex => $calculator){
               $calculator['id']                     = null;
               $calculator['system_created']         = 0;
               $calculator["fields"]                 = $fieldMapping['input'];
               $calculator["output_fields"]          = $fieldMapping['output'];
               $calculator["products"]               = array();
               $calculator["product_categories"]     = array();
               $calculator["options"]                = json_decode($calculator['options'], true);
               $calculator["conditional_logic"]      = json_decode($calculator['conditional_logic'], true);

               /* Overwrite Quantity Conversion */
               if(!empty($calculator['overwrite_quantity'])){
                   $calculator['overwrite_quantity']   = $fieldMappingIds[$calculator['overwrite_quantity']];
               }

               /* Overwrite Weight Conversion */
               if(!empty($calculator['overwrite_weight'])){
                   $calculator['overwrite_weight']   = $fieldMappingIds[$calculator['overwrite_weight']];
               }
               
               /* Overwrite Length Conversion*/
               if(!empty($calculator['overwrite_length'])){
                   $calculator['overwrite_length']   = $fieldMappingIds[$calculator['overwrite_length']];
               }
               
               /* Overwrite Width Conversion */
               if(!empty($calculator['overwrite_width'])){
                   $calculator['overwrite_width']   = $fieldMappingIds[$calculator['overwrite_width']];
               }
               
               /* Overwrite Height Conversion */
               if(!empty($calculator['overwrite_height'])){
                   $calculator['overwrite_height']   = $fieldMappingIds[$calculator['overwrite_height']];
               }
               
               /* Convert themes */
               if(!empty($calculator['theme'])){
                   $calculator['theme']                  = $themesMapping['all'][$calculator['theme']];
               }
               
               if($calculator['type'] == 'excel'){
                    /*WPC-PRO*/
                    $calculator['options']['file']   = $docsMapping[$calculator['options']['file']];

                    /* Conversion of input mapping fields */
                    foreach($calculator['options']['input'] as $cell => $fieldId){
                        if(is_numeric($fieldId)){
                            $calculator['options']['input'][$cell]  = $fieldMappingIds[$fieldId];
                        }
                    }
                    
                    /* Conversione dei campi di mapping di output */
                    foreach($calculator['options']['output'] as $cell => $fieldId){
                        if(is_numeric($fieldId)){
                            $calculator['options']['output'][$cell]  = $fieldMappingIds[$fieldId];
                        }
                    }
                    /*/WPC-PRO*/
               }else{
                   /* Convert any fields */
                   /* Sort the variables in descending order of length */
                   uksort($fields, function($a, $b){return strlen($a) < strlen($b);});
                   
                   /* Avoiding the bugs due to substitution */
                   $calculator['formula']       = str_replace("\$aws_price_calc_", "tmp_", $calculator['formula']);
                   
                   /* Replacing the formula variables */
                   foreach($fields as $fieldId => $field){
                       $newFieldId              = $fieldMapping[$field['mode']][$fieldId];
                       $calculator['formula']   = str_replace("tmp_{$fieldId}", "\$aws_price_calc_{$newFieldId}", $calculator['formula']);
                   }
                   
               }
               
               /* Save the calculator in the database */
               $calculators[$calculatorIndex]['id']  = $this->calculatorModel->save($calculator);
               
               /* Loading the Conditional Logic */
               if(!empty($calculator["conditional_logic"])){
                   $conditionLogic      = &$calculator["conditional_logic"];
                   $fieldFiltersJson    = array();
                   
                   foreach($conditionLogic['hide_fields'] as $hideFieldIndex => $hideField){
                       $conditionLogic['hide_fields'][$hideFieldIndex]      = $fieldMappingIds[$hideField];
                   }
                   
                   foreach($conditionLogic['field_filters_json'] as $oldFieldId => $oldFieldFiltersJson){
                       $newFieldId                      = $fieldMappingIds[$oldFieldId];
                       
                       $fieldFiltersJson[$newFieldId]   = $oldFieldFiltersJson;
                   }
                   
                   array_walk_recursive($fieldFiltersJson, array($this, 'updateConditionalLogicJsonFilters'), array(
                       'fieldMappingIds'    => $fieldMappingIds,
                   ));
                           
                   $conditionLogic['field_filters_json']    = $fieldFiltersJson;
                   $conditionLogic['field_filters_sql']     = $this->updateConditionalLogicSqlFilters($conditionLogic['field_filters_sql'], $fieldMappingIds);
                   
                   $this->calculatorModel->saveConditionalLogic($conditionLogic, $calculators[$calculatorIndex]['id']);
                   
               }              
               
            }

            return array(
                'newFields'     => $newFields,
                'mappedFields'  => $mappedFields,
                'calculators'   => $calculators,
                'themesMapping' => $themesMapping,
            );
        }else{
            return false;
        }
    }
    
    /**
     * Export a calculator
     *
     * @param object $calculator , calculator to export
     * @param string $filename, zip file name
     * @return string
     */
    public function export($calculator, $filename){
        $id                         = $calculator->id;
        $version                    = $this->wsf->getVersion();
        $zip                        = new \ZipArchive();
        $tmpDir                     = sys_get_temp_dir();
        $filePath                   = "{$tmpDir}/{$filename}";
        
        $calculatorOptions          = json_decode($calculator->options, true);
        
        $calculatorInputFields      = json_decode($calculator->fields, true);
        $calculatorOutputFields     = json_decode($calculator->output_fields, true);
        $mappingInputFields         = array();
        $mappingOutputFields        = array();
        
        if($calculator->type == 'excel'){
            $spreadsheetPath            = $this->wsf->getUploadPath("docs/{$calculatorOptions['file']}");
            
            /* If it's an Excel file I have to copy both the selected and the mapped fields */
            $mappingInputFields         = array_values($calculatorOptions['input']);
            $mappingOutputFields        = array_values($calculatorOptions['output']);

        }
        
        $fields                     = array_merge(
                $calculatorInputFields, 
                $calculatorOutputFields,
                $mappingInputFields,
                $mappingOutputFields
        );

        /* I check if the temp file already exists, if yes, deletes it */
        if(file_exists($filePath)){
            unlink($filePath);
        }
        
        if($zip->open($filePath, \ZipArchive::CREATE) !== TRUE){
            return false;
        }

        $zip->addFromString("version.data", $version);
        $zip->addFromString("calculators/{$id}.json", json_encode($calculator));

        foreach($fields as $fieldId){
            $field      = $this->fieldModel->get_field_by_id($fieldId);
            
            if(!empty($field)){
                $zip->addFromString("fields/{$fieldId}.json", json_encode($field));
            }
        }
        
        if($calculator->type == "excel"){
            $zip->addFile($spreadsheetPath, "docs/{$calculatorOptions['file']}");
        }
        
        if(!empty($calculator->theme)){
            $themePath            = $this->wsf->getUploadPath("themes/{$calculator->theme}");
            
            $zip->addFile($themePath, "themes/{$calculator->theme}");
        }
        
        $zip->close();

        return $filePath;
    }
    
    /**
     * Should the field be displayed on Cart?
     *
     * @param object $calculatorEntity
     * @param object $fieldEntity
     * @param string $value
     * @return bool
     * @throws \Exception
     */
    public function isFieldVisibleOnProductPage($calculatorEntity, $fieldEntity, $value){
        $calculatedValue            = $this->replaceFieldValue($calculatorEntity, $fieldEntity, $value);
        
        $hideFieldProductPage       = $fieldEntity->hide_field_product_page;
        
        if($hideFieldProductPage == true){
            return false;
        }
        
        return true;
    }
    
    /*
     * Should the field be displayed on Cart?
     *
     * @param object $calculatorEntity
     * @param object $fieldEntity
     * @param string $value
     * @return bool
     * @throws \Exception
     */
    public function isFieldVisibleOnCart($calculatorEntity, $fieldEntity, $value){
        $calculatedValue            = $this->replaceFieldValue($calculatorEntity, $fieldEntity, $value);
        $hideFieldCartIfEmpty       = $fieldEntity->hide_field_cart_if_empty;
        $hideFieldCart              = $fieldEntity->hide_field_cart;
        
        if($hideFieldCart == true){
            return false;
        }
        
        if($hideFieldCartIfEmpty == true && empty($calculatedValue)){
            return false;
        }
        
        return true;
    }
    
    /*
     * Should the field be displayed on Checkout step?
     *
     * @param object $calculatorEntity
     * @param object $fieldEntity
     * @param string $value
     * @return bool
     * @throws \Exception
     */
    public function isFieldVisibleOnCheckout($calculatorEntity, $fieldEntity, $value){
        $calculatedValue            = $this->replaceFieldValue($calculatorEntity, $fieldEntity, $value);
        $hideFieldCheckout          = $fieldEntity->hide_field_checkout;
        $hideFieldCheckoutIfEmpty   = $fieldEntity->hide_field_checkout_if_empty;
        
        if($hideFieldCheckout == true){
            return false;
        }
        
        if($hideFieldCheckoutIfEmpty == true && empty($calculatedValue)){
            return false;
        }
        
        return true;
    }
    
    /*
     * Should the field be displayed on Order details step?
     *
     * @param object $calculatorEntity
     * @param object $fieldEntity
     * @param string $value
     * @return bool
     * @throws \Exception
     */
    public function isFieldVisibleOnOrderDetails($calculatorEntity, $fieldEntity, $value){
        $fieldVisibleOnCheckout     = $this->isFieldVisibleOnCheckout($calculatorEntity, $fieldEntity, $value);
        $hideFieldOrder             = $fieldEntity->hide_field_order;
        
        /* Field is hidden for checkout */
        if($fieldVisibleOnCheckout == false){
            return false;
        }
        
        /* Hide Field On Order is checked on field options */
        if($hideFieldOrder == true){
            return false;
        }
        
        return true;
    }

    /**
     * Get the data of a product customer page
     *
     * @param string $calculatorId, the identification number of the calculator attached to the product
     * @return array
     */
    public function getProductPageUserData($calculator){
        $fieldValues    = $this->getFieldsDefaultValue($calculator, true);

        /* Woocommerce sample compatibility */
        $sample = $this->wsf->requestValue('sample');
        $woocoommerceAddSample = isset($sample)? true : false;

        /* If added to cart, to get the right price, I will get the post data values */
        if($this->wsf->isPost() && !$woocoommerceAddSample){
            $post   = $this->wsf->getPost();

            if(is_array($post)){
                if(!empty($post['add-to-cart'])){
                    $fieldValues    = $post;
                }
            }
        }

        return $fieldValues;
    }
    
    public function getThemePath($calculator){
        if(empty($calculator->theme)){
            return null;
        }
        
        return $this->wsf->getUploadPath("themes/{$calculator->theme}");
    }
    
    public function hasToCheckErrors($calculator){
        /* Decide to show the price if there are errors or not */
        if($calculator->force_to_show_price_on_errors == true){
            $checkErrors    = false;
        }else{
            $checkErrors    = true;
        }
        
        return $checkErrors;
    }
    
    public function getSessionCalculatorProductData($productId){
        return $this->wsf->getCookie("awspc_product_{$productId}");
    }
    
    public function setSessionCalculatorProductData($productId, $calculatorId, $fieldsData, $outputFieldsData, $quantity){
        $this->wsf->setCookie("awspc_product_{$productId}",array(
            'product_id'                => $productId,
            'simulator_id'              => $calculatorId,
            'simulator_fields_data'     => $fieldsData,
            'output_fields_data'        => $outputFieldsData,
            'quantity'                  => $quantity,
        ));
    }
    
    public function getNotMappedOutputFields($calculator){
        $outputFieldsIds                 = $calculator['output_fields'];
        $calculatorOptions               = $calculator['options'];
        $notMappedFields                 = array();
        
        $mappedFields                    = $calculatorOptions['output'];
                
        foreach($outputFieldsIds as $outputFieldId){
            if(!in_array($outputFieldId, $mappedFields)){
                
                $outputField            = $this->fieldModel->get_field_by_id($outputFieldId);
                $notMappedFields[]      = $outputField;
                
            }
        }
        
        return $notMappedFields;
    }
    
    public function getHideAlertErrors(){
        return $this->settingsModel->getValue("hide_alert_errors");
    }
    
    public function checkSpreadsheetCells($mappingInfo, $cloneObjWorksheet, $totalRows, $totalColumns){
        if(!empty($mappingInfo)){
            foreach($mappingInfo as $coordinates => $fieldId){
                $cell           = $cloneObjWorksheet->getCell($coordinates);
                $colIndex       = \PHPExcel_Cell::columnIndexFromString($cell->getColumn());
                $rowIndex       = $cell->getRow();

                if($rowIndex > $totalRows){
                    unset($mappingInfo[$coordinates]);
                }

                if($colIndex > $totalColumns){
                    unset($mappingInfo[$coordinates]);
                }
            }
        }

        return $mappingInfo;
    }
    
    public function getCalculatorQuantity($calculator, $product, $data){
        $productArray            = $this->ecommerceHelper->getProductArrayFromWooCommerce($product);

        $calculatedFieldsData    = $this->calculateFieldsData($calculator, $productArray, $data);

        $quantityFieldName  = $this->fieldHelper->getFieldName($calculator->overwrite_quantity);
        $quantity           = $calculatedFieldsData['data'][$quantityFieldName];

        return $quantity;
        
    }
    
    /**
     * Attach a specific product to a specific calculator
     *
     * @param $productId, the id of the product that the user is attaching a calculator
     * @param $calculatorId, the calculator id to be assigned to the product
     * @param $productsIds, the list of products id of the new calculator
     *
     * @return json
     */
    public function addAjaxProductToCalculator($productId, $calculatorId, $productsIds){

        //Firstly remove the product from tha actual assigned calculator if it has one
        $attachedCalculator = $this->get_simulator_for_product($productId);


        //check if chosen the same calculator as it the one the already is attached
        if ($calculatorId != $attachedCalculator->id) {

            $products = json_decode($attachedCalculator->products);

            if (isset($attachedCalculator)) {
                if (count($attachedCalculator->products) > 0) {
                    $pos = array_search($productId, $products);

                    if (is_numeric($pos)) {
                        unset($products[$pos]);
                        $this->calculatorModel->assignProductToCalculator($products, $attachedCalculator->id);
                    }
                }
            }//Finish deleting the previous attached calculator


            //Attach the new calculator
            if ($productsIds == null) {
                $productsIds = array();
            }

            array_push($productsIds, $productId);
            $this->calculatorModel->assignProductToCalculator($productsIds, $calculatorId);


        }

        $allCalculators = $this->calculatorModel->get_list();
        foreach ($allCalculators as $calculator) {
            $allArrayCalculators[$calculator->id] = (array)$calculator;
        }

        die(json_encode($allArrayCalculators));
    }


    /**
     * Attach a specific product to a specific calculator
     *
     * @param $productId
     * @param $calculatorId
     * @param $productsIds
     *
     * @return json
     */
    public function removeAjaxProductToCalculator($productId){

        $attachedCalculator = $this->get_simulator_for_product($productId);
        $products = json_decode($attachedCalculator->products);

        $pos = array_search($productId, $products);
        /*
         * check if the product id is found in the product attribute of the calculator,
         * if not , it means that this calculator is assigned to that product by the category product
         * in that case do nothing
         */
        if (is_numeric($pos)) {
            unset($products[$pos]);
            $this->calculatorModel->assignProductToCalculator($products, $attachedCalculator->id);

        }

        $allCalculators = $this->calculatorModel->get_list();
        foreach ($allCalculators as $calculator) {
            $allArrayCalculators[$calculator->id] = (array)$calculator;
        }

        die(json_encode($allArrayCalculators));

    }
    
    /**
     * Calculates the dimensions of the spreadsheet (Columns and rows)
     *
     * @param $objWorksheet: The worksheet from PHPExcel
     * @return array
     *
     * Also "$worksheetData[0]['totalColumns'];" and
     * "->getHighestRow();" was not working, so I had to create a custom solution
     */
    public function calculateSpreadsheetDimensions($objWorksheet){

        $highestColumm  = $objWorksheet->getHighestColumn();
        $highestRow     = $objWorksheet->getHighestRow();

        $calculatedCells    = 0;
        $calculatedRows     = 0;
        
        foreach ($objWorksheet->getRowIterator() as $row){
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);

            $calculateLineCells     = 0;
            foreach ($cellIterator as $cell){
                
                    $calculateLineCells++;
                    
                    if($calculateLineCells > $calculatedCells){
                        $calculatedCells    = $calculateLineCells;
                    }

            }

            $calculatedRows++;
        }
        
        return array(
            'totalRows'     => $calculatedRows,
            'totalColumns'  => $calculatedCells,
        );
    }
    
     /**
     * Returns the Price with Prefix and Suffix (General Settings)
     *
     * @param $price: The price to be changed
     *
     * @return string
     */
    public function getPriceWithPrefixAndSuffix($price){
        $pricePrefix            = $this->settingsModel->getValue("price_prefix");
        $priceSuffix            = $this->settingsModel->getValue("price_suffix");
        
        if(!empty($pricePrefix)){
            $price      = "{$pricePrefix} {$price}";
        }

        if(!empty($priceSuffix)){
            $price      = "{$price} {$priceSuffix}";
        }
        
        return $price;
    }
    
    
}
