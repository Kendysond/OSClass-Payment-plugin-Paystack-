<?php 

class os_class_paystack_plugin_tracker {
    var $public_key;
    var $plugin_name;
    function __construct($plugin, $pk){
        //configure plugin name
        //configure public key
        $this->plugin_name = $plugin;
        $this->public_key = $pk;
    }

   

    function log_transaction_success($trx_ref){
        //send reference to logger along with plugin name and public key
        $url = "https://plugin-tracker.paystackintegrations.com/log/charge_success";

        $fields = [
            'plugin_name'  => $this->plugin_name,
            'transaction_reference' => $trx_ref,
            'public_key' => $this->public_key
        ];

        $fields_string = http_build_query($fields);

        $ch = curl_init();

        curl_setopt($ch,CURLOPT_URL, $url);
        curl_setopt($ch,CURLOPT_POST, true);
        curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);

        curl_setopt($ch,CURLOPT_RETURNTRANSFER, true); 

        //execute post
        $result = curl_exec($ch);
        //  echo $result;
    }
}

Class Paystack
{

    public function __construct() { }
 
    public static function button($amount = '0.00', $description = '', $itemnumber = '101', $extra_array = null) {
        $extra = payment_prepare_custom($extra_array);
        $email = $extra_array['email'];
        $extra .= 'concept,'.$description.'|';
        $extra .= 'product,'.$itemnumber.'|';
        $r = rand(0,1000);
        $extra .= 'random,'.$r;
        
        $public_key = osc_get_preference('paystack_lpk', 'payment');
        
        if(osc_get_preference('paystack_sandbox', 'payment')  == 1){
            $public_key = osc_get_preference('paystack_tpk', 'payment');
        }
        ?>
        <form action="<?php echo osc_base_url(true); ?>" method="POST" >
             <input type="hidden" name="page" value="ajax" />
                <input type="hidden" name="action" value="runhook" />
                <input type="hidden" name="hook" value="paystack" />
                <input type="hidden" name="amount" value="" id="paystack-amount"/>
                <input type="hidden" name="extra" value="<?php echo $extra;?>"  />
                  <script
                    src="https://js.paystack.co/v1/inline.js" 
                    data-key="<?php echo $public_key;?>"
                    data-email="<?php echo $email;?>"
                    data-amount="<?php echo $amount * 100;?>"
                    data-metadata='{"custom_fields": [{"display_name": "Plugin","variable_name": "plugin","value": "os-class"}]}'
                  >
          </script>
        </form>

        <?php
    }

    public static  function processPayment() {
        
        $result = self::confirmPayment();
        $status = $result[0];
        $product_type = $result[1];
        if($status==PAYMENT_COMPLETED){

            osc_add_flash_ok_message(sprintf(__('Payment was successful! Transaction reference: %s', 'payment'), Params::getParam('paystack_reference')));


             if($product_type[0]==101){

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
                  payment_js_redirect_to(osc_route_url('payment-user-pack'));
            }


        }else{
            $errormessage = $result[1];
        
            osc_add_flash_error_message(sprintf(__($errormessage)));
            payment_js_redirect_to(osc_route_url('payment-user-menu'));
        }


    }

    public static function confirmPayment() {
        
        $reference = $_POST['paystack-trxref'];

        $key = osc_get_preference('paystack_tsk', 'payment');
        $pk =  osc_get_preference('paystack_tpk', 'payment');
        if(osc_get_preference('paystack_sandbox', 'payment') == 0) {
            $key = osc_get_preference('paystack_lsk', 'payment');
            $pk = osc_get_preference('paystack_lpk', 'payment');
        }
        $pstk_logger = new os_class_paystack_plugin_tracker('os-class', $pk);
        $result = array();
        /// Verify Transaction was successful
        $url = 'https://api.paystack.co/transaction/verify/'.$reference;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt(
          $ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer '.$key]
        );
        $request = curl_exec($ch);
        curl_close($ch);

        if ($request) {
          $result = json_decode($request, true);
        }

        if (array_key_exists('data', $result) && array_key_exists('status', $result['data']) && ($result['data']['status'] === 'success')) {
            $pstk_logger->log_transaction_success($reference);
            $status = 'success';
        }else{
            $status = 'failed';
        }
        $conn = DBConnectionClass::newInstance();
        $data = $conn->getOsclassDb();
        $comm = new DBCommandClass($data);
        $db_prefix = DB_TABLE_PREFIX;

        $query = "SELECT * FROM `{$db_prefix}t_payments_log` WHERE `s_code` = '".$reference."'";
        $queryresult = $comm->query($query);
        $rows = 0;
        if ($queryresult) {
            $dbrows = $queryresult->result();
            $rows = count($dbrows);
        }
        if ($rows === 0) {
            if($status == 'success'){
                   
                    Params::setParam('paystack_reference', $reference);

                    $data = payment_get_custom(Params::getParam('extra'));
                    $product_type = explode('x', $data['product']);
                    $amount = $result['data']['amount']/100;

                    $payment_id = ModelPayment::newInstance()->saveLog(
                        $data['concept'],
                        $result['data']['reference'],
                        $amount,
                        'NGN',
                        $data['email'],
                        $data['user'],
                        $data['itemid'],
                        $product_type[0],
                        'Paystack'
                    );
                    
                    if ($product_type[0] == '101') {
                        ModelPayment::newInstance()->payPublishFee($product_type[2], $payment_id);
                    } else if ($product_type[0] == '201') {
                        ModelPayment::newInstance()->payPremiumFee($product_type[2], $payment_id);
                    } else {
                        ModelPayment::newInstance()->addWallet($data['user'], $amount);
                    }

                    return Array(PAYMENT_COMPLETED,$product_type);

            }else{
             $errormessage = "Payment not successful";

            }
        }else{
           $errormessage = "Transaction reference has already been used";
        }
        

        
        error_log("Error verifing the payment error-> ".$errormessage);
        return Array(PAYMENT_FAILED,$errormessage);

    }

}

?>