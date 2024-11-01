<?php
/**
 * @package AWS Price Calculator
 * @author Enrico Venezia
 * @copyright (C) Altos Web Solutions Italia
 * @license GNU/GPL v2 http://www.gnu.org/licenses/gpl-2.0.html
**/

/*AWS_PHP_HEADER*/
?>

<span class="wpc-cart-container">
    <?php if($this->getLicense() == 1): ?>
    <!--WPC-PRO-->
    
        <!-- BEFORE PRICE -->
        <?php if($this->view['cartEditButtonPosition'] == "before-price"): ?>
            <?php $this->renderView('cart/edit-button.php', array(
               'cartEditButtonClass'    => $this->view['cartEditButtonClass'],
               'cartItemKey'            => $this->view['cartItemKey'],
            ));
            ?>

        <?php endif; ?>
    
        <?php if($this->view['cartHideItemPrice'] == false): ?>
            <?php echo $this->view['price']; ?>
        <?php endif; ?>
        
        <!-- AFTER PRICE -->
        <?php if($this->view['cartEditButtonPosition'] == "after-price"): ?>
            <?php $this->renderView('cart/edit-button.php', array(
               'cartEditButtonClass'    => $this->view['cartEditButtonClass'],
               'cartItemKey'            => $this->view['cartItemKey'],
            ));
            ?>
        <?php endif; ?>
     <!--/WPC-PRO-->
    <?php else: ?>
        <?php echo $this->view['price']; ?>
    <?php endif; ?>
</span>

<!--WPC-PRO-->
<div class="remodal wpc-cart-form" data-remodal-id="wpc_cart_item_<?php echo $this->view['cartItemKey']; ?>" data-cart-item-key="<?php echo $this->view['cartItemKey']; ?>">   
    <form>
        <div class="main-container">
            <div class="page-content">
                <div class="woocommerce">
                    <div class="wpc-modal-title">
                        <h3><?php echo $this->mixTrans('wpc.cart.modal.title', array('product_title' => $this->view['product']['name'])); ?></h3>
                    </div>
                    
                    <div class="wpc-modal-fields">
                        <?php echo $this->view['modal']; ?>
                    </div>
                    
                    <div class="wpc-cart-item-price">
                        <b><?php echo $this->mixTrans('wpc.cart.price'); ?>:</b> <span class="price">?</span>
                    </div>

                    <div class="wpc-cart-item-buttons">
                        <button data-remodal-action="cancel" class="button">
                            <?php echo $this->mixTrans('wpc.cart.cancel'); ?>
                        </button>
                        
                        <button type="button" class="button wpc-cart-edit">
                            <?php echo $this->mixTrans('wpc.cart.edit'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
<!--/WPC-PRO-->
