(function ($) {
    $(document).ready(function () {
        const $toggle = $('#rest-chatbot-toggle');
        const $window = $('#rest-chatbot-window');
        const $close = $('#rest-chatbot-close');
        const $restart = $('#rest-chatbot-restart');
        const $messages = $('#chatbot-messages');
        const $inputArea = $('#chatbot-input-area');
        const $input = $('#chatbot-input');
        const $sendBtn = $('#chatbot-send');

        let chatState = 'MENU';
        let orderDetails = { items: [], name: '', address: '', phone: '', preferences: '', paymentMethod: '', cashAmount: '', total: 0 };
        let reservationDetails = { type: '', date: '', time: '', people: '', name: '', phone: '' };
        let cancelReservationDetails = { name: '', phone: '', datetime: '' };
        let currentItemContext = '';
        let hasTriggeredInactivity = false;
        let hasTriggeredExit = false;

        // Variables dinámicas inyectadas desde WP php
        const WHATSAPP_NUMBER = restChatbotObj.whatsapp_number;
        const LOCATION_INFO = restChatbotObj.location_info;
        const paymentTarjeta = restChatbotObj.payment_tarjeta == 1;
        const paymentBizum = restChatbotObj.payment_bizum == 1;
        const paymentEfectivo = restChatbotObj.payment_efectivo == 1;
        const DELIVERY_FEE = parseFloat(restChatbotObj.delivery_fee) || 0;

        // --- Funciones de Utilidad para procesar menús ---
        function parseMenu(menuText) {
            if (!menuText) return [];
            return menuText.split('\n').filter(line => line.trim() !== '').map(line => {
                let dishName = line;
                let vars = [];
                let extras = [];
                if (line.includes('|')) {
                    const parts = line.split('|').map(p => p.trim());
                    dishName = parts[0];
                    for (let i = 1; i < parts.length; i++) {
                        if (parts[i].toLowerCase().startsWith('vars:')) {
                            vars = parts[i].substring(5).split(',').map(v => v.trim());
                        } else if (parts[i].toLowerCase().startsWith('extras:')) {
                            extras = parts[i].substring(7).split(',').map(e => e.trim());
                        }
                    }
                }
                return { name: dishName, vars: vars, extras: extras, raw: line };
            });
        }

        const menuDesayunos = parseMenu(restChatbotObj.menu_desayunos);
        const menuEntradas = parseMenu(restChatbotObj.menu_entradas);
        const menuPlatos = parseMenu(restChatbotObj.menu_platos);
        const menuComida = parseMenu(restChatbotObj.menu_comida);
        const menuCombos = parseMenu(restChatbotObj.menu_combos);
        const menuBar = parseMenu(restChatbotObj.menu_bar);

        // --- Funciones del UI ---

        function toggleChat() {
            $window.toggleClass('hidden');
            if (!$window.hasClass('hidden') && $messages.is(':empty')) {
                showMainMenu();
            }
        }

        $toggle.on('click', toggleChat);

        $close.on('click', function () {
            $window.addClass('hidden');
        });

        $restart.on('click', function () {
            if (confirm('¿Estás seguro de que deseas vaciar tu pedido y reiniciar el chat?')) {
                // Reiniciar estado
                orderDetails = { items: [], name: '', address: '', phone: '', preferences: '', paymentMethod: '', cashAmount: '', total: 0 };
                reservationDetails = { type: '', date: '', time: '', people: '', name: '', phone: '' };
                cancelReservationDetails = { name: '', phone: '', datetime: '' };
                currentDishObj = null;
                currentItemContext = '';

                // Limpiar mensajes y volver al frame inicial
                $messages.empty();
                showMainMenu();
            }
        });

        function addMessage(text, sender = 'bot') {
            const bubble = $(`<div class="chat-bubble ${sender}"></div>`).text(text);
            $messages.append(bubble);
            scrollToBottom();
        }

        function addOptions(options) {
            const container = $('<div class="chat-options"></div>');
            options.forEach(opt => {
                const btn = $(`<button class="chat-option-btn">${opt.text}</button>`);
                btn.on('click', function () {
                    container.find('button').prop('disabled', true).css('opacity', 0.5);
                    addMessage(opt.text, 'user');
                    setTimeout(() => {
                        container.hide();
                        if (opt.action) opt.action();
                    }, 400);
                });
                container.append(btn);
            });
            $messages.append(container);
            scrollToBottom();
        }

        function scrollToBottom() {
            $messages.animate({ scrollTop: $messages[0].scrollHeight }, 300);
        }

        function showInput() {
            $inputArea.removeClass('hidden');
            $input.focus();
        }

        function hideInput() {
            $inputArea.addClass('hidden');
        }

        // --- Flujos de Conversación de la Carta ---

        function showMainMenu() {
            hideInput();
            addMessage("¡Hola! 👋 Soy el asistente virtual del Café Bogotá Bar Peregrina. ¿Qué te apetece pedir hoy?");
            addOptions([
                { text: '🍳 Desayunos', action: showDesayunos },
                { text: '🥟 Entradas', action: showEntradas },
                { text: '🍽️ Platos Especiales', action: showPlatos },
                { text: '🍔 Comida Rápida y Arepas', action: showComida },
                { text: '📦 Combos', action: showCombos },
                { text: '🍺 Bar (Bebidas)', action: showBar },
                { text: '📍 Información / Horarios', action: showInfo },
                { text: '📅 Reservas', action: showReservations }
            ]);
            chatState = 'MENU';
        }

        function showReservations() {
            hideInput();
            addMessage("¡Qué bien que quieras visitarnos! 🍷 Selecciona el tipo de reserva que necesitas:");
            addOptions([
                { text: '🍽️ Reservar mesa', action: () => startReservation('Reservar mesa', 'Empieza el día con energía, disfruta de un menú con sabor de hogar o cierra la jornada con una cena inolvidable. En Bar Bogotá, tenemos la mesa puesta para ti desde el primer café hasta el último brindis. ¡Ven a disfrutar de la mejor compañía y el sabor que nos define!') },
                { text: '⚽ Fútbol', action: () => startReservation('Fútbol', 'Vive la pasión de cada partido con el mejor ambiente y la emoción de la grada directamente en nuestra sala. ¡Ven a gritar cada gol con nosotros mientras disfrutas de una cerveza bien fría y nuestros mejores pinchos!') },
                { text: '🎂 Cumpleaños', action: () => startReservation('Cumpleaños', 'Haz que tu día especial sea inolvidable celebrando en un lugar con estilo, gran sabor y la mejor atención. ¡Reserva hoy tu mesa de cumpleañero y permítenos organizar una sorpresa que te encantará!') },
                { text: '🍾 Reservado y Eventos', action: () => startReservation('Reservado y Eventos', 'Disfruta de la máxima exclusividad en nuestro salón privado, ideal para reuniones de negocios o celebraciones íntimas. ¡Asegura tu fecha ahora y garantiza el éxito de tu evento con nuestro servicio personalizado de alta gama!') },
                { text: '🔄 Gestionar o Cancelar Reserva', action: startCancelReservation },
                { text: '🔙 Volver', action: showMainMenu }
            ]);
        }

        function startCancelReservation() {
            addMessage("¡Hola! Si tus planes han cambiado y necesitas cancelar tu reserva, te agradecemos mucho que nos lo comuniques con antelación. Así nos ayudas a organizar mejor nuestro servicio.");
            setTimeout(() => {
                addMessage("Sigue estos pasos rápidos:\n1. Recuérdanos tu nombre\n2. Teléfono\n3. Fecha y hora\n\nEmpecemos: ¿A nombre de quién está la reserva? 📝");
                chatState = 'WAITING_CANCEL_NAME';
                showInput();
            }, 1000);
        }

        function startReservation(type, description) {
            reservationDetails.type = type;
            addMessage(`${description}`);
            setTimeout(() => {
                addMessage(`Para hacer tu reserva de *${type}*, por favor dime, ¿para qué día la quieres? (Ej: Mañana, Viernes 15, etc.) 📅`);
                chatState = 'WAITING_RESERVATION_DATE';
                showInput();
            }, 1500);
        }

        function renderDynamicMenu(title, categoryArray) {
            addMessage(title);
            let options = [];

            categoryArray.forEach(itemObj => {
                options.push({ text: itemObj.name, action: () => handleDishSelection(itemObj) });
            });

            options.push({ text: '🔙 Volver', action: showMainMenu });
            addOptions(options);
        }

        let currentDishObj = null;

        function handleDishSelection(itemObj) {
            currentDishObj = itemObj;
            if (itemObj.vars && itemObj.vars.length > 0) {
                addMessage(`¿Qué tipo de ${itemObj.name.split('(')[0].trim()} prefieres?`);
                let options = itemObj.vars.map(v => ({ text: v, action: () => addToOrder(itemObj, v) }));
                options.push({ text: '🔙 Cancelar', action: showMainMenu });
                addOptions(options);
            } else {
                addToOrder(itemObj, null);
            }
        }

        function showDesayunos() {
            renderDynamicMenu("Nuestros desayunos para empezar el día con energía. ¿Cuál prefieres?", menuDesayunos);
        }

        function showEntradas() {
            renderDynamicMenu("Aquí tienes nuestras deliciosas entradas. ¿Cuál te gusta?", menuEntradas);
        }

        function showPlatos() {
            renderDynamicMenu("Nuestros Platos Especiales. ¿Cuál te apetece?", menuPlatos);
        }

        function showComida() {
            renderDynamicMenu("Disfruta de nuestra Comida Rápida y Arepas. ¿Qué eliges?", menuComida);
        }

        function showCombos() {
            renderDynamicMenu("Nuestros Combos para compartir o disfrutar al máximo:", menuCombos);
        }

        function showBar() {
            renderDynamicMenu("Bebidas frías, cócteles y licores. ¿Qué te sirvo?", menuBar);
        }

        function addToOrder(itemObj, selectedVar) {
            currentDishObj = itemObj;
            let displayItem = selectedVar ? `${itemObj.name.replace(/\(.*\)/, '').trim()} - ${selectedVar} ${itemObj.name.match(/\(.*\)/) ? itemObj.name.match(/\(.*\)/)[0] : ''}`.trim() : itemObj.name;

            orderDetails.items.push(displayItem);
            currentItemContext = displayItem;

            // Intentar extraer el precio usando expresión regular: busca (X,XX€) o (X€)
            const priceMatch = itemObj.name.match(/\((\d+(?:,\d{1,2})?)€\)/);
            if (priceMatch && priceMatch[1]) {
                const num = parseFloat(priceMatch[1].replace(',', '.'));
                orderDetails.total += num;
            }

            addMessage(`¡Añadido: ${displayItem}! ¿Qué deseas hacer ahora?`);
            addOptions([
                { text: '➕ Añadir más platos', action: showMainMenu },
                { text: '🍺 Añadir Bebida', action: showBar },
                { text: '✅ Finalizar y Enviar Pedido', action: startCheckout },
                { text: '🗑️ Me equivoqué, quitar plato', action: undoLastItem }
            ]);
        }

        function undoLastItem() {
            const removedItem = orderDetails.items.pop();
            if (!removedItem) {
                showMainMenu();
                return;
            }

            const priceMatch = removedItem.match(/(\d+(?:,\d{1,2})?)€/);
            if (priceMatch && priceMatch[1]) {
                const num = parseFloat(priceMatch[1].replace(',', '.'));
                orderDetails.total -= num;
                if (orderDetails.total < 0) orderDetails.total = 0;
            }

            currentItemContext = '';
            addMessage(`Entendido, he quitado: "${removedItem}". ¿Qué hacemos ahora?`);
            addOptions([
                { text: '➕ Añadir más platos', action: showMainMenu },
                { text: '🍺 Añadir Bebida', action: showBar },
                { text: '✅ Opciones de pedido', action: askNextSteps }
            ]);
        }

        function askNextSteps() {
            currentItemContext = '';
            addMessage(`¿Qué deseas hacer ahora?`);
            addOptions([
                { text: '➕ Añadir más platos', action: showMainMenu },
                { text: '🍺 Añadir Bebida', action: showBar },
                { text: '✅ Finalizar y Enviar Pedido', action: startCheckout }
            ]);
        }

        function showInfo() {
            addMessage(LOCATION_INFO);
            addOptions([
                { text: 'Hacer un Pedido', action: showMainMenu },
            ]);
        }

        function askSpecialDetails() {
            addMessage("¿Algún detalle en especial? Queremos que tu pedido sea perfecto. Si quieres quitar algún ingrediente o tienes alguna alergia o intolerancia, escríbelo aquí abajo. ¡Gracias por tu compra!");
            chatState = 'WAITING_PREFERENCES';
            showInput();
        }

        function proceedToPayment() {
            const totalConDomicilio = orderDetails.total + DELIVERY_FEE;

            let msg = `Tus platos suman ${orderDetails.total.toFixed(2).replace('.', ',')}€`;
            if (DELIVERY_FEE > 0) {
                msg += ` + ${DELIVERY_FEE.toFixed(2).replace('.', ',')}€ de envío`;
            }
            msg += `. El Total final es: *${totalConDomicilio.toFixed(2).replace('.', ',')}€*. ¿Cómo prefieres pagar? 💳`;

            addMessage(msg);

            let paymentOptions = [];
            if (paymentTarjeta) paymentOptions.push({ text: '💳 Tarjeta', action: () => selectPayment('Tarjeta') });
            if (paymentBizum) paymentOptions.push({ text: '📱 Bizum', action: () => selectPayment('Bizum') });
            if (paymentEfectivo) paymentOptions.push({ text: '💵 Efectivo', action: () => selectPayment('Efectivo') });

            if (paymentOptions.length === 0) {
                paymentOptions.push({ text: 'Acordar con el local', action: () => selectPayment('A convenir') });
            }
            addOptions(paymentOptions);
        }

        // === PASO FINAL: PREPARAR WHATSAPP ===
        function startCheckout() {
            if (orderDetails.items.length === 0) {
                addMessage("Tu pedido está vacío. Elige algo primero.");
                setTimeout(showMainMenu, 1000);
                return;
            }

            addMessage("Para enviar tu pedido directamente a nuestro WhatsApp, dime tú nombre por favor. 📝");
            chatState = 'WAITING_NAME';
            showInput();
        }

        function handleInput() {
            const text = $input.val().trim();
            if (!text) return;

            $input.val('');
            addMessage(text, 'user');

            setTimeout(() => {
                if (chatState === 'WAITING_NAME') {
                    orderDetails.name = text;
                    addMessage(`Gracias ${text}. ¿A qué dirección debemos enviar tu pedido? (Si pasas a recogerlo, escribe "Recoger"). 🛵`);
                    chatState = 'WAITING_ADDRESS';
                    showInput();
                } else if (chatState === 'WAITING_ADDRESS') {
                    orderDetails.address = text;
                    addMessage(`Perfecto. ¿Cuál es tu número de teléfono por si necesitamos contactarte? 📞`);
                    chatState = 'WAITING_PHONE';
                    showInput();
                } else if (chatState === 'WAITING_PHONE') {
                    orderDetails.phone = text;
                    hideInput();

                    proceedToPayment();

                } else if (chatState === 'WAITING_PREFERENCES') {
                    orderDetails.preferences = text;
                    hideInput();
                    processOrderRequest();

                } else if (chatState === 'WAITING_CASH_AMOUNT') {
                    orderDetails.cashAmount = text;
                    hideInput();
                    askSpecialDetails();
                } else if (chatState === 'WAITING_RESERVATION_DATE') {
                    reservationDetails.date = text;
                    addMessage(`Perfecto, ¿a qué hora deseas reservar? ⏰ (Ej: 14:30 o 21:00)`);
                    chatState = 'WAITING_RESERVATION_TIME';
                    showInput();
                } else if (chatState === 'WAITING_RESERVATION_TIME') {
                    reservationDetails.time = text;
                    addMessage(`Muy bien, ¿para cuántas personas sería la reserva? 👥 (Ej: 4 personas)`);
                    chatState = 'WAITING_RESERVATION_PEOPLE';
                    showInput();
                } else if (chatState === 'WAITING_RESERVATION_PEOPLE') {
                    reservationDetails.people = text;
                    addMessage(`Genial, ¿a qué nombre hacemos la reserva? 📝`);
                    chatState = 'WAITING_RESERVATION_NAME';
                    showInput();
                } else if (chatState === 'WAITING_RESERVATION_NAME') {
                    reservationDetails.name = text;
                    addMessage(`Por último, ¿cuál es tu número de teléfono por si necesitamos contactarte? 📞`);
                    chatState = 'WAITING_RESERVATION_PHONE';
                    showInput();
                } else if (chatState === 'WAITING_RESERVATION_PHONE') {
                    reservationDetails.phone = text;
                    hideInput();
                    sendReservationToWhatsApp();
                } else if (chatState === 'WAITING_CANCEL_NAME') {
                    cancelReservationDetails.name = text;
                    addMessage(`Gracias. Ahora, ¿cuál es el número de teléfono con el que hiciste la reserva? 📞`);
                    chatState = 'WAITING_CANCEL_PHONE';
                    showInput();
                } else if (chatState === 'WAITING_CANCEL_PHONE') {
                    cancelReservationDetails.phone = text;
                    addMessage(`Casi listo. Por último, dime por favor la fecha y la hora para la que tenías la reserva (ej: Viernes 15 a las 21:00) 📅⏰`);
                    chatState = 'WAITING_CANCEL_DATETIME';
                    showInput();
                } else if (chatState === 'WAITING_CANCEL_DATETIME') {
                    cancelReservationDetails.datetime = text;
                    hideInput();
                    sendCancelationToWhatsApp();
                } else {
                    addMessage("Por favor, usa las opciones del menú.");
                    hideInput();
                    setTimeout(showMainMenu, 1000);
                }
            }, 600);
        }

        function selectPayment(method) {
            if (method === 'Efectivo') {
                orderDetails.paymentMethod = method;
                addMessage(`Has elegido Efectivo. ¿Con cuánto vas a pagar para que el repartidor lleve el cambio exacto? 💶 (ej: 20€)`);
                chatState = 'WAITING_CASH_AMOUNT';
                showInput();
            } else if (method === 'Bizum') {
                orderDetails.paymentMethod = 'Bizum (643472485)';
                addMessage(`Has elegido pagar vía Bizum. Nuestro número es 643472485. 📱`);
                setTimeout(askSpecialDetails, 2000);
            } else {
                orderDetails.paymentMethod = method;
                askSpecialDetails();
            }
        }

        $sendBtn.on('click', handleInput);
        $input.on('keypress', function (e) {
            if (e.which == 13) handleInput();
        });

        // --- Petición AJAX y Envío final a WhatsApp ---
        function processOrderRequest() {
            addMessage("El tiempo de preparación y entrega es de 30 a 40 minutos. ⏱️");
            addMessage("⏳ Generando número de pedido...");

            const totalFinal = orderDetails.total + DELIVERY_FEE;

            const payload = {
                action: 'rest_chatbot_get_order',
                name: orderDetails.name,
                phone: orderDetails.phone,
                address: orderDetails.address,
                items: JSON.stringify(orderDetails.items),
                total: totalFinal,
                payment_method: orderDetails.paymentMethod,
                cash_amount: orderDetails.cashAmount,
                preferences: orderDetails.preferences
            };

            $.post(restChatbotObj.ajax_url, payload, function (response) {
                let orderNumber = 'Pendiente';
                if (response.success && response.data && response.data.order_number) {
                    orderNumber = response.data.order_number;
                }
                sendToWhatsApp(orderNumber);
            }).fail(function () {
                // Fallback continuo si el AJAX falla
                sendToWhatsApp('Desconocido');
            });
        }

        function sendToWhatsApp(orderNumber) {
            const finalTotal = orderDetails.total + DELIVERY_FEE;

            let message = `*NUEVO PEDIDO #${orderNumber} - CAFÉ BOGOTÁ BAR*\n\n`;
            message += `👤 *Cliente:* ${orderDetails.name}\n`;
            message += `📍 *Dirección:* ${orderDetails.address}\n`;
            message += `📞 *Teléfono:* ${orderDetails.phone}\n`;
            message += `---------------------------\n`;
            message += `🍽️ *Platos:*\n`;
            orderDetails.items.forEach(item => {
                message += `- ${item}\n`;
            });
            message += `---------------------------\n`;

            if (orderDetails.preferences && orderDetails.preferences.toLowerCase() !== 'no') {
                message += `⭐ *Preferencias / Alergias:* ${orderDetails.preferences}\n`;
                message += `---------------------------\n`;
            }

            if (DELIVERY_FEE > 0) {
                message += `🛵 *Domicilio:* ${DELIVERY_FEE.toFixed(2).replace('.', ',')}€\n`;
            }
            message += `💰 *GRAN TOTAL:* ${finalTotal.toFixed(2).replace('.', ',')}€\n`;
            message += `💳 *Método de Pago:* ${orderDetails.paymentMethod}\n`;

            if (orderDetails.paymentMethod === 'Efectivo' && orderDetails.cashAmount) {
                message += `💶 *Paga con:* ${orderDetails.cashAmount}\n`;
            }

            message += `\nEnviado desde el asistente web.`;
            message += `\n\n¡Muchas gracias por tu compra! 😍 Ya nos pusimos manos a la obra con tu pedido. El tiempo estimado de espera es de 30 a 40 minutos. ¡Vale la pena la espera!`;

            let whatsappUrl = `https://api.whatsapp.com/send?phone=${WHATSAPP_NUMBER}&text=${encodeURIComponent(message)}`;

            // Arreglo PopUp Blocker: No hacer window.open asíncrono, crear un botón directo.
            addMessage("✅ Pedido listo. Haz clic en el siguiente botón para enviárnoslo por WhatsApp:");

            const actionContainer = $('<div class="chat-options"></div>');
            const linkBtn = $(`<a href="${whatsappUrl}" target="_blank" class="chat-option-btn whatsapp-send-btn" style="text-decoration: none; display: inline-block; color: white; background: #25D366;">📲 Enviar a WhatsApp</a>`);

            linkBtn.on('click', function () {
                $(this).css('opacity', 0.5).css('pointer-events', 'none');
                setTimeout(() => {
                    // Resetear estado
                    orderDetails = { items: [], name: '', address: '', phone: '', preferences: '', paymentMethod: '', cashAmount: '', total: 0 };
                    addMessage("¡Muchas gracias por tu compra! 😍 Ya nos pusimos manos a la obra con tu pedido. El tiempo estimado de espera es de 30 a 40 minutos. ¡Vale la pena la espera!");
                    setTimeout(showMainMenu, 3000);
                }, 2000);
            });

            actionContainer.append(linkBtn);
            $messages.append(actionContainer);
            scrollToBottom();
        }

        // --- Envío de Reservas a WhatsApp ---
        function sendReservationToWhatsApp() {
            let message = `*NUEVA SOLICITUD DE RESERVA - CAFÉ BOGOTÁ BAR*\n\n`;
            message += `📌 *Tipo de reserva:* ${reservationDetails.type}\n`;
            message += `👤 *A nombre de:* ${reservationDetails.name}\n`;
            message += `📞 *Teléfono:* ${reservationDetails.phone}\n`;
            message += `👥 *Personas:* ${reservationDetails.people}\n`;
            message += `📅 *Fecha:* ${reservationDetails.date}\n`;
            message += `⏰ *Hora:* ${reservationDetails.time}\n\n`;
            message += `Enviado desde el asistente web.`;

            let whatsappUrl = `https://api.whatsapp.com/send?phone=${WHATSAPP_NUMBER}&text=${encodeURIComponent(message)}`;

            addMessage("✅ Hemos tomado los datos de tu reserva. Haz clic en el botón abajo para enviarla a nuestro WhatsApp y confirmar la disponibilidad:");

            const actionContainer = $('<div class="chat-options"></div>');
            const linkBtn = $(`<a href="${whatsappUrl}" target="_blank" class="chat-option-btn whatsapp-send-btn" style="text-decoration: none; display: inline-block; color: white; background: #25D366;">📲 Confirmar por WhatsApp</a>`);

            linkBtn.on('click', function () {
                $(this).css('opacity', 0.5).css('pointer-events', 'none');
                setTimeout(() => {
                    reservationDetails = { type: '', date: '', time: '', people: '', name: '', phone: '' };
                    addMessage("¡Gracias por contactarnos! En breve te confirmaremos la reserva por WhatsApp. 👋");
                    setTimeout(showMainMenu, 3000);
                }, 2000);
            });

            actionContainer.append(linkBtn);
            $messages.append(actionContainer);
            scrollToBottom();
        }

        // --- Cancelación de Reservas a WhatsApp ---
        function sendCancelationToWhatsApp() {
            let message = `*CANCELACIÓN O GESTIÓN DE RESERVA - CAFÉ BOGOTÁ BAR*\n\n`;
            message += `👤 *A nombre de:* ${cancelReservationDetails.name}\n`;
            message += `📞 *Teléfono:* ${cancelReservationDetails.phone}\n`;
            message += `📅 *Fecha y Hora original:* ${cancelReservationDetails.datetime}\n\n`;
            message += `Enviado desde el asistente web.`;

            let whatsappUrl = `https://api.whatsapp.com/send?phone=${WHATSAPP_NUMBER}&text=${encodeURIComponent(message)}`;

            addMessage("✅ Hemos recopilado los datos de la cancelación. Haz clic en el botón abajo para enviarla a nuestro WhatsApp y liberar tu mesa:");

            const actionContainer = $('<div class="chat-options"></div>');
            const linkBtn = $(`<a href="${whatsappUrl}" target="_blank" class="chat-option-btn whatsapp-send-btn" style="text-decoration: none; display: inline-block; color: white; background: #E53935;">📲 Enviar Cancelación a WhatsApp</a>`);

            linkBtn.on('click', function () {
                $(this).css('opacity', 0.5).css('pointer-events', 'none');
                setTimeout(() => {
                    cancelReservationDetails = { name: '', phone: '', datetime: '' };
                    addMessage("¡Gracias por tu consideración y te esperamos en otra ocasión! 👋");
                    setTimeout(showMainMenu, 3000);
                }, 2000);
            });

            actionContainer.append(linkBtn);
            $messages.append(actionContainer);
            scrollToBottom();
        }

        // --- Disparadores Automáticos (Triggers) ---

        setTimeout(() => {
            if (!hasTriggeredInactivity && $window.hasClass('hidden')) {
                hasTriggeredInactivity = true;
                $window.removeClass('hidden');
                if ($messages.is(':empty')) {
                    addMessage("¿Un antojo de comida colombiana? 🌮 Empanadas, Arepas... ¡Haz tu pedido aquí!");
                    addOptions([
                        { text: '¡Quiero pedir!', action: showMainMenu },
                        { text: 'No, gracias', action: () => { $window.addClass('hidden'); } }
                    ]);
                }
            }
        }, 15000);

        $(document).on('mouseleave', function (e) {
            if (e.clientY < 10 && !hasTriggeredExit && $window.hasClass('hidden')) {
                hasTriggeredExit = true;
                $window.removeClass('hidden');
                if ($messages.is(':empty')) {
                    addMessage("¡Espera! ¿No te vas a ir sin probar nuestras Arepas Rellenas? 🫓");
                    addOptions([
                        { text: 'Ver la Carta', action: showMainMenu },
                    ]);
                }
            }
        });

    });
})(jQuery);
