<?php
declare(strict_types=1);

/** Contenido editable de la página pública Mayoristas. */
function mayoristas_default_content(): array {
    return [
        'visual_title' => "Tu cuenta mayorista,\nlista para producir.",
        'chips' => ['Talleres', 'Gráficas', 'Comercios', 'Emprendedores'],
        'eyebrow' => 'COMPRÁ AL POR MAYOR',
        'title' => "Precios mayoristas\npara tu negocio.",
        'intro_one' => 'En Serinsumos trabajamos con talleres, gráficas, comercios y emprendedores que reponen insumos todo el año. Con tu cuenta mayorista accedés a precios exclusivos y a un equipo que te ayuda a elegir lo que mejor rinde.',
        'intro_two' => 'Revisamos cada solicitud a mano, así que no vas a comprar nada que no podamos entregarte.',
        'bullets' => ['Precios mayoristas en todo el catálogo', 'Stock revisado antes de confirmar tu pedido', 'Asesoramiento para elegir insumos y equipos', 'Envíos a todo el país o retiro en el local'],
        'register_button' => 'Solicitar cuenta mayorista →',
        'whatsapp_button' => 'Consultar por WhatsApp',
        'benefits' => [
            ['title' => 'Precios mayoristas', 'text' => 'Accedé a la lista de precios para clientes aprobados en todos los productos de la tienda.'],
            ['title' => 'Stock revisado', 'text' => 'Verificamos la disponibilidad física antes de confirmar cada pedido, para que no compres lo que no podemos entregar.'],
            ['title' => 'Atención personalizada', 'text' => 'Te respondemos por WhatsApp o en el local, con recomendaciones según lo que producís.'],
            ['title' => 'Envíos a todo el país', 'text' => 'Despachamos por expresos y transportes, o coordinamos el retiro en el local cuando te queda más cómodo.'],
        ],
        'steps_eyebrow' => 'PASO A PASO',
        'steps_title' => "Comprar al por mayor,\nsin complicaciones.",
        'steps' => [
            ['title' => 'Registrá tu negocio', 'text' => 'Completá tus datos en el registro mayorista. Revisamos y aprobamos tu cuenta manualmente.'],
            ['title' => 'Armá tu pedido', 'text' => 'Ya con la cuenta aprobada vas a ver los precios mayoristas. Cargá tu pedido desde la tienda o mandanos el detalle.'],
            ['title' => 'Confirmamos y coordinamos', 'text' => 'Verificamos stock e importe final antes del pago. Cuando habilitemos tu pedido, adjuntás el comprobante desde tu cuenta.'],
        ],
        'request_eyebrow' => 'ANTES DE ARRANCAR',
        'request_title' => 'Contanos sobre tu negocio.',
        'request_body' => 'No hace falta que tengas todo resuelto: si nos contás cómo trabajás, te armamos una propuesta con los insumos y equipos que mejor se adaptan a tu producción.',
        'request_card_title' => 'Nos ayuda saber',
        'request_list' => ['Tu rubro: sublimación, estampado, gráfica, marroquinería o regalería', 'Si producís vos, revendés o hacés las dos cosas', 'Tu zona, para calcular el envío y los tiempos de entrega', 'Los insumos que necesitás reponer con más frecuencia'],
        'faq_eyebrow' => 'ANTES DE EMPEZAR',
        'faq_title' => 'Resolvemos tus dudas.',
        'faqs' => [
            ['question' => '¿Cómo accedo a los precios mayoristas?', 'answer' => 'Registrá tu negocio desde el formulario de registro. Revisamos la solicitud manualmente y, cuando la aprobamos, vas a ver los precios en toda la tienda.'],
            ['question' => '¿Puedo comprar sin tener cuenta?', 'answer' => 'Sí. Podés consultar por un producto o enviarnos el detalle de tu pedido por WhatsApp. Los precios mayoristas se muestran a los clientes con cuenta aprobada.'],
            ['question' => '¿Cuándo tengo que pagar?', 'answer' => 'No transfieras antes de que confirmemos el stock y el importe final. Cuando habilitemos tu pedido, vas a poder cargar el comprobante desde tu cuenta.'],
            ['question' => '¿Hacen envíos a todo el país?', 'answer' => 'Sí. Despachamos por expresos y transportes a todo el país y también podés retirar en el local de San Vicente, Córdoba.'],
            ['question' => '¿Buscan insumos que no están en la tienda?', 'answer' => 'Escribinos con lo que necesitás. Si no lo tenemos publicado, lo buscamos y te avisamos disponibilidad y precio.'],
        ],
        'cta_title' => '¿Arrancamos con tu cuenta mayorista?',
        'cta_body' => 'Registrate y empezá a ver precios pensados para tu negocio, con stock revisado y asesoramiento real.',
        'cta_register_button' => 'Solicitar cuenta mayorista →',
        'cta_catalog_button' => 'Ver el catálogo',
    ];
}

function mayoristas_content(): array {
    $defaults = mayoristas_default_content();
    $json = setting('mayoristas_content', '');
    $stored = $json !== '' ? json_decode($json, true) : null;
    if (!is_array($stored)) return $defaults;
    return array_replace_recursive($defaults, $stored);
}

function save_mayoristas_content(array $input): void {
    $content = mayoristas_default_content();
    $copy = static function ($value): string {
        $text = trim((string)$value);
        if (mb_strlen($text, 'UTF-8') > 3000) {
            throw new RuntimeException('Cada texto debe tener como máximo 3.000 caracteres.');
        }
        return $text;
    };

    foreach (['visual_title','eyebrow','title','intro_one','intro_two','register_button','whatsapp_button','steps_eyebrow','steps_title','request_eyebrow','request_title','request_body','request_card_title','faq_eyebrow','faq_title','cta_title','cta_body','cta_register_button','cta_catalog_button'] as $key) {
        $content[$key] = $copy($input[$key] ?? $content[$key]);
    }
    foreach (['chips','bullets','request_list'] as $key) {
        foreach ($content[$key] as $i => $fallback) {
            $content[$key][$i] = $copy($input[$key][$i] ?? $fallback);
        }
    }
    foreach (['benefits' => ['title','text'], 'steps' => ['title','text'], 'faqs' => ['question','answer']] as $group => $fields) {
        foreach ($content[$group] as $i => $item) {
            foreach ($fields as $field) {
                $content[$group][$i][$field] = $copy($input[$group][$i][$field] ?? $item[$field]);
            }
        }
    }

    $json = json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($json) || strlen($json) > 60000) {
        throw new RuntimeException('El contenido total supera el límite permitido. Acortá algunos textos e intentá de nuevo.');
    }
    save_setting('mayoristas_content', $json);
}
