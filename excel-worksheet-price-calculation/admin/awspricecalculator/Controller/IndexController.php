<?php
/**
 * @package AWS Price Calculator
 * @author Enrico Venezia
 * @copyright (C) Altos Web Solutions Italia
 * @license GNU/GPL v2 http://www.gnu.org/licenses/gpl-2.0.html
**/

namespace AWSPriceCalculator\Controller;

/*AWS_PHP_HEADER*/

use WSF\Helper\FrameworkHelper;
use AWSPriceCalculator\Helper\PluginHelper;

class IndexController {
    private $wsf;
    
    public function __construct(FrameworkHelper $wsf){
        $this->wsf = $wsf;
        
        $this->pluginHelper = $this->wsf->get('\\AWSPriceCalculator\\Helper', true, 'awspricecalculator/Helper', 'PluginHelper', array($wsf));
    }

    /**
     * Render the first view, the main panel. It is the entry point to the plug-in.
     *
     * @return void
     */
    public function indexAction(){
        $logo       = $this->pluginHelper->logo();
        $icon       = $this->pluginHelper->icon();
        $credits    = $this->pluginHelper->getCreditsUrl();
        
        $page       = $this->wsf->requestValue("page");
        $controller = $this->wsf->requestValue("controller");
        
        $this->wsf->renderView('index/index.php', array(
            'logo'                  => $logo,
            'icon'                  => $icon,
            'homeUrl'               => $this->pluginHelper->getHomeUrl(),
            'documentationUrl'      => $this->pluginHelper->getDocumentationUrl(),
            'forumUrl'              => $this->pluginHelper->getForumUrl(),
            'credits'               => $credits,
            //'controller'           => $this->wsf->getCurrentControllerName(),
            
            'page'                  => $page,
            'controller'            => $controller,
            
            'tabs'                  => apply_filters('awspc_filter_index_tabs', null),
            
            'extensions'            => $this->wsf->checkRequiredExtensions(array('xml'=> 'php-xml','zip'=>'php-zip')),
            'directories'           => $this->wsf->checkRequiredDirectories(),
        ));
        
        $firstExecution = $this->wsf->getFirstExecution();

        if($firstExecution['controller'] == 'IndexController' &&
           $firstExecution['action']     == 'indexAction' &&
           empty($controller)){

            $this->wsf->execute('awspricecalculator', true, '\\AWSPriceCalculator\\Controller', 'field', 'index');
        }
    }

    /**
     * Render the footer view of the plug-in.
     *
     *
     * @return void
     */
    public function footerAction(){
        $this->wsf->renderView('app/footer.php');
    }

}
