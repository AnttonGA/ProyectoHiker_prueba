<?php
/**
 * Configuración específica del chat para Kahir.AI
 * 
 * Este archivo contiene la configuración relacionada con el comportamiento
 * del chat, prompts predefinidos, y patrones de respuesta.
 */

return [
    // Configuración general del chat
    'chat' => [
        'max_context_length' => 10,            // Número máximo de mensajes en el contexto
        'max_message_length' => 500,           // Longitud máxima de mensajes del usuario
        'typing_simulation' => true,           // Simular efecto de escritura
        'typing_speed' => [100, 300],         // Rango de velocidad de escritura (ms por caracter)
        'idle_timeout' => 1800,               // Tiempo de inactividad antes de reiniciar contexto (30 min)
        'min_response_time' => 500,           // Tiempo mínimo de respuesta (ms)
        'max_response_time' => 5000,          // Tiempo máximo de respuesta (ms)
    ],

    // Prompts predefinidos para diferentes tipos de consultas
    'prompts' => [
        'route_request' => [
            'required_fields' => ['activity', 'location', 'difficulty'],
            'optional_fields' => ['date', 'duration', 'elevation'],
            'clarification_questions' => [
                'activity' => '¿Qué actividad te gustaría realizar? (Ski touring, senderismo, etc.)',
                'location' => '¿En qué zona de los Pirineos te gustaría realizar la actividad?',
                'difficulty' => '¿Qué nivel de dificultad prefieres? (principiante, intermedio, avanzado)',
                'duration' => '¿Cuánto tiempo dispones para la actividad?',
                'elevation' => '¿Tienes alguna preferencia en cuanto al desnivel?'
            ]
        ],
        'weather_request' => [
            'required_fields' => ['location', 'date'],
            'optional_fields' => ['specific_time'],
            'clarification_questions' => [
                'location' => '¿Para qué ubicación necesitas la previsión meteorológica?',
                'date' => '¿Para qué fecha necesitas la previsión?'
            ]
        ]
    ],

    // Plantillas de respuesta para diferentes situaciones
    'response_templates' => [
        'greeting' => [
            'es' => 'Hola, soy Kahir. ¿En qué puedo ayudarte hoy?',
            'en' => 'Hi, I\'m Kahir. How can I help you today?',
            'fr' => 'Bonjour, je suis Kahir. Comment puis-je vous aider aujourd\'hui?',
            'de' => 'Hallo, ich bin Kahir. Wie kann ich Ihnen heute helfen?',
            'it' => 'Ciao, sono Kahir. Come posso aiutarti oggi?',
            'eu' => 'Kaixo, Kahir naiz. Zertan lagun zaitzaket gaur?'
        ],
        'no_routes_found' => [
            'es' => 'No he encontrado rutas que coincidan con tus criterios. ¿Te gustaría explorar alternativas cercanas?',
            // Otros idiomas...
        ],
        'bad_weather' => [
            'es' => 'Las condiciones meteorológicas no son favorables para esa fecha. ¿Te gustaría ver otras fechas disponibles?',
            // Otros idiomas...
        ],
        'missing_info' => [
            'es' => 'Necesito un poco más de información para ayudarte mejor.',
            // Otros idiomas...
        ]
    ],

    // Configuración de las tarjetas de respuesta
    'card_templates' => [
        'route' => [
            'template' => 'route_card',
            'required_fields' => ['title', 'difficulty', 'duration', 'elevation', 'location'],
            'optional_fields' => ['description', 'images', 'weather']
        ],
        'weather' => [
            'template' => 'weather_card',
            'required_fields' => ['location', 'temperature', 'humidity', 'wind'],
            'optional_fields' => ['snow_level', 'iso_zero', 'precipitation_type']
        ]
    ],

    // Configuración de análisis de lenguaje natural
    'nlp' => [
        'keywords' => [
            'route' => ['ruta', 'camino', 'sendero', 'track', 'itinerario'],
            'weather' => ['tiempo', 'meteorología', 'previsión', 'clima'],
            'difficulty' => ['fácil', 'medio', 'difícil', 'principiante', 'intermedio', 'avanzado']
        ],
        'sentiment_analysis' => true,
        'entity_recognition' => true
    ],

    // Configuración de aprendizaje y personalización
    'learning' => [
        'store_interactions' => true,
        'learn_from_feedback' => true,
        'min_confidence_score' => 0.7,
        'update_preferences_after' => 5,  // Actualizar preferencias cada 5 interacciones
        'max_suggestions' => 3
    ],

    // Configuración de interacción
    'interaction' => [
        'max_clarification_questions' => 3,  // Máximo de preguntas de clarificación por consulta
        'suggestion_threshold' => 0.6,       // Umbral de confianza para sugerir alternativas
        'auto_complete_threshold' => 0.8,    // Umbral para autocompletar información
        'context_expiration' => 1800         // Tiempo de expiración del contexto en segundos
    ],

    // Configuración de elementos visuales
    'ui' => [
        'show_typing_indicator' => true,
        'enable_animations' => true,
        'show_timestamps' => true,
        'enable_markdown' => true,
        'max_cards_per_response' => 5
    ]
];