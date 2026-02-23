<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('rest_chatbot_handle_lead')) {
    add_action('wp_ajax_rest_chatbot_submit_lead', 'rest_chatbot_handle_lead');
    add_action('wp_ajax_nopriv_rest_chatbot_submit_lead', 'rest_chatbot_handle_lead');

    function rest_chatbot_handle_lead()
    {
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';

        if (empty($name) || empty($phone)) {
            wp_send_json_error(array('message' => 'Faltan datos.'));
        }

        wp_send_json_success(array('message' => 'Lead recibido correctamente.'));
    }
}

if (!function_exists('rest_chatbot_get_order_number')) {
    add_action('wp_ajax_rest_chatbot_get_order', 'rest_chatbot_get_order_number');
    add_action('wp_ajax_nopriv_rest_chatbot_get_order', 'rest_chatbot_get_order_number');

    function rest_chatbot_get_order_number()
    {
        global $wpdb;
        $options = get_option('rest_chatbot_options');
        $current_order = isset($options['order_counter']) ? intval($options['order_counter']) : 1;

        // El número a devolver es el actual
        $response_number = $current_order;

        // Incrementar para la próxima vez
        $next_order = $current_order + 1;
        if ($next_order > 10000) {
            $next_order = 1;
        }

        $options['order_counter'] = $next_order;
        update_option('rest_chatbot_options', $options);

        // Guardar el pedido en la base de datos
        $table_name = $wpdb->prefix . 'rest_chatbot_orders';

        // Si la tabla existe, insertamos los datos que vienen por POST
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {

            $customer_name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
            $customer_phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
            $customer_addr = isset($_POST['address']) ? sanitize_text_field($_POST['address']) : '';
            $items_json = isset($_POST['items']) ? sanitize_text_field(stripslashes($_POST['items'])) : '';
            $total = isset($_POST['total']) ? floatval($_POST['total']) : 0;
            $payment = isset($_POST['payment_method']) ? sanitize_text_field($_POST['payment_method']) : '';
            $cash = isset($_POST['cash_amount']) ? sanitize_text_field($_POST['cash_amount']) : '';
            $preferences = isset($_POST['preferences']) ? sanitize_textarea_field($_POST['preferences']) : '';

            if (!empty($cash)) {
                $payment .= " (Paga con: $cash)";
            }

            $wpdb->insert(
                $table_name,
                array(
                    'order_number' => $response_number,
                    'customer_name' => $customer_name,
                    'customer_phone' => $customer_phone,
                    'customer_address' => $customer_addr,
                    'order_items' => $items_json,
                    'order_total' => $total,
                    'payment_method' => $payment,
                    'customer_preferences' => $preferences,
                )
            );
        }

        wp_send_json_success(array('order_number' => $response_number));
    }
}
