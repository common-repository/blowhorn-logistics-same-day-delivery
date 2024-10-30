<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if (!class_exists('BLSDD_Tracking')) {


    class BLSDD_Tracking
    {

        /**
         * @var $_panel Panel Object
         */
        protected $_panel;

        private $options;

        public function __construct()
        {
            session_start();
            $this->initialize_settings();

            add_action('admin_menu', array(
                $this,
                'blsdd_add_plugin_page'
            ));
            add_action('admin_init', array(
                $this,
                'blsdd_page_init'
            ));
            add_action('add_meta_boxes', array(
                $this,
                'blsdd_add_order_tracking_metabox'
            ));
            add_action('woocommerce_process_shop_order_meta', array(
                $this,
                'blsdd_save_order_tracking_metabox'
            ), 10);
            add_action( 'admin_notices', array(
                $this,
                'blsdd_my_admin_notices'
            ));
            add_action('woocommerce_after_checkout_validation', array(
                $this,
                'blsdd_post_checkout_validation'
            ));
            add_action('woocommerce_thankyou', array(
                $this,
                'blsdd_send_order_to_bh'
            ));

            // Adding to admin order list bulk dropdown a custom action 'assign_orders'
            add_filter( 'bulk_actions-edit-shop_order', array(
                $this,
                'blsdd_bulk_actions_push_orders_to_bh'
            ));

            // Make the action from selected orders
            add_filter( 'handle_bulk_actions-edit-shop_order', array(
                $this,
                'blsdd_bulk_action_handle_assign_orders_bh'
            ), 10, 3);

            // The results notice from bulk action on orders
            add_action( 'admin_notices', array(
                $this,
                'blsdd_assign_orders_bulk_action_admin_notice'
            ));

            // Adding a column to show Blowhorn reference number
            add_filter( 'manage_edit-shop_order_columns', array(
                $this,
                'blsdd_show_bh_reference_number_column'
            ));

            add_action( 'manage_shop_order_posts_custom_column', array(
                $this,
                'blsdd_show_bh_reference_numbers'
            ), 20, 2);
        }


        /**
         * Set values from plugin settings page
         */
        public function initialize_settings()
        {
            $this->order_text_position = get_option('order_tracking_text_position');
        }


        function blsdd_add_order_tracking_metabox()
        {
            $userdata = get_option('my_option_name');
            if ($userdata) {
                add_meta_box('order-tracking-information', __('Blowhorn Order tracking', 'bh'), array(
                    $this,
                    'blsdd_show_order_tracking_metabox'
                ), 'shop_order', 'side', 'high');
            }
        }



        function blsdd_show_order_tracking_metabox($post)
        {

            $userdata = get_option('my_option_name');

            if ($userdata) {

                $data                = get_post_custom($post->ID);
                $order_reference_number = isset($data['bh_reference_number'][0]) ? $data['bh_reference_number'][0] : '';
                $awb_number = isset($data['awb_number'][0]) ? $data['awb_number'][0] : '';
                $tracking_link = 'https://blowhorn.com/track-parcel/' . $awb_number;

                if ($order_reference_number != '') {
                   ?>

                    <div class="track-information">
                        <p>
                            <label for="bh_reference_number"> <?php
                                _e('Reference number: ', 'bh');
                            ?></label><?php
                            echo $order_reference_number; ?>

                        </p>

                        <p>
                            <label for="awb_number"> <?php
                                _e('AWB Number: ', 'bh');
                            ?></label><?php
                            echo $awb_number; ?>
                         </p>

                         <p>
                            <a href="<?php echo $tracking_link;?>"> Track Order </a>
                         </p>

                    </div>
	               <?php
                } else {
                        ?>
                        <div class="track-information">
                            <p>
                                <label for="bh_reference_number"> <?php
                                _e('Reference number:', 'bh'); ?></label>
                                <br/>
                                <input style="width: 100%" type="text" name="bh_reference_number" id="bh_reference_number"
                                       placeholder="<?php
                                _e('Enter reference number', 'bh'); ?>"
                                       value="<?php
                                echo $order_reference_number; ?>"/>
                            </p>

                        </div>
                    <?php
                }
            }
        }

        function blsdd_my_admin_notices(){
            if(!empty($_SESSION['my_admin_notices'])) print  $_SESSION['my_admin_notices'];
            unset ($_SESSION['my_admin_notices']);
        }

        function blsdd_bulk_actions_push_orders_to_bh( $actions ) {
           $actions['assign_orders'] = __( 'Assign Orders to Blowhorn', 'woocommerce' );
           return $actions;
        }

        function blsdd_bulk_action_handle_assign_orders_bh( $redirect_to, $action, $post_ids ) {
            if ( $action !== 'assign_orders' )
                return $redirect_to; // Exit

            $processed_ids = array();
            $message = '';

            foreach ( $post_ids as $post_id ) {
                $order = wc_get_order( $post_id );
                $user_id = $order->get_user_id();
                $order_id = $order->get_id();
                $company = get_bloginfo('name');
                $reference_tag = strtoupper(mb_substr($company, 0, 3));

                // Reference number for blowhorn tracking
                $reference_number = $reference_tag.'-'.$order_id;
                $a = get_option('my_option_name');

                $data = array();

                $customer_name  = $order->get_billing_first_name().' '.$order->get_billing_last_name();

                $data['customer_name']    = $customer_name;


                $data['customer_email']   = $order->get_billing_email();
                $data['customer_mobile']  = $order->get_billing_phone();
                $data['reference_number'] = $reference_number;


                $headers =  array(
                                'api_key'      => $a['id_number'],
                                'Content-Type' => 'application/json'
                            );

                // get the product details
                $items = $order->get_items();

                $data['item_details'] = array();

                $products = array();

                foreach ($items as $key => $product) {
                    $products['item_name']                = $product['name'];
                    $products['item_price_per_each']      = $product['line_total'] / $product['qty'];
                    $products['item_quantity']            = $product['qty'];
                    $products['total_item_price']         = $product['line_total'];
                    $products['weight']                   = $product['weight'];
                    $products['volume']                   = $product['volume'];
                    $products['brand']                    = $product['brand'];
                    $products['length']                   = $product['length'];
                    $products['breadth']                  = $product['breadth'];
                    $products['height']                   = $product['height'];
                    $products['item_category']            = $product['item_category'];

                    array_push($data['item_details'], $products);
                }

//                 $data['order_creation_time']   = date_format(date_create($order->get_date_created()), 'c');
                $country = $order->get_billing_country();
                $state = $order->get_billing_state();
                $billing_state = WC()->countries->get_states( $country )[$state];

                $data['delivery_address']      = $order->get_billing_address_1().', '.$order->get_billing_address_2().', '.$order->get_billing_city().', '.$billing_state.', '.$order->get_billing_postcode();
                $data['delivery_postal_code']  = $order->get_billing_postcode();

                $is_cod                        = false;
                if ($order->get_payment_method() == 'cod') {
                        $is_cod = true;
                }

                $data['is_cod']           = $is_cod;
                $data['cash_on_delivery'] = $is_cod ? $order->get_total() : 0;

                $data['source'] = 'woocommerce';


                $url = "https://blowhorn.com/api/orders/shipment";

                $args = array(
                    'body'    => json_encode($data),
                    'timeout' => '180',
                    'headers' => $headers,
                    'blocking' => true
                );
                $response = wp_remote_post( $url, $args );
                $output   = wp_remote_retrieve_body( $response );
                $output = json_decode( $output );


                // store awb_number and reference_number in order meta if order is placed in blowhorn
                if (isset($output->status) && $output->status == 'PASS') {
                    $awb_number = $output->message->awb_number;
                    update_post_meta($order_id, 'awb_number', stripslashes($awb_number));
                    delete_post_meta($order_id, 'bh_reference_number');
                    update_post_meta($order_id, 'bh_reference_number', stripslashes($reference_number));

                    $processed_ids[] = $post_id;
                }
                else {
                    $message .= '<p>'.$order_id.' - '.$output->message.'</p>';
                }

            }
            if (!empty($message)){
                // display error message for which assign action failed
                $_SESSION['my_admin_notices'] = "<div class='error'>
                                            Assign failed for Order IDs: {$message}</div>";
            }
            return $redirect_to = add_query_arg( array(
                'assign_orders' => '1',
                'processed_count' => count( $processed_ids ),
                'processed_ids' => implode( ',', $processed_ids ),
            ), $redirect_to );
        }


        function blsdd_assign_orders_bulk_action_admin_notice() {
            if ( empty( $_REQUEST['assign_orders'] ) ) return; // Exit

            $count = intval( $_REQUEST['processed_count'] );

            printf( '<div id="message" class="updated fade"><p>' .
                _n( 'Assigned %s Order(s) to Blowhorn.',
                'Assigned %s Order(s) to Blowhorn.',
                $count,
                'assign_orders'
            ) . '</p></div>', $count );
        }

        function blsdd_show_bh_reference_number_column( $columns ){
            $reordered_columns = array();

            // Inserting columns to a specific location
            foreach( $columns as $key => $column){
                $reordered_columns[$key] = $column;
                if( $key ==  'order_status' ){
                    // Inserting after "Status" column
                    $reordered_columns['bh-reference-number'] = __( 'Blowhorn Ref. No','theme_domain');
                }
            }
            return $reordered_columns;
        }

        function blsdd_show_bh_reference_numbers( $column, $post_id ){
            if ( $column == 'bh-reference-number'){
                $reference_number = get_post_meta( $post_id, 'bh_reference_number', true);

                if (!empty($reference_number)){
                    echo $reference_number;
                }
            }
        }


        function blsdd_post_checkout_validation( $posted ){
            $data = array();
            $postcode = $_POST['billing_postcode'];

            $a = get_option('my_option_name');

            if ( !$a['validate_pincode'] ) {
                return;
            }

            $headers  = array(
                            'api_key'      => $a['id_number'],
                            'Content-Type' => 'application/json');

            $url = 'https://blowhorn.com/api/pincodes';

            $args = array(
                'timeout'  => '10',
                'blocking' => true,
                'headers'  => $headers
            );

            $response = wp_remote_get( $url.'?pincode='.$postcode, $args );
            $output   = wp_remote_retrieve_body( $response );
            $output = json_decode( $output );

            if (isset($output->serviceable) && !$output->serviceable){
                wc_add_notice( __( "Entered Pincode is not serviceable", 'woocommerce' ), 'error' );
            }
        }

        function blsdd_send_order_to_bh( $order_id ) {
            // get order object and order details
            $order = new WC_Order( $order_id );
            $user_id = $order->get_user_id();
            $company = get_bloginfo('name');
            $reference_tag = strtoupper(mb_substr($company, 0, 3));

            // Reference number blowhorn tracking
            $reference_number = $reference_tag.'-'.$order_id;
            $a = get_option('my_option_name');

            if ( !$a['auto_push'] ) {
                return;
            }

            $data = array();

            $customer_name  = $order->get_billing_first_name().' '.$order->get_billing_last_name();

            $data['customer_name']    = $customer_name;


            $data['customer_email']   = $order->get_billing_email();
            $data['customer_mobile']  = $order->get_billing_phone();
            $data['reference_number'] = $reference_number;


            $headers =  array(
                            'api_key'      => $a['id_number'],
                            'Content-Type' => 'application/json'
                        );

            // get the product details
            $items = $order->get_items();

            $data['item_details'] = array();

            $products = array();

            foreach ($items as $key => $product) {
                $products['item_name']                = $product['name'];
                $products['item_price_per_each']      = $product['line_total'] / $product['qty'];
                $products['item_quantity']            = $product['qty'];
                $products['total_item_price']         = $product['line_total'];
                $products['weight']                   = $product['weight'];
                $products['volume']                   = $product['volume'];
                $products['brand']                    = $product['brand'];
                $products['length']                   = $product['length'];
                $products['breadth']                  = $product['breadth'];
                $products['height']                   = $product['height'];
                $products['item_category']            = $product['item_category'];

                array_push($data['item_details'], $products);
            }

//                     $data['order_creation_time']   = date_format(date_create($_POST['order_date']), 'c');
            $country = $order->get_billing_country();
            $state = $order->get_billing_state();
            $billing_state = WC()->countries->get_states( $country )[$state];

            $data['delivery_address']      = $order->get_billing_address_1().', '.$order->get_billing_address_2().', '.$order->get_billing_city().', '.$billing_state.', '.$order->get_billing_postcode();
            $data['delivery_postal_code']  = $order->get_billing_postcode();

            $is_cod                        = false;
            if ($order->get_payment_method() == 'cod') {
                    $is_cod = true;
            }

            $data['is_cod']           = $is_cod;
            $data['cash_on_delivery'] = $is_cod ? $order->get_total() : 0;

            $data['source'] = 'woocommerce';


            $url = "https://blowhorn.com/api/orders/shipment";

            $args = array(
                'body'    => json_encode($data),
                'timeout' => '30',
                'headers' => $headers,
                'blocking' => true
            );
            $response = wp_remote_post( $url, $args );
            $output   = wp_remote_retrieve_body( $response );
            $output = json_decode( $output );

            if (isset($output->status) && $output->status == 'PASS') {
                $awb_number = $output->message->awb_number;
                update_post_meta($order_id, 'awb_number', stripslashes($awb_number));
                delete_post_meta($order_id, 'bh_reference_number');
                update_post_meta($order_id, 'bh_reference_number', stripslashes($reference_number));
            }

        }


        function blsdd_save_order_tracking_metabox($post_id)
        {
            $post_id = $post_id;
            $key     = 'bh_reference_number';
            $single  = TRUE;
            if (get_post_meta($post_id, $key, $single)) {

                $a = get_option('my_option_name');

            } else {

                $a = get_option('my_option_name');

                if (isset($_POST['bh_reference_number']) && $_POST['bh_reference_number'] != '') {
                    $data     = array();
                    $order_no = get_post_meta(get_the_ID(),'_order_number_formatted',true);

					if (empty($order_no)) {
					    $orderid = absint( $_POST['ID'] );
					} else {
					    $orderid = $order_no;
					}

                    $firstname = sanitize_text_field( $_POST['_billing_first_name'] );
                    $lastname = sanitize_text_field( $_POST['_billing_last_name'] );
                    $customer_name = $firstname . ' '. $lastname;
                    $data['customer_name']    = $customer_name;

                    $reference_number = sanitize_key( $_POST['bh_reference_number'] );

                    $data['customer_email']   = sanitize_email( $_POST['_billing_email'] );
                    $data['customer_mobile']  = wc_sanitize_phone_number( $_POST['_billing_phone'] );

                    $data['reference_number'] = $reference_number;

                    $headers =  array(
                                    'api_key'      => $a['id_number'],
                                    'Content-Type' => 'application/json'
                                );

                    $order = new WC_Order($_POST['ID']);
                    $items = $order->get_items();

                    $data['item_details'] = array();

                    $products = array();

                    foreach ($items as $key => $product) {
                        $products['item_name']                = $product['name'];
                        $products['item_price_per_each']      = $product['line_total'] / $product['qty'];
                        $products['item_quantity']            = $product['qty'];
                        $products['total_item_price']         = $product['line_total'];
                        $products['weight']                   = $product['weight'];
                        $products['volume']                   = $product['volume'];
                        $products['brand']                    = $product['brand'];
                        $products['length']                   = $product['length'];
                        $products['breadth']                  = $product['breadth'];
                        $products['height']                   = $product['height'];
                        $products['item_category']            = $product['item_category'];

                        array_push($data['item_details'], $products);
                    }

//                     $data['order_creation_time']   = date_format(date_create($_POST['order_date']), 'c');

                    $country           = sanitize_text_field( $_POST['_billing_country'] );
                    $state             = sanitize_text_field( $_POST['_billing_state'] );
                    $billing_state     = WC()->countries->get_states( $country )[$state];
                    $billing_address_1 = sanitize_text_field( $_POST['_billing_address_1'] );
                    $billing_address_2 = sanitize_text_field( $_POST['_billing_address_2'] );
                    $billing_city      = sanitize_text_field( $_POST['_billing_city'] );
                    $billing_postcode  = sanitize_text_field( $_POST['_billing_postcode'] );

                    $data['delivery_address']      = $billing_address_1.', '.$billing_address_2.', '.$billing_city.', '.$billing_state.', '.$billing_postcode;
                    $data['delivery_postal_code']  = $billing_postcode;

                    $is_cod                        = false;
                    if ($_POST['_payment_method'] == 'cod') {
                        $is_cod = true;
                    }

                    $data['is_cod']           = $is_cod;
                    $data['cash_on_delivery'] = $is_cod ? $order->get_total() : 0;

					$data['source'] = 'woocommerce';

                    $url = "https://blowhorn.com/api/orders/shipment";

                    $args = array(
                        'body'    => json_encode($data),
                        'timeout' => '30',
                        'headers' => $headers,
                        'blocking' => true
                    );
                    $response = wp_remote_post( $url, $args );
                    $output   = wp_remote_retrieve_body( $response );
                    $output   = json_decode( $output );

                    if (isset($output->status) && $output->status == 'PASS') {
                        $awb_number = $output->message->awb_number;
                        update_post_meta($post_id, 'awb_number', stripslashes($awb_number));

                        if (isset($_POST['bh_reference_number'])) {
				        	delete_post_meta($post_id, 'bh_reference_number');
					        add_post_meta($post_id, 'bh_reference_number', $reference_number);
				        }
                    }
                    else {
                        $message = $output->message;
                        $_SESSION['my_admin_notices'] = "<div class='error'>
                                                            <p>Order Tracking Error: {$message}</p></div>";
                    }

                }

            }

        }

        public function blsdd_add_plugin_page()
        {
            add_menu_page('Blowhorn', 'Blowhorn', 'manage_options', 'my-setting-admin', array(
                $this,
                'blsdd_create_admin_page'
            ), '', 20);


        }

        /**
         * Options page callback
         */
        public function blsdd_create_admin_page()
        {

            // Set class property
            $this->options = get_option('my_option_name');
?>
        <div class="wrap">
            <h2>Blowhorn</h2>

            <form method="post" id="form1"  action="options.php">

            <?php
            // This prints out all hidden setting fields
            settings_fields('my_option_group');
            do_settings_sections('my-setting-admin');
            submit_button();
?>

            </form>

        </div>
        <?php
        }

        /**
         * Register and add settings
         */
        public function blsdd_page_init()
        {
            register_setting('my_option_group', // Option group
                'my_option_name', // Option name
                array(
                $this,
                'blsdd_sanitize'
            ) // Sanitize
                );

            add_settings_section('setting_section_id', // ID
                '', // Title
                array(
                    $this,
                    'blsdd_display_contact'
                ), // Callback
                    'my-setting-admin' // Page
            );

            add_settings_field('id_number', // ID
                'API Key', // Title
                array(
                    $this,
                    'blsdd_id_number_callback'
                ), // Callback
                    'my-setting-admin', // Page
                    'setting_section_id' // Section
            );

            add_settings_field('auto_push', // ID
                'Auto push orders to Blowhorn', // Title
                array(
                    $this,
                    'blsdd_auto_push_callback'
                ), // Callback
                    'my-setting-admin', // Page
                    'setting_section_id' // Section
            );

            add_settings_field('validate_pincode', // ID
                'Validate pincodes when placing orders', // Title
                array(
                    $this,
                    'blsdd_validate_pincode_callback'
                ), // Callback
                    'my-setting-admin', // Page
                    'setting_section_id' // Section
            );

        }

        /**
         * Sanitize each setting field as needed
         *
         * @param array $input Contains all settings fields as array keys
         */
        public function blsdd_sanitize($input)
        {
            $new_input = array();
            if ($_REQUEST['submit']) {

                $api_key = $input['id_number'];
                $auto_push = isset($input['auto_push']) ? true : false;
                $validate_pincode = isset($input['validate_pincode']) ? true : false;

                if (isset($input['id_number'])) {
                    $new_input['id_number'] = $input['id_number'];

                    if (isset($input['auto_push']))
                        $new_input['auto_push'] = $input['auto_push'];

                    if (isset($input['validate_pincode']))
                        $new_input['validate_pincode'] = $input['validate_pincode'];

                    global $wpdb;
                    $table_name = $wpdb->prefix.'bh_logistics';
                    $wpdb->query('DELETE  FROM ' . $table_name);
                    $wpdb->insert($table_name, array(
                        'api_key'          => $api_key,
                        'auto_push'        => $auto_push,
                        'validate_pincode' => $validate_pincode
                    ));
                }
            }

            return $new_input;
        }

        /**
         * Print the Section text
         */
        public function blsdd_display_contact()
        {
            print '<td colspan="2">
					<p> Please contact <u style="color: #00D2F6">process@blowhorn.net</u> for the API key.</p></td>';
        }


        /**
         * Get the settings option array and print one of its values
         */
        public function blsdd_id_number_callback()

        {
            printf('<input type="text" id="id_number" name="my_option_name[id_number]" value="%s"  required="required"/>', isset($this->options['id_number']) ? esc_attr($this->options['id_number']) : '');
        }

        public function blsdd_auto_push_callback()

        {
            printf('<input type="checkbox" id="auto_push" name="my_option_name[auto_push]" value="1"' .checked( 1, $this->options['auto_push'], false ) . '/>');
        }

        public function blsdd_validate_pincode_callback()

        {
            printf('<input type="checkbox" id="validate_pincode" name="my_option_name[validate_pincode]" value="1"' .checked( 1, $this->options['validate_pincode'], false ) . '/>');
        }

    }
}