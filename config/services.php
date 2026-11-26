<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'sponte' => [
        'url' => env('SPONTE_URL', 'https://api.sponteeducacional.net.br/WSAPIEdu.asmx'),
        'codigo_cliente' => env('SPONTE_CODIGO_CLIENTE'),
        'token' => env('SPONTE_TOKEN'),

        // Cliente HTTP (segundos). O GetAlunos devolve ~19 MB e precisa de mais tempo.
        'http' => [
            'connect_timeout' => (int) env('SPONTE_HTTP_CONNECT_TIMEOUT', 10),
            'timeout' => (int) env('SPONTE_HTTP_TIMEOUT', 60),
            'alunos_timeout' => (int) env('SPONTE_ALUNOS_TIMEOUT', 300),
            // Tentativas extras após a primeira (backoff exponencial a partir de retry_delay_ms).
            'retries' => (int) env('SPONTE_HTTP_RETRIES', 2),
            'alunos_retries' => (int) env('SPONTE_ALUNOS_RETRIES', 1),
            'retry_delay_ms' => (int) env('SPONTE_HTTP_RETRY_DELAY_MS', 1000),
        ],

        // Situações consideradas "ativas" no ciclo frequente (campo Situacao do GetAlunos).
        'active_statuses' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('SPONTE_ACTIVE_STATUSES', 'Ativo'))
        ))),

        // Agendamento (routes/console.php). Cada unidade usa um OFFSET (minuto 0-59) diferente
        // para as 5 instalações do droplet não chamarem a Sponte ao mesmo tempo.
        'schedule' => [
            'offset' => (int) env('SPONTE_SCHEDULE_OFFSET', 0),
            'lock_minutes' => (int) env('SPONTE_SCHEDULE_LOCK_MINUTES', 360),

            // Varredura completa (--all) de enrollments, financial e installment, uma vez por semana,
            // no dia FULL_SCAN_DAY (0 = domingo ... 6 = sábado), na START_HOUR de cada command.
            'full_scan' => [
                'enabled' => (bool) env('SPONTE_FULL_SCAN_ENABLED', true),
                'day' => (int) env('SPONTE_FULL_SCAN_DAY', 0),
                'lock_minutes' => (int) env('SPONTE_FULL_SCAN_LOCK_MINUTES', 1440),
            ],
            'commands' => [
                'student' => [
                    'every_hours' => (int) env('SPONTE_STUDENT_EVERY_HOURS', 6),
                    'start_hour' => (int) env('SPONTE_STUDENT_START_HOUR', 1),
                ],
                'enrollments' => [
                    'every_hours' => (int) env('SPONTE_ENROLLMENTS_EVERY_HOURS', 6),
                    'start_hour' => (int) env('SPONTE_ENROLLMENTS_START_HOUR', 2),
                ],
                'financial' => [
                    'every_hours' => (int) env('SPONTE_FINANCIAL_EVERY_HOURS', 6),
                    'start_hour' => (int) env('SPONTE_FINANCIAL_START_HOUR', 3),
                ],
                'installment' => [
                    'every_hours' => (int) env('SPONTE_INSTALLMENT_EVERY_HOURS', 6),
                    'start_hour' => (int) env('SPONTE_INSTALLMENT_START_HOUR', 4),
                ],
            ],
        ],
    ],

    // Token exigido pelo ApiTokenMiddleware (header Authorization: Bearer <token>).
    'api_access_token' => env('API_ACCESS_TOKEN'),
    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
