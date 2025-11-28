<?php

require dirname(__DIR__) . '/vendor/autoload.php';
require __DIR__ . '/KanbanWebSocket.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use app\websocket\KanbanWebSocket;

$port = getenv('WS_PORT') ?: 8081;

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new KanbanWebSocket()
        )
    ),
    $port,
    '0.0.0.0'
);

echo "=========================================\n";
echo "Kanban WebSocket Server\n";
echo "=========================================\n";
echo "Listening on port {$port}\n";
echo "Press Ctrl+C to stop\n";
echo "=========================================\n\n";

$server->run();
