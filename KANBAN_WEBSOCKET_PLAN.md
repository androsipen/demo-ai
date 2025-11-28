# Kanban Board WebSocket Real-Time Sync Plan

This document outlines the implementation plan for adding WebSocket functionality to the Kanban board, enabling real-time synchronization across all connected clients.

---

## Overview

When a user moves, creates, or deletes a task, all other connected clients will instantly see the change without refreshing the page.

```
┌─────────────┐     ┌─────────────┐     ┌─────────────┐
│  Client A   │     │  WebSocket  │     │  Client B   │
│  (Browser)  │────▶│   Server    │────▶│  (Browser)  │
└─────────────┘     └─────────────┘     └─────────────┘
      │                   │                    │
      │   1. Move task    │                    │
      │──────────────────▶│                    │
      │                   │  2. Broadcast      │
      │                   │───────────────────▶│
      │                   │                    │
      │                   │    3. Update UI    │
      │                   │                    │
```

---

## Phase 1: Choose WebSocket Technology

### Option A: Ratchet (PHP - Recommended for Yii2)
Pure PHP WebSocket library, keeps the stack consistent.

```bash
composer require cboden/ratchet
```

**Pros:**
- Same language as Yii2
- Can share models and business logic
- No additional runtime required

**Cons:**
- PHP not ideal for long-running processes
- Requires separate process management (Supervisor)

### Option B: Node.js with Socket.IO
Dedicated WebSocket server in Node.js.

```bash
npm init -y
npm install socket.io express
```

**Pros:**
- Excellent WebSocket support
- Built-in reconnection, rooms, namespaces
- Better suited for real-time applications

**Cons:**
- Additional technology in stack
- Need to communicate between PHP and Node.js (Redis pub/sub)

### Option C: Soketi (Laravel Echo Server Alternative)
Self-hosted WebSocket server compatible with Pusher protocol.

```bash
docker pull quay.io/soketi/soketi:latest
```

**Pros:**
- Drop-in Pusher replacement
- Works with existing Pusher JS client
- Easy Docker deployment

**Cons:**
- Additional service to manage

### Recommendation
**Option A (Ratchet)** for simplicity and PHP consistency, or **Option B (Node.js + Socket.IO)** for better real-time performance and features.

---

## Phase 2: Server Architecture

### 2.1 Using Ratchet (PHP)

#### Directory Structure
```
├── websocket/
│   ├── server.php              # WebSocket server entry point
│   └── KanbanWebSocket.php     # WebSocket handler class
├── config/
│   └── websocket.php           # WebSocket configuration
```

#### WebSocket Server (`websocket/KanbanWebSocket.php`)
```php
<?php

namespace app\websocket;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class KanbanWebSocket implements MessageComponentInterface
{
    protected $clients;

    public function __construct()
    {
        $this->clients = new \SplObjectStorage;
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $this->clients->attach($conn);
        echo "New connection: {$conn->resourceId}\n";
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        $data = json_decode($msg, true);

        // Broadcast to all OTHER clients
        foreach ($this->clients as $client) {
            if ($from !== $client) {
                $client->send($msg);
            }
        }
    }

    public function onClose(ConnectionInterface $conn)
    {
        $this->clients->detach($conn);
        echo "Connection closed: {$conn->resourceId}\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "Error: {$e->getMessage()}\n";
        $conn->close();
    }
}
```

#### Server Entry Point (`websocket/server.php`)
```php
<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use app\websocket\KanbanWebSocket;

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new KanbanWebSocket()
        )
    ),
    8081  // WebSocket port
);

echo "WebSocket server started on port 8081\n";
$server->run();
```

### 2.2 Using Node.js + Socket.IO

#### Directory Structure
```
├── websocket-server/
│   ├── package.json
│   ├── server.js
│   └── Dockerfile
```

#### Socket.IO Server (`websocket-server/server.js`)
```javascript
const express = require('express');
const http = require('http');
const { Server } = require('socket.io');

const app = express();
const server = http.createServer(app);
const io = new Server(server, {
    cors: {
        origin: "http://localhost:8080",
        methods: ["GET", "POST"]
    }
});

io.on('connection', (socket) => {
    console.log('Client connected:', socket.id);

    // Join kanban room
    socket.join('kanban');

    // Handle task moved event
    socket.on('task:moved', (data) => {
        // Broadcast to all other clients in the room
        socket.to('kanban').emit('task:moved', data);
    });

    // Handle task created event
    socket.on('task:created', (data) => {
        socket.to('kanban').emit('task:created', data);
    });

    // Handle task deleted event
    socket.on('task:deleted', (data) => {
        socket.to('kanban').emit('task:deleted', data);
    });

    socket.on('disconnect', () => {
        console.log('Client disconnected:', socket.id);
    });
});

const PORT = process.env.PORT || 3000;
server.listen(PORT, () => {
    console.log(`WebSocket server running on port ${PORT}`);
});
```

---

## Phase 3: Docker Configuration

### 3.1 Update docker-compose.yml

```yaml
version: '3.8'

services:
  postgres:
    image: postgres:16-alpine
    container_name: kanban_postgres
    environment:
      POSTGRES_USER: kanban_user
      POSTGRES_PASSWORD: kanban123
      POSTGRES_DB: kanban_db
    ports:
      - "5433:5432"
    volumes:
      - kanban_pgdata:/var/lib/postgresql/data
    restart: unless-stopped

  # Option A: PHP Ratchet WebSocket Server
  websocket-php:
    build:
      context: .
      dockerfile: Dockerfile.websocket
    container_name: kanban_websocket
    ports:
      - "8081:8081"
    volumes:
      - .:/app
    depends_on:
      - postgres
    restart: unless-stopped

  # Option B: Node.js Socket.IO Server
  websocket-node:
    build:
      context: ./websocket-server
      dockerfile: Dockerfile
    container_name: kanban_websocket_node
    ports:
      - "3000:3000"
    environment:
      - PORT=3000
    restart: unless-stopped

volumes:
  kanban_pgdata:
```

### 3.2 Dockerfile for PHP WebSocket (`Dockerfile.websocket`)
```dockerfile
FROM php:8.2-cli

WORKDIR /app

RUN docker-php-ext-install pcntl

COPY . /app

CMD ["php", "websocket/server.php"]
```

### 3.3 Dockerfile for Node.js (`websocket-server/Dockerfile`)
```dockerfile
FROM node:20-alpine

WORKDIR /app

COPY package*.json ./
RUN npm install

COPY . .

EXPOSE 3000

CMD ["node", "server.js"]
```

---

## Phase 4: Client-Side Integration

### 4.1 WebSocket Client Service

Create `web/js/kanban-websocket.js`:

```javascript
class KanbanWebSocket {
    constructor(url) {
        this.url = url;
        this.socket = null;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 5;
        this.reconnectDelay = 1000;
        this.listeners = {};
    }

    connect() {
        this.socket = new WebSocket(this.url);

        this.socket.onopen = () => {
            console.log('WebSocket connected');
            this.reconnectAttempts = 0;
            this.emit('connected');
        };

        this.socket.onmessage = (event) => {
            const data = JSON.parse(event.data);
            this.handleMessage(data);
        };

        this.socket.onclose = () => {
            console.log('WebSocket disconnected');
            this.emit('disconnected');
            this.attemptReconnect();
        };

        this.socket.onerror = (error) => {
            console.error('WebSocket error:', error);
        };
    }

    attemptReconnect() {
        if (this.reconnectAttempts < this.maxReconnectAttempts) {
            this.reconnectAttempts++;
            console.log(`Reconnecting... Attempt ${this.reconnectAttempts}`);
            setTimeout(() => this.connect(), this.reconnectDelay * this.reconnectAttempts);
        }
    }

    send(type, payload) {
        if (this.socket && this.socket.readyState === WebSocket.OPEN) {
            this.socket.send(JSON.stringify({ type, payload }));
        }
    }

    on(event, callback) {
        if (!this.listeners[event]) {
            this.listeners[event] = [];
        }
        this.listeners[event].push(callback);
    }

    emit(event, data) {
        if (this.listeners[event]) {
            this.listeners[event].forEach(callback => callback(data));
        }
    }

    handleMessage(data) {
        switch (data.type) {
            case 'task:moved':
                this.emit('task:moved', data.payload);
                break;
            case 'task:created':
                this.emit('task:created', data.payload);
                break;
            case 'task:deleted':
                this.emit('task:deleted', data.payload);
                break;
        }
    }

    // Convenience methods
    taskMoved(taskId, fromStatus, toStatus) {
        this.send('task:moved', { taskId, fromStatus, toStatus });
    }

    taskCreated(task) {
        this.send('task:created', task);
    }

    taskDeleted(taskId) {
        this.send('task:deleted', { taskId });
    }
}
```

### 4.2 Socket.IO Client (if using Node.js)

Add to view or asset bundle:
```html
<script src="https://cdn.socket.io/4.7.2/socket.io.min.js"></script>
```

```javascript
const socket = io('http://localhost:3000');

socket.on('connect', () => {
    console.log('Connected to WebSocket server');
});

socket.on('task:moved', (data) => {
    // Update UI when another client moves a task
    moveTaskInUI(data.taskId, data.toStatus);
});

// When current user moves a task
function onTaskMoved(taskId, fromStatus, toStatus) {
    socket.emit('task:moved', { taskId, fromStatus, toStatus });
}
```

---

## Phase 5: Update Kanban View

### 5.1 Integrate WebSocket into View

Update `views/kanban/index.php`:

```php
<?php
// Register WebSocket JavaScript
$wsUrl = 'ws://localhost:8081';  // or ws://localhost:3000 for Socket.IO
$this->registerJsFile('@web/js/kanban-websocket.js', ['position' => \yii\web\View::POS_HEAD]);
?>
```

### 5.2 Updated JavaScript with WebSocket

```javascript
document.addEventListener('DOMContentLoaded', function() {
    // Initialize WebSocket
    const ws = new KanbanWebSocket('ws://localhost:8081');
    ws.connect();

    // Listen for remote task moves
    ws.on('task:moved', function(data) {
        moveTaskInDOM(data.taskId, data.toStatus);
        updateTaskCounts();
    });

    ws.on('task:created', function(data) {
        addTaskToDOM(data.task);
        updateTaskCounts();
    });

    ws.on('task:deleted', function(data) {
        removeTaskFromDOM(data.taskId);
        updateTaskCounts();
    });

    // Connection status indicator
    ws.on('connected', function() {
        showConnectionStatus('connected');
    });

    ws.on('disconnected', function() {
        showConnectionStatus('disconnected');
    });

    // Existing drag-and-drop code...
    function handleDrop(e) {
        // ... existing code ...

        if (draggedTask) {
            const newStatus = this.dataset.status;
            const oldStatus = draggedTask.closest('.kanban-column').dataset.status;
            const taskId = draggedTask.dataset.taskId;

            // Move in DOM
            this.appendChild(draggedTask);
            updateTaskCounts();

            // Save to server
            fetch(moveTaskUrl, { /* ... */ });

            // Broadcast to other clients
            ws.taskMoved(taskId, oldStatus, newStatus);
        }
    }

    // Helper functions
    function moveTaskInDOM(taskId, toStatus) {
        const task = document.querySelector(`[data-task-id="${taskId}"]`);
        const targetColumn = document.querySelector(`[data-status="${toStatus}"] .kanban-column-body`);
        if (task && targetColumn) {
            targetColumn.appendChild(task);
        }
    }

    function addTaskToDOM(taskData) {
        const column = document.querySelector(`[data-status="${taskData.status}"] .kanban-column-body`);
        if (column) {
            const taskEl = createTaskElement(taskData);
            column.appendChild(taskEl);
        }
    }

    function removeTaskFromDOM(taskId) {
        const task = document.querySelector(`[data-task-id="${taskId}"]`);
        if (task) {
            task.remove();
        }
    }

    function showConnectionStatus(status) {
        // Add visual indicator for connection status
        const indicator = document.querySelector('.ws-status');
        if (indicator) {
            indicator.className = 'ws-status ws-status--' + status;
        }
    }
});
```

---

## Phase 6: Connection Status UI

### 6.1 Add Status Indicator

```html
<div class="ws-status ws-status--connecting">
    <span class="ws-status__dot"></span>
    <span class="ws-status__text">Connecting...</span>
</div>
```

### 6.2 Status CSS

```css
.ws-status {
    position: fixed;
    top: 70px;
    right: 20px;
    padding: 8px 12px;
    border-radius: 20px;
    font-size: 12px;
    display: flex;
    align-items: center;
    gap: 6px;
    z-index: 1000;
}

.ws-status__dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
}

.ws-status--connected {
    background: #d4edda;
    color: #155724;
}
.ws-status--connected .ws-status__dot {
    background: #28a745;
}

.ws-status--disconnected {
    background: #f8d7da;
    color: #721c24;
}
.ws-status--disconnected .ws-status__dot {
    background: #dc3545;
}

.ws-status--connecting {
    background: #fff3cd;
    color: #856404;
}
.ws-status--connecting .ws-status__dot {
    background: #ffc107;
    animation: pulse 1s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}
```

---

## Phase 7: Process Management

### 7.1 Using Supervisor (for PHP Ratchet)

Install Supervisor:
```bash
# macOS
brew install supervisor

# Ubuntu/Debian
sudo apt-get install supervisor
```

Create `/etc/supervisor/conf.d/kanban-websocket.conf`:
```ini
[program:kanban-websocket]
command=php /path/to/project/websocket/server.php
autostart=true
autorestart=true
stderr_logfile=/var/log/kanban-websocket.err.log
stdout_logfile=/var/log/kanban-websocket.out.log
```

### 7.2 Using PM2 (for Node.js)

```bash
npm install -g pm2
pm2 start websocket-server/server.js --name kanban-ws
pm2 save
pm2 startup
```

---

## Phase 8: Security Considerations

### 8.1 WebSocket Authentication

```javascript
// Client: Send auth token on connect
const ws = new WebSocket('ws://localhost:8081?token=' + authToken);

// Server: Validate token
public function onOpen(ConnectionInterface $conn)
{
    $queryString = $conn->httpRequest->getUri()->getQuery();
    parse_str($queryString, $params);

    if (!$this->validateToken($params['token'] ?? '')) {
        $conn->close();
        return;
    }

    $this->clients->attach($conn);
}
```

### 8.2 Rate Limiting

```php
protected $messageCount = [];
protected $rateLimit = 100; // messages per minute

public function onMessage(ConnectionInterface $from, $msg)
{
    $id = $from->resourceId;
    $this->messageCount[$id] = ($this->messageCount[$id] ?? 0) + 1;

    if ($this->messageCount[$id] > $this->rateLimit) {
        $from->send(json_encode(['error' => 'Rate limit exceeded']));
        return;
    }

    // Process message...
}
```

### 8.3 WSS (WebSocket Secure)

For production, use WSS with SSL certificates:
```javascript
const ws = new WebSocket('wss://yourdomain.com/ws');
```

---

## Implementation Checklist

### Server Setup
- [ ] Choose WebSocket technology (Ratchet or Socket.IO)
- [ ] Install dependencies
- [ ] Create WebSocket server class
- [ ] Create server entry point
- [ ] Add Docker configuration
- [ ] Set up process manager (Supervisor/PM2)

### Client Integration
- [ ] Create WebSocket client class
- [ ] Add connection status indicator
- [ ] Integrate with drag-and-drop handlers
- [ ] Handle reconnection logic
- [ ] Add error handling

### Event Handlers
- [ ] task:moved - broadcast task status changes
- [ ] task:created - broadcast new tasks
- [ ] task:deleted - broadcast task deletions
- [ ] task:updated - broadcast task edits (optional)

### Testing
- [ ] Test with multiple browser windows
- [ ] Test reconnection after disconnect
- [ ] Test with network latency
- [ ] Load test with multiple concurrent users

### Security
- [ ] Implement authentication
- [ ] Add rate limiting
- [ ] Enable WSS for production
- [ ] Validate message payloads

---

## Quick Start Commands

```bash
# Option A: PHP Ratchet
composer require cboden/ratchet
php websocket/server.php

# Option B: Node.js Socket.IO
cd websocket-server
npm install
node server.js

# Docker (both options)
docker-compose up -d websocket-php   # or websocket-node
```

---

## File Changes Summary

| File | Action |
|------|--------|
| `composer.json` | Add Ratchet dependency (Option A) |
| `websocket/server.php` | Create - Server entry point |
| `websocket/KanbanWebSocket.php` | Create - WebSocket handler |
| `websocket-server/server.js` | Create - Node.js server (Option B) |
| `websocket-server/package.json` | Create - Node dependencies |
| `docker-compose.yml` | Modify - Add WebSocket service |
| `web/js/kanban-websocket.js` | Create - Client WebSocket class |
| `views/kanban/index.php` | Modify - Add WebSocket integration |
| `web/css/site.css` | Modify - Add status indicator styles |

---

## Estimated Implementation Steps: 8

1. Choose and install WebSocket technology
2. Create WebSocket server
3. Configure Docker for WebSocket service
4. Create client-side WebSocket class
5. Integrate WebSocket with Kanban view
6. Add connection status indicator
7. Implement security measures
8. Test with multiple clients
