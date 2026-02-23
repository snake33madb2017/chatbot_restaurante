<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('RestChatbotSettings')) {
    class RestChatbotSettings
    {
        public function __construct()
        {
            add_action('admin_menu', array($this, 'add_plugin_page'));
            add_action('admin_init', array($this, 'page_init'));
        }

        public function add_plugin_page()
        {
            // Pagina de configuración en Ajustes
            add_menu_page(
                'Chatbot Restaurante',
                'Chatbot',
                'manage_options',
                'rest-chatbot',
                array($this, 'create_admin_page'),
                'dashicons-format-chat',
                30
            );

            // Submenú: Preferencias y Cartas
            add_submenu_page(
                'rest-chatbot',
                'Ajustes Chatbot',
                'Ajustes',
                'manage_options',
                'rest-chatbot',
                array($this, 'create_admin_page') // usa la misma
            );

            // Submenú: Ver Pedidos
            add_submenu_page(
                'rest-chatbot',
                'Lista de Pedidos',
                'Pedidos',
                'manage_options',
                'rest-chatbot-orders',
                array($this, 'create_orders_page')
            );
        }

        public function create_admin_page()
        {
            ?>
            <div class="wrap">
                <h1>Ajustes de Chatbot del Restaurante</h1>
                <p>Configura las opciones de tu asistente virtual aquí. Separa cada plato del menú con una nueva línea (presionando
                    Enter).</p>
                <form method="post" action="options.php"> <!-- ¡No cambies options.php! Es nativo de WP -->
                    <?php
                    settings_fields('rest_chatbot_option_group');
                    do_settings_sections('rest-chatbot-admin');
                    submit_button('Guardar Cambios');
                    ?>
                </form>
            </div>
            <?php
        }

        public function create_orders_page()
        {
            global $wpdb;
            $table_name = $wpdb->prefix . 'rest_chatbot_orders';

            // Comprobar que la tabla existe
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
                echo '<div class="wrap"><h1>Pedidos del Chatbot</h1><p>La tabla de pedidos no existe aún. Por favor desactiva y vuelve a activar el plugin.</p></div>';
                return;
            }

            // Paginación simple
            $per_page = 20;
            $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
            $offset = ($page - 1) * $per_page;

            $total_orders = $wpdb->get_var("SELECT COUNT(id) FROM $table_name");
            $orders = $wpdb->get_results(
                $wpdb->prepare("SELECT * FROM $table_name ORDER BY created_at DESC LIMIT %d OFFSET %d", $per_page, $offset)
            );
            $total_pages = ceil($total_orders / $per_page);
            ?>
            <div class="wrap">
                <h1 class="wp-heading-inline">Pedidos del Chatbot</h1>
                <a href="<?php echo esc_url(admin_url('admin.php?page=rest-chatbot-orders&action=export_csv')); ?>"
                    class="page-title-action">Exportar a CSV</a>
                <hr class="wp-header-end">

                <table class="wp-list-table widefat fixed striped table-view-list">
                    <thead>
                        <tr>
                            <th scope="col" class="manage-column column-primary">Nº Pedido</th>
                            <th scope="col" class="manage-column">Fecha</th>
                            <th scope="col" class="manage-column">Cliente</th>
                            <th scope="col" class="manage-column">Teléfono / Dirección</th>
                            <th scope="col" class="manage-column">Pedido</th>
                            <th scope="col" class="manage-column">Preferencias</th>
                            <th scope="col" class="manage-column">Total</th>
                            <th scope="col" class="manage-column">Método de Pago</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($orders): ?>
                            <?php foreach ($orders as $order): ?>
                                <?php
                                $items = json_decode($order->order_items, true);
                                $items_str = is_array($items) ? implode(', ', $items) : $order->order_items;
                                ?>
                                <tr>
                                    <td class="column-primary" data-colname="Nº Pedido">
                                        <strong>#<?php echo esc_html($order->order_number); ?></strong>
                                    </td>
                                    <td data-colname="Fecha"><?php echo esc_html($order->created_at); ?></td>
                                    <td data-colname="Cliente"><?php echo esc_html($order->customer_name); ?></td>
                                    <td data-colname="Teléfono / Dirección">
                                        📞 <?php echo esc_html($order->customer_phone); ?><br>
                                        📍 <?php echo esc_html($order->customer_address); ?>
                                    </td>
                                    <td data-colname="Pedido"><?php echo esc_html($items_str); ?></td>
                                    <td data-colname="Preferencias"><?php echo esc_html($order->customer_preferences); ?></td>
                                    <td data-colname="Total">
                                        <strong><?php echo esc_html(number_format($order->order_total, 2, ',', '.')); ?>€</strong>
                                    </td>
                                    <td data-colname="Método de Pago"><?php echo esc_html($order->payment_method); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8">No hay pedidos registrados todavía.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <?php if ($total_pages > 1): ?>
                    <div class="tablenav bottom">
                        <div class="tablenav-pages">
                            <span class="pagination-links">
                                <?php
                                $page_links = paginate_links(array(
                                    'base' => add_query_arg('paged', '%#%'),
                                    'format' => '',
                                    'prev_text' => '&laquo;',
                                    'next_text' => '&raquo;',
                                    'total' => $total_pages,
                                    'current' => $page
                                ));
                                echo $page_links;
                                ?>
                            </span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <?php
        }

        public function page_init()
        {
            // Registrar los campos
            register_setting(
                'rest_chatbot_option_group', // Option group
                'rest_chatbot_options', // Option name (el arreglo en DB)
                array($this, 'sanitize') // Sanitize callback
            );

            // Sección General
            add_settings_section(
                'rest_chatbot_general_section', // ID
                'Configuración General', // Title
                array($this, 'print_general_section_info'), // Callback
                'rest-chatbot-admin' // Page
            );

            add_settings_field(
                'whatsapp_number', // ID
                'Número de WhatsApp', // Title 
                array($this, 'whatsapp_number_callback'), // Callback
                'rest-chatbot-admin', // Page
                'rest_chatbot_general_section' // Section           
            );

            add_settings_field(
                'chatbot_logo',
                'URL del Logo del Chatbot',
                array($this, 'chatbot_logo_callback'),
                'rest-chatbot-admin',
                'rest_chatbot_general_section'
            );

            add_settings_field(
                'location_info',
                'Información / Horarios',
                array($this, 'location_info_callback'),
                'rest-chatbot-admin',
                'rest_chatbot_general_section'
            );

            // Nuevos campos de pago
            add_settings_field(
                'payment_tarjeta',
                'Activar Pago con Tarjeta',
                array($this, 'payment_tarjeta_callback'),
                'rest-chatbot-admin',
                'rest_chatbot_general_section'
            );

            add_settings_field(
                'payment_bizum',
                'Activar Pago con Bizum',
                array($this, 'payment_bizum_callback'),
                'rest-chatbot-admin',
                'rest_chatbot_general_section'
            );

            add_settings_field(
                'payment_efectivo',
                'Activar Pago en Efectivo',
                array($this, 'payment_efectivo_callback'),
                'rest-chatbot-admin',
                'rest_chatbot_general_section'
            );

            add_settings_field(
                'delivery_fee',
                'Costo Domicilio (€)',
                array($this, 'delivery_fee_callback'),
                'rest-chatbot-admin',
                'rest_chatbot_general_section'
            );

            add_settings_field(
                'order_counter',
                'Número de Pedido Actual',
                array($this, 'order_counter_callback'),
                'rest-chatbot-admin',
                'rest_chatbot_general_section'
            );

            // Sección Menú
            add_settings_section(
                'rest_chatbot_menu_section', // ID
                'Configuración de la Carta (Platos)', // Title
                array($this, 'print_menu_section_info'), // Callback
                'rest-chatbot-admin' // Page
            );

            add_settings_field(
                'menu_desayunos',
                'Menú: Desayunos',
                array($this, 'menu_desayunos_callback'),
                'rest-chatbot-admin',
                'rest_chatbot_menu_section'
            );

            add_settings_field(
                'menu_entradas',
                'Menú: Entradas',
                array($this, 'menu_entradas_callback'),
                'rest-chatbot-admin',
                'rest_chatbot_menu_section'
            );

            add_settings_field(
                'menu_platos',
                'Menú: Platos Especiales',
                array($this, 'menu_platos_callback'),
                'rest-chatbot-admin',
                'rest_chatbot_menu_section'
            );

            add_settings_field(
                'menu_comida',
                'Menú: Comida Rápida y Arepas',
                array($this, 'menu_comida_callback'),
                'rest-chatbot-admin',
                'rest_chatbot_menu_section'
            );

            add_settings_field(
                'menu_combos',
                'Menú: Combos',
                array($this, 'menu_combos_callback'),
                'rest-chatbot-admin',
                'rest_chatbot_menu_section'
            );

            add_settings_field(
                'menu_bar',
                'Menú: Bar (Bebidas)',
                array($this, 'menu_bar_callback'),
                'rest-chatbot-admin',
                'rest_chatbot_menu_section'
            );
        }

        public function sanitize($input)
        {
            $sanitized_input = array();

            if (isset($input['whatsapp_number']))
                $sanitized_input['whatsapp_number'] = sanitize_text_field($input['whatsapp_number']);

            if (isset($input['chatbot_logo']))
                $sanitized_input['chatbot_logo'] = esc_url_raw($input['chatbot_logo']);

            if (isset($input['location_info']))
                $sanitized_input['location_info'] = sanitize_textarea_field($input['location_info']);

            $sanitized_input['payment_tarjeta'] = isset($input['payment_tarjeta']) ? 1 : 0;
            $sanitized_input['payment_bizum'] = isset($input['payment_bizum']) ? 1 : 0;
            $sanitized_input['payment_efectivo'] = isset($input['payment_efectivo']) ? 1 : 0;

            if (isset($input['delivery_fee'])) {
                $sanitized_input['delivery_fee'] = floatval(str_replace(',', '.', $input['delivery_fee']));
            }

            if (isset($input['order_counter'])) {
                $val = intval($input['order_counter']);
                // Limitar de 1 a 10000
                if ($val < 1 || $val > 10000) {
                    $val = 1;
                }
                $sanitized_input['order_counter'] = $val;
            }

            if (isset($input['menu_desayunos']))
                $sanitized_input['menu_desayunos'] = sanitize_textarea_field($input['menu_desayunos']);

            if (isset($input['menu_entradas']))
                $sanitized_input['menu_entradas'] = sanitize_textarea_field($input['menu_entradas']);

            if (isset($input['menu_platos']))
                $sanitized_input['menu_platos'] = sanitize_textarea_field($input['menu_platos']);

            if (isset($input['menu_comida']))
                $sanitized_input['menu_comida'] = sanitize_textarea_field($input['menu_comida']);

            if (isset($input['menu_combos']))
                $sanitized_input['menu_combos'] = sanitize_textarea_field($input['menu_combos']);

            if (isset($input['menu_bar']))
                $sanitized_input['menu_bar'] = sanitize_textarea_field($input['menu_bar']);

            return $sanitized_input;
        }

        public function print_general_section_info()
        {
            echo 'Ingresa los datos básicos para que el bot pueda atender a los clientes adecuadamente.';
        }

        public function print_menu_section_info()
        {
            echo 'Escribe un plato por línea usando este formato si deseas opciones extras:<br><code>Nachos con Guacamole (8,00€) | Vars: Ternera, Pollo | Extras: Guacamole, Aji</code><br>Si no tiene opciones solo usa: <code>Plato de Patatas (4,00€)</code>';
        }

        // --- Callbacks de Campos ---

        public function whatsapp_number_callback()
        {
            $options = get_option('rest_chatbot_options');
            // Hardcode fallback si no hay nada guardado todavía
            $val = isset($options['whatsapp_number']) ? esc_attr($options['whatsapp_number']) : '34643472485';
            printf(
                '<input type="text" id="whatsapp_number" name="rest_chatbot_options[whatsapp_number]" value="%s" class="regular-text" placeholder="Ej: +3412345678" />',
                $val
            );
        }

        public function chatbot_logo_callback()
        {
            $options = get_option('rest_chatbot_options');
            $default_logo = 'https://barbogotaperegrina.es/wp-content/uploads/2026/02/chatbot.png';
            $val = isset($options['chatbot_logo']) && !empty($options['chatbot_logo']) ? esc_url($options['chatbot_logo']) : $default_logo;
            printf(
                '<input type="url" id="chatbot_logo" name="rest_chatbot_options[chatbot_logo]" value="%s" class="large-text" placeholder="https://barbogotaperegrina.es/wp-content/uploads/2026/02/chatbot.png" /><p class="description">Pega aquí la URL de la imagen que quieres usar como logo (ej. súbela a la biblioteca de medios de WP y pega el enlace). Si lo dejas vacío, se usará el logo por defecto.</p>',
                $val
            );
        }

        public function location_info_callback()
        {
            $options = get_option('rest_chatbot_options');
            $default_loc = "📍 Estamos ubicados en Gonzalez Zuñiga, 3 Bajo\nPontevedra - Galicia.\n\n🕒 Horario:\nMar-Dom: 13:00 - 16:00 y 20:00 - 23:30\nLunes cerrado.";
            $val = isset($options['location_info']) ? esc_textarea($options['location_info']) : esc_textarea($default_loc);
            printf(
                '<textarea id="location_info" name="rest_chatbot_options[location_info]" rows="5" cols="50">%s</textarea>',
                $val
            );
        }

        public function payment_tarjeta_callback()
        {
            $options = get_option('rest_chatbot_options');
            $checked = isset($options['payment_tarjeta']) && $options['payment_tarjeta'] == 1 ? 'checked' : '';
            // Por defecto activo si no existe (primera carga)
            if (!isset($options['payment_tarjeta']))
                $checked = 'checked';

            echo '<input type="checkbox" id="payment_tarjeta" name="rest_chatbot_options[payment_tarjeta]" value="1" ' . $checked . '/>';
        }

        public function payment_bizum_callback()
        {
            $options = get_option('rest_chatbot_options');
            $checked = isset($options['payment_bizum']) && $options['payment_bizum'] == 1 ? 'checked' : '';
            if (!isset($options['payment_bizum']))
                $checked = 'checked';

            echo '<input type="checkbox" id="payment_bizum" name="rest_chatbot_options[payment_bizum]" value="1" ' . $checked . '/>';
        }

        public function payment_efectivo_callback()
        {
            $options = get_option('rest_chatbot_options');
            $checked = isset($options['payment_efectivo']) && $options['payment_efectivo'] == 1 ? 'checked' : '';
            if (!isset($options['payment_efectivo']))
                $checked = 'checked';

            echo '<input type="checkbox" id="payment_efectivo" name="rest_chatbot_options[payment_efectivo]" value="1" ' . $checked . '/>';
        }

        public function delivery_fee_callback()
        {
            $options = get_option('rest_chatbot_options');
            // Por defecto 4€
            $val = isset($options['delivery_fee']) ? esc_attr($options['delivery_fee']) : '4.00';
            printf(
                '<input type="number" step="0.01" id="delivery_fee" name="rest_chatbot_options[delivery_fee]" value="%s" class="small-text" /> €',
                $val
            );
        }

        public function order_counter_callback()
        {
            $options = get_option('rest_chatbot_options');
            // Por defecto 1
            $val = isset($options['order_counter']) ? esc_attr($options['order_counter']) : '1';
            printf(
                '<input type="number" step="1" min="1" max="10000" id="order_counter" name="rest_chatbot_options[order_counter]" value="%s" class="regular-text" /> <span class="description">Puedes editarlo para reiniciar el contador manualmente (máx: 10000).</span>',
                $val
            );
        }

        // Helper para los defaults del menu
        private function get_menu_default($key)
        {
            if ($key === 'desayunos') {
                return "Arepa con Huevos Pericos (6.00€)\nArepa con Queso o Jamón (3.50€) | Vars: Queso, Jamón\nArepa con Queso y Jamón (4.00€)\nPatacón con Huevos Pericos (6.00€)\nCalentao de Frijoles (Arroz, frijoles, chorizo y huevo) (8.00€)\nTamal (8.00€)\nMoñona (Arroz blanco, carne asada, huevos y patatas) (8.00€)\nHuevos al Gusto (4.00€)\nTostada de Pan con Huevos Revueltos (4.00€)\nTostada de Pan con Tomate y Aceite (3.50€)";
            }
            if ($key === 'entradas') {
                return "Patacón con Huevos Pericos (6.50€)\nPatacón con Carne Mechada y Queso (8.00€)\nPatacón con Pollo Desmechado y Queso (8.00€)\nPatacón Mixto (Carne y Pollo) (8.00€)\nPatacón con Chicharrón (8.00€)\nPatacón Especial (Carne, pollo, guacamole, chicharrón, queso) (10.00€)\nTacos 3 uds (9.00€) | Vars: Ternera, Pollo, Mixtos\nEmpanada de Carne (Patata y carne mechada) (1.70€)\nCombo 5 uds Empanada de Carne (7.50€)\nPastel de Yuca (Arroz, carne picada y huevo) (2.80€)\nPapa Rellena (Patata, carne mechada y huevo) (3.50€)\nNachos con Guacamole (6.00€)\nPapa Criolla con Chicharrón (8.00€)\nPapa Criolla (7.00€) | Vars: Morcilla, Chorizo\nEnsalada Natural (5.00€)";
            }
            if ($key === 'platos') {
                return "Bandeja Paisa (15.00€)\nBandeja Paisa para compartir (22.00€)\nPicada Criolla Personal (22.00€)\nFrijolada (12.00€)\nSancocho de Gallina (12.00€)\nArroz con Pollo (11.00€)\nSobrebarriga en Salsa (11.00€)\nFiletes (con arroz, ensalada y patatas) (8.50€) | Vars: Ternera, Cerdo, Pechuga\nMazorcada (Maíz, cerdo, queso y salsas) (8.50€)";
            }
            if ($key === 'comida') {
                return "Hamburguesa Sencilla (4.00€)\nHamburguesa Completa (5.00€)\nHamburguesa Bogotá (Carne, bacon, huevo, queso) (8.00€)\nSalchipapa Junior (5.00€)\nSalchipapa Especial (9.00€)\nSalchipapa Súper Especial (15.00€)\nHot Dog Sencillo (5.00€)\nHot Dog Especial (7.00€)\nSándwich Mixto (3.50€)\nSándwich Completo (4.00€)\nSándwich Completo + Huevo (4.50€)\nBocadillo (con queso) (5.00€) | Vars: Ternera, Pollo, Lomo, Bacon\nArepa con Carne o Pollo Mechado (7.00€) | Vars: Carne, Pollo\nArepa Mixta (9.00€)\nArepa con Chorizo (7.00€)\nArepa con Chicharrón (8.00€)\nArepa Especial (Ternera, chicharrón, queso) (9.50€)\nArepa Paisa (Carne, frijol, aguacate, plátano, chicharrón) (11.00€)";
            }
            if ($key === 'combos') {
                return "Combo #1 (2 Empanadas + café grande) (4.50€)\nCombo #4 (4 Buñuelos + 2 cafés grandes) (8.90€)\nCombo #7 (Hamburguesa completa + patatas + refresco) (9.90€)\nCombo #8 (Tacos + refresco) (10.90€) | Vars: Pollo, Ternera";
            }
            if ($key === 'bar') {
                return "Cerveza Caña Mahou (2.60€)\nCerveza 1906 / Tostada (2.70€)\nCerveza Estrella Botella (2.80€)\nCopa de Vino (2.50€) | Vars: Blanco, Tinto\nBotella de Vino (13.00€)\nCóctel (7.00€) | Vars: Michelada, Mojito, Margarita, Daiquiri\nChupito (2.00€) | Vars: Ron, Whisky, Ginebra, Aguardiente\nCopa de Licor (4.50€) | Vars: Ron, Whisky, Ginebra, Aguardiente\nCombinado (6.00€) | Vars: Ron, Whisky, Ginebra, Aguardiente";
            }
            return "";
        }

        public function menu_desayunos_callback()
        {
            $options = get_option('rest_chatbot_options');
            $val = isset($options['menu_desayunos']) ? esc_textarea($options['menu_desayunos']) : esc_textarea($this->get_menu_default('desayunos'));
            printf('<textarea id="menu_desayunos" name="rest_chatbot_options[menu_desayunos]" rows="8" cols="100">%s</textarea>', $val);
        }

        public function menu_entradas_callback()
        {
            $options = get_option('rest_chatbot_options');
            $val = isset($options['menu_entradas']) ? esc_textarea($options['menu_entradas']) : esc_textarea($this->get_menu_default('entradas'));
            printf('<textarea id="menu_entradas" name="rest_chatbot_options[menu_entradas]" rows="8" cols="100">%s</textarea>', $val);
        }

        public function menu_platos_callback()
        {
            $options = get_option('rest_chatbot_options');
            $val = isset($options['menu_platos']) ? esc_textarea($options['menu_platos']) : esc_textarea($this->get_menu_default('platos'));
            printf('<textarea id="menu_platos" name="rest_chatbot_options[menu_platos]" rows="8" cols="100">%s</textarea>', $val);
        }

        public function menu_comida_callback()
        {
            $options = get_option('rest_chatbot_options');
            $val = isset($options['menu_comida']) ? esc_textarea($options['menu_comida']) : esc_textarea($this->get_menu_default('comida'));
            printf('<textarea id="menu_comida" name="rest_chatbot_options[menu_comida]" rows="8" cols="100">%s</textarea>', $val);
        }

        public function menu_combos_callback()
        {
            $options = get_option('rest_chatbot_options');
            $val = isset($options['menu_combos']) ? esc_textarea($options['menu_combos']) : esc_textarea($this->get_menu_default('combos'));
            printf('<textarea id="menu_combos" name="rest_chatbot_options[menu_combos]" rows="8" cols="100">%s</textarea>', $val);
        }

        public function menu_bar_callback()
        {
            $options = get_option('rest_chatbot_options');
            $val = isset($options['menu_bar']) ? esc_textarea($options['menu_bar']) : esc_textarea($this->get_menu_default('bar'));
            printf('<textarea id="menu_bar" name="rest_chatbot_options[menu_bar]" rows="8" cols="100">%s</textarea>', $val);
        }
    }

    if (is_admin()) {
        $rest_chatbot_settings = new RestChatbotSettings();
    }
}

// Interceptar la solicitud de exportar a CSV antes de que WP pinte los headers
add_action('admin_init', 'rest_chatbot_export_csv');
function rest_chatbot_export_csv()
{
    if (isset($_GET['page']) && $_GET['page'] === 'rest-chatbot-orders' && isset($_GET['action']) && $_GET['action'] === 'export_csv') {
        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos suficientes para acceder a esta página.');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'rest_chatbot_orders';

        // Set Headers para descarga CSV
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="pedidos_chatbot_' . date('Y-m-d') . '.csv"');

        // Abrir puntero de salida (output)
        $output = fopen('php://output', 'w');

        // Insertar cabeceras en español (UTF-8 BOM para Excel)
        fputs($output, "\xEF\xBB\xBF");
        fputcsv($output, array('Nº Pedido', 'Fecha y Hora', 'Cliente', 'Teléfono', 'Dirección', 'Platos y Totales', 'Preferencias/Alergias', 'Total a Pagar', 'Método de Pago'), ';');

        // Leer datos
        $orders = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC", ARRAY_A);

        if (!empty($orders)) {
            foreach ($orders as $order) {
                // Desglosar arreglo de items
                $items = json_decode($order['order_items'], true);
                $items_str = is_array($items) ? implode(' | ', $items) : $order['order_items'];

                $row = array(
                    $order['order_number'],
                    $order['created_at'],
                    $order['customer_name'],
                    $order['customer_phone'],
                    $order['customer_address'],
                    $items_str,
                    $order['customer_preferences'],
                    number_format($order['order_total'], 2, ',', '.') . ' €',
                    $order['payment_method']
                );
                fputcsv($output, $row, ';'); // Usamos punto y coma para que Excel (España) lo abra correctamente en columnas por defecto
            }
        }

        fclose($output);
        exit;
    }
}
