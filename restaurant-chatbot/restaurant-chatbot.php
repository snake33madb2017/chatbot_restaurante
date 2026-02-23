<?php
/**
 * Plugin Name: Restaurant Chatbot
 * Description: Un chatbot digital para hostelerÃ­a con menÃº de navegaciÃ³n, disparadores automÃ¡ticos, galerÃ­a de imÃ¡genes y captura de leads.
 * Version: 1.0
 * Author: Marco Daza
 */

if (!defined('ABSPATH')) {
    exit; // Exit si se accede directamente.
}

// Definir rutas del plugin
if (!defined('REST_CHATBOT_PATH')) {
    define('REST_CHATBOT_PATH', plugin_dir_path(__FILE__));
}
if (!defined('REST_CHATBOT_URL')) {
    define('REST_CHATBOT_URL', plugin_dir_url(__FILE__));
}

// Incluir el manejador AJAX y ConfiguraciÃ³n de Admin
require_once REST_CHATBOT_PATH . 'includes/ajax-handler.php';
require_once REST_CHATBOT_PATH . 'includes/admin-settings.php';
require_once REST_CHATBOT_PATH . 'includes/frontend-admin.php';

// ActivaciÃ³n del plugin: Crear tabla de pedidos
register_activation_hook(__FILE__, 'rest_chatbot_create_db_table');
function rest_chatbot_create_db_table()
{
    global $wpdb;

    $table_name = $wpdb->prefix . 'rest_chatbot_orders';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        order_number mediumint(9) NOT NULL,
        customer_name varchar(255) NOT NULL,
        customer_phone varchar(50) NOT NULL,
        customer_address text NOT NULL,
        order_items text NOT NULL,
        order_total decimal(10,2) NOT NULL,
        payment_method varchar(50) NOT NULL,
        customer_preferences text NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Encolar Scripts y Estilos
if (!function_exists('rest_chatbot_enqueue_assets')) {
    add_action('wp_enqueue_scripts', 'rest_chatbot_enqueue_assets');
    function rest_chatbot_enqueue_assets()
    {
        $version = (string) time(); // Para evitar la cachÃ© de navegador
        wp_enqueue_style('rest-chatbot-style', REST_CHATBOT_URL . 'assets/css/chatbot.css', array(), $version);
        wp_enqueue_script('rest-chatbot-script', REST_CHATBOT_URL . 'assets/js/chatbot.js', array('jquery'), $version, true);

        // Fetch options from DB
        $options = get_option('rest_chatbot_options');

        $whatsapp = isset($options['whatsapp_number']) && !empty($options['whatsapp_number']) ? $options['whatsapp_number'] : "34643472485";

        $default_loc = "ðŸ“ Estamos ubicados en Gonzalez ZuÃ±iga, 3 Bajo\nPontevedra - Galicia.\n\nðŸ•’ Horario:\nMar-Dom: 13:00 - 16:00 y 20:00 - 23:30\nLunes cerrado.";
        $location = isset($options['location_info']) && !empty(trim($options['location_info'])) ? $options['location_info'] : $default_loc;

        $default_des = "Arepa con Huevos Pericos (6.00€)\nArepa con Queso o Jamón (3.50€) | Vars: Queso, Jamón\nArepa con Queso y Jamón (4.00€)\nPatacón con Huevos Pericos (6.00€)\nCalentao de Frijoles (Arroz, frijoles, chorizo y huevo) (8.00€)\nTamal (8.00€)\nMoñona (Arroz blanco, carne asada, huevos y patatas) (8.00€)\nHuevos al Gusto (4.00€)\nTostada de Pan con Huevos Revueltos (4.00€)\nTostada de Pan con Tomate y Aceite (3.50€)";
        $menu_des = isset($options['menu_desayunos']) && !empty(trim($options['menu_desayunos'])) ? $options['menu_desayunos'] : $default_des;

        $default_ent = "Patacón con Huevos Pericos (6.50€)\nPatacón con Carne Mechada y Queso (8.00€)\nPatacón con Pollo Desmechado y Queso (8.00€)\nPatacón Mixto (Carne y Pollo) (8.00€)\nPatacón con Chicharrón (8.00€)\nPatacón Especial (Carne, pollo, guacamole, chicharrón, queso) (10.00€)\nTacos 3 uds (9.00€) | Vars: Ternera, Pollo, Mixtos\nEmpanada de Carne (Patata y carne mechada) (1.70€)\nCombo 5 uds Empanada de Carne (7.50€)\nPastel de Yuca (Arroz, carne picada y huevo) (2.80€)\nPapa Rellena (Patata, carne mechada y huevo) (3.50€)\nNachos con Guacamole (6.00€)\nPapa Criolla con Chicharrón (8.00€)\nPapa Criolla (7.00€) | Vars: Morcilla, Chorizo\nEnsalada Natural (5.00€)";
        $menu_ent = isset($options['menu_entradas']) && !empty(trim($options['menu_entradas'])) ? $options['menu_entradas'] : $default_ent;

        $default_pla = "Bandeja Paisa (15.00€)\nBandeja Paisa para compartir (22.00€)\nPicada Criolla Personal (22.00€)\nFrijolada (12.00€)\nSancocho de Gallina (12.00€)\nArroz con Pollo (11.00€)\nSobrebarriga en Salsa (11.00€)\nFiletes (con arroz, ensalada y patatas) (8.50€) | Vars: Ternera, Cerdo, Pechuga\nMazorcada (Maíz, cerdo, queso y salsas) (8.50€)";
        $menu_pla = isset($options['menu_platos']) && !empty(trim($options['menu_platos'])) ? $options['menu_platos'] : $default_pla;

        $default_com = "Hamburguesa Sencilla (4.00€)\nHamburguesa Completa (5.00€)\nHamburguesa Bogotá (Carne, bacon, huevo, queso) (8.00€)\nSalchipapa Junior (5.00€)\nSalchipapa Especial (9.00€)\nSalchipapa Súper Especial (15.00€)\nHot Dog Sencillo (5.00€)\nHot Dog Especial (7.00€)\nSándwich Mixto (3.50€)\nSándwich Completo (4.00€)\nSándwich Completo + Huevo (4.50€)\nBocadillo (con queso) (5.00€) | Vars: Ternera, Pollo, Lomo, Bacon\nArepa con Carne o Pollo Mechado (7.00€) | Vars: Carne, Pollo\nArepa Mixta (9.00€)\nArepa con Chorizo (7.00€)\nArepa con Chicharrón (8.00€)\nArepa Especial (Ternera, chicharrón, queso) (9.50€)\nArepa Paisa (Carne, frijol, aguacate, plátano, chicharrón) (11.00€)";
        $menu_com = isset($options['menu_comida']) && !empty(trim($options['menu_comida'])) ? $options['menu_comida'] : $default_com;

        $default_cmb = "Combo #1 (2 Empanadas + café grande) (4.50€)\nCombo #4 (4 Buñuelos + 2 cafés grandes) (8.90€)\nCombo #7 (Hamburguesa completa + patatas + refresco) (9.90€)\nCombo #8 (Tacos + refresco) (10.90€) | Vars: Pollo, Ternera";
        $menu_cmb = isset($options['menu_combos']) && !empty(trim($options['menu_combos'])) ? $options['menu_combos'] : $default_cmb;

        $default_bar = "Cerveza Caña Mahou (2.60€)\nCerveza 1906 / Tostada (2.70€)\nCerveza Estrella Botella (2.80€)\nCopa de Vino (2.50€) | Vars: Blanco, Tinto\nBotella de Vino (13.00€)\nCóctel (7.00€) | Vars: Michelada, Mojito, Margarita, Daiquiri\nChupito (2.00€) | Vars: Ron, Whisky, Ginebra, Aguardiente\nCopa de Licor (4.50€) | Vars: Ron, Whisky, Ginebra, Aguardiente\nCombinado (6.00€) | Vars: Ron, Whisky, Ginebra, Aguardiente";
        $menu_bar = isset($options['menu_bar']) && !empty(trim($options['menu_bar'])) ? $options['menu_bar'] : $default_bar;

        // Payment Options
        $payment_tarjeta = isset($options['payment_tarjeta']) ? $options['payment_tarjeta'] : 1;
        $payment_bizum = isset($options['payment_bizum']) ? $options['payment_bizum'] : 1;
        $payment_efectivo = isset($options['payment_efectivo']) ? $options['payment_efectivo'] : 1;
        $delivery_fee = isset($options['delivery_fee']) ? floatval(str_replace(',', '.', $options['delivery_fee'])) : 4.00;

        // Pasar URL de AJAX y Opciones DinÃ¡micas al script JS
        wp_localize_script('rest-chatbot-script', 'restChatbotObj', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'whatsapp_number' => $whatsapp,
            'location_info' => $location,
            'menu_desayunos' => $menu_des,
            'menu_entradas' => $menu_ent,
            'menu_platos' => $menu_pla,
            'menu_comida' => $menu_com,
            'menu_combos' => $menu_cmb,
            'menu_bar' => $menu_bar,
            'payment_tarjeta' => $payment_tarjeta,
            'payment_bizum' => $payment_bizum,
            'payment_efectivo' => $payment_efectivo,
            'delivery_fee' => $delivery_fee
        ));
    }
}

// Imprimir HTML del Chatbot en el Footer
if (!function_exists('rest_chatbot_render_html')) {
    add_action('wp_footer', 'rest_chatbot_render_html');
    function rest_chatbot_render_html()
    {
        $options = get_option('rest_chatbot_options');
        $default_logo = 'https://barbogotaperegrina.es/wp-content/uploads/2026/02/chatbot.png';
        $logo_url = isset($options['chatbot_logo']) && !empty($options['chatbot_logo']) ? $options['chatbot_logo'] : $default_logo;
        ?>
        <div id="rest-chatbot-wrapper">
            <div id="rest-chatbot-toggle" aria-label="Abrir chat">
                <img src="<?php echo esc_url($logo_url); ?>" alt="Logo" class="chat-logo">
            </div>
            <div id="rest-chatbot-window" class="hidden">
                <div class="chatbot-header">
                    <span class="chatbot-title">Asistente Virtual</span>
                    <div class="chatbot-header-actions" style="display:flex; gap:8px;">
                        <button id="rest-chatbot-restart" title="Vaciar pedido y reiniciar chat"
                            style="background:none; border:none; color:white; font-size:18px; cursor:pointer;"
                            aria-label="Reiniciar chat">🗑️</button>
                        <button id="rest-chatbot-close" title="Minimizar chat">&times;</button>
                    </div>
                </div>
                <div class="chatbot-messages" id="chatbot-messages"></div>
                <div class="chatbot-input-area hidden" id="chatbot-input-area">
                    <input type="text" id="chatbot-input" placeholder="Escribe tu mensaje..." autocomplete="off">
                    <button id="chatbot-send">Enviar</button>
                </div>
            </div>
        </div>
        <?php
    }
}

