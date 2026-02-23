<?php
/**
 * Frontend Admin Shortcode para Restaurant Chatbot.
 */

if (!defined('ABSPATH')) {
    exit; // Exit si se accede directamente.
}

// Registrar el shortcode
add_shortcode('chatbot_admin_menu', 'rest_chatbot_frontend_admin_shortcode');
function rest_chatbot_frontend_admin_shortcode($atts)
{
    // ATENCIÓN: Se ha comentado la restricción de administrador a petición del usuario
    // para que la página sea pública. Asegúrate de proteger la página con contraseña de WordPress.
    /*
    if (!current_user_can('manage_options')) {
        return '<p class="chatbot-admin-error">No tienes permisos para ver o editar esta sección.</p>';
    }
    */

    $message = '';

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rest_chatbot_frontend_admin_nonce'])) {
        if (wp_verify_nonce($_POST['rest_chatbot_frontend_admin_nonce'], 'rest_chatbot_update_menu')) {
            $options = get_option('rest_chatbot_options', array());

            // Actualizar si está definido
            if (isset($_POST['menu_desayunos'])) {
                $options['menu_desayunos'] = sanitize_textarea_field(wp_unslash($_POST['menu_desayunos']));
            }
            if (isset($_POST['menu_entradas'])) {
                $options['menu_entradas'] = sanitize_textarea_field(wp_unslash($_POST['menu_entradas']));
            }
            if (isset($_POST['menu_platos'])) {
                $options['menu_platos'] = sanitize_textarea_field(wp_unslash($_POST['menu_platos']));
            }
            if (isset($_POST['menu_comida'])) {
                $options['menu_comida'] = sanitize_textarea_field(wp_unslash($_POST['menu_comida']));
            }
            if (isset($_POST['menu_combos'])) {
                $options['menu_combos'] = sanitize_textarea_field(wp_unslash($_POST['menu_combos']));
            }
            if (isset($_POST['menu_bar'])) {
                $options['menu_bar'] = sanitize_textarea_field(wp_unslash($_POST['menu_bar']));
            }

            update_option('rest_chatbot_options', $options);
            $message = '<div class="chatbot-admin-notice success"><p> Menu actualizado correctamente!</p></div>';
        } else {
            $message = '<div class="chatbot-admin-notice error"><p>Error de seguridad. Por favor, intenta de nuevo.</p></div>';
        }
    }

    // Obtener opciones actuales
    $options = get_option('rest_chatbot_options', array());

    // Defaults helpers
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

    // Buffer output
    ob_start();
    ?>

    <div class="chatbot-frontend-admin-wrapper">
        <h3 class="chatbot-admin-title">Administracin de Carta (Chatbot)</h3>
        <?php echo $message; ?>
        <p class="chatbot-admin-desc">Escribe un plato por línea usando este formato si deseas opciones
            extras:<br><code>Nachos con Guacamole (8,00€) | Vars: Ternera, Pollo | Extras: Guacamole, Aji</code></p>

        <form method="POST" action="">
            <?php wp_nonce_field('rest_chatbot_update_menu', 'rest_chatbot_frontend_admin_nonce'); ?>

            <div class="chatbot-form-group">
                <label for="menu_desayunos"><strong>Menú: Desayunos</strong></label>
                <textarea id="menu_desayunos" name="menu_desayunos"
                    rows="8"><?php echo esc_textarea($menu_des); ?></textarea>
            </div>

            <div class="chatbot-form-group">
                <label for="menu_entradas"><strong>Menú: Entradas</strong></label>
                <textarea id="menu_entradas" name="menu_entradas" rows="8"><?php echo esc_textarea($menu_ent); ?></textarea>
            </div>

            <div class="chatbot-form-group">
                <label for="menu_platos"><strong>Menú: Platos Especiales</strong></label>
                <textarea id="menu_platos" name="menu_platos" rows="8"><?php echo esc_textarea($menu_pla); ?></textarea>
            </div>

            <div class="chatbot-form-group">
                <label for="menu_comida"><strong>Menú: Comida Rápida y Arepas</strong></label>
                <textarea id="menu_comida" name="menu_comida" rows="8"><?php echo esc_textarea($menu_com); ?></textarea>
            </div>

            <div class="chatbot-form-group">
                <label for="menu_combos"><strong>Menú: Combos</strong></label>
                <textarea id="menu_combos" name="menu_combos" rows="8"><?php echo esc_textarea($menu_cmb); ?></textarea>
            </div>

            <div class="chatbot-form-group">
                <label for="menu_bar"><strong>Menú: Bar (Bebidas)</strong></label>
                <textarea id="menu_bar" name="menu_bar" rows="8"><?php echo esc_textarea($menu_bar); ?></textarea>
            </div>

            <div class="chatbot-form-actions">
                <button type="submit" class="chatbot-btn-save">Guardar Cambios del Menǧ</button>
            </div>
        </form>
    </div>

    <style>
        .chatbot-frontend-admin-wrapper {
            background-color: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            max-width: 600px;
            margin: 20px 0;
            font-family: inherit;
        }

        .chatbot-admin-title {
            margin-top: 0;
            color: #333;
        }

        .chatbot-admin-desc {
            font-size: 14px;
            color: #666;
            margin-bottom: 20px;
        }

        .chatbot-form-group {
            margin-bottom: 15px;
        }

        .chatbot-form-group label {
            display: block;
            margin-bottom: 5px;
            color: #444;
        }

        .chatbot-form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-family: monospace;
            font-size: 14px;
            resize: vertical;
        }

        .chatbot-btn-save {
            background-color: #2DCA73;
            color: white;
            border: none;
            padding: 10px 20px;
            font-size: 16px;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.3s;
        }

        .chatbot-btn-save:hover {
            background-color: #24a25c;
        }

        .chatbot-admin-notice {
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
        }

        .chatbot-admin-notice.success {
            background-color: #e6f9ed;
            border: 1px solid #2DCA73;
            color: #1a7a44;
        }

        .chatbot-admin-notice.error {
            background-color: #fce8e8;
            border: 1px solid #e74c3c;
            color: #a32a1d;
        }

        .chatbot-admin-error {
            color: red;
            font-weight: bold;
            padding: 20px;
            border: 1px solid red;
            border-radius: 5px;
            background: #fffafa;
        }
    </style>

    <?php
    return ob_get_clean();
}
