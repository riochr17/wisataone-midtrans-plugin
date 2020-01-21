<?php
/*
  Version: 1.0.0
  Author: Rio Chandra Rajagukguk - Midtrans
  License: Apache License 2.0
  Date: 2019-09-21
 */

/*
 * CONFIGURATIONS
 */

require_once(dirname(__FILE__) . '/veritrans-php-master/Veritrans.php');

/**
 * Start payment, by inquiring snap token.
 * Order ID and amount are required.
 * Snap token will be used on SNAP Popup on frontend.
 */
if (!function_exists('startPayment')) {
    function startPayment($order_id, $amount, $bdata, $isDeposit) {

        $server_key = tourmaster_get_option('payment', 'midtrans-server-key', '');
        $production_mode = tourmaster_get_option('payment', 'midtrans-production-mode', 'disable');
        //Set Your server key
        Veritrans_Config::$serverKey = $server_key;

        // Uncomment for production environment
        Veritrans_Config::$isProduction = !(empty($production_mode) || $production_mode == 'disable');

        Veritrans_Config::$isSanitized = true;
        Veritrans_Config::$is3ds = true;

        $contact_info = json_decode($bdata->contact_info);
        $billing_info = json_decode($bdata->billing_info);
        $booking_detail = json_decode($bdata->booking_detail);
        $pricing_info = json_decode($bdata->pricing_info);

        // Fill transaction details
        $transaction_details = array(
            'order_id' => $order_id,
            'gross_amount' => $amount // no decimal allowed
        );
        
        // Mandatory for Mandiri bill payment and BCA KlikPay
        // Optional for other payment methods
        $item1_details = array(
            'id' => $booking_detail->{"tour-id"},
            'price' => $amount,
            'quantity' => 1, // revision
            //'quantity' => intval($bdata->traveller_amount),
            'name' => ($isDeposit ? "Deposit: " : "Full Payment: ") . get_the_title($booking_detail->{"tour-id"})
        );
        $item_details = array ($item1_details);
        
        // Optional
        $billing_address = array(
            'first_name'    => $billing_info->first_name,
            'last_name'     => $billing_info->last_name,
            'address'       => $billing_info->contact_address,
            'city'          => "Not Found",
            'postal_code'   => "10000",
            'phone'         => $billing_info->phone,
            'country_code'  => 'IDN'
        );
        
        $customer_details = array(
            'first_name'    => $contact_info->first_name,
            'last_name'     => $contact_info->last_name,
            'email'         => $contact_info->email,
            'phone'         => $contact_info->phone, //mandatory
            'billing_address'  => $billing_address, //optional
        );
        
        // Fill transaction details
        $transaction = array(
            'transaction_details' => $transaction_details,
            'customer_details' => $customer_details,
            'item_details' => $item_details,
        );

        $snapToken = Veritrans_Snap::getSnapToken($transaction);
        return $snapToken;
    }
}


/**
 * Admin page configuration for Midtrans Configuration.
 * - Production Mode
 * - Client Key
 * - Server Key
 */
add_filter('goodlayers_plugin_payment_option', 'tourmaster_midtrans_payment_option');
if (!function_exists('tourmaster_midtrans_payment_option')) {

    function tourmaster_midtrans_payment_option($options) {
        $options['midtrans'] = array(
            'title' => esc_html__('Midtrans', 'tourmaster'),
            'options' => array(
                'midtrans-production-mode' => array(
                    'title' => __('Production Mode', 'tourmaster'),
                    'type' => 'checkbox',
                    'default' => 'disable',
                    'description' => esc_html__('Jika memilih Production Mode, client key dan server key dibawah harus key dari production bukan sandbox, dan sebaliknya.', 'tourmaster')
                ),
                'midtrans-client-key' => array(
                    'title' => esc_html__('Client Key', 'tourmaster'),
                    'type' => 'text'
                ),
                'midtrans-server-key' => array(
                    'title' => esc_html__('Server Key', 'tourmaster'),
                    'type' => 'text',
                )
            )
        );
        return $options;
    }

}

/**
 * idk much about this one.
 * Returning button attribute for midtrans payment option
 * on choosing payment page.
 */
add_filter('tourmaster_midtrans_button_atts', 'tourmaster_midtrans_button_attribute');
if( !function_exists('tourmaster_midtrans_button_attribute') ){
    function tourmaster_midtrans_button_attribute( $attributes ){
        $service_fee = tourmaster_get_option('payment', 'paypal-service-fee', '');
        return array('method' => 'ajax', 'type' => 'midtrans', 'service-fee' => $service_fee);
    }
}

/**
 * Midtrans payment form.
 * Since using SNAP Popup, the form are showing
 * popup for user to choose payment method available
 * on SNAP Midtrans Popup.
 * 
 * Inquiring snap token, then show SNAP Popup.
 */
add_filter('goodlayers_midtrans_payment_form', 'tourmaster_midtrans_payment_form', 10, 2);
if( !function_exists('tourmaster_midtrans_payment_form') ){
    function tourmaster_midtrans_payment_form( $ret = '', $tid = '' ){

        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");
        
        $t_data = apply_filters('goodlayers_payment_get_transaction_data', array(), $tid, array('price', 'tour_id'));
        $bdata = tourmaster_get_booking_data(array('id' => $tid), array('single' => true));
        $client_key = tourmaster_get_option('payment', 'midtrans-client-key', '');
        $production_mode = tourmaster_get_option('payment', 'midtrans-production-mode', 'disable');
        $isProduction = !(empty($production_mode) || $production_mode == 'disable');
        
        $price = '';
        if( $t_data['price']['deposit-price'] ){
            $price = $t_data['price']['deposit-price'];
        }else{
            $price = $t_data['price']['pay-amount'];
        }

        $price = intval($price);
        $snap_Token = startPayment(($t_data['price']['deposit-price'] ? "dp-" : "") . "track-wst-$tid", $price, $bdata, $t_data['price']['deposit-price']);
        ob_start();
?>
<div class="goodlayers-paypal-redirecting-message" ><?php esc_html_e('Please wait while we redirect you to midtrans.', 'tourmaster') ?></div>
    <script type="text/javascript"
            src="<?php
            if ($isProduction) {
                echo 'https://app.midtrans.com/snap/snap.js';
            } else {
                echo 'https://app.sandbox.midtrans.com/snap/snap.js';
            }
            ?>"
            data-client-key="<?php echo $client_key; ?>">
    </script> 

    <script type="text/javascript">
        function whenAvailable(callback) {
            var interval = 100; // ms
            window.setTimeout(function() {
                if (typeof snap !== "undefined") {
                    callback();
                } else {
                    window.setTimeout(arguments.callee, interval);
                }
            }, interval);
        }

        whenAvailable(function() {
            snap.pay('<?php echo $snap_Token; ?>', {
                onSuccess: function(result) {
                    window.location.replace("/?midtrans");
                },
                onPending: function(result) {
                    window.location.replace("/?midtrans");
                },
                onError: function(result) {
                    window.location.replace("/?midtrans");
                },
                onClose: function() {
                    //
                }
            });
        });
    </script>
<?php
        $ret = ob_get_contents();
        ob_end_clean();

        return $ret;

    } // goodlayers_midtrans_payment_form
}

/**
 * This section should be payment notification handling
 * after payment finished/unfinished.
 * 
 * But this section is not important, since
 * payment notification has been proceed on
 * wisataone-midtrans.php
 */
add_action('init', 'tourmaster_midtrans_init');
if( !function_exists('tourmaster_midtrans_init') ){
    function tourmaster_midtrans_init(){
        

        if (isset($_GET['midtrans'])) {

            $payment_info = array(
                'payment_method' => 'midtrans'
            );

            tourmaster_update_booking_data( 
                array(
                    'payment_info' => json_encode($payment_info),
                ),
                array('id' => $tid),
                array('%s'),
                array('%d')
            );
            tourmaster_mail_notification('payment-made-mail', $tid);
            tourmaster_mail_notification('admin-online-payment-made-mail', $tid);
        }
    }
}