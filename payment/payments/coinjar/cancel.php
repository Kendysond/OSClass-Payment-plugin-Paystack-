<?php

    $data = payment_get_custom(Params::getParam('extra'));
    $url = osc_base_url();
    if(isset($data['product']) && isset($data['itemid'])) {
        $product = explode('x', $data['product']);
        if($product[0]=='301') { // PACK PAYMENT FROM USER'S DASHBOARD
            $url = osc_user_dashboard_url();
        } else {
            $item     = Item::newInstance()->findByPrimaryKey($data['itemid']);
            $category = Category::newInstance()->findByPrimaryKey($item['fk_i_category_id']);
            View::newInstance()->_exportVariableToView('category', $category);
            $url = osc_search_category_url();
        }
    } else {
    }
    osc_add_flash_error_message(__('You cancel the payment process or there was an error. If the error continue, please contact the administrator', 'payment'));
    _e('You cancel the payment process or there was an error. If the error continue, please contact the administrator', 'payment');
    payment_js_redirect_to($url);

?>