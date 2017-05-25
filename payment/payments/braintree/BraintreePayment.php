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

    class BraintreePayment
    {

        public function __construct()
        {
            Braintree_Configuration::environment('sandbox');
            Braintree_Configuration::merchantId('6sydfqcpfbzmcj7w');
            Braintree_Configuration::publicKey('bytsnfpmh8f2v637');
            Braintree_Configuration::privateKey('88396fd311a6e1016a903bf08660065f');
        }

        public static function button($amount = '0.00', $description = '', $itemnumber = '101', $extra_array = null) {
            $extra = payment_prepare_custom($extra_array);
            $extra .= 'concept,'.$description.'|';
            $extra .= 'product,'.$itemnumber.'|';
            $r = rand(0,1000);
            $extra .= 'random,'.$r;
            $CALLBACK_URL = osc_base_url() . 'oc-content/plugins/' . osc_plugin_folder(__FILE__) . 'callback.php?extra=' . $extra;
            echo '<li class="payment braintree-btn"><a href="javascript:braintree_pay(\''.$amount.'\',\''.$description.'\',\''.$itemnumber.'\',\''.$extra.'\');" ><img src="'.osc_base_url() . 'oc-content/plugins/' . osc_plugin_folder(__FILE__).'pay_with_card.png" ></a></li>';
        }
                                            //style="display: none"
        public static function dialogJS() { ?>
            <div id="braintree-dialog" >
                <div id="braintree-info">
                    <div id="braintree-data">
                        <p id="braintree-desc"></p>
                        <p id="braintree-price"></p>
                    </div>
                    <form action="<?php echo osc_base_url(true); ?>" method="POST" id="braintree-payment-form" >
                        <input type="hidden" name="page" value="ajax" />
                        <input type="hidden" name="action" value="runhook" />
                        <input type="hidden" name="hook" value="braintree" />
                        <input type="hidden" name="extra" value="" id="braintree-extra" />
                        <p>
                            <label><?php _e('Card number', 'payment'); ?></label>
                            <input type="text" size="20" autocomplete="off" data-encrypted-name="braintree_number" />
                        </p>
                        <p>
                            <label><?php _e('CVV', 'payment'); ?></label>
                            <input type="text" size="4" autocomplete="off" data-encrypted-name="braintree_cvv" />
                        </p>
                        <p>
                            <label><?php _e('Expiration (MM/YYYY)', 'payment'); ?></label>
                            <input type="text" size="2" data-encrypted-name="braintree_month" /> / <input type="text" size="4" data-encrypted-name="braintree_year" />
                        </p>
                        <input type="submit" id="submit" />
                    </form>
                </div>
                <div id="braintree-results" style="display:none;" ><?php _e('Processing payment, please wait.', 'payment'); ?></div>
            </div>
            <script type="text/javascript" src="https://js.braintreegateway.com/v1/braintree.js"></script>
            <script type="text/javascript">
                $(document).ready(function(){
                    $("#braintree-dialog").dialog({
                        autoOpen: false,
                        modal: true
                    });
                });

                var ajax_submit = function (e) {
                    form = $('#braintree-payment-form');
                    e.preventDefault();
                    $("#submit").attr("disabled", "disabled");
                    $("#braintree-info").hide();
                    $("#braintree-results").html('<?php _e('Processing the payment, please wait', 'payment');?>');
                    $("#braintree-results").show();
                    $.post(form.attr('action'), form.serialize(), function (data) {
                        $("#braintree-results").html(data);
                    });
                };
                var braintree = Braintree.create('<?php echo payment_decrypt(osc_get_preference('braintree_encryption_key', 'payment')); ?>');
                braintree.onSubmitEncryptForm('braintree-payment-form', ajax_submit);

                function braintree_pay(amount, description, itemnumber, extra) {
                    $("#braintree-extra").prop('value', extra);
                    $("#braintree-desc").html(description);
                    $("#braintree-price").html(amount+" <?php echo osc_get_preference("currency", "payment");?>");
                    $("#braintree-results").html('');
                    $("#braintree-results").hide();
                    $("#submit").removeAttr('disabled');
                    $("#braintree-info").show();
                    $("#braintree-dialog").dialog('open');
                }

            </script>
        <?php
        }

        public static  function ajaxPayment() {
            $status = BraintreePayment::processPayment();
            if ($status==PAYMENT_COMPLETED) {
                printf(__('Success! Please write down this transaction ID in case you have any problem: %s', 'payment'), Params::getParam('braintree_transaction_id'));
            } else if ($status==PAYMENT_ALREADY_PAID) {
                _e('Warning! This payment was already paid', 'payment');
            } else {
                _e('There were an error processing your payment', 'payment');
            }
        }

        public static function processPayment() {
            require_once osc_plugins_path() . osc_plugin_folder(__FILE__) . 'lib/Braintree.php';

            Braintree_Configuration::environment(osc_get_preference('braintree_sandbox', 'payment'));
            Braintree_Configuration::merchantId(payment_decrypt(osc_get_preference('braintree_merchant_id', 'payment')));
            Braintree_Configuration::publicKey(payment_decrypt(osc_get_preference('braintree_public_key', 'payment')));
            Braintree_Configuration::privateKey(payment_decrypt(osc_get_preference('braintree_private_key', 'payment')));

            $data = payment_get_custom(Params::getParam('extra'));

            $amount = payment_get_amount($data['product']);
            if($amount<=0) { return PAYMENT_FAILED; }

            $result = Braintree_Transaction::sale(array(
                'amount' => $amount,
                'creditCard' => array(
                    'number' => Params::getParam('braintree_number'),
                    'cvv' => Params::getParam('braintree_cvv'),
                    'expirationMonth' => Params::getParam('braintree_month'),
                    'expirationYear' => Params::getParam('braintree_year')
                ),
                'options' => array(
                    'submitForSettlement' => true
                )
            ));

            if($result->success==1) {
                Params::setParam('braintree_transaction_id', $result->transaction->id);
                $exists = ModelPayment::newInstance()->getPaymentByCode($result->transaction->id, 'BRAINTREE');
                if(isset($exists['pk_i_id'])) { return PAYMENT_ALREADY_PAID; }
                $product_type = explode('x', $data['product']);
                // SAVE TRANSACTION LOG
                $payment_id = ModelPayment::newInstance()->saveLog(
                    $data['concept'], //concept
                    $result->transaction->id, // transaction code
                    $result->transaction->amount, //amount
                    $result->transaction->currencyIsoCode, //currency
                    $data['email'], // payer's email
                    $data['user'], //user
                    $data['itemid'], //item
                    $product_type[0], //product type
                    'BRAINTREE'); //source

                if ($product_type[0] == '101') {
                    ModelPayment::newInstance()->payPublishFee($product_type[2], $payment_id);
                } else if ($product_type[0] == '201') {
                    ModelPayment::newInstance()->payPremiumFee($product_type[2], $payment_id);
                } else {
                    ModelPayment::newInstance()->addWallet($data['user'], $result->transaction->amount);
                }

                return PAYMENT_COMPLETED;
            } else {
                return PAYMENT_FAILED;
            }
        }

    }

?>