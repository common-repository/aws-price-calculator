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

class CalculatorForm {
    
    private $wsf;
    
    private $form;
    
    private $calculatorModel;
    
    public function __construct(FrameworkHelper $wsf) {
        $this->wsf = $wsf;
        
        /* MODELS */
        $this->calculatorModel      = $this->wsf->get('\\AWSPriceCalculator\\Model', true, 'awspricecalculator/Model', 'CalculatorModel', array($this->wsf));
        
        /* HELPERS */
        $this->calculatorHelper     = $this->wsf->get('\\AWSPriceCalculator\\Helper', true, 'awspricecalculator/Helper', 'CalculatorHelper', array($this->wsf));
        $this->ecommerceHelper      = $this->wsf->get('\\WSF\\Helper', true, 'awsframework/Helper', 'EcommerceHelper', array($this->wsf));
        
        $this->form[] = array(
            'name' => 'name'
        );
        
        $this->form[] = array(
            'name' => 'description'
        );
        
        $this->form[] = array(
            'name'      => 'fields',
            'default'   => array()
        );
        
        $this->form[] = array(
            'name'      => 'output_fields',
            'default'   => array()
        );
        
        $this->form[] = array(
            'name'      => 'products',
            'default'   => array()
        );
        
        $this->form[] = array(
            'name'      => 'product_categories',
            'default'   => array()
        );
        
        $this->form[] = array(
            'name'      => 'overwrite_quantity',
            'default'   => null
        );
        
        $this->form[] = array(
            'name'      => 'overwrite_weight',
            'default'   => null
        );
        
        $this->form[] = array(
            'name'      => 'overwrite_length',
            'default'   => null
        );
        
        $this->form[] = array(
            'name'      => 'overwrite_width',
            'default'   => null
        );
        
        $this->form[] = array(
            'name'      => 'overwrite_height',
            'default'   => null
        );
        
        $this->form[] = array(
            'name'      => 'options',
            'default'   => array()
        );
        
        $this->form[] = array(
            'name'      => 'field_orders',
            'default'   => array(),
        );
        
        $this->form[] = array(
            'name'      => 'output_field_orders',
            'default'   => array(),
        );
        
        $this->form[] = array(
            'name' => 'formula'
        );
        
        $this->form[] = array(
            'name' => 'product_page_include_taxes'
        );
        
        $this->form[] = array(
            'name' => 'force_to_show_price_on_errors',
        );
        
        $this->form[] = array(
            'name' => 'hide_startup_fields_errors',
        );
        
        $this->form[] = array(
            'name' => 'redirect'
        );
        
        $this->form[] = array(
            'name' => 'empty_cart'
        );
        
        $this->form[] = array(
            'name' => 'type'
        );
                
        $this->form[] = array(
            'name' => 'theme'
        );
        
        $this->form[] = array(
            'name' => 'system_created'
        );
        
    }

    /**
     * Check and validate the fields
     *
     * @param array $record
     * @param array $params
     * @return array
     */
    public function check($record, $params = array()){
        
        $errors = array();
        
        /* Check that the name is not empty */
        if(empty($record['name'])){
            $errors[] = "- Name must not be empty.";
        }

        /* Control of the formula if simple calculator */
        if($record['type'] == "simple"){
            $fieldIds                 = $record['fields'];
            $fieldsDefaultValue       = $this->calculatorHelper->getFieldsDefaultValueByFieldIds($record, $fieldIds);
            /* Fake product */
            $product                  = $this->ecommerceHelper->getProductArray(0, 'Checking formula', 100, 20);

            $calculatorArray          = array_merge($record, array(
                'id'                    => 0,
                'conditional_logic'     => null,
            ));

            $calculator                     = $this->calculatorModel->exchangeObject($calculatorArray);

            /* If it does not work, an exception returns */
            if(is_a($this->calculatorHelper->calculate($calculator, $product, $fieldsDefaultValue), 'Exception')){

                $errors[] = $this->wsf->trans('calculator.formula_problem');
            }
        }

        $errors     = array_merge($errors, $this->calculatorHelper->checkCalculatorDuplicate($record, $params['id']));


        return $errors;
    }
    
    public function checkWarnings($record, $params = array()){
        $warnings                   = array();
        
        if($record['type'] == 'excel'){
            $notMappedOutputFields      = $this->calculatorHelper->getNotMappedOutputFields($record);

            foreach($notMappedOutputFields as $notMappedOutputField){
                $warnings[]   = $this->wsf->trans('calculator.form.warning.output_field_not_mapped', array(
                    'fieldName'     => $notMappedOutputField->label,
                ));
            }
        }
        
        $warnings   = \array_merge($warnings, $this->calculatorHelper->checkProductPrices($record['products']));
        
        return $warnings;
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

