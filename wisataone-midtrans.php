<?php
/**
* Plugin Name: Wisataone Midtrans
* Plugin URI: https://www.treasurf.com/
* Description: Wisataone Midtrans interface payment plugin
* Version: 1.0
* Author: Rio Chandra Rajagukguk
* Author URI: http://www.treasurf.com/
**/


include_once(dirname(__FILE__) . '/midtrans.php');

global $midtrans_plugin_db_version, $tblname;
$midtrans_plugin_db_version = '1.0.0';
$tblname = 'midtrans_notification';

/**
 * Initialize Plugin
 * Create database for payment notification.
 */
function create_plugin_database_table() {
    global $wpdb, $midtrans_plugin_db_version, $tblname;

    $wp_track_table = $wpdb->prefix . $tblname;
    $charset_collate = $wpdb->get_charset_collate();

    #Check to see if the table exists already, if not, then create it

    if($wpdb->get_var( "show tables like '$wp_track_table'" ) != $wp_track_table) {
        $sql = "CREATE TABLE {$wp_track_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            notification_data text DEFAULT NULL,
            PRIMARY KEY  (id)
        ) {$charset_collate};";
        require_once( ABSPATH . '/wp-admin/includes/upgrade.php' );
        dbDelta($sql);
        add_option('midtrans_plugin_db_version', $midtrans_plugin_db_version);
    }
}

/**
 * Initialize Plugin.
 * Database insert testing.
 */
function init_plugin_db() {
    insert_notification("success");
}

/**
 * Insert new notification to database.
 */
function insert_notification ($data) {
    global $wpdb, $tblname;
    $wp_track_table = $wpdb->prefix . $tblname;

    $wpdb->insert(
        $wp_track_table, 
        array(
            'notification_data' => $data, 
        ),
        array('%s')
    );
}

/**
 * Get all notification from database.
 */
function get_all_notification() {
    global $wpdb, $tblname;
    $wp_track_table = $wpdb->prefix . $tblname;

    return $wpdb->get_results( 
        "
        SELECT id, notification_data 
        FROM {$wp_track_table}
        "
    );
}

/**
 * Register payment notification hook
 * for midtrans notification payment.
 */
function register_notification_payment_route() {
    register_rest_route('payment/v1', 'notify', array(
        'methods'  => 'GET',
        'callback' => 'sample_request'
    ));
    register_rest_route('payment/v1', 'notify', array(
        'methods'  => 'POST',
        'callback' => 'sample_request'
    ));
}

/**
 * Testing purpose.
 * Can be removed.
 */
function sample_request($request) {

    $posts = json_encode($request->get_params());
    insert_notification($posts);

    $response = new WP_REST_Response(changeOrderStatus($request->get_params()));
    $response->set_status(200);

    return $response;
}

/**
 * Convert midtrans payment status to 
 * tourmaster theme payment status.
 */
function convertOrderStatus($midtrans_order_status) {
    $Pending = 'pending';
    $Approved = 'approved';
    $Receipt_Submitted = 'receipt-submitted';
    $Online_Paid = 'online-paid';
    $Deposit_Paid = 'deposit-paid';
    $Departed = 'departed';
    $Rejected = 'rejected';
    $Cancel = 'cancel';
    $Wait_For_Approval = 'wait-for-approval';
    switch ($midtrans_order_status) {
        case 'authorize': return $Pending;
        case 'capture': return $Pending;
        case 'settlement': return $Online_Paid;
        case 'deny': return $Rejected;
        case 'pending': return $Pending;
        case 'cancel': return $Cancel;
        case 'refund': return $Cancel;
        case 'partial_refund': return $Cancel;
        case 'chargeback': return $Pending;
        case 'partial_chargeback': return $Pending;
        case 'expire': return $Cancel;
        case 'failure': return $Rejected;
    }

    return false;
}

/**
 * Change/decide order status after
 * receiving payment notification hook 
 * from midtrans.
 */
function changeOrderStatus($payment_notification) {
    if (!isset($payment_notification['order_id']) 
        || !isset($payment_notification['transaction_status'])) {
        return false;
    }

    global $wpdb;
    $wp_track_table = $wpdb->prefix . "tourmaster_order";

    $order_status = convertOrderStatus($payment_notification['transaction_status']);
    if (!$order_status) {
        return false;
    }

    $wpdb->update( 
        $wp_track_table, 
        array( 
            'order_status' => $order_status
        ), 
        array( 'id' => $payment_notification['order_id'] ), 
        array( 
            '%s'
        ), 
        array( '%d' ) 
    );

    return true;
}




/**
 * Debugging Menu for tracking payment 
 * notification. Can be found on Setting menu
 * on Admin Page.
 */
function midtrans_notification_payment_menu() {
    $page_title = "Midtrans Notification Payment";
    $option_name = "Midtrans Notifs";
    $uniq_id = "midtrans-notifs";
    add_options_page(
        $page_title, 
        $option_name, 
        'manage_options', 
        $uniq_id, 
        'midtrans_notification_options_view'
    );
}

/**
 * Part of Debugging Menu
 */
function midtrans_notification_options_view() {
	if ( !current_user_can( 'manage_options' ) )  {
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
	}
    echo '<div class="wrap">';
    echo '<table><thead><tr><th>ID</th><th>Data</th></tr></thead><tbody>';
    foreach (get_all_notification() as $notification) {
        echo '<tr><td>';
        echo "{$notification->id}";
        echo '</td><td>';
        echo "<pre>{$notification->notification_data}</pre>";
        echo '</td></tr>';
    }
    echo '</tbody></table>';
	echo '</div>';
}


register_activation_hook(__FILE__, 'create_plugin_database_table');
register_activation_hook(__FILE__, 'init_plugin_db');
add_action('rest_api_init', 'register_notification_payment_route');
add_action('admin_menu', 'midtrans_notification_payment_menu');
