<?php

if(Params::getParam('plugin_action')=='add_category') {
    if(Params::getParam('catId')!='') {
        ModelPayment::newInstance()->insertPrice(
            Params::getParam('catId'),
            Params::getParam('publish_price')==''?osc_get_preference('default_publish_cost'):Params::getParam('publish_price'),
            Params::getParam('premium_price')==''?osc_get_preference('default_premium_cost'):Params::getParam('premium_price'));
        osc_add_flash_ok_message(__('Category prices updated correctly', 'payment'), 'admin');
    } else {
        osc_add_flash_error_message(__('Category is not defined', 'payment'), 'admin');
    }
    ob_get_clean();
    osc_redirect_to(osc_route_admin_url('payment-admin-prices'));
} else if(Params::getParam('plugin_action')=='delete') {
    if(Params::getParam('catId')!='') {
        ModelPayment::newInstance()->deletePrices(Params::getParam('catId'));
        osc_add_flash_ok_message(__('Category prices changed to default', 'payment'), 'admin');
    } else {
        osc_add_flash_error_message(__('Category is not defined', 'payment'), 'admin');
    }
    ob_get_clean();
    osc_redirect_to(osc_route_admin_url('payment-admin-prices'));
}

$catMgr = Category::newInstance();
$prices= ModelPayment::newInstance()->getCategoriesPrices();

?>
<style type="text/css">
    .payment-pub {
        background-color: #d8e6ff;
    }
    .payment-prm {
        background-color: #d8e6ff;
    }
</style>
<script type="text/javascript" >
    $(document).ready(function(){
        $("#dialog-new").dialog({
            autoOpen: false,
            width: "500px",
            modal: true,
            title: '<?php echo osc_esc_js( __('Set category prices', 'payment') ); ?>'
        });
        $("#dialog-delete").dialog({
            autoOpen: false,
            width: "500px",
            modal: true,
            title: '<?php echo osc_esc_js( __('Delete category prices', 'payment') ); ?>'
        });
    });
    function new_cat() {
        $('#select_row').show();
        $('#dialog-new').dialog('open');
    };
    function edit_cat(id, pub, prm) {
        $('#catId').prop('value', id);
        $('#publish_price').prop('value', pub);
        $('#premium_price').prop('value', prm);
        $('#select_row').hide();
        $('#dialog-new').dialog('open');
    };
    function delete_cat(id) {
        $('#delete_cat').prop('value', id);
        $('#dialog-delete').dialog('open');
    };
</script>
<div id="general-setting">
    <div id="general-settings">
        <h2 class="render-title"><?php _e('Set categories prices', 'payment'); ?> <span><a id="new-price" href="javascript:new_cat();" ><?php _e('Add new price', 'payment'); ?></a></span></h2>
        <ul id="error_list"></ul>
        <form name="payment_form" action="#" method="post">
            <fieldset>
                <div class="form-horizontal">
                    <?php foreach($prices as $price) {
                        $category = $catMgr->findByPrimaryKey($price['fk_i_category_id']); ?>
                        <div class="form-row">
                            <div class="form-label"><?php echo $category['s_name']; ?></div>
                            <div class="form-controls">
                                <span class="payment-pub" ><?php printf(__('Publish cost: %s %s'), $price['f_publish_cost'], osc_get_preference('currency', 'payment')); ?></span>
                                <span class="payment-prm" ><?php printf(__('Premium cost: %s %s'), $price['f_premium_cost'], osc_get_preference('currency', 'payment')); ?></span>
                                <span><a href="javascript:edit_cat(<?php echo $price['fk_i_category_id'].", ".$price['f_publish_cost'].", ".$price['f_premium_cost']; ?>);" ><?php _e('edit', 'payment'); ?></a></span>
                                <span><a href="javascript:delete_cat(<?php echo $price['fk_i_category_id']; ?>);" ><?php _e('delete', 'payment'); ?></a></span>
                            </div>
                        </div>
                    <?php } ?>
                </div>
                <div class="clear"></div>
                <div class="form-actions">
                    <input type="submit" id="save_changes" value="<?php echo osc_esc_html( __('Save changes') ); ?>" class="btn btn-submit" />
                </div>
                </div>
            </fieldset>
        </form>
    </div>
</div>
<form id="dialog-new" method="post" action="<?php echo osc_admin_base_url(true); ?>" class="has-form-actions hide">
    <input type="hidden" name="page" value="plugins" />
    <input type="hidden" name="action" value="renderplugin" />
    <input type="hidden" name="route" value="payment-admin-prices" />
    <input type="hidden" name="plugin_action" value="add_category" />
    <div class="form-horizontal">
        <div class="form-row" id="select_row" >
            <div class="form-label"><?php _e('Category', 'payment'); ?></div>
            <div class="form-controls">
                <?php ItemForm::category_select(); ?>
            </div>
        </div>
        <div class="form-row">
            <div class="form-label"><?php _e('Publish price', 'payment'); ?></div>
            <div class="form-controls"><input type="text" id="publish_price" name="publish_price" value="" placeholder="<?php echo osc_get_preference('default_publish_cost', 'payment'); ?>" /> <?php echo osc_get_preference('currency', 'payment'); ?></div>
        </div>
        <div class="form-row">
            <div class="form-label"><?php _e('Premium price', 'payment'); ?></div>
            <div class="form-controls"><input type="text" id="premium_price" name="premium_price" value="" placeholder="<?php echo osc_get_preference('default_premium_cost', 'payment'); ?>" /> <?php echo osc_get_preference('currency',
                    'payment'); ?></div>
        </div>
        <div class="form-actions">
            <div class="wrapper">
                <a class="btn" href="javascript:void(0);" onclick="$('#dialog-new').dialog('close');"><?php _e('Cancel', 'payment'); ?></a>
                <input id="payment-submit" type="submit" value="<?php echo osc_esc_html( __('Add', 'payment')); ?>" class="btn btn-red" />
            </div>
        </div>
    </div>
</form>
<form id="dialog-delete" method="post" action="<?php echo osc_admin_base_url(true); ?>" class="has-form-actions hide">
    <input type="hidden" name="page" value="plugins" />
    <input type="hidden" name="action" value="renderplugin" />
    <input type="hidden" name="route" value="payment-admin-prices" />
    <input type="hidden" name="plugin_action" value="delete" />
    <input type="hidden" name="catId" id="delete_cat" value="" />
    <div class="form-horizontal">
        <div class="form-row">
            <?php _e('This will revert back the prices to the default ones. Do you want to continue?', 'payment'); ?>
        </div>
        <div class="form-actions">
            <div class="wrapper">
                <a class="btn" href="javascript:void(0);" onclick="$('#dialog-delete').dialog('close');"><?php _e('Cancel', 'payment'); ?></a>
                <input id="price-delete-submit" type="submit" value="<?php echo osc_esc_html( __('Delete', 'payment')); ?>" class="btn btn-red" />
            </div>
        </div>
    </div>
</form>




<?php
/*

    $mp = ModelPayment::newInstance();

    if(Params::getParam('plugin_action') == 'done') {
        $pub_prices = Params::getParam("pub_prices");
        $pr_prices  = Params::getParam("pr_prices");
        foreach($pr_prices as $k => $v) {
            $mp->insertPrice($k, $pub_prices[$k]==''?NULL:$pub_prices[$k], $v==''?NULL:$v);
        }
        // HACK : This will make possible use of the flash messages ;)
        ob_get_clean();
        osc_add_flash_ok_message(__('Congratulations, the plugin is now configured', 'payment'), 'admin');
        osc_redirect_to(osc_route_admin_url('payment-admin-prices'));
    }

    $categories = Category::newInstance()->toTreeAll();
    $prices     = ModelPayment::newInstance()->getCategoriesPrices();
    $cat_prices = array();
    foreach($prices as $p) {
        $cat_prices[$p['fk_i_category_id']]['f_publish_cost'] = $p['f_publish_cost'];
        $cat_prices[$p['fk_i_category_id']]['f_premium_cost'] = $p['f_premium_cost'];
    }

    function drawCategories($categories, $depth = 0, $cat_prices) {
        foreach($categories as $c) { ?>
            <tr>
                <td>
                    <?php for($d=0;$d<$depth;$d++) { echo "&nbsp;&nbsp;"; }; echo $c['s_name']; ?>
                </td>
                <td>
                    <input style="width:150px;text-align:right;" type="text" name="pub_prices[<?php echo $c['pk_i_id']?>]" id="pub_prices[<?php echo $c['pk_i_id']?>]" value="<?php echo isset($cat_prices[$c['pk_i_id']]) ? $cat_prices[$c['pk_i_id']]['f_publish_cost'] : ''; ?>" />
                </td>
                <td>
                    <input style="width:150px;text-align:right;" type="text" name="pr_prices[<?php echo $c['pk_i_id']?>]" id="pr_prices[<?php echo $c['pk_i_id']?>]" value="<?php echo isset($cat_prices[$c['pk_i_id']]) ? $cat_prices[$c['pk_i_id']]['f_premium_cost'] : ''; ?>" />
                </td>
            </tr>
        <?php drawCategories($c['categories'], $depth+1, $cat_prices);
        }
    };


?>
    <div style="padding: 20px;">
        <div style="float: left; width: 100%;">
            <fieldset>
                <legend><?php _e('Paypal Options', 'payment'); ?></legend>
                <div style="float: left; width: 100%;">
                    <form name="payment_form" id="payment_form" action="<?php echo osc_admin_base_url(true);?>" method="POST" enctype="multipart/form-data" >
                        <input type="hidden" name="page" value="plugins" />
                        <input type="hidden" name="action" value="renderplugin" />
                        <input type="hidden" name="route" value="payment-admin-prices" />
                        <input type="hidden" name="plugin_action" value="done" />
                        <table border="0" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="width:300px;"><?php _e('Category Name', 'payment'); ?></td>
                                <td style="width:175px;"><?php echo sprintf(__('Publish fee (%s)', 'payment'), osc_get_preference('currency', 'payment')); ?></td>
                                <td style="width:175px;"><?php echo sprintf(__('Premium fee (%s)', 'payment'), osc_get_preference('currency', 'payment')); ?></td>
                            </tr>
                            <?php drawCategories($categories, 0, $cat_prices); ?>
                        </table>
                        <button type="submit" style="float: right;"><?php _e('Update', 'payment'); ?></button>
                    </form>
                </div>
            </fieldset>
        </div>
        <div style="clear:both;">
            <div style="float: left; width: 100%;">
                <fieldset>
                    <legend><?php _e('Help', 'payment'); ?></legend>
                    <h3><?php _e('Setting up your fees', 'payment'); ?></h3>
                    <p>
                        <?php _e('You could set up different prices for each category', 'payment'); ?>. <?php _e('If the price of a category is left empty, the default value will be applied', 'payment'); ?>.
                    </p>
                </fieldset>
            </div>
            <div style="clear: both;"></div>
        </div>
    </div>
 */ ?>
