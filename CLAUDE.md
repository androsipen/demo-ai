# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a Yii2 Basic Application extended with a real-time Kanban board feature. The Kanban board uses PostgreSQL for data persistence and Ratchet WebSockets for real-time synchronization across multiple clients.

## Common Commands

### Development Server
```bash
php yii serve                    # Start web server on port 8080
php yii serve --port=8000        # Custom port
```

### Database
```bash
docker-compose up -d             # Start PostgreSQL and RabbitMQ
docker-compose down              # Stop services
docker-compose down -v           # Reset database completely
php yii migrate                  # Run migrations
php yii migrate/down             # Rollback last migration
docker exec -it kanban_postgres psql -U kanban_user -d kanban_db  # Connect to DB
```

### WebSocket Server
```bash
php websocket/server.php         # Start WebSocket server (port 8081)
docker-compose up -d websocket   # Start via Docker
```

### RabbitMQ Activity Worker
```bash
php workers/activity_worker.php  # Start activity log worker
```

### Testing
```bash
vendor/bin/codecept run              # Run all tests
vendor/bin/codecept run unit         # Unit tests only
vendor/bin/codecept run functional   # Functional tests only
```

## Architecture

### Kanban Board Components

**Models** (`models/`):
- `KanbanStatus` - Board columns (backlog, todo, in_progress, done)
- `KanbanTask` - Tasks with foreign key to status
- `ActivityLog` - Activity feed entries with action descriptions

**Controller** (`controllers/KanbanController.php`):
- `actionIndex` - Render board with statuses and tasks
- `actionMoveTask` - AJAX endpoint for drag-and-drop (publishes to RabbitMQ)
- `actionCreateTask` - AJAX endpoint for new tasks (publishes to RabbitMQ)
- `actionDeleteTask` - AJAX endpoint for deletion (publishes to RabbitMQ)
- `actionActivityFeed` - JSON endpoint for activity feed data

**Components** (`components/`):
- `RabbitMQPublisher` - Publishes events to RabbitMQ queue

**Workers** (`workers/`):
- `activity_worker.php` - Consumes RabbitMQ messages, writes to DB, broadcasts via WebSocket

**WebSocket** (`websocket/`):
- `server.php` - Entry point, runs on port 8081
- `KanbanWebSocket.php` - Ratchet handler, broadcasts events to all clients

**Client-Side** (`web/js/kanban-websocket.js`):
- WebSocket client class with auto-reconnect
- Event handlers for task:moved, task:created, task:deleted

### Database Schema

PostgreSQL running on port 5433 (to avoid conflicts with local installations):
- `kanban_status` - id, key, label, color, sort_order
- `kanban_task` - id, title, description, status_id (FK), sort_order, timestamps
- `activity_log` - id, action, task_id, task_title, from_status, to_status, details, created_at

### Real-Time Flow

1. User performs action (drag task, create task)
2. AJAX request saves to database
3. On success, WebSocket broadcasts event to other clients
4. Other clients update DOM without page refresh

### Activity Feed Flow (RabbitMQ)

1. User performs action (move/create/delete task)
2. Controller publishes event to RabbitMQ exchange
3. Activity worker consumes message from queue
4. Worker writes activity to `activity_log` table
5. Worker broadcasts `activity:new` event via WebSocket
6. All clients receive and display new activity in feed

## Configuration Files

- `config/db.php` - PostgreSQL connection (port 5433)
- `config/web.php` - Main app config (pretty URLs disabled, RabbitMQ component)
- `config/rabbitmq.php` - RabbitMQ connection settings
- `docker-compose.yml` - PostgreSQL, WebSocket, and RabbitMQ services

## Key URLs

- Web app: `http://localhost:8080/index.php?r=kanban/index`
- WebSocket: `ws://localhost:8081`
- RabbitMQ Management: `http://localhost:15672` (kanban_user / kanban123)

## Notes

- Pretty URLs are disabled; use query string format (`?r=controller/action`)
- CSRF token must be included in AJAX POST requests (both header and body)
- WebSocket server must be running separately from the web server
