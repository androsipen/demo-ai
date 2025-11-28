<?php

/**
 * Activity Log Worker
 *
 * Consumes messages from RabbitMQ, writes to activity_log table,
 * and broadcasts updates via WebSocket.
 *
 * Usage: php workers/activity_worker.php
 */

require dirname(__DIR__) . '/vendor/autoload.php';

// Bootstrap Yii application (console mode)
defined('YII_DEBUG') or define('YII_DEBUG', true);
defined('YII_ENV') or define('YII_ENV', 'dev');

require dirname(__DIR__) . '/vendor/yiisoft/yii2/Yii.php';

$config = require dirname(__DIR__) . '/config/console.php';
new yii\console\Application($config);

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use app\models\ActivityLog;
use WebSocket\Client as WebSocketClient;

class ActivityWorker
{
    private $connection;
    private $channel;
    private $config;
    private $wsHost;
    private $wsPort;

    public function __construct()
    {
        $this->config = require dirname(__DIR__) . '/config/rabbitmq.php';
        $this->wsHost = getenv('WS_HOST') ?: 'localhost';
        $this->wsPort = getenv('WS_PORT') ?: 8081;
    }

    public function run()
    {
        echo "=========================================\n";
        echo "Activity Log Worker\n";
        echo "=========================================\n";
        echo "Connecting to RabbitMQ...\n";

        try {
            $this->connection = new AMQPStreamConnection(
                $this->config['host'],
                $this->config['port'],
                $this->config['user'],
                $this->config['password'],
                $this->config['vhost']
            );

            $this->channel = $this->connection->channel();

            // Declare exchange and queue
            $this->channel->exchange_declare(
                $this->config['exchange'],
                'fanout',
                false,
                true,
                false
            );

            $this->channel->queue_declare(
                $this->config['queue'],
                false,
                true,
                false,
                false
            );

            $this->channel->queue_bind(
                $this->config['queue'],
                $this->config['exchange']
            );

            echo "Connected to RabbitMQ\n";
            echo "Queue: {$this->config['queue']}\n";
            echo "Waiting for messages...\n";
            echo "=========================================\n\n";

            $callback = function (AMQPMessage $msg) {
                $this->processMessage($msg);
            };

            $this->channel->basic_qos(null, 1, null);
            $this->channel->basic_consume(
                $this->config['queue'],
                '',
                false,
                false,
                false,
                false,
                $callback
            );

            while ($this->channel->is_consuming()) {
                $this->channel->wait();
            }

        } catch (\Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
            $this->cleanup();
            exit(1);
        }
    }

    private function processMessage(AMQPMessage $msg)
    {
        $body = $msg->body;
        echo "[" . date('Y-m-d H:i:s') . "] Received: $body\n";

        try {
            $data = json_decode($body, true);

            if (!$data || !isset($data['action'])) {
                echo "  Invalid message format, skipping\n";
                $msg->ack();
                return;
            }

            // Save to database
            $activityLog = $this->saveToDatabase($data);

            if ($activityLog) {
                echo "  Saved to database (ID: {$activityLog->id})\n";

                // Broadcast to WebSocket clients
                $this->broadcastToWebSocket($activityLog);
                echo "  Broadcasted to WebSocket\n";
            }

            $msg->ack();

        } catch (\Exception $e) {
            echo "  Error processing message: " . $e->getMessage() . "\n";
            // Negative acknowledge - requeue the message
            $msg->nack(true);
        }
    }

    private function saveToDatabase(array $data)
    {
        $log = new ActivityLog();
        $log->action = $data['action'];
        $log->task_id = $data['task_id'] ?? null;
        $log->task_title = $data['task_title'] ?? null;
        $log->from_status = $data['from_status'] ?? null;
        $log->to_status = $data['to_status'] ?? null;
        $log->details = isset($data['details']) ? json_encode($data['details']) : null;

        if ($log->save()) {
            return $log;
        }

        echo "  Failed to save: " . json_encode($log->errors) . "\n";
        return null;
    }

    private function broadcastToWebSocket(ActivityLog $log)
    {
        $wsUrl = "ws://{$this->wsHost}:{$this->wsPort}";

        try {
            $client = new WebSocketClient($wsUrl, [
                'timeout' => 5,
            ]);

            $message = json_encode([
                'type' => 'activity:new',
                'payload' => $log->toArray(),
            ]);

            $client->text($message);
            $client->close();

        } catch (\Exception $e) {
            echo "  WebSocket broadcast error: " . $e->getMessage() . "\n";
        }
    }

    private function cleanup()
    {
        if ($this->channel) {
            $this->channel->close();
        }
        if ($this->connection) {
            $this->connection->close();
        }
    }

    public function __destruct()
    {
        $this->cleanup();
    }
}

// Handle shutdown signals
if (function_exists('pcntl_signal')) {
    pcntl_signal(SIGTERM, function () {
        echo "\nReceived SIGTERM, shutting down...\n";
        exit(0);
    });
    pcntl_signal(SIGINT, function () {
        echo "\nReceived SIGINT, shutting down...\n";
        exit(0);
    });
}

// Run worker
$worker = new ActivityWorker();
$worker->run();
