<?php

    require_once osc_plugins_path() . osc_plugin_folder(__FILE__) . 'CoinJar.php';

    class CoinjarPayment
    {

        public function __construct()
        {
        }

        public static function button($amount = '0.00', $description = '', $itemnumber = '101', $extra_array = null) {
            $extra = payment_prepare_custom($extra_array);
            $extra .= 'concept,'.$description.'|';
            $extra .= 'product,'.$itemnumber.'|';
            $r = rand(0,1000);
            $extra .= 'random,'.$r;
            echo '<li class="payment coinjar-btn"><a href="javascript:coinjar_pay(\''.$amount.'\',\''.$description.'\',\''.$itemnumber.'\',\''.$extra.'\');" ><img src="'.osc_base_url() . 'oc-content/plugins/' . osc_plugin_folder(__FILE__).'payment.png" ></a></li>';
        }

        public static function dialogJS() { ?>
            <div id="coinjar-dialog" title="<?php _e('CoinJar', 'payment'); ?>" style="display: none;"><span id="coinjar-dialog-text"></span></div>
            <script type="text/javascript">
                function coinjar_pay(amount, description, itemnumber, extra) {
                    $('#coinjar-dialog-text').html('<?php _e('You are going to be redirected to our payment processor to continue with the payment. Please wait', 'payment'); ?>');
                    $('#coinjar-dialog').dialog('open');
                    $.ajax({
                        type: "POST",
                        url: '<?php echo osc_base_url(true); ?>',
                        dataType: 'json',
                        data: {
                            'page':'ajax',
                            'action':'runhook',
                            'hook':'coinjar',
                            'amount':amount,
                            'description':description,
                            'itemnumber':itemnumber,
                            'extra':extra
                        },
                        success: function(data)
                        {
                            console.log(data);
                            if(data.error==0) {
                                window.location = data.url;
                            } else {
                                $('#coinjar-dialog-text').html('<?php _e('We are experiencing some errors, please try in a few moments', 'payment'); ?>');
                            }
                        }
                    });
                }

                $(document).ready(function(){
                    $("#coinjar-dialog").dialog({
                        autoOpen: false,
                        modal: true
                    });
                });

            </script>

        <?php
        }

        public static function createOrder() {
            if(osc_get_preference('coinjar_sandbox', 'payment')!=1) {
                $coinjar = new CoinJar(
                    payment_decrypt(osc_get_preference('coinjar_merchant_user', 'payment')),
                    payment_decrypt(osc_get_preference('coinjar_merchant_password', 'payment')),
                    payment_decrypt(osc_get_preference('coinjar_api_key', 'payment'))
                );
            } else {
                $coinjar = new CoinJar(
                    payment_decrypt(osc_get_preference('coinjar_sb_merchant_user', 'payment')),
                    payment_decrypt(osc_get_preference('coinjar_sb_merchant_password', 'payment')),
                    payment_decrypt(osc_get_preference('coinjar_sb_api_key', 'payment')),
                    true
                );
            }
            $items[0]['name'] = Params::getParam('description');
            $items[0]['quantity'] = 1;
            $items[0]['amount'] = payment_get_amount(Params::getParam('itemnumber'));
            $order_json = $coinjar->createOrder(
                $items, //items
                osc_get_preference('currency', 'payment'), //currency
                Params::getParam('itemnumber'), //merchant invoice
                osc_get_preference('coinjar_merchant_reference', 'payment'), //merchant reference
                osc_route_url('coinjar-notify', array('extra' => Params::getParam('extra'))), // notify url
                osc_route_url('coinjar-return', array('extra' => Params::getParam('extra'))), // return url
                osc_route_url('coinjar-cancel', array('extra' => Params::getParam('extra'))) //cancel url
            );
            $order = json_decode($order_json);
            if(isset($order->order->uuid)) {
                echo json_encode(array('url' => $coinjar->orderPage($order->order->uuid), 'error' => 0));
            } else {
                echo json_encode(array('error' => 1, 'status' => @$order->order->status, 'msg' => @$order->order->error));
            }
        }


        public static function processPayment() {
            if(osc_get_preference('coinjar_sandbox', 'payment')!=1) {
                $coinjar = new CoinJar(
                    payment_decrypt(osc_get_preference('coinjar_merchant_user', 'payment')),
                    payment_decrypt(osc_get_preference('coinjar_merchant_password', 'payment')),
                    payment_decrypt(osc_get_preference('coinjar_api_key', 'payment'))
                );
            } else {
                $coinjar = new CoinJar(
                    payment_decrypt(osc_get_preference('coinjar_sb_merchant_user', 'payment')),
                    payment_decrypt(osc_get_preference('coinjar_sb_merchant_password', 'payment')),
                    payment_decrypt(osc_get_preference('coinjar_sb_api_key', 'payment')),
                    true
                );
            }

            // data from the post (it's supposed to be sent by CoinJar, is sent by POST!)
            if(Params::existParam('uuid') && Params::existParam('amount') && Params::existParam('currency') && Params::existParam('status') && Params::existParam('ipn_digest')) {
                if(Params::getParam('status')=='COMPLETED') {
                    $digest = $coinjar->IPNDigest(Params::getParam('uuid'), Params::getParam('amount'), Params::getParam('currency'), Params::getParam('status'));
                    if($digest==Params::getParam('ipn_digest')) {
                        $order = json_decode($coinjar->order(Params::getParam('uuid')));
                        if($order!=null) {
                            if(Params::getParam('amount')==$order->order->amount && Params::getParam('currency')==$order->order->currency && Params::getParam('status')==$order->order->status) {
                                $payment = ModelPayment::newInstance()->getPayment($order->order->uuid);
                                if (!$payment) {
                                    $data = payment_get_custom(Params::getParam('extra'));
                                    $product_type = explode('x', Params::getParam('item_number'));
                                    // SAVE TRANSACTION LOG
                                    $payment_id = ModelPayment::newInstance()->saveLog(
                                        $data['concept'], //concept
                                        $order->order->uuid,
                                        $order->order->amount, //amount
                                        $order->order->currenct, //currency
                                        $data['email'], // payer's email
                                        $data['user'], //user
                                        $data['itemid'], //item
                                        $product_type[0], //product type
                                        'COINJAR'); //source
                                    if ($product_type[0] == '101') {
                                        ModelPayment::newInstance()->payPublishFee($product_type[2], $payment_id);
                                    } else if ($product_type[0] == '201') {
                                        ModelPayment::newInstance()->payPremiumFee($product_type[2], $payment_id);
                                    } else {
                                        ModelPayment::newInstance()->addWallet($data['user'], $order->order->amount);
                                    }
                                    return PAYMENT_COMPLETED;
                                } else {
                                    return PAYMENT_ALREADY_PAID;
                                }
                            }
                        }
                    }
                }
            }
            return PAYMENT_PENDING;
        }

    }

?>