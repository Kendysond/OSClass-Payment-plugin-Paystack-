<?php

    $data = payment_get_custom(Params::getParam('extra'));
    $product_type = explode('x', Params::getParam('item_number'));

    osc_add_flash_info_message(__('We are processing your payment, if we did not finish in a few minutes, please contact us', 'payment'));
    if($product_type[0]==301) {
        if(osc_is_web_user_logged_in()) {
            osc_redirect_to(osc_route_url('payment-user-pack'));
        } else {
            // THIS SHOULD NOT HAPPEN
            osc_redirect_to(osc_base_path());
        }
    } else {
        if(osc_is_web_user_logged_in()) {
            osc_redirect_to(osc_route_url('payment-user-menu'));
        } else {
            View::newInstance()->_exportVariableToView('item', Item::newInstance()->findByPrimaryKey($product_type[2]));
            osc_redirect_to(osc_item_url());
        }
    }
?>