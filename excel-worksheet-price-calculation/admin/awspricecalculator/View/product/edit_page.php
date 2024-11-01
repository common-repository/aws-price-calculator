<?php
/**
 * Created by PhpStorm.
 * User: naidi
 * Date: 31/08/18
 * Time: 10:19
 */

?>


<div id='calculator_product_data' class='panel woocommerce_options_panel hidden'>

    <h4><?php echo $this->trans("Selected Calculator") ?> : <span id='selected_calculator'>"<?php echo isset($this->view['selectedSimulator']->name) ?  $this->view['selectedSimulator']->name : ""?>"</span></h4>




    <?php

    woocommerce_wp_select( array(
        'id'          => 'calculator',
        'label'   => __( 'Select calculator to attach', 'woocommerce' ),
        'options'     => $this->view['selectCalculatorArray'],
    ));

    ?>

    <div>
        <button  type='button' class='button attach_calculator button-primary'><?php echo $this->trans("Attach calculator") ?></button>

        <button style='display: <?php echo empty($this->view['selectedSimulator']->name) ? "none":"inline-block"  ?>' type='button' class='button remove_calculator button-danger'><?php echo $this->trans("Remove calculator") ?></button>

    </div>

    <input id='availableCalculators' type='hidden' value='<?php echo $this->view['availableCalculators'] ?>'/>
    <input id='productId' type='hidden' value='<?php echo $this->view['productId'] ?>'/>
    <input id='ajaxUrl' type='hidden' value='<?php echo $this->view['ajaxUrl'] ?>'/>

</div>

<script src='<?php echo $this->view['resourceUrl'] ?>'></script>
