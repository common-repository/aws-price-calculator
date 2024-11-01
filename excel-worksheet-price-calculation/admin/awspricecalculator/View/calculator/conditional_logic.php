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
<?php if($this->getLicense() == 1): ?>
<div class="wsf-bs wsf-wrap">
    <div class="mt-md">
        <?php if(count($this->view['errors']) != 0): ?>
        <div class="alert alert-danger">
            <?php echo implode("<br/>", $this->view['errors']); ?> 
        </div>
        <?php endif; ?>
        
        <?php if(count($this->view['warnings']) != 0): ?>
        <div class="alert alert-warning">
            <h4><?php echo $this->trans('wpc.warnings'); ?></h4>
            
            <?php echo implode("<br/>", $this->view['warnings']); ?> 
        </div>
        <?php endif; ?>
        
        <div class="panel panel-default">
            <div class="panel-heading text-center">
                <h4>
                    <i class="fa fa-bars"></i> <?php echo $this->trans('wpc.conditional_logic.title', array(
                        'calculatorName'    => $this->view['calculator']->name,
                    ));
                    ?>
                </h4>
            </div>
            <div class="panel-body">
                <div class="row">
                    <div class="col-xs-6 col-xs-offset-2">
                        <form class="form-horizontal wpc-form" action="<?php echo $this->adminUrl(array('controller' => 'calculator', 'action' => 'conditionalLogic', 'id' => $this->view['id'])); ?>" method="POST">

                           <div class="form-group">
                               <label class="control-label col-sm-4" for="enabled">
                                   <?php $this->renderView('partial/help.php', array('text' => $this->trans('wpc.conditional_logic.form.enabled.tooltip'))); ?> <?php echo $this->trans('wpc.conditional_logic.form.enabled'); ?>
                               </label>
                               <div class="col-sm-8">
                                   <select class="form-control" name="enabled">
                                       <option value="0" <?php if(empty($this->view['form']['enabled'])){echo 'selected="selected"';} ?>><?php echo $this->trans('wpc.no'); ?></option>
                                       <option value="1" <?php if($this->view['form']['enabled'] == 1){echo 'selected="selected"';} ?>><?php echo $this->trans('wpc.yes'); ?></option>
                                   </select>
                               </div>
                           </div>
                           
                           <div class="form-group">
                               <label class="control-label col-sm-4" for="hide_fields">
                                   <?php $this->renderView('partial/help.php', array('text' => $this->trans('aws.conditional_logic.form.hide_fields.tooltip'))); ?> <?php echo $this->trans('aws.conditional_logic.form.hide_fields'); ?>
                               </label>
                               <div id="show_fields" class="col-sm-8">                             
                                   <select id="fields" class="form-control wpc-conditional-logic-multiselect" name="hide_fields[]" multiple="multiple">
                                       <?php foreach($this->view['fields'] as $field): ?>
                                       <option value="<?php echo $field->id; ?>"<?php if($this->view['form']['hide_fields']!= null){ if(in_array($field->id, $this->view['form']['hide_fields'])){echo 'selected="selected"';} }?>>
                                               <?php $this->e($field->label, true); ?> [<?php echo $this->view['fieldHelper']->getFieldName($field->id); ?>]
                                           </option>
                                       <?php endforeach; ?>
                                   </select>
                               </div>
                           </div>
                                                       
                           <div class="form-group">
                               <label class="control-label col-sm-4" for="fields">
                                   <?php $this->renderView('partial/help.php', 
                                               array('text' => $this->trans('wpc.conditional_logic.form.rules.tooltip'))
                                   ); ?> <?php echo $this->trans('wpc.conditional_logic.form.rules'); ?>
                               </label>
                               <div class="col-sm-8">
                                   <div class="awspc-conditional-logic-table">
                                        <table class="table table-striped table-bordered data-table" width="100%">
                                            <thead>
                                                <tr>
                                                    <th class="text-center"><?php echo $this->trans('Field'); ?></th>
                                                    <th class="text-center"><?php echo $this->trans('wpc.actions'); ?></th>
                                                </tr>
                                            </thead>

                                            <tbody>
                                                <?php foreach ($this->view['fields'] as $field): ?>
                                                    <tr>
                                                        <td><?php echo $field->label; ?> [<?php echo $this->view['fieldHelper']->getFieldName($field->id); ?>]</td>
                                                        <td class="col-xs-2">
                                                            <button type="button" data-field-id="<?php echo $field->id; ?>" class="btn btn-primary edit-conditional-logic-rules"><?php echo $this->trans('wpc.edit'); ?></button>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                   </div>
                               </div>
                           </div>

                           <div class="form-group">
                               <div class="col-sm-10 col-sm-offset-3 text-center">
                                   <button type="submit" class="btn btn-primary" <?php echo ($this->view['calculator']->system_created == true)?'disabled="disabled"':''; ?>>
                                       <i class="fa fa-floppy-o"></i> <?php echo $this->trans('wpc.save'); ?>
                                   </button>
                               </div>
                           </div>

                           <input type="hidden" id="calculator_id" name="id" value="<?php echo $this->view['id']; ?>" />
                           <input type="hidden" name="task" value="save" />

                           <!-- Campi nascosti delle regole -->
                           <?php foreach($this->view['fields'] as $field): ?>
                           <input type="hidden" id="field_rules_json_<?php echo $field->id; ?>" name="field_filters_json[<?php echo $field->id; ?>]" value="<?php echo isset($this->view['form']['field_filters_json'][$field->id])?htmlentities(json_encode($this->view['form']['field_filters_json'][$field->id])):""; ?>" />
                           <input type="hidden" id="field_rules_sql_<?php echo $field->id; ?>" name="field_filters_sql[<?php echo $field->id; ?>]" value="<?php echo isset($this->view['form']['field_filters_sql'][$field->id])?htmlentities($this->view['form']['field_filters_sql'][$field->id]):""; ?>" />
                           <?php endforeach; ?>
                           <!-- /Campi nascosti delle regole -->
                           
                        </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php foreach($this->view['fields'] as $field): ?>
            <div id="conditionalLogicRuleModal_<?php echo $field->id; ?>" class="modal fade" role="dialog">
                <div class="modal-dialog" style="min-width: 800px">

                    <div class="modal-content">
                        <div class="modal-header">
                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                            <h4 class="modal-title"><?php echo $this->trans('wpc.conditional_logic.rules.modal.title', array('fieldLabel' => $field->label)); ?></h4>
                        </div>
                        <div class="modal-body">
                            <div class="query-builder" data-filters="<?php echo htmlentities(json_encode($this->view['filters'][$field->id])); ?>" data-field-id="<?php echo $field->id; ?>" id="query_builder_<?php echo $field->id; ?>"></div>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-primary conditional-logic-modal-confirm" data-field-id="<?php echo $field->id; ?>" type="button" >
                                <?php echo $this->trans('wpc.ok'); ?>
                            </button>

                            <button type="button" class="btn btn-default" data-dismiss="modal">
                                <?php echo $this->trans('wpc.close'); ?>
                            </button>
                        </div>
                    </div>

                </div>
            </div>
        <?php endforeach; ?>
    
    </div>

<?php $this->renderView('app/footer.php'); ?>
<?php endif; ?>
<!--/WPC-PRO-->