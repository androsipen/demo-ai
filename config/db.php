<?php

return [
    'class' => 'yii\db\Connection',
    'dsn' => 'pgsql:host=localhost;port=5433;dbname=kanban_db',
    'username' => 'kanban_user',
    'password' => 'kanban123',
    'charset' => 'utf8',
    'schemaMap' => [
        'pgsql' => [
            'class' => 'yii\db\pgsql\Schema',
            'defaultSchema' => 'public',
        ],
    ],
];
