<?php
/**
 * @package AWS Price Calculator
 * @author Enrico Venezia
 * @copyright (C) Altos Web Solutions Italia
 * @license GNU/GPL v2 http://www.gnu.org/licenses/gpl-2.0.html
**/

/*AWS_PHP_HEADER*/
?>

<!--WPC-PRO-->

<div class="wsf-bs">
    <center>
        <h2>
            <?php if(empty($this->view['calculatorId'])): ?>
                <?php echo $this->trans('wpc.load_calculator'); ?>
            <?php else: ?>
                <?php echo $this->trans('wpc.calculator.edit_mapping'); ?>
            <?php endif; ?>
        </h2>
        <strong><?php echo $this->trans('You can map fields by clicking cells'); ?></strong>

        <p>
            <?php if(empty($this->view['calculatorId'])): ?>
                <a href="<?php echo $this->adminUrl(array('controller' => 'calculator', 'action' => 'loadersheet', 'file' => $this->view['file'], 'filename' => $this->view['filename'])); ?>" class="btn btn-primary"><?php echo $this->trans('wpc.previous'); ?></a>
            <?php endif; ?>
                
            <a href="<?php echo $this->adminUrl(array('controller' => 'calculator', 'action' => 'loadermapping', 'file' => $this->view['file'], 'filename' => $this->view['filename'], 'calculator_id' => $this->view['calculatorId'])); ?>" class="btn btn-primary"><?php echo $this->trans('Reload'); ?></a>
            
            <?php if(!empty($this->view['calculatorId'])): ?>
                <?php if($this->view['calculator']->system_created == false): ?>
                    <a href="<?php echo $this->adminUrl(array('controller' => 'calculator', 'action' => 'loader', 'set_token' => $this->view['file'], 'calculator_id' => $this->view['calculatorId'])); ?>" class="btn btn-primary">
                        <?php echo $this->trans('aws.calculator.reupload'); ?>
                    </a>
                <?php else: ?>
                    <button class="btn btn-primary" disabled="disabled">
                        <?php echo $this->trans('aws.calculator.reupload'); ?>
                    </button>
                <?php endif; ?>
            <?php endif; ?>
            
            <?php if(empty($this->view['calculatorId'])): ?>
                <button id="wpc_load_mapping_button" class="btn btn-primary">
                    <?php echo $this->trans('wpc.next'); ?>
                </button>
            <?php else: ?>
                <button id="wpc_load_mapping_button" class="btn btn-primary" <?php echo ($this->view['calculator']->system_created == true)?'disabled="disabled"':''; ?>>
                    <?php echo $this->trans('wpc.save'); ?>
                </button>
            <?php endif; ?>
        </p>
    </center>


    <form id="cell_next_form" action="<?php echo (empty($this->view['calculatorId']))?$this->adminUrl(array('controller' => 'calculator', 'action' => 'add', 'file' => $this->view['file'], 'filename' => $this->view['filename'])):""; ?>" method="POST">
        
        <input type="hidden" name="worksheet" value="<?php echo $this->view['worksheet']; ?>" />
        <input type="hidden" name="type" value="excel" />
        <input type="hidden" name="mapping" value="1" />
        
        <?php if(isset($this->view['mappingInfo']['input'])): ?>
            <?php foreach($this->view['mappingInfo']['input'] as $coordinates => $fieldId): ?>
            <input class="input_mapping_fields" type="hidden" id="input_field_<?php echo $fieldId; ?>[]" name="input_field_<?php echo $fieldId; ?>[]" value="<?php echo $coordinates; ?>" />
            <?php endforeach; ?>
        <?php endif; ?>
        
        <?php if(isset($this->view['mappingInfo']['error'])): ?>
            <?php foreach($this->view['mappingInfo']['error'] as $coordinates => $fieldId): ?>
            <input class="error_mapping_fields" type="hidden" id="error_field_<?php echo $fieldId; ?>[]" name="error_field_<?php echo $fieldId; ?>[]" value="<?php echo $coordinates; ?>" />
            <?php endforeach; ?>
        <?php endif; ?>
            
        <?php if(isset($this->view['mappingInfo']['output'])): ?>
            <?php foreach($this->view['mappingInfo']['output'] as $coordinates => $fieldId): ?>
            <input class="output_mapping_fields" type="hidden" id="output_field_<?php echo $fieldId; ?>" name="output_field_<?php echo $fieldId; ?>" value="<?php echo $coordinates; ?>" />
            <?php endforeach; ?>
        <?php endif; ?>
            
        <input type="hidden" id="price" name="price" value="<?php echo $this->view['mappingInfo']['price']; ?>" />
        <input type="hidden" id="tax_rate" name="tax_rate" value="<?php echo $this->view['mappingInfo']['tax_rate']; ?>" />
        <!--    sku change AT860    -->
        <input type="hidden" id="sku" name="sku" value="<?php echo $this->view['mappingInfo']['sku']; ?>" />
    </form>

    <div class="row">
        <div class="col-xs-12" style="margin-left: -7px">
            <div class="excel-container">
                <table class="worksheet ExcelTable2007">
                    <?php //echo $this->view['objWorksheet']->getHighestColumn(); ?>

                    <tr>
                        <th class="heading"></th>

                        <?php $columnIndex = 'a'; ?>
                        <?php for($i = 0; $i < $this->view['columns']; $i++): ?>
                        <th><?php echo strtoupper($columnIndex); ?></th>
                        <?php $columnIndex++; ?>
                        <?php endfor; ?>
                    </tr>

                <?php
                            try {
                                    $rowIndex   = 1;
                                    foreach ($this->view['objWorksheet']->getRowIterator() as $row){
                                        echo '<tr>' . "\n";
                                        echo "<td class='heading'>{$rowIndex}</td>";

                                        $cellIterator   = $row->getCellIterator();
                                        $cellIndex      = 0;
                                        $cellIterator->setIterateOnlyExistingCells(false);

                                        foreach($cellIterator as $cell){
                                          //$cell->getValue()

                                            $cellClass              = "";
                                            $cellDataType           = "";
                                            $cellDataFieldId        = "";
                                            $cellDataFieldText      = "";

                                            if(!empty($this->view['mappingInfo']['input'][$cell->getCoordinate()])){
                                                $fieldId            = $this->view['mappingInfo']['input'][$cell->getCoordinate()];

                                                $cellClass          = "cell_type_selected";
                                                $cellDataType       = "input";
                                                $cellDataFieldId    = $fieldId;

                                            }else if(!empty($this->view['mappingInfo']['output'][$cell->getCoordinate()])){
                                                $fieldId            = $this->view['mappingInfo']['output'][$cell->getCoordinate()];
                                                
                                                $cellClass          = "cell_type_selected";
                                                $cellDataType       = "output";
                                                $cellDataFieldId    = $fieldId;
                                                
                                            }else if(!empty($this->view['mappingInfo']['error'][$cell->getCoordinate()])){
                                                $fieldId            = $this->view['mappingInfo']['error'][$cell->getCoordinate()];
                                                
                                                $cellClass          = "cell_type_selected";
                                                $cellDataType       = "error";
                                                $cellDataFieldId    = $fieldId;
                                                
                                            }else if($this->view['mappingInfo']['price'] == $cell->getCoordinate()){
                                                $cellClass          = "cell_type_selected";
                                                $cellDataType       = "price";
                                                
                                            }else if($this->view['mappingInfo']['tax_rate'] == $cell->getCoordinate()){
                                                $cellClass          = "cell_type_selected";
                                                $cellDataType       = "tax_rate";

                                             /*    sku change AT860    */
                                            }else if($this->view['mappingInfo']['sku'] == $cell->getCoordinate()){
                                                $cellClass          = "cell_type_selected";
                                                $cellDataType       = "sku";
                                            }

                                            echo "<td class=\"cell {$cellClass}\" data-coordinates=\"{$cell->getCoordinate()}\" data-type=\"{$cellDataType}\" data-field-id=\"{$cellDataFieldId}\">{$cell->getCalculatedValue()}</td>\n";

                                            $cellIndex++;
                                        }
                                        ?>
                                        <!-- Colonne vuote -->
                                        <?php for($i = 0; $i < $this->view['columns']-$cellIndex; $i++): ?>
                                        <td class="cell disabled"></td>
                                        <?php endfor; ?>

                                        <?php
                                        echo '</tr>' . "\n";

                                        $rowIndex++;
                                    }
                                    
                                    ?>
                                    
                                    <!-- Righe vuote per un'effetto migliore -->
                                    <?php for($i = $rowIndex; $i < $this->view['MAX_EMPTY_ROWS']; $i++): ?>
                                        <tr>
                                        <td class='heading'><?php echo $i; ?></td>
                                        <?php for($h = 0; $h < $this->view['columns']; $h++): ?>
                                            <td class="cell disabled"></td>
                                        <?php endfor; ?>
                                        </tr>
                                    <?php endfor; ?>
                                    <!-- Fine righe vuote -->
                           <?php


                            }catch(PHPExcel_Calculation_Exception $ce){
                                die("<b>Your worksheet contains errors: </b>{$ce}");
                            }
                ?>
                </table>
            </div>
        </div>
    </div>

</div>

<!-- POPUP FOR CELLS SELECTION -->
<div id="cell_type" class="wsf-bs" style="display: none;">

    <div id="cell_type_no_content" style="display: none;"><?php echo $this->trans("wpc.calculator.cell.no_content"); ?></div>

    <form id="cell_type_form">
        <?php echo $this->trans('Use this field as:'); ?><br/>

        <!-- No Mapping -->
        <div id="cell_type_none_div">
            <hr/>

            <input name="cell_type_mode" type="radio" id="cell_type_none" value="none" /> <?php echo $this->trans('wpc.calculator.cell.none'); ?>
        </div>

        <!-- Input Fields Mapping -->
        <div id="cell_type_input_div">
            <hr/>

            <input name="cell_type_mode" type="radio" id="cell_type_input" value="input" /> <?php echo $this->trans('wpc.calculator.cell.input'); ?>
            <select class="" style="min-width: 300px;" id="cell_type_select_input">
                <?php foreach($this->view['fields'] as $field): ?>
                <option value="<?php echo $field->id; ?>">
                        <?php $this->e($field->label, true); ?> [aws_price_calc_<?php echo $field->id; ?>]
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Output Fields Mapping --->
        <div id="cell_type_output_div">
            <hr/>

            <input name="cell_type_mode" type="radio" id="cell_type_output" value="output" /> <?php echo $this->trans('wpc.calculator.cell.output'); ?>
            <select class="" style="min-width: 300px;" id="cell_type_select_output">
                
                <option value="price">Price</option>
                    
                <?php foreach($this->view['outputFields'] as $outputField): ?>
                    <option value="<?php echo $outputField->id; ?>">
                        <?php if($outputField->id == 'price'): ?>
                            <?php $this->e($outputField->label, true); ?>
                        <?php else: ?>
                            <?php $this->e($outputField->label, true); ?> [aws_price_calc_<?php echo $outputField->id; ?>]
                        <?php endif; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Mapping Error Cell --->
        <div id="cell_type_error_div">
            <hr/>

            <input name="cell_type_mode" type="radio" id="cell_type_error" value="error" /> <?php echo $this->trans('wpc.calculator.cell.error'); ?>
            <select class="" style="min-width: 300px;" id="cell_type_select_error">
                <?php foreach($this->view['fields'] as $field): ?>
                <option value="<?php echo $field->id; ?>">
                        <?php $this->e($field->label, true); ?> [aws_price_calc_<?php echo $field->id; ?>]
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <!-- /Mapping Error Cell --->
        
        <!-- Base Price -->
        <div id="cell_type_price_div">
            <hr/>

            <input name="cell_type_mode" type="radio" id="cell_type_price" value="price" /> <?php echo $this->trans('wpc.calculator.cell.price'); ?>
        </div>
        <!-- /Base Price -->
        
        <!-- Tax Rate -->
        <div id="cell_type_tax_rate_div">
            <hr/>

            <input name="cell_type_mode" type="radio" id="cell_type_tax_rate" value="tax_rate" /> <?php echo $this->trans('wpc.calculator.cell.tax_rate'); ?>
        </div>
        <!-- /Tax Rate -->

        <!--  SKU  -->
        <div id="cell_type_sku_div">
            <hr/>

            <input name="cell_type_mode" type="radio" id="cell_type_sku" value="sku" /> SKU
        </div>
        <!--  /SKU  -->

        <div style="text-align: center">
            <input id="cell_type_submit" type="button" class="btn btn-primary" value="<?php echo $this->trans('wpc.ok'); ?>" />
        </div>
    </form>
</div>
<!-- /POPUP FOR CELLS SELECTION -->

<?php $this->renderView('app/footer.php'); ?>
<!--/WPC-PRO-->
