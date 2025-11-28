<?php

namespace app\websocket;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class KanbanWebSocket implements MessageComponentInterface
{
    protected $clients;
    protected $clientInfo;

    public function __construct()
    {
        $this->clients = new \SplObjectStorage;
        $this->clientInfo = [];
        echo "Kanban WebSocket Server initialized\n";
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $this->clients->attach($conn);
        $this->clientInfo[$conn->resourceId] = [
            'connectedAt' => date('Y-m-d H:i:s'),
        ];

        echo "New connection: {$conn->resourceId} (Total: {$this->clients->count()})\n";

        // Send welcome message to the new client
        $conn->send(json_encode([
            'type' => 'connected',
            'payload' => [
                'clientId' => $conn->resourceId,
                'totalClients' => $this->clients->count(),
            ],
        ]));

        // Notify all other clients about new connection
        $this->broadcast([
            'type' => 'client:joined',
            'payload' => [
                'totalClients' => $this->clients->count(),
            ],
        ], $conn);
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        $data = json_decode($msg, true);

        if (!$data || !isset($data['type'])) {
            $from->send(json_encode([
                'type' => 'error',
                'payload' => ['message' => 'Invalid message format'],
            ]));
            return;
        }

        echo "Message from {$from->resourceId}: {$data['type']}\n";

        // Add sender info to the payload
        $data['payload']['senderId'] = $from->resourceId;

        // Broadcast to all OTHER clients
        $this->broadcast($data, $from);
    }

    public function onClose(ConnectionInterface $conn)
    {
        $this->clients->detach($conn);
        unset($this->clientInfo[$conn->resourceId]);

        echo "Connection closed: {$conn->resourceId} (Total: {$this->clients->count()})\n";

        // Notify remaining clients
        $this->broadcast([
            'type' => 'client:left',
            'payload' => [
                'totalClients' => $this->clients->count(),
            ],
        ]);
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "Error on connection {$conn->resourceId}: {$e->getMessage()}\n";
        $conn->close();
    }

    /**
     * Broadcast message to all clients except the sender
     */
    protected function broadcast(array $data, ?ConnectionInterface $exclude = null)
    {
        $message = json_encode($data);

        foreach ($this->clients as $client) {
            if ($exclude !== null && $client === $exclude) {
                continue;
            }
            $client->send($message);
        }
    }

    /**
     * Send message to all clients including the sender
     */
    protected function broadcastAll(array $data)
    {
        $message = json_encode($data);

        foreach ($this->clients as $client) {
            $client->send($message);
        }
    }
}
