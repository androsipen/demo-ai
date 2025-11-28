<?php

return [
    'host' => getenv('RABBITMQ_HOST') ?: 'localhost',
    'port' => getenv('RABBITMQ_PORT') ?: 5672,
    'user' => getenv('RABBITMQ_USER') ?: 'kanban_user',
    'password' => getenv('RABBITMQ_PASSWORD') ?: 'kanban123',
    'vhost' => getenv('RABBITMQ_VHOST') ?: '/',
    'queue' => 'activity_log',
    'exchange' => 'kanban_events',
];
