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
docker-compose up -d             # Start PostgreSQL (port 5433)
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

**Controller** (`controllers/KanbanController.php`):
- `actionIndex` - Render board with statuses and tasks
- `actionMoveTask` - AJAX endpoint for drag-and-drop
- `actionCreateTask` - AJAX endpoint for new tasks
- `actionDeleteTask` - AJAX endpoint for deletion

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

### Real-Time Flow

1. User performs action (drag task, create task)
2. AJAX request saves to database
3. On success, WebSocket broadcasts event to other clients
4. Other clients update DOM without page refresh

## Configuration Files

- `config/db.php` - PostgreSQL connection (port 5433)
- `config/web.php` - Main app config (pretty URLs disabled)
- `docker-compose.yml` - PostgreSQL and WebSocket services

## Key URLs

- Web app: `http://localhost:8080/index.php?r=kanban/index`
- WebSocket: `ws://localhost:8081`

## Notes

- Pretty URLs are disabled; use query string format (`?r=controller/action`)
- CSRF token must be included in AJAX POST requests (both header and body)
- WebSocket server must be running separately from the web server
