<?php
/**
 * @package AWS Price Calculator
 * @author Enrico Venezia
 * @copyright (C) Altos Web Solutions Italia
 * @license GNU/GPL v2 http://www.gnu.org/licenses/gpl-2.0.html
**/

/*WPC-PRO*/

namespace AWSPriceCalculator\Controller;

/*AWS_PHP_HEADER*/

use WSF\Helper\FrameworkHelper;

class RegexController {
    private $wsf;
    
    private $tableHelper;
    private $calculatorHelper;
    
    private $calculatorTable;
    
    private $fieldModel;
    private $calculatorModel;
    
    private $wooCommerceHelper;
    
    public function __construct(FrameworkHelper $wsf){
        // if(!session_id()){
        //     session_start();
        // }
        
        $this->wsf  = $wsf;
        
        $this->tableHelper  = $this->wsf->get('\\AWSPriceCalculator\\Helper', true, 'awspricecalculator/Helper', 'TableHelper', array($this->wsf));
        
        /* MODELS */
        $this->regexModel   = $this->wsf->get('\\AWSPriceCalculator\\Model', true, 'awspricecalculator/Model', 'RegexModel', array($this->wsf));

    }

    /**
     * Regex section.
     *
     * It is the entry point for the regex panel.
     * Where it is able to create , modify and delete regex rules for input fields
     *
     * @return void
     */
    public function indexAction(){
        $this->wsf->execute('awspricecalculator', true, '\\AWSPriceCalculator\\Controller', 'index', 'index');
        
        $this->wsf->renderView('regex/list.php', array(
            'list_header'    => array(
                'name'              => $this->wsf->trans('wpc.regex.name'),
                'description'       => $this->wsf->trans('wpc.regex.user_created'),
                'actions'           => $this->wsf->trans('wpc.actions'),
            ),
            'list_rows'      => $this->regexModel->get_list(),
        ));
    }

    /**
     * Create a new regex rule.
     *
     * Generate the form that creates new regex rules and save them to the database.
     *
     * @return void
     * @throws \ReflectionException
     */
    public function formAction(){
        $this->wsf->execute('awspricecalculator', true, '\\AWSPriceCalculator\\Controller', 'index', 'index');

        $form                   = null;
        $regexForm              = $this->wsf->get('\\AWSPriceCalculator\\Form', true, 'awspricecalculator/Form', 'RegexForm', array($this->wsf));
        $errors                 = array();
        
        $id                     = $this->wsf->requestValue('id');
        $task                   = $this->wsf->requestValue('task');

        if(!empty($id)){
            
            $regex = $this->regexModel->get($id);
            
            $form = $this->wsf->requestForm($regexForm, array(
                'name'          => $regex->name,
                'regex'         => $regex->regex,
            ));
        }
        
        if($this->wsf->isPost() && $task == 'form'){
            $form       = $this->wsf->requestForm($regexForm);
            $errors     = $regexForm->check($form, array('id' => $id));

            if(count($errors) == 0){
                    $insertId   = $this->regexModel->save($form, $id);
                    
                    $id         = (empty($insertId))?$id:$insertId;

                //checking if the record was created in the database, if not display an error message
                if($id == 0){

                    $this->wsf->renderView('app/form_message.php', array(
                        'type'                  => 'danger',
                        'message'               => $this->wsf->trans('database_problem'),
                    ));


                }else {

                    $this->wsf->renderView('app/form_message.php', array(
                        'message'   => $this->wsf->trans('aws.regex.form.success'),
                        'url'       => $this->wsf->adminUrl(array('controller' => 'regex'))
                    ));
                }



            }
        }

        $this->wsf->renderView('regex/form.php', array(
            'title'                     => $this->wsf->trans('Edit'),
            'form'                      => $form,
            'errors'                    => $errors,
            'id'                        => $id,
        ));
    }

    /**
     * Delete an existing regex rules.
     *
     * @return void
     */
    public function deleteAction(){
        $id = $this->wsf->requestValue('id');
        
        $this->regexModel->delete($id);
        
        $this->wsf->execute('awspricecalculator', true, '\\AWSPriceCalculator\\Controller', 'regex', 'index');
    }
    
}

/*/WPC-PRO*/
