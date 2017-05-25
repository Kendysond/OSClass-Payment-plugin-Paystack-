<?php
/*
 *      OSCLass – software for creating and publishing online classified
 *                           advertising platforms
 *
 *                        Copyright (C) 2010 OSCLASS
 *
 *       This program is free software: you can redistribute it and/or
 *     modify it under the terms of the GNU Affero General Public License
 *     as published by the Free Software Foundation, either version 3 of
 *            the License, or (at your option) any later version.
 *
 *     This program is distributed in the hope that it will be useful, but
 *         WITHOUT ANY WARRANTY; without even the implied warranty of
 *        MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *             GNU Affero General Public License for more details.
 *
 *      You should have received a copy of the GNU Affero General Public
 * License along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

class StripePayment
{

    public function __construct() { }

    public static function button($amount = '0.00', $description = '', $itemnumber = '101', $extra_array = null) {
        $extra = payment_prepare_custom($extra_array);
        $extra .= 'concept,'.$description.'|';
        $extra .= 'product,'.$itemnumber.'|';
        $r = rand(0,1000);
        $extra .= 'random,'.$r;
        echo '<li class="payment stripe-btn"><a href="javascript:stripe_pay(\''.$amount.'\',\''.$description.'\',\''.$itemnumber.'\',\''.$extra.'\');" ><img src="'.osc_base_url() . 'oc-content/plugins/' . osc_plugin_folder(__FILE__).'pay_with_card.png" ></a></li>';
    }

    public static function dialogJS() { ?>
        <div id="stripe-dialog" title="<?php _e('Stripe', 'payment'); ?>" style="display: none;"><span id="stripe-dialog-text"></span></div>
        <form action="<?php echo osc_base_url(true); ?>" method="post" id="stripe-payment-form" class="nocsrf" >
            <input type="hidden" name="page" value="ajax" />
            <input type="hidden" name="action" value="runhook" />
            <input type="hidden" name="hook" value="stripe" />
            <input type="hidden" name="extra" value="" id="stripe-extra" />
        </form>
        <script type="text/javascript">
            function stripe_pay(amount, description, itemnumber, extra) {
                var token = function(res){
                    var $input = $('<input type=hidden name=stripeToken />').val(res.id);
                    $('#stripe-extra').attr('value', extra);
                    $('#stripe-payment-form').append($input);
                    $.ajax({
                        type: "POST",
                        url: '<?php echo osc_base_url(true); ?>',
                        data: $("#stripe-payment-form").serialize(),
                        success: function(data)
                        {
                            $('#stripe-dialog-text').html(data);
                        }
                    });
                    setTimeout(openStripeDialog, 150);
                };


                StripeCheckout.open({
                    key:         'pk_test_qOEpVaCdBkYZ59HWLvy8xL1p',
                    address:     false,
                    amount:      (amount*100),
                    currency:    '<?php echo osc_get_preference("currency", "payment");?>',
                    name:        description,
                    description: amount+' <?php echo osc_get_preference("currency", "payment"); ?> ('+itemnumber+')',
                    panelLabel:  'Checkout',
                    token:       token
                });


                return false;
            };

            function openStripeDialog() {
                $('#stripe-dialog-text').html('<?php echo osc_esc_js(__("Please wait a moment while we're processing your payment", 'payment')); ?>');
                $('#stripe-dialog').dialog('open')
            }

            $(document).ready(function(){
                $("#stripe-dialog").dialog({
                    autoOpen: false,
                    modal: true
                });
            });

        </script>

    <?php
    }

    public static  function ajaxPayment() {
        $status = self::processPayment();

        $data = payment_get_custom(Params::getParam('extra'));
        $product_type = explode('x', $data['product']);
        if ($status==PAYMENT_COMPLETED) {
            osc_add_flash_ok_message(sprintf(__('Success! Please write down this transaction ID in case you have any problem: %s', 'payment'), Params::getParam('stripe_transaction_id')));
            if($product_type[0]==101) {
                $item = Item::newInstance()->findByPrimaryKey($product_type[2]);
                $category = Category::newInstance()->findByPrimaryKey($item['fk_i_category_id']);
                View::newInstance()->_exportVariableToView('category', $category);
                payment_js_redirect_to(osc_search_category_url());
            } else if($product_type[0]==201) {
                if(osc_is_web_user_logged_in()) {
                    payment_js_redirect_to(osc_route_url('payment-user-menu'));
                } else {
                    View::newInstance()->_exportVariableToView('item', Item::newInstance()->findByPrimaryKey($product_type[2]));
                    payment_js_redirect_to(osc_item_url());
                }
            } else {
                if(osc_is_web_user_logged_in()) {
                    payment_js_redirect_to(osc_route_url('payment-user-pack'));
                } else {
                    // THIS SHOULD NOT HAPPEN
                    payment_js_redirect_to(osc_base_path());
                }
            }
        } else {
            if ($status==PAYMENT_ALREADY_PAID) {
                osc_add_flash_warning_message(__('Warning! This payment was already paid', 'payment'));
            } else {
                osc_add_flash_error_message(_e('There were an error processing your payment', 'payment'));
            }
            if($product_type[0]==301) {
                if(osc_is_web_user_logged_in()) {
                    payment_js_redirect_to(osc_route_url('payment-user-pack'));
                } else {
                    // THIS SHOULD NOT HAPPEN
                    payment_js_redirect_to(osc_base_path());
                }
            } else {
                if(osc_is_web_user_logged_in()) {
                    payment_js_redirect_to(osc_route_url('payment-user-menu'));
                } else {
                    View::newInstance()->_exportVariableToView('item', Item::newInstance()->findByPrimaryKey($product_type[2]));
                    payment_js_redirect_to(osc_item_url());
                }
            }
        }

    }

    public static function processPayment() {
        require_once osc_plugins_path() . osc_plugin_folder(__FILE__) . 'lib/Stripe.php';

        if(osc_get_preference('stripe_sandbox', 'payment')==0) {
            $stripe = array(
                "secret_key"      => osc_get_preference('stripe_secret_key', 'payment'),
                "publishable_key" => osc_get_preference('stripe_public_key', 'payment')
            );
        } else {
            $stripe = array(
                "secret_key"      => osc_get_preference('stripe_secret_key_test', 'payment'),
                "publishable_key" => osc_get_preference('stripe_public_key_test', 'payment')
            );
        }

        Stripe::setApiKey($stripe['secret_key']);

        $token  = Params::getParam('stripeToken');
        $data = payment_get_custom(Params::getParam('extra'));

        $amount = payment_get_amount($data['product']);
        if($amount<=0) { return PAYMENT_FAILED; }

        $customer = Stripe_Customer::create(array(
            'email' => $data['email'],
            'card'  => $token
        ));

        try {
            $charge = @Stripe_Charge::create(array(
                'customer' => $customer->id,
                'amount'   => $amount*100,
                'currency' => osc_get_preference("currency", "payment")
            ));

            if($charge->__get('paid')==1) {

                $exists = ModelPayment::newInstance()->getPaymentByCode($charge->__get('id'), 'STRIPE');
                if(isset($exists['pk_i_id'])) { return PAYMENT_ALREADY_PAID; }
                $product_type = explode('x', $data['product']);
                Params::setParam('stripe_transaction_id', $charge->__get('id'));
                // SAVE TRANSACTION LOG
                $payment_id = ModelPayment::newInstance()->saveLog(
                    $data['concept'], //concept
                    $charge->__get('id'), // transaction code
                    $charge->__get('amount')/100, //amount
                    $charge->__get('currency'), //currency
                    $data['email'], // payer's email
                    $data['user'], //user
                    $data['itemid'], //item
                    $product_type[0], //product type
                    'STRIPE'); //source

                if ($product_type[0] == '101') {
                    ModelPayment::newInstance()->payPublishFee($product_type[2], $payment_id);
                } else if ($product_type[0] == '201') {
                    ModelPayment::newInstance()->payPremiumFee($product_type[2], $payment_id);
                } else {
                    ModelPayment::newInstance()->addWallet($data['user'], ($charge->__get('amount')/100));
                }
                return PAYMENT_COMPLETED;
            }
            return PAYMENT_FAILED;
        } catch(Stripe_CardError $e) {
            return PAYMENT_FAILED;
        }

        return PAYMENT_FAILED;

    }

}

?>