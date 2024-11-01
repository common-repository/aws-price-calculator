<?php
/**
 * Created by PhpStorm.
 * User: naidi
 * Date: 04/04/18
 * Time: 11:37
 */
/*AWS_PHP_HEADER*/
?>

<!--WPC-PRO-->
<?php if($this->getLicense()== 1): ?>

<div id="format" style="display: none;">
    
    <div class="form-group">
        <label class="control-label col-sm-4" for="default_value">
            <?php echo $this->trans('Default Value'); ?>
        </label>
        <div class="col-sm-8">
            <select class="form-control" name="datetime_default_value">
                <option value="" <?php echo (empty($this->view['form']['datetime_default_value']))?"selected='selected'":""; ?>><?php echo $this->trans('empty'); ?></option>
                <option value="[spreadsheet]" <?php echo ($this->view['form']['datetime_default_value'] == "[spreadsheet]")?"selected='selected'":""; ?>><?php echo $this->trans('calculator.form.default_value.spreadsheet'); ?></option>
            </select>
        </div>
    </div>
    
    <div class="form-group">

        <label class="control-label col-sm-4" for="label">
            <?php echo $this->trans('field.form.datetime.format'); ?>
        </label>

        <div id="date_format" class="col-sm-8" style="display: none;">

            <select class="form-control" id="field_date_format" name="date_format">
                <option value="Y-m-d" <?php if($this->view['form']['date_format'] == "Y-m-d"){echo 'selected="selected"';} ?>><?php echo $this->trans('field.form.datetime.format.date'); ?></option>
                <option value="d-m-Y" <?php if($this->view['form']['date_format'] == "d-m-Y"){echo 'selected="selected"';} ?>>d-m-Y</option>
                <option value="d/m/Y" <?php if($this->view['form']['date_format'] == "d/m/Y"){echo 'selected="selected"';} ?>>d/m/y</option>
                <option value="m-d-Y" <?php if($this->view['form']['date_format'] == "m-d-Y"){echo 'selected="selected"';} ?>>m-d-Y</option>
                <option value="d.m.Y" <?php if($this->view['form']['date_format'] == "d.m.Y"){echo 'selected="selected"';} ?>>d.m.Y</option>
            </select>

        </div>

        <div id="time_format" class="col-sm-8" style="display: none;">

            <select class="form-control" id="field_date_format" name="time_format">
                <option value="H:i:s" <?php if($this->view['form']['time_format'] == "H:i:s"){echo 'selected="selected"';} ?>><?php echo $this->trans('field.form.datetime.format.time'); ?></option>
                <option value="s:i:H" <?php if($this->view['form']['time_format'] == "s:i:H"){echo 'selected="selected"';} ?>>s:i:H</option>
                <option value="i:s:H" <?php if($this->view['form']['time_format'] == "i:s:H"){echo 'selected="selected"';} ?>>i:s:H</option>
            </select>

        </div>

        <div id="date_time_format" class="col-sm-8" style="display: none;">

            <select class="form-control" id="field_date_format" name="datetime_format">
                <option value="Y-m-d H:i:s" <?php if($this->view['form']['datetime_format'] == "Y-m-d H:i:s"){echo 'selected="selected"';} ?>><?php echo $this->trans('field.form.datetime.format.datetime'); ?></option>
                <option value="d-m-Y H:i:s" <?php if($this->view['form']['datetime_format'] == "d-m-Y H:i:s"){echo 'selected="selected"';} ?>>d-m-Y H:i:s</option>
                <option value="d/m/Y H:i:s" <?php if($this->view['form']['datetime_format'] == "d/m/Y H:i:s"){echo 'selected="selected"';} ?>>d/m/y H:i:s</option>
                <option value="m-d-Y H:i:s" <?php if($this->view['form']['datetime_format'] == "m-d-Y H:i:s"){echo 'selected="selected"';} ?>>m-d-Y H:i:s</option>
                <option value="d.m.Y H:i:s" <?php if($this->view['form']['datetime_format'] == "d.m.Y H:i:s"){echo 'selected="selected"';} ?>>d.m.Y H:i:s</option>
                <option value="H:i:s Y-m-d" <?php if($this->view['form']['datetime_format'] == "H:i:s Y-m-d"){echo 'selected="selected"';} ?>>H:i:s Y-m-d</option>
                <option value="H:i:s d-m-Y" <?php if($this->view['form']['datetime_format'] == "H:i:s d-m-Y"){echo 'selected="selected"';} ?>>H:i:s d-m-Y</option>
                <option value="H:i:s d/m/Y" <?php if($this->view['form']['datetime_format'] == "H:i:s d/m/Y"){echo 'selected="selected"';} ?>>H:i:s d/m/y</option>
                <option value="H:i:s m-d-Y" <?php if($this->view['form']['datetime_format'] == "H:i:s m-d-Y"){echo 'selected="selected"';} ?>>H:i:s m-d-Y</option>
                <option value="H:i:s d.m.Y" <?php if($this->view['form']['datetime_format'] == "H:i:s d.m.Y"){echo 'selected="selected"';} ?>>H:i:s d.m.Y</option>
            </select>

        </div>

    </div>

</div>
<?php endif; ?>
<!--/WPC-PRO-->
