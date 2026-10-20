<?php

use Sibol\Core\Env;

return [
    'app' => [
        'env' => Env::get('APP_ENV', 'production'),
        'url' => rtrim((string) Env::get('APP_URL', ''), '/'),
        'name' => Env::get('APP_NAME', 'Sibol'),
        'timezone' => Env::get('APP_TIMEZONE', 'Asia/Manila'),
        'locale' => Env::get('APP_LOCALE', 'en'),
        'demo_login' => Env::bool('APP_DEMO_LOGIN', false),
        'secure_cookie' => Env::bool('SESSION_SECURE_COOKIE', false),
        'idle_minutes' => Env::int('SESSION_IDLE_MINUTES', 120),
        'locales' => ['en' => 'English', 'fil' => 'Filipino'],
    ],
    // The school this copy of Sibol serves: shown on the sign-in page and used for the demo school.
    // The seal and photograph are image files in public/assets/school (see tools/school-assets.mjs).
    'school' => [
        'name' => Env::get('SCHOOL_NAME', 'B.E.S.T. College of Polomolok'),
        'short_name' => Env::get('SCHOOL_SHORT_NAME', 'B.E.S.T.'),
        'location' => Env::get('SCHOOL_LOCATION', 'Polomolok, South Cotabato'),
        'founded' => Env::get('SCHOOL_FOUNDED', '1995'),
        'motto' => Env::get('SCHOOL_MOTTO', 'I am the Way, the Truth, the Life.'),
        // Describe the photograph if you replace it; empty uses the description of the current photo.
        'photo_alt' => Env::get('SCHOOL_PHOTO_ALT', ''),
    ],
    'db' => [
        'host' => Env::get('DB_HOST', '127.0.0.1'),
        'port' => Env::int('DB_PORT', 3306),
        'name' => Env::get('DB_NAME', 'sibol'),
        'user' => Env::get('DB_USER', 'root'),
        'password' => Env::get('DB_PASSWORD', ''),
    ],
    'ai' => [
        'provider' => Env::get('AI_PROVIDER', 'demo'),
        'api_key' => Env::get('AI_API_KEY', ''),
        'model' => Env::get('AI_MODEL', ''),
        'base_url' => rtrim((string) Env::get('AI_BASE_URL', ''), '/'),
        'timeout' => Env::int('AI_TIMEOUT_SECONDS', 90),
        // Reasoning depth for supported Claude models: low | medium | high.
        'effort' => Env::get('AI_EFFORT', 'medium'),
        // Server-side refusal fallback for supported Claude models: default | off.
        'fallbacks' => Env::get('AI_FALLBACKS', 'default'),
        'price_input_per_mtok' => Env::get('AI_PRICE_INPUT_PER_MTOK', ''),
        'price_output_per_mtok' => Env::get('AI_PRICE_OUTPUT_PER_MTOK', ''),
    ],
    // Optional email (SMTP). Empty MAIL_HOST keeps it off: administrators copy links instead.
    'mail' => [
        'host' => Env::get('MAIL_HOST', ''),
        'port' => Env::int('MAIL_PORT', 587),
        'encryption' => Env::get('MAIL_ENCRYPTION', 'tls'),
        'username' => Env::get('MAIL_USERNAME', ''),
        'password' => Env::get('MAIL_PASSWORD', ''),
        'from_address' => Env::get('MAIL_FROM_ADDRESS', ''),
        'from_name' => Env::get('MAIL_FROM_NAME', 'Sibol'),
    ],
    'uploads' => [
        'max_mb' => Env::int('UPLOAD_MAX_MB', 20),
        'path' => BASE_PATH . '/storage/uploads',
    ],
    'log' => [
        'level' => Env::get('LOG_LEVEL', 'info'),
        'path' => BASE_PATH . '/storage/logs/sibol.log',
    ],
];
