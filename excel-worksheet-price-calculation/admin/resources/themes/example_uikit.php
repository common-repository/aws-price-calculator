<?php
/**
 * @package AWS Price Calculator
 * @author Enrico Venezia
 * @copyright (C) Altos Web Solutions Italia
 * @license GNU/GPL v2 http://www.gnu.org/licenses/gpl-2.0.html
**/

/*AWS_PHP_HEADER*/

/*
 * THEME_NAME: Example (UIkit)
 */
?>

<div class="wsf-uikit">
    <div class="uk-grid">
        <div class="uk-width-1-1">
            <h1>This is an example</h1>
        </div>
        
        <div class="uk-width-1-1">
			<div class="row awspc-output-product">
				<?php foreach($this->view['outputResults'] as $outputFieldId => $outputResult): ?>
					<div class="col-xs-12">	
						<div class="awspc-output-result-row <?php echo $outputResult['fieldName']; ?>" style="<?php echo (count($this->view['errors']) != 0)?"display:none":"" ?>">
							<span class="awspc-output-result-label"><b><?php echo $outputResult['field']->label; ?></b>: </span>
							<span class="awspc-output-result-value"><?php echo $outputResult['value']; ?></span>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		
        <div class="uk-width-1-1">
            <div class="uk-form wpc-product-form">
                <?php foreach($this->view['data'] as $key => $data): ?>
                    <div class="form-group">
                        <legend><?php echo $this->userTrans($data['field']->label); ?>:</legend>

                        <div class="uk-form-row">
                            <?php echo $data['widget']; ?>
                        </div>

                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
