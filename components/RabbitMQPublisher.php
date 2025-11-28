<?php

namespace app\components;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Exception\AMQPExceptionInterface;
use yii\base\Component;
use Yii;

class RabbitMQPublisher extends Component
{
    public $host;
    public $port;
    public $user;
    public $password;
    public $vhost;
    public $queue;
    public $exchange;

    private $connection;
    private $channel;

    public function init()
    {
        parent::init();

        // Load defaults from config if not set
        $config = require Yii::getAlias('@app/config/rabbitmq.php');
        $this->host = $this->host ?: $config['host'];
        $this->port = $this->port ?: $config['port'];
        $this->user = $this->user ?: $config['user'];
        $this->password = $this->password ?: $config['password'];
        $this->vhost = $this->vhost ?: $config['vhost'];
        $this->queue = $this->queue ?: $config['queue'];
        $this->exchange = $this->exchange ?: $config['exchange'];
    }

    /**
     * Get or create connection
     */
    protected function getConnection()
    {
        if (!$this->connection || !$this->connection->isConnected()) {
            try {
                $this->connection = new AMQPStreamConnection(
                    $this->host,
                    $this->port,
                    $this->user,
                    $this->password,
                    $this->vhost
                );
            } catch (\Exception $e) {
                Yii::error("RabbitMQ connection failed: " . $e->getMessage(), 'rabbitmq');
                return null;
            }
        }
        return $this->connection;
    }

    /**
     * Get or create channel
     */
    protected function getChannel()
    {
        $connection = $this->getConnection();
        if (!$connection) {
            return null;
        }

        if (!$this->channel || !$this->channel->is_open()) {
            $this->channel = $connection->channel();

            // Declare exchange
            $this->channel->exchange_declare(
                $this->exchange,
                'fanout',
                false,
                true,
                false
            );

            // Declare queue
            $this->channel->queue_declare(
                $this->queue,
                false,
                true,
                false,
                false
            );

            // Bind queue to exchange
            $this->channel->queue_bind($this->queue, $this->exchange);
        }

        return $this->channel;
    }

    /**
     * Publish a message to RabbitMQ
     */
    public function publish(array $data)
    {
        $channel = $this->getChannel();
        if (!$channel) {
            Yii::warning("Cannot publish message - RabbitMQ not connected", 'rabbitmq');
            return false;
        }

        try {
            $message = new AMQPMessage(
                json_encode($data),
                [
                    'content_type' => 'application/json',
                    'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                ]
            );

            $channel->basic_publish($message, $this->exchange);
            Yii::info("Published message: " . json_encode($data), 'rabbitmq');
            return true;
        } catch (\Exception $e) {
            Yii::error("Failed to publish message: " . $e->getMessage(), 'rabbitmq');
            return false;
        }
    }

    /**
     * Publish task created event
     */
    public function publishTaskCreated($taskId, $title, $status)
    {
        return $this->publish([
            'action' => 'task_created',
            'task_id' => $taskId,
            'task_title' => $title,
            'to_status' => $status,
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Publish task moved event
     */
    public function publishTaskMoved($taskId, $title, $fromStatus, $toStatus)
    {
        return $this->publish([
            'action' => 'task_moved',
            'task_id' => $taskId,
            'task_title' => $title,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Publish task deleted event
     */
    public function publishTaskDeleted($taskId, $title)
    {
        return $this->publish([
            'action' => 'task_deleted',
            'task_id' => $taskId,
            'task_title' => $title,
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Close connection
     */
    public function close()
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
        $this->close();
    }
}
