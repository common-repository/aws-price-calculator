<?php
/**
 * @package AWS Price Calculator
 * @author Enrico Venezia
 * @copyright (C) Altos Web Solutions Italia
 * @license GNU/GPL v2 http://www.gnu.org/licenses/gpl-2.0.html
**/

namespace AWSPriceCalculator\Model;

/*AWS_PHP_HEADER*/

use WSF\Helper\FrameworkHelper;

class CalculatorModel {
    
    var $wsf;
    
    public function __construct(FrameworkHelper $wsf){
        $this->wsf = $wsf;

        $this->databaseHelper    = $this->wsf->get('\\WSF\\Helper', true, 'awsframework/Helper', 'DatabaseHelper', array($this->wsf));
    }
    
    /**
     * Return a simulator using the ID
     *
     * @param string $id, identification number
     * @return array
     */
    public function get($id){
        return $this->databaseHelper->getRow("SELECT * FROM [prefix]woopricesim_simulators WHERE id = :id", array(
            'id'    => $id,
        ));
    }
    
    /**
     * Return the list of all the simulators
     *
     * @return array
     */
    public function get_list(){
        return $this->databaseHelper->getResults("SELECT * FROM [prefix]woopricesim_simulators");
    }
    
    public function exchangeArray($object){
        return array(
            "name"                          => $object->name,
            "description"                   => $object->description,
            "fields"                        => json_decode($object->fields, true),
            "output_fields"                 => json_decode($object->output_fields, true),
            "products"                      => json_decode($object->products, true),
            "product_categories"            => json_decode($object->product_categories, true),
            "overwrite_quantity"            => $object->overwrite_quantity,
            "overwrite_weight"              => $object->overwrite_weight,
            "overwrite_length"              => $object->overwrite_length,
            "overwrite_width"               => $object->overwrite_width,
            "overwrite_height"              => $object->overwrite_height,
            "options"                       => json_decode($object->options, true),
            "formula"                       => $object->formula,
            "product_page_include_taxes"    => $object->product_page_include_taxes,
            "force_to_show_price_on_errors" => $object->force_to_show_price_on_errors,
            "hide_startup_fields_errors"    => $object->hide_startup_fields_errors,
            "redirect"                      => $object->redirect,
            "empty_cart"                    => $object->empty_cart,
            "type"                          => $object->type,
            "theme"                         => $object->theme,
            "system_created"                => $object->system_created,
        );
    }
    
    /**
     * Exchanges the object
     *
     * @param  array $array, array of data
     * @return object
     */
    public function exchangeObject($array){
        $object                 = (object)$array;

        $object->fields                 = json_encode($object->fields);
        $object->output_fields          = json_encode($object->output_fields);
        $object->products               = json_encode($object->products);
        $object->product_categories     = json_encode($object->product_categories);
        $object->options                = json_encode($object->options);
        $object->conditional_logic      = json_encode($object->conditional_logic);
        
        return $object;
    }
    
    /**
     * Saves the whole model
     *
     * @param array $data
     * @param string $id
     * @return string | null
     */
    public function save($data, $id = null){
            $record = array(
               "name"                           => $data['name'],
               "description"                    => $data['description'],
               "fields"                         => json_encode($data['fields']),
               "output_fields"                  => json_encode($data['output_fields']),
               "products"                       => json_encode($data['products']),
               "product_categories"             => json_encode($data['product_categories']),
               "options"                        => json_encode($data['options']),
                "overwrite_quantity"            => $data['overwrite_quantity'],
               "overwrite_weight"               => $data['overwrite_weight'],
               "overwrite_length"               => $data['overwrite_length'],
               "overwrite_width"                => $data['overwrite_width'],
               "overwrite_height"               => $data['overwrite_height'],
               "formula"                        => $data['formula'],
               "product_page_include_taxes"     => $data['product_page_include_taxes'],
               "force_to_show_price_on_errors"  => $data['force_to_show_price_on_errors'],
               "hide_startup_fields_errors"     => $data['hide_startup_fields_errors'],
               "redirect"                       => $data['redirect'],
               "empty_cart"                     => $data['empty_cart'],
               "type"                           => $data['type'],
               "theme"                          => $data['theme'],
               "system_created"                 => 0,
            );
                        
            if(empty($id)){
                return $this->databaseHelper->insert("[prefix]woopricesim_simulators", $record);
            }else{
                $this->databaseHelper->update("[prefix]woopricesim_simulators", $record, array(
                    'id' => $id
                ));

                return $id;
            }
            
            return null;
    }
    
    /**
     * Return the associated conditional logic of a calculator
     *
     * @param string $id, identification number of a specific calculator
     * @return array
     */
    public function getConditionalLogic($id){
        $record         = $this->get($id);
        
        return json_decode($record->conditional_logic, true);
    }


    /**
     * Save the conditional logic
     *
     * @param array $data
     * @param string $id
     * @return void
     */
    public function saveConditionalLogic($data, $id){
            $record = array(
               "conditional_logic"      => json_encode(array(
                   "enabled"                => $data['enabled'],
                   "hide_fields"            => $data['hide_fields'],
                   "field_filters_json"     => $data['field_filters_json'],
                   "field_filters_sql"      => $data['field_filters_sql'],
               ))
            );

            $this->databaseHelper->update("[prefix]woopricesim_simulators", $record, array(
                'id' => $id
            ));
    }
    
    /**
     * Saves the simulation
     *
     * @param string $orderId
     * @param array $orderData
     * @param array $dataBackup
     * @return string
     */
    public function saveSimulation($orderId, $orderData, $dataBackup){
        return $this->databaseHelper->insert("[prefix]woopricesim_simulations", array(
           "order_id"           => $orderId,
           "simulation_data"    => json_encode($orderData),
           "simulators"         => json_encode($dataBackup),
        ));
    }
    
    /**
     * Delete the model
     *
     * @param string id
     * @return void
     */
    public function delete($id){
        $this->databaseHelper->delete("[prefix]woopricesim_simulators", array(
            'id'    => $id,
        ));
    }
    
    /**
     * Return the Simulation searching on the bases of id
     *
     * @param string $orderId
     * @return object ARRAY_A | ARRAY_N | OBJECT | OBJECT_K
     */
    public function getSimulationByOrderId($orderId){
        
        return $this->databaseHelper->getRow("SELECT * FROM [prefix]woopricesim_simulations WHERE order_id = :order_id", array(
            'order_id'      => $orderId,
        ));
    }

    /**
     * Assign product to a given calculator
     *
     * @param $productsId
     * @param $simulatorId
     *
     * @return void
     */
    public function assignProductToCalculator($productsId, $simulatorId){

        if (!is_array($productsId)){
            $productsId = array();
        }

        $record = array(
            "products"      => json_encode($productsId)
        );

        $this->databaseHelper->update("[prefix]woopricesim_simulators", $record, array(
            'id' => $simulatorId
        ));

    }
    
}
