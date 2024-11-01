<?php
/**
 * @package AWS Price Calculator
 * @author Enrico Venezia
 * @copyright (C) Altos Web Solutions Italia
 * @license GNU/GPL v2 http://www.gnu.org/licenses/gpl-2.0.html
**/

/*AWS_PHP_HEADER*/

/*
 * THEME_NAME: Example (No Front End Framework)
 */
?>

<h2>This is a template example</h2>

<div class="wpc-product-form">

    <div class="awspc-output-product">
		<?php foreach($this->view['outputResults'] as $outputFieldId => $outputResult): ?>
			<div class="awspc-output-result-row <?php echo $outputResult['fieldName']; ?>" style="<?php echo (count($this->view['errors']) != 0)?"display:none":"" ?>">
				<span class="awspc-output-result-label"><b><?php echo $outputResult['field']->label; ?></b>: </span>
				<span class="awspc-output-result-value"><?php echo $outputResult['value']; ?></span>
			</div>
		<?php endforeach; ?>
    </div>
    
    <table>
            <?php foreach($this->view['data'] as $key => $data): ?>
                <tr class="awspc-field-row" data-field-id="<?php echo $data['field']->id; ?>" 
						style="<?php echo ($this->view['conditionalLogic'][$data['field']->id] == true)?"":"display:none"; ?>">
                    <td id="<?php echo $data['labelId']; ?>">
                        <?php echo $this->userTrans($data['field']->label); ?>
                    </td>

                    <td id="<?php echo $data['inputId']; ?>">
                        <?php echo $data['widget']; ?>
                    </td>
                </tr>
            <?php endforeach; ?>

    </table>
</div>
