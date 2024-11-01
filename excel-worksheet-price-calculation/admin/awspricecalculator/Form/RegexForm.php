<?php
/**
 * @package AWS Price Calculator
 * @author Enrico Venezia
 * @copyright (C) Altos Web Solutions Italia
 * @license GNU/GPL v2 http://www.gnu.org/licenses/gpl-2.0.html
**/

namespace AWSPriceCalculator\Form;

/*AWS_PHP_HEADER*/

use WSF\Helper\FrameworkHelper;

class RegexForm {
    
    private $wsf;
    
    private $form;
    
    private $calculatorModel;
    
    public function __construct(FrameworkHelper $wsf) {
        $this->wsf = $wsf;
        
        $this->form[] = array(
            'name' => 'name'
        );
        
        $this->form[] = array(
            'name' => 'regex'
        );

    }

    /**
     * Check and validate a regex expression
     *
     * @param array $record
     * @param array $params
     * @return array
     */
    public function check($record, $params = array()){
        
        $errors = array();
        
        if(empty($record['name'])){
            $errors[]   = "- Name must not be empty.";
        }
        
        if(@preg_match($record['regex'], "") === false){
            $regexError     = error_get_last();
            $errors[]       = $this->wsf->trans('field.form.error.regex_error', array('error_message' => $regexError['message']));
        }

        return $errors;
    }

    /**
     * Get the form
     *
     * @return array
     */
    public function getForm(){
        return $this->form;
    }

    /**
     * Set the form
     *
     * @return void
     */
    public function setForm($form){
        $this->form = $form;
    }
}

