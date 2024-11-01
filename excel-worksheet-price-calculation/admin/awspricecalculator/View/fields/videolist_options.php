<?php
/**
 * Created by PhpStorm.
 * User: naidi
 * Date: 24/09/18
 * Time: 20:37
 */
?>

<div id="videolist_options" style="display: none;width: 100%">

    <!-- Video Field Width -->
    <div class="form-group">
        <label class="control-label col-sm-4" for="videolist_field_width">
            <?php $this->renderView('partial/help.php', array('text' => $this->trans('field.form.field_video_width.tooltip'))); ?> <?php echo $this->trans('field.form.field_video_width'); ?>
        </label>
        <div class="col-sm-8">
            <input class="form-control" name="videolist_field_width" type="text" value="<?php echo $this->view['form']['videolist_field_width']; ?>" />
        </div>
    </div>
    <!-- /Video Field Width -->

    <!-- Video Field Height -->
    <div class="form-group">
        <label class="control-label col-sm-4" for="videolist_field_height">
            <?php $this->renderView('partial/help.php', array('text' => $this->trans('field.form.field_video_height.tooltip'))); ?> <?php echo $this->trans('field.form.field_video_height'); ?>
        </label>
        <div class="col-sm-8">
            <input class="form-control" name="videolist_field_height" type="text" value="<?php echo $this->view['form']['videolist_field_height']; ?>" />
        </div>
    </div>
    <!-- /Video Field Height -->

    <!-- Video Items -->
    <div class="form-group">
        <label class="control-label col-sm-4" for="default_status">
            <?php
            $this->renderView('partial/help.php',
                array('text' => $this->trans('wpc.field.picklist.tooltip')));
            ?> <?php echo $this->trans('Picklist Items'); ?>
        </label>
        <div class="col-sm-8">

            <div class="row">
                <div class="col-xs-12 text-center">
                    <button data-sortable-items="#videolist_items_sortable" data-sortable-items-data="#videolist_items" type="button" class="field_list_add btn btn-primary">
                        <i class="fa fa-plus"></i> <?php echo $this->trans('wpc.add'); ?>
                    </button>
                    <?php if(!isset($_GET['id'])) $_GET['id']="" ?>
                </div>
            </div>

            <div class="row">
                <div class="col-xs-12">
                    <ul id="videolist_items_sortable">
                        <?php foreach($this->view['videolist_items_data'] as $index => $item): ?>
                            <li data-id="<?php echo $item['id']; ?>" data-value="<?php echo $item['value']; ?>" data-label="<?php echo $item['label']; ?>" data-tooltip-message="<?php echo (isset($item['tooltip_message'])?$item['tooltip_message']:""); ?>" data-tooltip-position="<?php echo (isset($item['tooltip_position'])?$item['tooltip_position']:"none"); ?>" data-default-option="<?php echo (isset($item['default_option'])?$item['default_option']:"0"); ?>" data-order-details="<?php echo (isset($item['order_details'])?$item['order_details']:""); ?>" data-video="<?php echo (isset($item['video'])?$item['video']:""); ?>">
                                <a class="btn btn-danger js-remove" data-sortable-items="#videolist_items_sortable" data-sortable-items-data="#picklist_items">
                                    <i class="fa fa-times"></i>
                                </a>

                                <a class="btn btn-primary sortable-edit" data-sortable-items="#videolist_items_sortable" data-sortable-items-data="#videolist_items">
                                    <i class="fa fa-pencil"></i>
                                </a>

                                <?php echo $item['label']; ?> <i>[Value: <?php echo $item['value']; ?>]</i>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            

            <input type="hidden" id="videolist_items" name="videolist_items" value="<?php $this->e($this->view['form']['videolist_items'], true); ?>" />

        </div>
    </div>
    <!-- /Video Items -->

</div>
