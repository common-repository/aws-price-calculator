<?php
/**
 * @package AWS Price Calculator
 * @author Enrico Venezia
 * @copyright (C) Altos Web Solutions Italia
 * @license GNU/GPL v2 http://www.gnu.org/licenses/gpl-2.0.html
**/

/*
 * Framework for Wordpress
 */
namespace WSF\Helper;

/*AWS_PHP_HEADER*/

use WSF\Controller;

class FrameworkHelper {
        var $plugin_label;
        var $plugin;
        var $pluginDirPath;
        
        var $view;
        
        var $controllerName;
        var $actionName;
        
        var $controller;
        
        var $executions;
        
        var $targetPlatform;
        
        var $translatorFiles;
        
        var $version;
        
        public function __construct($plugin, $pluginDirPath, $targetPlatform, $translatorFiles = array()){
            
            $this->plugin               = $plugin;
            $this->pluginDirPath        = $pluginDirPath;
            $this->targetPlatform       = $targetPlatform;
            $this->translatorFiles      = $translatorFiles;
            
            if($this->getTargetPlatform() == "joomla"){
                //Faccio in modo che la libreria jquery.js sia caricata come prima
                \JHtml::_('jquery.framework', true, true);
            }
  
        }

    /**
     * Get the plug-in directory path.
     *
     * @return string
     */
    public function getPluginDirPath(){
        return $this->pluginDirPath;
    }

    /**
     * Set the software version.
     *
     * @return void
     */
    public function setVersion($version){
        $this->version  = $version;
    }

    /**
     * Get the software version.
     *
     * @return string
     */
    public function getVersion(){
        return $this->version;
    }

    /**
     * Route functions.
     *
     * Execute the right action in controller in base of the parameters
     *
     * @param string $path
     * @param bool $admin , is true if the user is admin
     * @param string $namespace
     * @param string $pcontroller , name of the controller
     * @param string $paction , name of the action(function) inside a controller
     * @return void
     */
    public function execute($path = null, $admin = null, $namespace = null, $pcontroller = null, $paction = null){
        if(empty($pcontroller)){
            $controllerRequest     = $this->requestValue('controller');
        }else{
            $controllerRequest     = $pcontroller;
        }

        if(empty($paction)){
        /*
         * Force by request from GET, so that other components,
         * can use the GET, with hidden action type variables.
         */

            $actionRequest         = $this->requestValue('action', 'GET');
        }else{
            $actionRequest         = $paction;
        }

        if(empty($actionRequest)){
            $actionRequest = "index";
        }

        $this->actionName = $actionRequest;

        $actionRequest      .= 'Action';

        $controllerName = $this->getControllerName($controllerRequest);
        $controllerClass = "{$namespace}\\{$controllerName}";

        $controllerPath = $this->getPluginPath("{$path}/Controller/{$controllerName}.php", $admin);

        $this->controllerName = $controllerName;

        require_once($controllerPath);

        $controller = new $controllerClass($this);
        $this->controller = array(
            'instance'  => $controller,
            'path'      => $path,
            'admin'     => $admin,
        );

        if(empty($this->executions)){
            $this->executions = array();
        }

        /* Avoid loops when performing actions */
        if($this->checkLoop($controllerName, $actionRequest, $this->executions) == true){
            return;
        }

        $this->executions[] = array(
            'controller'    => $controllerName,
            'action'        => $actionRequest,
        );

        /* DEBUG */
        if(count($this->executions) >= 1){
            //print_r($this->executions);
           // exit(-1);
        }

        $controller->{$actionRequest}();
    }

    /**
     * Get the first execution.
     *
     * @return array
     */
    public function getFirstExecution(){
        return $this->executions[0];
    }

    /**
     * Check a specific execution.
     *
     * @param string $controller
     * @param string $action
     * @param array $executions
     * @return bool
     */
    private function checkLoop($controller, $action, $executions){
        foreach($executions as $execution){
            if($execution['controller'] == $controller &&
               $execution['action']     == $action){
                return true;
	}
            }
            
            return false;
    }
    
    /**
     * Check a request.
     *
     * Check a request in base of different specifications.
     *
     * @param string $name describe the request.
     * @param string $type[REQUEST] type of the request exmp :GET or POST.
     * @param string $default[null] value.
     *
     * @return bool | string | array
     */
    public function requestValue($name = null, $type = "REQUEST", $default = null){
        if($type == null){
            $type   = "REQUEST";
        }

        if($this->getTargetPlatform() == "wordpress"):
            if(empty($name)){
                if($type == "REQUEST"){
                    return $_REQUEST;
                }else if($type == "GET"){
                    return $_GET;
                }else if($type == "POST"){
                    return $_POST;
                }
            }

            if($type == "REQUEST"){
                if(isset($_REQUEST[$name])){
                    return is_string($_REQUEST[$name])?stripslashes($_REQUEST[$name]):$_REQUEST[$name];
                }
            }else if($type == "GET"){
                if(isset($_GET[$name])){
                    return is_string($_GET[$name])?stripslashes($_GET[$name]):$_GET[$name];
                }
            }else if($type == "POST"){
                if(isset($_POST[$name])){
                    return is_string($_POST[$name])?stripslashes($_POST[$name]):$_POST[$name];
                }
            }

            return $default;
        elseif($this->getTargetPlatform() == "joomla"):

            $jinput             = \JFactory::getApplication()->input;

            $values['GET']       = $jinput->get->get($name, $default, 'STR');
            $values['POST']      = $jinput->post->get($name, $default, 'STR');
            $values['REQUEST']   = $jinput->request->get($name, $default, 'STR');

            if(empty($name)){
                if($type == "REQUEST"){
                    return \JFactory::getApplication()->input->request->getArray();
                }else if($type == "GET"){
                    return \JFactory::getApplication()->input->get->getArray();
                }else if($type == "POST"){
                    return \JFactory::getApplication()->input->post->getArray();
                }
            }

            return $values[$type];

        endif;
    }
    
    /**
     * Return the path of the plug-in.
     *
     * @param string $relpath relativo path del plug-in.
     * @param bool $admin certificazione dell prodotto.
     * @return string
     */
    function getPluginPath($relpath, $admin = false){
        if($this->getTargetPlatform() == "joomla"){
            if($admin == true){
                return JPATH_ROOT . "/administrator/components/com_hikapricecalculator/{$relpath}";
            }else{
                return JPATH_ROOT . "/components/com_hikapricecalculator/{$relpath}";
            }


        }else if($this->getTargetPlatform() == "wordpress"){
            return "{$this->pluginDirPath}{$this->plugin}/admin/{$relpath}";                
        }

    }

    function getUploadUrl($relPath){
        if($this->getTargetPlatform() == "wordpress"){
            $uploadDirArray     = wp_upload_dir();

            return "{$uploadDirArray['baseurl']}/{$this->plugin}/{$relPath}";
        }else if($this->getTargetPlatform() == "joomla"){
            return "{$this->getSiteUrl()}/media/com_hikapricecalculator/{$relPath}";
        }
    }
    
    /**
     * Get the upload path.
     *
     * @param string $relPath, the relative path
     * @return string
     */
    function getUploadPath($relPath){
        if($this->getTargetPlatform() == "wordpress"){
            $uploadDirArray     = wp_upload_dir();
            return "{$uploadDirArray['basedir']}/{$this->plugin}/{$relPath}";
        }else if($this->getTargetPlatform() == "joomla"){
            return JPATH_ROOT . "/media/com_hikapricecalculator/{$relPath}";
        }

    }

    /**
     * Get the site url.
     *
     * @return string
     */
    function getSiteUrl(){
        if($this->targetPlatform == "wordpress"){
            $siteUrl    = site_url();
        }else if($this->targetPlatform == "joomla"){
            $siteUrl    = \JURI::root();
        }

        /* Remove if the last character present is / */
        return rtrim($siteUrl, '/');
    }
    
    /**
     * Get the plug-in url.
     *
     * @param string $relPath, the relative path
     * @return string
     */
    function getPluginUrl($relpath = ''){
        return  plugins_url()."/{$this->plugin}/{$relpath}";
    }

    /**
     * Get the resources url.
     *
     * @param string $readpath, the relative path
     * @return string
     */
    function getResourcesUrl($readpath = ''){
        $siteUrl    = $this->getSiteUrl();
        if($this->targetPlatform == "wordpress"){
            return plugins_url()."/{$this->plugin}/admin/resources/{$readpath}";
        }else if($this->targetPlatform == "joomla"){
            return "{$siteUrl}/administrator/components/com_hikapricecalculator/resources/{$readpath}";
        }
    }

    /**
     * Get the controller name.
     *
     * @param string $controllerName
     * @return string
     */
    function getControllerName($controllerName = null){
        if(empty($controllerName)){
            $retControllerName = 'Index';
        }else{
            $retControllerName = $controllerName;
            $retControllerName = ucfirst($retControllerName);
        }

        $retControllerName .= 'Controller';

        return $retControllerName;
    }
    
    /**
     * Render requested view.
     *
     * @param array $view
     * @param array $params
     * @param bool $absolutePath
     * @return void
     */
    function renderView($view, $params = array(), $absolutePath = false){

        foreach($params as $param_name => $param_value){
            $this->view[$param_name] = $param_value;
        }

        if($absolutePath == false){
            if(!isset($this->controller['admin'])){
                $admin      = false;
            }else{
                $admin      = $this->controller['admin'];
            }

            require($this->getPluginPath("{$this->controller['path']}/View/{$view}", $admin));
        }else{
            require($view);
        }
    }
    
    /**
     * Get the view .
     *
     * @param array $module
     * @param array $view, the relative path
     * @param bool $admin
     * @param array $params
     * @return string
     */
    function getView($module, $view, $admin, $params = array()){
        $this->controller['path']   = $module;

        foreach($params as $param_name => $param_value){
            $this->view[$param_name] = $param_value;
        }

        ob_start();
        require($this->getPluginPath("{$module}/View/{$view}", $admin));
        $view   = ob_get_contents();
        ob_end_clean();

        return $view;
    }
    
    /**
     * Require the custom files
     *
     * If the user want to use a custom theme , this function requires all the custom files.
     *
     * @param string $path the path of a specific file.
     * @param array $params the names of the files to be included.
     * @return object[view]
     */
    function requireFile($path, $params = array()){
        foreach($params as $param_name => $param_value){
            $this->view[$param_name] = $param_value;
        }

        ob_start();
        require($path);
        $view   = ob_get_contents();
        ob_end_clean();

        return $view;
    }
    
    /**
     * Get Class.
     *
     * Return an instance of the class passed in the parameters.
     *
     * @param string $namespace.
     * @param bool $admin the certification of the plug-in.
     * @param string $path the specified path.
     * @param string $class classes to create instances.
     * @param array $params.
     * @return object
     * @throws \ReflectionException
     */
    function get($namespace, $admin, $path, $class, $params = array()){
        $className = $namespace . '\\' . $class;

        if(!class_exists($className)){
            require_once($this->getPluginPath($path . '/' . $class . '.php', $admin));
        }


        $reflection = new \ReflectionClass($className); 
        return $reflection->newInstanceArgs($params); 
    }
    
    /**
     * Get the plugin label.
     *
     * @return string
     */
    function getPluginLabel(){
        return $this->plugin_label;
    }

    /**
     * Get the plugin directory.
     *
     * @return string
     */
    function getPluginDir(){
        return $this->plugin;
    }
    
    /**
     * Return the admin page url.
     *
     * @param array $params
     * @return string
     */
    function adminUrl($params = null){
        if($this->targetPlatform == "wordpress"){
            $url = "admin.php?page={$this->plugin}";

            foreach($params as $key => $value){
                if(!empty($value)){
                    $url .= '&' . $key . '=' . $value;
                }
            }
            return esc_url_raw(admin_url($url));
        }else if($this->targetPlatform == "joomla"){
            $url = "administrator/index.php?option=com_hikapricecalculator";

            foreach($params as $key => $value){
                if(!empty($value)){
                    $url .= '&' . $key . '=' . $value;
                }
            }
            return "{$this->getSiteUrl()}/{$url}";
        }
    }
    
    /**
     * Get the current controller name.
     *
     * @return string
     */
    function getCurrentControllerName(){
        return $this->controllerName;
    }
    
    /**
     * Get the current action name.
     *
     * @return string
     */
    function getCurrentActionName(){
        return $this->actionName;
    }

    /*
     * Get the file path of the user
     * 
     * @return string Path of file
     */
    function getUserCurrentLocaleFilePath(){
        $defaultLocale      = "en_US";
        $locale             = $this->getLocale();
        $langFilePath       = $this->getUploadPath("translations/{$locale}.php");

        if(empty($locale) || file_exists($langFilePath) == false){
            $locale         = $defaultLocale;

            $langFilePath   = $this->getUploadPath("translations/{$locale}.php");

            if(file_exists($langFilePath) == false){
                return false;
            }
        }

        return $langFilePath;
    }


    /**
     * Perform the translations of the text fields using the user's chosen language.
     *
     * @param string $string, string to convert
     * @param array $tokens
     * @return string
     */
    function userTrans($string, $tokens = array()){

        $langFilePath       = $this->getUserCurrentLocaleFilePath();
        $translations       = array();

        if($langFilePath !== false){
            $translations   = include $langFilePath;
        }

        if(isset($this->translatorFiles['user'])){
            foreach($this->translatorFiles['user'] as $translatorFile){
                if(!empty($translatorFile)){
                    $translations   = array_merge($translations, include $translatorFile);
                }
            }
        }

        if(!isset($translations[$string])){
            return $string;
        }

        $translation    = $translations[$string];

        foreach($tokens as $key => $value){
            $translation     = str_replace("%{$key}%", $value, $translation);
        }

        if(empty($translation)){
            return $string;
        }

        return $translation;
    }

    /**
     * Return the locale in xx_XX format
     *
     * @return string
     */
    function getLocale(){
        if($this->targetPlatform == "wordpress"){
            return get_locale();
        }else if($this->targetPlatform == "joomla"){
            $locale = \JFactory::getLanguage()->getLocale();
            return $locale[2];
        }
    }
    
    /**
     * Returns the controller path
     *
     * @return string
     */
    function getControllerPath(){
        if(empty($this->controller['path'])){
            return ($this->plugin == "excel-worksheet-price-calculation")?"awspricecalculator":$this->plugin; /* Default */
        }

        return $this->controller['path'];
    }

    /*
     * Get the current locale file path for the system
     * 
     * @return string File path
     */
    function getSystemCurrentLocaleFilePath(){
        $defaultLocale      = "en_US";   
        $locale             = $this->getLocale();

        $langFilePath       = $this->getPluginPath("{$this->getControllerPath()}/Language/{$locale}.php", true);

        if(empty($locale) || file_exists($langFilePath) == false){
            $locale         = $defaultLocale;
            $langFilePath   = $this->getPluginPath("{$this->getControllerPath()}/Language/{$locale}.php", true);
        }

        return $langFilePath;

    }
    
    /**
     * Strings in different languages.
     *
     * Return the string requested by a key in the required language.
     *
     * @param string $string
     * @param array $tokens
     * @return string
     */
    function trans($string, $tokens = array()){

        $langFilePath   = $this->getSystemCurrentLocaleFilePath();
        $translations   = include $langFilePath;

        if(isset($this->translatorFiles['system'])){
            foreach($this->translatorFiles['system'] as $translatorFile){
                if(!empty($translatorFile)){
                    $translations   = array_merge($translations, include $translatorFile);
                }
            }
        }

        if(isset($translations[$string])){
            $translation    = $translations[$string];

            foreach($tokens as $key => $value){
                $translation     = str_replace("%{$key}%", $value, $translation);
            }
        }else{
            return $string;
        }

        if(empty($translation)){
            return $string;
        }

        return $translation;
    }

    /**
     * Default translation.
     *
     * If no user translations are defined, the system translation will be used.
     *
     * @param string $string
     * @param array $tokens
     * @return string
     */
    function mixTrans($string, $tokens = array()){

        $trans  = $this->userTrans($string, $tokens);

        /* No user translations defined, getting system translation */
        if(empty($trans) || $string == $trans){
            $trans  = $this->trans($string, $tokens);
        }

        return $trans;
    }

    /**
     * Requested form.
     *
     * Return an array with the values given by submitting a form.
     *
     * @param object $formClass
     * @param array $setValues
     * @return array
     */
    public function requestForm($formClass, $setValues = array()){
        $ret = array();

        $fields = $formClass->getForm();

        foreach($fields as $field){
            $default = $this->isset_or($field['default']);

            $ret[$field['name']] = $this->requestValue($field['name'], null, $default);
        }

        $ret = array_merge($ret, $setValues);

        return $ret;
    }
    
    /**
     * Set the form fields
     *
     * @return void
     */
    public function setFormField($formClass, $field_name, $field_value){
        $fields = $formClass->getForm();

        $fields[$field_name] = $field_value;

        $formClass->setForm($fields);
    }
    
    /**
     * Check if the request is of the type POST
     *
     * @return bool
     */
    public function isPost(){
        if($_SERVER['REQUEST_METHOD'] == 'POST'){
            return true;
        }

        return false;
    }
    
    /**
     * Helper function to check if a variable is set
     *
     * @param parent $check, variable to check
     * @param parent $alternate, the value to return otherwise
     * @return object | string | int | parent
     */
    function isset_or(&$check, $alternate = NULL){
        return (isset($check)) ? $check : $alternate;
    } 

    /**
     * Get JSON decode.
     *
     * It performs the decoding of JSON code inserted in the database
     *
     * @param string $string
     * @return string
     */
    public function decode($string){
        $ret = $string;

        $ret = str_replace("\\\"", '"', $ret);
        $ret = str_replace("\\'", "'", $ret);

        return $ret;
    }
    
    /**
     * Get license.
     *
     * @return string
     */
    public function getLicense(){
        $license   = file_get_contents($this->getPluginPath("resources/data/license.bin", true));

        return $license;
    }
    
    /**
     * Get the url of a given image.
     *
     * @param string $imagePath
     * @return string
     */
    public function getImageUrl($imagePath){
        return $this->getResourcesUrl("assets/images/{$imagePath}");
    }
      
        
    /**
     * Get the current used platform.
     *
     * @return string
     */
    public function getTargetPlatform(){
        return $this->targetPlatform;
    }
    
    /**
     * Insert javascript files
     *
     * @param string $name
     * @param string $url
     * @param array $deps
     * @param bool $version
     * @return void
     */
    public function enqueueScript($name, $url, $deps = array(), $version = false){
        if($this->getTargetPlatform() == "wordpress"){
            wp_enqueue_script($name, $this->getResourcesUrl($url), $deps, $version); 
        }else if($this->getTargetPlatform() == "joomla"){
            
            /* Librerie già caricate */
            if(!in_array($url, array(
                'lib/wsf-bootstrap-4.5.0/js/bootstrap.js',
            ))){
                if($version !== false){
                    $url    = "$url?ver={$version}";
                }

                $document = \JFactory::getDocument();
                $document->addScript($this->getResourcesUrl($url));
            }
        }
    }
     
    /**
     * Insert stylesheet files
     *
     * @param string $name
     * @param string $url
     * @param bool $absolute
     * @return void
     */
    public function enqueueStyle($name, $url, $absolute = false, $version = false, $deps = array()){
        
        if($absolute == false){
            $url    = $this->getResourcesUrl($url);
        }
        
        if($this->getTargetPlatform() == "wordpress"){
            wp_enqueue_style($name, $url, $deps, $version); 
        }else if($this->getTargetPlatform() == "joomla"){
            $document = \JFactory::getDocument();
            $document->addStyleSheet($url);
        }
    }
    
    /**
     * Create javascript variables
     *
     * @param string $handle
     * @param string $name
     * @param array $data
     * @return void
     */
    public function localizeScript($handle, $name, $data){
        if($this->getTargetPlatform() == "wordpress"){
            wp_localize_script($handle, $name, $data);
        }else if($this->getTargetPlatform() == "joomla"){
            $document   = \JFactory::getDocument();
            $jsonData   = json_encode($data);
            
            $document->addScriptDeclaration("
                /* <![CDATA[ */
                var {$name} = {$jsonData};
                /* ]]> */
            ");
        }
    }
    
    /**
     * Get ajax base url file
     *
     * @return string
     */
    public function getAjaxBaseUrl(){
        $siteUrl        = $this->getSiteUrl();
        
        if($this->getTargetPlatform() == "wordpress"){
            return "{$siteUrl}/wp-admin/admin-ajax.php";
        }else if($this->getTargetPlatform() == "joomla"){
            return "{$siteUrl}/index.php?option=com_ajax&format=raw";
        }
    }
    
    /**
     * Get url to create ajax
     *
     * @param array $params
     * @return string
     */
    public function getAjaxUrl($params = array()){
        $stringParams   = http_build_query($params);
        $baseAjaxUrl    = $this->getAjaxBaseUrl();
        
        if(count($params) == 0){
            return $baseAjaxUrl;
        }
        
        if($this->getTargetPlatform() == "wordpress"){
            
            //url: WPC_HANDLE_SCRIPT.siteurl + "/wp-admin/admin-ajax.php?action=ajax_callback&id=" + productId + "&simulatorid=" + simulatorId,
            return "{$baseAjaxUrl}?{$stringParams}";
        }else if($this->getTargetPlatform() == "joomla"){
            return "{$baseAjaxUrl}&{$stringParams}";
        }
    }
    
    /**
     * Get the entire POST request
     *
     * @return array
     */
    public function getPost(){
        if($this->getTargetPlatform() == "wordpress"){
            return $_POST;
        }else if($this->getTargetPlatform() == "joomla"){
            return \JFactory::getApplication()->input->post->getArray();
        }
    }

    /**
     * Create folder in the given path (also recursively)
     *
     * @param string $path
     * @return void
     */
    public function createFolder($path){
        if($this->getTargetPlatform() == "wordpress"){
            wp_mkdir_p($path);
        }else if($this->getTargetPlatform() == "joomla"){
            \JFolder::create($path);
        }
    }
    
    
    /**
     * Get the active template.
     *
     * In case used in a Joomla CMS this function return the current active template by the plug-in
     *
     * @return string
     * @throws \ReflectionException
     */
    public function getCmsActiveTemplateName(){
        if($this->getTargetPlatform() == "wordpress"){
            throw new Exception("getCmsActiveTemplateName not implemented");
        }else if($this->getTargetPlatform() == "joomla"){
            /*
             * Non ho trovato altro modo, tranne che usare una query per prendere
             * il nome del template attivo
             */
            $databaseHelper   = $this->get('\\WSF\\Helper', true, 'awsframework/Helper', 'DatabaseHelper', array($this));
            $res              = $databaseHelper->getRow("SELECT template FROM [prefix]template_styles WHERE client_id = 0 AND home = 1");

            return $res->template;
        }

    }
    
    /**
     * Get the active template path.
     *
     * In case used in a Joomla CMS this function return the current active template path.
     *
     * @param string $relativePath il path relativo del template
     * @return string
     * @throws \ReflectionException
     */
    public function getCmsActiveTemplatePath($relativePath = ''){
        if($this->getTargetPlatform() == "wordpress"){
            throw new \Exception("getCmsActiveTemplatePath not implemented");
        }else if($this->getTargetPlatform() == "joomla"){
            $root           = JPATH_ROOT;
            $templateName   = $this->getCmsActiveTemplateName();
            
            return "{$root}/templates/{$templateName}/{$relativePath}";
        }

    }
    
    /**
     * Redirects to the given url
     *
     * @param string $url
     * @return void
     */
    public function redirect($url){
        if($this->getTargetPlatform() == "wordpress"){
            wp_redirect($url);
        }else if($this->getTargetPlatform() == "joomla"){
            header("Location: {$url}");
        }
        
        die();
    }


    /**
     * Check required extensions
     *
     * If an extension or a group of are missing, return them
     *
     * @return null | string
     */
    public function checkRequiredExtensions($required_extensions){
        $extension_message=null;

        foreach ($required_extensions as $extension => $value){
            if (!in_array($extension , get_loaded_extensions())){
                $extension_message .= "'".$value."' "." ";

            }

        }

        if (empty($extension_message)){
            return null;
        }else return $extension_message;

    }

    /**
     * Check required directories
     *
     * If a directory or a group of are missing, return them
     *
     * @return array
     */
    public function checkRequiredDirectories(){
        $requiredDirectories    = array("docs" , "style" , "themes" , "translations", "tmp_files");
        $missingDirectories     = array();

        //Added to rename old upload folder to the new name
        $uploadPath = $this->getUploadPath("");
        $oldPath = str_replace($this->plugin,"woo-price-calculator",$uploadPath);
        if (file_exists($oldPath)){
            rename($oldPath, $uploadPath);
        }

        foreach($requiredDirectories as $requiredDirectory){
            $path   = $this->getUploadPath($requiredDirectory);
            
            if(!file_exists($path)){
                array_push($missingDirectories , $path);
            }
        }

        return $missingDirectories;

    }
    
    /**
     * Encoding type.
     *
     * Check the encoding type of a string and return it in the required way.
     * @param string $text
     * @param bool $htmlEntities
     * @param bool $return
     * @return string
     */
    public function e($text, $htmlEntities = false, $return = false){
        if($htmlEntities == true){
            $text   = htmlentities($text, ENT_COMPAT | ENT_HTML401, "UTF-8");
        }else{
            $text   = utf8_encode($text);
        }
        
        if($return == true){
            return $text;
        }
        
        echo $text;
    }
    
    /**
     * Get an image Thumbnail from the src url
     *
     * @param string $imageSrc
     * @param string $size
     * @param bool $icon
     * @param string $attr
     * @throws "getImageThumbnail not implemented for Joomla!"
     * @return string
     */
    public function getImageThumbnail($imageSrc, $size = 'thumbnail', $icon = false, $attr = ''){
        if($this->getTargetPlatform() == "wordpress"){
            $attachmentId		= attachment_url_to_postid($imageSrc);

            return wp_get_attachment_image($attachmentId, $size, $icon, $attr);
            
        }else{
            throw "getImageThumbnail not implemented for Joomla!";
        }
    }
    
    /*
     * Get home path of the website
     * 
     * @return string The website home path
     */
    function getHomePath(){
        $home    = set_url_scheme( get_option( 'home' ), 'http' );
        $siteurl = set_url_scheme( get_option( 'siteurl' ), 'http' );
        if ( ! empty( $home ) && 0 !== strcasecmp( $home, $siteurl ) ) {
                $wp_path_rel_to_home = str_ireplace( $home, '', $siteurl ); /* $siteurl - $home */
                $pos                 = strripos( str_replace( '\\', '/', $_SERVER['SCRIPT_FILENAME'] ), trailingslashit( $wp_path_rel_to_home ) );
                $home_path           = substr( $_SERVER['SCRIPT_FILENAME'], 0, $pos );
                $home_path           = trailingslashit( $home_path );
        } else {
                $home_path = ABSPATH;
        }

        return str_replace( '\\', '/', $home_path );
    }
    
    function getCurrentUrl(){
        return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    }

    function setCookie($key, $value, $expire = 0){
        $val = base64_encode(serialize($value));
        if ( ! headers_sent() ) {
            setcookie ($key, $val, $expire, '/',COOKIE_DOMAIN);
        }
    }

    function getCookie($key){
        if ( isset($_COOKIE[$key])){
            return unserialize(base64_decode($_COOKIE[$key]));
        }else {
            return null;
        }
    }

    function deleteCookies($cookie){
        if(is_array($cookie)){
            foreach($cookie as $c){
                $this->setCookie($c,0,time() - HOUR_IN_SECONDS);
            }
        }else {
            $this->setCookie($cookie,0,time() - HOUR_IN_SECONDS);
        }
    }
}
