<?php
/**
 * @package AWS Price Calculator
 * @author Enrico Venezia
 * @copyright (C) Altos Web Solutions Italia
 * @license GNU/GPL v2 http://www.gnu.org/licenses/gpl-2.0.html
**/

/*AWS_PHP_HEADER*/

/*
 * THEME_NAME: Example Custom Theme
 */
?>

<h3>
	<p>
		This template may not work! 
	</p>
	
	<p>
	This is because you first need to edit it and change 
	the "aws_price_calc_ID" with your fields ID.
	</p>
	
	<p>
	This is an advanced example that demonstrate how it's possible to split every field components.
	</p>
</h3>

<div class="wpc-product-form">
	<!--Field start-->
	<div data-id="<?php echo $this->view['data']['aws_price_calc_1']['elementId']; ?>" class="form-group awspc-field-widget">
		<div class="awspc-field <?php echo $this->view['data']['aws_price_calc_1']['class']; ?>">
		<?php echo $this->view['fields']['aws_price_calc_1']['html']; ?>
		<label for="<?php echo $this->view['data']['aws_price_calc_1']['elementId']; ?>_field">
			<?php echo $this->view['fields']['aws_price_calc_1']['label_name']; ?>
		</label>
		</div>
		<div class="awspc-field-error"></div>
	</div>
	<!--Field end-->
	<!--Field start-->
	<div data-id="<?php echo $this->view['data']['aws_price_calc_2']['elementId']; ?>" class="form-group awspc-field-widget">
		<div class="awspc-field <?php echo $this->view['data']['aws_price_calc_2']['class']; ?>">
		<?php echo $this->view['fields']['aws_price_calc_2']['html']; ?>
		<label for="<?php echo $this->view['data']['aws_price_calc_2']['elementId']; ?>_field">
			<?php echo $this->view['fields']['aws_price_calc_2']['label_name']; ?>
		</label>
		</div>
		<div class="awspc-field-error"></div>
	</div>
	<!--Field end-->
	<!--Field start-->
	<div data-id="<?php echo $this->view['data']['aws_price_calc_3']['elementId']; ?>" class="form-group awspc-field-widget">
		<div class="awspc-field <?php echo $this->view['data']['aws_price_calc_3']['class']; ?>">
		<?php echo $this->view['fields']['aws_price_calc_3']['html']; ?>
		<label for="<?php echo $this->view['data']['aws_price_calc_3']['elementId']; ?>_field">
			<?php echo $this->view['fields']['aws_price_calc_3']['label_name']; ?>
		</label>
		</div>
		<div class="awspc-field-error"></div>
	</div>
	<!--Field end-->
</div>
