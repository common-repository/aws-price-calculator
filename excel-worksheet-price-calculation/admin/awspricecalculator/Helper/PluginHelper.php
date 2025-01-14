<?php
/**
 * @package AWS Price Calculator
 * @author Enrico Venezia
 * @copyright (C) Altos Web Solutions Italia
 * @license GNU/GPL v2 http://www.gnu.org/licenses/gpl-2.0.html
**/

namespace AWSPriceCalculator\Helper;

/*AWS_PHP_HEADER*/

use WSF\Helper\FrameworkHelper;

class PluginHelper {
    
    var $wsf;
    
    public function __construct(FrameworkHelper $wsf) {
        $this->wsf = $wsf;
        
        $this->databaseHelper   = $this->wsf->get('\\WSF\\Helper', true, 'awsframework/Helper', 'DatabaseHelper', array($this->wsf));
        $this->settingsModel    = $this->wsf->get('\\AWSPriceCalculator\\Model', true, 'awspricecalculator/Model', 'SettingsModel', array($this->wsf));
        $this->ecommerceHelper  = $this->wsf->get('\\WSF\\Helper', true, 'awsframework/Helper', 'EcommerceHelper', array($this->wsf));
        $this->calculatorHelper = $this->wsf->get('\\AWSPriceCalculator\\Helper', true, 'awspricecalculator/Helper', 'CalculatorHelper', array($this->wsf));
        $this->productHelper    = $this->wsf->get('\\AWSPriceCalculator\\Helper', true, 'awspricecalculator/Helper', 'ProductHelper', array($this->wsf));
                
        $this->calculatorModel      = $this->wsf->get('\\AWSPriceCalculator\\Model', true, 'awspricecalculator/Model', 'CalculatorModel', array($this->wsf));
        
    }
    
    /**
     * Returns the Credit URL
     *
     * @return string
     */
    function getCreditsUrl(){
        return 'http://www.altosmail.com?wt_source=woo-price-calculator';
    }

    /**
     * Return the path of the logo.
     *
     * @return string
     */
    function logo(){
        return $this->wsf->getResourcesUrl('assets/images/Altosmail-logo.png');
    }

    /**
     * Return the path of the WooPriceCalculator icon.
     *
     * @return string
     */
    function icon(){
        if($this->ecommerceHelper->getTargetEcommerce() == "woocommerce"){
            return $this->wsf->getResourcesUrl('assets/images/woopricecalculator.png');
        }else if($this->ecommerceHelper->getTargetEcommerce() == "hikashop"){
            return $this->wsf->getResourcesUrl('assets/images/hikapricecalculator.png');
        }
    }
    
    /**
     * Returns the home URL
     *
     * @return string
     */
    function getHomeUrl(){
        if($this->ecommerceHelper->getTargetEcommerce() == "woocommerce"){
            return "https://altoswebsolutions.com/cms-plugins/woopricecalculator?wt_source=woo-price-calculator";
        }else if($this->ecommerceHelper->getTargetEcommerce() == "hikashop"){
            return "https://altoswebsolutions.com/cms-plugins/hikaprice-calculator?wt_source=hikapricecalculator";
        }
    }
    
    /**
     * Returns the documentation URL
     *
     * @return  string
     */
    function getDocumentationUrl(){
        if($this->ecommerceHelper->getTargetEcommerce() == "woocommerce"){
            return "https://altoswebsolutions.com/manual/woo-price-calculator/start";
        }else if($this->ecommerceHelper->getTargetEcommerce() == "hikashop"){
            return "https://altoswebsolutions.com/manual/hika-price-calculator";
        }
    }
    
    /**
     * Returns the form URL
     *
     * @return string
     */
    function getForumUrl(){
        if($this->ecommerceHelper->getTargetEcommerce() == "woocommerce"){
            return "https://altoswebsolutions.com/forum/index?wt_source=woo-price-calculator";
        }else if($this->ecommerceHelper->getTargetEcommerce() == "hikashop"){
            return "https://altoswebsolutions.com/forum/index?wt_source=hikapricecalculator";
        }
    }
        
    function help($text, $size = "13"){
        ?>

        <?php
    }
    
    /**
     * Get the plugin version
     *
     * @return string | null
     */
    function getCurrentVersion(){
        $row    = $this->databaseHelper->getRow("SHOW TABLES LIKE '[prefix]woopricesim_settings';");
        
        if(empty($row)){
            return null;
        }

        return $this->settingsModel->getValue("version");
    }
    
    /**
     * Sets the plugin version
     *
     * @return void
     */
    function setCurrentVersion($version){
        $this->settingsModel->setValue("version", $version);
    }

    /**
     * Upgrade the plugin
     *
     * Installation: Upgrade execution is always performed
     *
     * @param string $dbVersion , the current version of the database
     * @return void
     * @throws \ReflectionException
     */
    function pluginUpgrade($dbVersion){

            $charset_collate        = $this->databaseHelper->getCharsetCollate();
            $oldVersion             = $this->getCurrentVersion();

            /* Force upgrade for testing, remember to comment it for production */
            //$oldVersion             = null; 
            
            if($oldVersion != $dbVersion){
                /* WARNING: For the dbDelta function the ALTER TABLE must not be used, 
                 * but must be inserted in the CREATE TABLE
                 */

                /* woopricesim_fields */
                $sql = "CREATE TABLE [prefix]woopricesim_fields (
                        `id` int(11) NOT NULL AUTO_INCREMENT,
                        `label` text,
                        `short_label` TEXT DEFAULT NULL,
                        `description` TEXT DEFAULT NULL,
                        `mode` varchar(100) DEFAULT NULL,
                        `type` varchar(100) DEFAULT NULL,
                        `check_errors` varchar(100) DEFAULT 'always',
                        `required` varchar(1) DEFAULT '0',
                        `required_error_message` TEXT DEFAULT NULL,
                        `hide_field_product_page` varchar(1) DEFAULT '0',
                        `hide_field_cart_if_empty` varchar(1) DEFAULT '0',
                        `hide_field_checkout_if_empty` varchar(1) DEFAULT '0',
                        `hide_field_cart` varchar(1) DEFAULT '0',
                        `hide_field_checkout` varchar(1) DEFAULT '0',
                        `hide_field_order` varchar(1) DEFAULT '0',
                        `text_after_field` MEDIUMTEXT DEFAULT '',
                        `validator` blob,
                        `options` LONGTEXT,
                        `system_created` TINYINT(1) NOT NULL,
                        PRIMARY KEY (`id`)
                      )" . $charset_collate . ";";
                $this->databaseHelper->dbDelta($sql);

                /* woopricesim_simulations */
                $sql = "CREATE TABLE [prefix]woopricesim_simulations (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `order_id` int(11) DEFAULT NULL,
                  `simulation_data` blob,
                  `simulators` blob,
                  PRIMARY KEY (`id`)
                )" . $charset_collate . ";";
                $this->databaseHelper->dbDelta($sql);

                /* woopricesim_simulators */
                $sql = "CREATE TABLE [prefix]woopricesim_simulators (
                        `id` int(11) NOT NULL AUTO_INCREMENT,
                        `name` varchar(255) DEFAULT NULL,
                        `description` text,
                        `fields` blob,
                        `output_fields` blob,
                        `products` blob,
                        `product_categories` blob,
                        `options` blob,
                        `conditional_logic` blob,
                        `formula` text,
                        `product_page_include_taxes` TINYINT(1) DEFAULT 0,
                        `overwrite_quantity` INT DEFAULT NULL,
                        `overwrite_weight` INT DEFAULT NULL,
                        `overwrite_length` INT DEFAULT NULL,
                        `overwrite_width` INT DEFAULT NULL,
                        `overwrite_height` INT DEFAULT NULL,
                        `force_to_show_price_on_errors` TINYINT(1) NULL DEFAULT 0,
                        `hide_startup_fields_errors` TINYINT(1) NULL DEFAULT 0,
                        `redirect` TINYINT(1),
                        `empty_cart` TINYINT(1) NULL DEFAULT 0,
                        `type` VARCHAR(50) NULL DEFAULT 'simple',
                        `theme` VARCHAR(255) NULL,
                        `system_created` TINYINT(1) NOT NULL,
                        PRIMARY KEY (`id`)
                      )" . $charset_collate . ";";
                $this->databaseHelper->dbDelta($sql);

                /* woopricesim_regex */
                $sql = "CREATE TABLE [prefix]woopricesim_regex (
                        `id` INT NOT NULL AUTO_INCREMENT,
                        `name` VARCHAR(255) NOT NULL,
                        `regex` MEDIUMTEXT NOT NULL,
                        `user_created` TINYINT(1) NOT NULL,
                        PRIMARY KEY (`id`)
                        ){$charset_collate};";
                $this->databaseHelper->dbDelta($sql);

                /* woopricesim_settings */
                $sql = "CREATE TABLE [prefix]woopricesim_settings (
                        `s_key` VARCHAR(100) NOT NULL,
                        `s_value` MEDIUMTEXT NOT NULL,
                        PRIMARY KEY (`s_key`)
                        ){$charset_collate};";
                $this->databaseHelper->dbDelta($sql);
          
                if($dbVersion == "1.1"){
                    $this->databaseHelper->query("INSERT INTO [prefix]woopricesim_regex SET "
                                . "[prefix]woopricesim_regex.id = 1, "
                                . "[prefix]woopricesim_regex.name = 'Email Check', "
                                . "[prefix]woopricesim_regex.regex = '/^(([^<>()\\\\[\\\\]\\\\\\\\.,;:\\\\s@\\\"]+(\\\\.[^<>()\\\\[\\\\]\\\\\\\\.,;:\\\\s@\\\"]+)*)|(\\\".+\\\"))@((\\\\[[0-9]{1,3}\\\\.[0-9]{1,3}\\\\.[0-9]{1,3}\\\\.[0-9]{1,3}])|(([a-zA-Z\\\\-0-9]+\\\\.)+[a-zA-Z]{2,}))$/', "
                                . "[prefix]woopricesim_regex.user_created = 0 "
                                . "ON DUPLICATE KEY UPDATE "
                                . "[prefix]woopricesim_regex.name = 'Email Check', "
                                . "[prefix]woopricesim_regex.regex = '/^(([^<>()\\\\[\\\\]\\\\\\\\.,;:\\\\s@\\\"]+(\\\\.[^<>()\\\\[\\\\]\\\\\\\\.,;:\\\\s@\\\"]+)*)|(\\\".+\\\"))@((\\\\[[0-9]{1,3}\\\\.[0-9]{1,3}\\\\.[0-9]{1,3}\\\\.[0-9]{1,3}])|(([a-zA-Z\\\\-0-9]+\\\\.)+[a-zA-Z]{2,}))$/', "
                                . "[prefix]woopricesim_regex.user_created = 0 "
                                );
                }else if($oldVersion == "1.0" || 
                         $oldVersion == "1.1" || 
                         (version_compare($oldVersion, "1.1.0", ">=") == true && version_compare($oldVersion, "1.2.8", "<") == true)){
                    /*
                     * Data collection of drop-down menus / Radio
                     */
                    $rows   = $this->databaseHelper->getResults("SELECT * FROM [prefix]woopricesim_fields "
                    . "WHERE [prefix]woopricesim_fields.type = 'radio' || [prefix]woopricesim_fields.type = 'picklist'");

                    foreach($rows as $row){
                        $options                = json_decode($row->options, true);

                        if($row->type == 'radio'){
                            $items                  = str_replace("\r", "", $options['radio']['radio_items']);
                        }else if($row->type == 'picklist'){
                            $items                  = str_replace("\r", "", $options['picklist_items']);
                        }


                        if(substr($items, 0, strlen("\"[{")) === "\"[{" || substr($items, 0, strlen("[{")) === "[{"){
                            //I do nothing
                        }else{
                            $explodedItems          = explode("\n", $items);
                            $id                     = 1;
                            $arrayItems             = array();

                            foreach($explodedItems as $explodedItem){
                                $explodedItemValues     = explode("#$#", $explodedItem);

                                if(count($explodedItemValues) == 2){
                                    $arrayItems[]           = array(
                                        'id'        => $id++,
                                        'label'     => $explodedItemValues[1],
                                        'value'     => $explodedItemValues[0],
                                    );
                                }
                            }

                            if($row->type == "radio"){
                                $options['radio']['radio_items']        = json_encode($arrayItems);
                            }else if($row->type == "picklist"){
                                $options['picklist_items']              = json_encode($arrayItems);
                            }

                            $row->options                               = json_encode($options);

                            $this->databaseHelper->update("[prefix]woopricesim_fields", $row,
                                array('id' => $row->id)
                            );

                        }
                    }


                    $this->databaseHelper->query("UPDATE [prefix]woopricesim_simulators SET "
                                . "[prefix]woopricesim_simulators.options = [prefix]woopricesim_simulators.fields "
                                . "WHERE [prefix]woopricesim_simulators.type = 'excel';");

                    $this->databaseHelper->query("UPDATE [prefix]woopricesim_simulators SET "
                                . "[prefix]woopricesim_simulators.fields = NULL "
                                . "WHERE [prefix]woopricesim_simulators.type = 'excel';");
                }else if($dbVersion == "2.0.0"){
                    /*
                     * Change the name of the fields from "$ woo_price_calc_NUM" to "$ aws_price_calc_NUM"
                     */
                    $this->databaseHelper->query("UPDATE [prefix]woopricesim_simulators
                                SET formula = REPLACE(formula, '\$woo_price_calc_', '\$aws_price_calc_')");
                    
                    /*
                     * Initialize the mode field: The previous fields were all input
                     */
                    $this->databaseHelper->query("UPDATE [prefix]woopricesim_fields SET mode = 'input' WHERE mode IS NULL OR mode = ''");
                    
                    /*
                     * Change the mode of the output fields: Set the price as one of the fields
                     */
                    foreach($this->calculatorModel->get_list() as $calculator){
                        if($calculator->type == 'excel'){
                            $options                = json_decode($calculator->options, true);
                            
                            $options['output']      = array(
                                $options['output']  => 'price'
                            );
                            
                            $calculator->options    = json_encode($options);
                            
                            $this->calculatorModel->save($this->calculatorModel->exchangeArray($calculator), $calculator->id);
                        }
                    }
                    
                }
               
                /*
                 * Creating folders for Upload
                 */

                $this->wsf->createFolder($this->wsf->getUploadPath("docs"));
                $this->wsf->createFolder($this->wsf->getUploadPath("themes"));
                $this->wsf->createFolder($this->wsf->getUploadPath("translations"));
                $this->wsf->createFolder($this->wsf->getUploadPath("style"));
                $this->wsf->createFolder($this->wsf->getUploadPath("tmp_files"));
                $this->wsf->createFolder($this->wsf->getUploadPath("debug"));
                
                $customCssPath  = $this->wsf->getUploadPath("style/custom.css");

                if(!file_exists($customCssPath)){
                    file_put_contents($customCssPath, "/* YOUR CUSTOM CSS */");
                }

                /* Configuration keys */
                if($this->settingsModel->isValue("cart_edit_button_class") == false){
                    $this->settingsModel->setValue("cart_edit_button_class", "button");
                }
                
                if($this->settingsModel->isValue("cart_edit_button_position") == false){
                    $this->settingsModel->setValue("cart_edit_button_position", "before-price");
                }
                
                if($this->settingsModel->isValue("single_product_ajax_hook_class") == false){
                    $this->settingsModel->setValue("single_product_ajax_hook_class", "");
                }

                if($this->ecommerceHelper->getTargetEcommerce() == "hikashop"){
                    /*
                    * Creating the folder for the template override in hikashop
                    */
                    if($this->wsf->getLicense() == 1){
                        $templatePath       = $this->wsf->getCmsActiveTemplatePath('html/com_hikashop/checkout');
                        
                        $templatesToCopy    = array(
                            array(
                                'src'   => $this->wsf->getPluginPath("resources/parts/com_hikashop/checkout/cart.php", true),
                                'dest'  => "{$templatePath}/cart.php",
                            ),
                                        
                            array(
                                'src'   => $this->wsf->getPluginPath("resources/parts/com_hikashop/checkout/show_block_cart.php", true),
                                'dest'  => "{$templatePath}/show_block_cart.php",
                            )
                        );

                        $this->wsf->createFolder($templatePath);

                        foreach($templatesToCopy as $templateToCopy){
                            if(!file_exists($templatesToCopy['dest'])){
                                copy($templateToCopy['src'], $templateToCopy['dest']);
                            }
                        }

                    }
                    
                    /*
                     * I create the necessary field for storing data in the cart
                     */
                    $checkField     = $this->databaseHelper->getRow("SELECT COUNT(*) as checkCount FROM [prefix]hikashop_field WHERE field_namekey = 'awspricecalculator'");
                    if($checkField->checkCount == 0){
                        $this->databaseHelper->query("ALTER TABLE [prefix]hikashop_cart_product ADD COLUMN `awspricecalculator` TEXT NULL;");
                        $this->databaseHelper->query("ALTER TABLE [prefix]hikashop_order_product ADD `awspricecalculator` TEXT NULL;");
                        
                        $this->databaseHelper->query("INSERT INTO [prefix]hikashop_field (`field_table`, `field_realname`, `field_namekey`, `field_type`, `field_published`, `field_options`, `field_core`, `field_required`, `field_access`, `field_with_sub_categories`, `field_frontcomp`, `field_backend`, `field_backend_listing`) VALUES ('item', 'AWS Price Calculator', 'awspricecalculator', 'text', '1', 'a:5:{s:12:\"errormessage\";s:0:\"\";s:4:\"cols\";s:0:\"\";s:4:\"rows\";s:0:\"\";s:4:\"size\";s:0:\"\";s:6:\"format\";s:0:\"\";}', '0', '0', 'all', '0', '1', '1', '1');");
                    }
                }

                $this->setCurrentVersion($dbVersion);
            }
            

    }

    /**
     * Loading styles and scripts only on the plugin pages.
     *
     * The suffix hook to look for would be "woocommerce_page_woo-price-calculator"
     * but in some systems with other languages (ex: Jew), the first part (woocommerce) could be different.
     * Loading Wordpress Media Library files .
     *
     * @return void
     */
    function adminEnqueueScripts($pluginCode, $hookSuffix){


        //enqueue the overall css file that will be included in all admin pages
        $this->wsf->enqueueStyle('overall', 'assets/css/admin-overall.css');
        
        $this->wsf->enqueueStyle('colorpicker-ext', 'lib/colorpicker/css/colorpicker.css');
        $this->wsf->enqueueScript('colorpicker-ext', 'lib/colorpicker/js/colorpicker.js', array('jquery'));

        if(strpos($hookSuffix, '_page_excel-worksheet-price-calculation') !== false){
            
            // wp_enqueue_script('jquery');

            /* Questo bootstrap ha un prefisso per non modificare l'aspetto di altre cose */
            $this->wsf->enqueueScript('wsf-bootstrap', 'lib/wsf-bootstrap-4.5.0/js/popper.js', array('jquery'), '4.5.0');
            $this->wsf->enqueueScript('bootstrap', 'lib/wsf-bootstrap-4.5.0/js/bootstrap.js', array('jquery'), '4.5.0');
            $this->wsf->enqueueStyle('wsf-bootstrap', 'lib/wsf-bootstrap-4.5.0/css/wsf-bootstrap.css');
            $this->wsf->enqueueStyle('wsf-bootstrap', 'lib/wsf-bootstrap-4.5.0/css/wsf-bootstrap-theme.css');

            $this->wsf->enqueueStyle('tooltipstercss', 'assets/css/tooltipster.css');
            $this->wsf->enqueueStyle('tooltipster-shadow', 'assets/css/tooltipster-shadow.css');
            $this->wsf->enqueueScript('tooltipster', 'assets/js/jquery.tooltipster.min.js', array('jquery'), '3.2.6');
            
            $this->wsf->enqueueScript("{$pluginCode}-admin", 'assets/js/admin.js', array('jquery', 'jquery-ui-tooltip', 'tooltipster'), '1.0.1');
            $this->wsf->enqueueScript("{$pluginCode}-jquery-numeric", 'assets/js/jquery.numeric.min.js', array('jquery'));
            $this->wsf->enqueueScript("{$pluginCode}-jquery-tooltipster", 'assets/js/jquery.tooltipster.min.js', array('jquery'));

            $this->wsf->enqueueStyle("{$pluginCode}-admin-style", 'assets/css/admin.css');

            // $this->wsf->enqueueScript('uploadify-js', 'lib/uploadify/jquery.uploadify.js', array('jquery'),'1.7.1');
            // $this->wsf->enqueueStyle('uploadify', 'lib/uploadify/uploadify.css');

            $this->wsf->enqueueScript('lou-multi-select', 'lib/lou-multi-select-0.9.12/js/jquery.multi-select.js', array('jquery'), '0.9.12');
            $this->wsf->enqueueStyle('lou-multi-select', 'lib/lou-multi-select-0.9.12/css/multi-select.css');

            $this->wsf->enqueueScript('datetimepicker', 'lib/datetimepicker-2.5.4/build/jquery.datetimepicker.full.js', array('jquery'), '2.5.4');
            $this->wsf->enqueueStyle('datetimepicker', 'lib/datetimepicker-2.5.4/build/jquery.datetimepicker.css');

            $this->wsf->enqueueStyle('dataTables-bootstrap', 'lib/DataTables-1.10.12/media/css/dataTables.bootstrap.min.css');
            $this->wsf->enqueueScript('dataTables', 'lib/DataTables-1.10.12/media/js/jquery.dataTables.min.js', array('jquery'), '1.10.12');
            $this->wsf->enqueueScript('dataTables-bootstrap', 'lib/DataTables-1.10.12/media/js/dataTables.bootstrap.min.js', array('jquery'), '1.10.12');

            $this->wsf->enqueueStyle('font-awesome', 'lib/font-awesome-4.6.3/css/font-awesome.min.css');

            $this->wsf->enqueueScript('Sortable', 'lib/Sortable-1.4.2/Sortable.min.js', array('jquery'), '1.10.12');

            wp_enqueue_script( 'jquery-ui-core');
            // $this->wsf->enqueueScript('jquery-ui', 'lib/jqueryui-1.12.4/jquery-ui.min.js', array('jquery'), '1.12.4');
            
            $this->wsf->enqueueScript('interact-js', 'lib/interactjs-1.2.9/interact.min.js');
            
            $this->wsf->enqueueStyle('chosen', 'lib/chosen-1.7.0/chosen.min.css');
            $this->wsf->enqueueScript('chosen', 'lib/chosen-1.7.0/chosen.jquery.min.js');
            
            $this->wsf->enqueueStyle('jQuery-QueryBuilder', 'lib/jQuery-QueryBuilder-2.4.3/query-builder.default.css');
            $this->wsf->enqueueScript('jQuery-QueryBuilder', 'lib/jQuery-QueryBuilder-2.4.3/query-builder.standalone.min.js',array('jquery'));
            
            $this->wsf->enqueueStyle('remodal', 'lib/remodal-1.0.7/remodal.css');
            $this->wsf->enqueueStyle('remodal-default-theme', 'lib/remodal-1.0.7/remodal-wpc-theme.css');
            $this->wsf->enqueueScript('remodal', 'lib/remodal-1.0.7/remodal.min.js', array('jquery'), '2.5.4');
            
            if($this->wsf->getTargetPlatform() == "wordpress"){
                /* Loading Wordpress Media Library files */
                wp_enqueue_media();
            }
            
        }

        /* Always performed last */
        $this->wsf->localizeScript("{$pluginCode}-admin", 'WPC_HANDLE_SCRIPT', array( 
            'siteurl'           => $this->wsf->getSiteUrl(),
            'ajax_url'          => $this->getPluginAjaxUrl('price_callback'),
            'ajax_products_url' => $this->getPluginAjaxUrl('products_callback'),
        ));
        
        
    }
    
    function frontEnqueueScripts($pluginCode, $productId){
        if($this->ecommerceHelper->isProduct() === true){
            $simulator = $this->calculatorHelper->get_simulator_for_product($productId);
        }

        $this->wsf->enqueueScript("{$pluginCode}-jquery-numeric", 'assets/js/jquery.numeric.min.js', array('jquery'));

        $this->wsf->enqueueScript("{$pluginCode}-datetimepicker", 'lib/datetimepicker-2.5.4/build/jquery.datetimepicker.full.js', array('jquery'), '2.5.4');
        $this->wsf->enqueueStyle("{$pluginCode}-datetimepicker", 'lib/datetimepicker-2.5.4/build/jquery.datetimepicker.css');

        $this->wsf->enqueueStyle('woocommerce-pricesimulator-main', 'assets/css/main.css');
        
        /*
         * array('jquery', 'woocommerce'): But if you reload "woocommerce" twice, it generates errors on other libraries
         * It also appears that no woocommerce function is used in main.js. In any case I leave this comment for any future problems, 
         * but if no problems are reported, the comment can be deleted
         */
        $this->wsf->enqueueScript("{$pluginCode}-main", 'assets/js/main.js', array('jquery'));

        $this->wsf->enqueueStyle('remodal', 'lib/remodal-1.0.7/remodal.css');
            $this->wsf->enqueueStyle('remodal-default-theme', 'lib/remodal-1.0.7/remodal-wpc-theme.css');
            $this->wsf->enqueueScript('remodal', 'lib/remodal-1.0.7/remodal.min.js', array('jquery'), '2.5.4');
        
        $this->wsf->enqueueStyle('tooltipstercss', 'assets/css/tooltipster.css');
        $this->wsf->enqueueStyle('tooltipster-shadow', 'assets/css/tooltipster-shadow.css');
        $this->wsf->enqueueScript('tooltipster', 'assets/js/jquery.tooltipster.min.js', array('jquery'), '3.2.6');
        
        $this->wsf->enqueueStyle('rangeslider-ext', 'lib/rangeslider.js-2.3.0/rangeslider.css');
        $this->wsf->enqueueScript('rangeslider-ext', 'lib/rangeslider.js-2.3.0/rangeslider.min.js', array('jquery'));
            
        /* Only if the simulator is present */
        if(!empty($simulator)){
            $this->wsf->enqueueStyle('wsf-bootstrap', 'lib/wsf-bootstrap-4.5.0/css/wsf-bootstrap.css');
            $this->wsf->enqueueStyle('wsf-bootstrap', 'lib/wsf-bootstrap-4.5.0/css/wsf-bootstrap-theme.css');

            $this->wsf->enqueueStyle('wsf-uikit', 'lib/wsf-uikit-2.27.1/src/less/wsf-uikit.css');
        }

        $this->wsf->enqueueStyle('awspricecalculator-custom', $this->wsf->getUploadUrl('style/custom.css'), true, null);
        
        /* Always performed last */        
        $this->wsf->localizeScript("{$pluginCode}-main", 'WPC_HANDLE_SCRIPT', array( 
            'siteurl'                           => $this->wsf->getSiteUrl(),
            'resources_url'                     => $this->wsf->getResourcesUrl(),
            'target_platform'                   => $this->wsf->getTargetPlatform(),
            'target_ecommerce'                  => $this->ecommerceHelper->getTargetEcommerce(),
            'page_type'                         => $this->ecommerceHelper->getPageType(),
            'is_cart'                           => ($this->ecommerceHelper->isCart() == true)?1:0,
            'is_product'                        => ($this->ecommerceHelper->isProduct() == true)?1:0,
            'cart_url'                          => $this->ecommerceHelper->getCartUrl(),
            'ajax_url'                          => $this->getPluginAjaxUrl("price_callback"),
            'disable_ajax_price_product_page'   => $this->settingsModel->getValue("disable_ajax_price_product_page"),
            'single_product_ajax_hook_class'    => $this->settingsModel->getValue("single_product_ajax_hook_class"),
            'hide_alert_errors'                 => $this->calculatorHelper->getHideAlertErrors(),
        ));
        
    }
    
    /**
     * Returns the Plugin AJAX URL
     *
     * @param string $task
     * @param array $params
     * @return string
     */
    public function getPluginAjaxUrl($task, $params = array()){
        
        $params['task']     = $task;
        
        if($this->wsf->getTargetPlatform() == "wordpress"){
            return $this->wsf->getAjaxUrl(array_merge($params, array(
                'action'    => 'awspricecalculator_ajax_callback',
            )));
        }else if($this->wsf->getTargetPlatform() == "joomla"){
            return $this->wsf->getAjaxUrl(array_merge($params, array(
                'plugin'    => 'hikapricecalculator_ajax',
            )));
        }
    }


    /**
     * Asynchronous call.
     *
     * Update in asynchronously mode the instances of the products wich has a calculator attached in base of the task required.
     *
     * @return void
     */
    public function ajaxCallback(){
        $task               = $this->wsf->requestValue('task');

        if($task == "price_callback"){
            $action             = $this->wsf->requestValue('wpc_action');
            $productId          = $this->wsf->requestValue('id');
            $calculatorId       = $this->wsf->requestValue('simulatorid');
            $cartItemKey        = $this->wsf->requestValue('cart_item_key');
            $quantity           = $this->wsf->requestValue('quantity');
            $page               = $this->wsf->requestValue('page');
            $compositeBasePrice = $this->wsf->requestValue('compositeBasePrice');


            $this->calculatorHelper->calculatePriceAjax($action, $productId, $calculatorId, $cartItemKey, $quantity, $page, $compositeBasePrice);
        }else if($task == "products_callback"){
            $this->productHelper->getAjaxProductsTable();
        }
    }
    
    /**
     * Trasform SQL to be more readable for the user
     *
     * @param string $sql, query
     * @param array $fields
     * @return string
     */
    public function getSqlForUser($sql, $fields = array()){
        $retSql     = $sql;

        $highlights = array(
            " OR "      => " <i>OR</i> ",
            " AND "     => " <i>AND</i><br/>",
            "LIKE"      => " <i>LIKE</i>",
        );
        
        foreach($highlights as $search => $replace){
            $retSql     = str_replace($search, $replace, $retSql);
        }
        
        /* FieldId => FieldLabel */
        $fieldNames = array();
        foreach($fields as $field){
            $fieldNames[$field->id]     = $field->label;
        }
        
        /* Ordering fields to avoid/replace bugs */
        uksort($fieldNames, function($a, $b){return strlen($a) < strlen($b);});
        
        foreach($fieldNames as $fieldId => $fieldLabel){
            $retSql     = str_replace("aws_price_calc_{$fieldId}", "<b>$fieldLabel</b>", $retSql);
        }
        
        return $retSql;
    }
     
}
