# Kanban Board PostgreSQL Integration Plan

This document outlines the steps to set up PostgreSQL using Docker and connect the Kanban Board to persist all changes to the database.

---

## Phase 1: PostgreSQL with Docker

### 1.1 Prerequisites
Ensure Docker is installed and running:
```bash
# Check Docker installation
docker --version

# If not installed, download from:
# https://www.docker.com/products/docker-desktop/
```

### 1.2 Create Docker Compose File
Create `docker-compose.yml` in project root (already exists, will be updated):
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
      - "5433:5432"  # Using 5433 to avoid conflicts with local PostgreSQL
    volumes:
      - kanban_pgdata:/var/lib/postgresql/data
    restart: unless-stopped

volumes:
  kanban_pgdata:
```

### 1.3 Start PostgreSQL Container
```bash
# Start the container
docker-compose up -d

# Verify it's running
docker ps

# View logs if needed
docker logs kanban_postgres
```

### 1.4 Useful Docker Commands
```bash
# Stop the container
docker-compose down

# Stop and remove data volume (reset database)
docker-compose down -v

# Connect to PostgreSQL CLI inside container
docker exec -it kanban_postgres psql -U kanban_user -d kanban_db

# Restart container
docker-compose restart
```

---

## Phase 2: Verify Database Connection

### 2.1 Test Connection from Host
```bash
# Using psql (if installed locally)
psql -h localhost -U kanban_user -d kanban_db

# Or connect via Docker
docker exec -it kanban_postgres psql -U kanban_user -d kanban_db
```

### 2.2 Verify Database Exists
```sql
-- Inside psql
\l                    -- List databases
\dt                   -- List tables (empty initially)
\q                    -- Quit
```

---

## Phase 3: Yii2 Configuration

### 3.1 Verify PHP PostgreSQL Extension
```bash
# Check if pdo_pgsql is installed
php -m | grep pgsql

# Should output:
# pdo_pgsql
# pgsql
```

### 3.2 Update Database Configuration
Edit `config/db.php`:
```php
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
```

### 3.3 Test Yii2 Connection
```bash
# This should connect without errors
php yii migrate/history
```

---

## Phase 4: Database Migrations

### 4.1 Create Migration for Statuses Table
```bash
php yii migrate/create create_kanban_status_table
```

Migration content:
```php
public function safeUp()
{
    $this->createTable('{{%kanban_status}}', [
        'id' => $this->primaryKey(),
        'key' => $this->string(50)->notNull()->unique(),
        'label' => $this->string(100)->notNull(),
        'color' => $this->string(7)->notNull(),
        'sort_order' => $this->integer()->defaultValue(0),
        'created_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP'),
    ]);

    // Insert default statuses
    $this->batchInsert('{{%kanban_status}}',
        ['key', 'label', 'color', 'sort_order'],
        [
            ['backlog', 'Backlog', '#6c757d', 1],
            ['todo', 'To Do', '#0d6efd', 2],
            ['in_progress', 'In Progress', '#ffc107', 3],
            ['done', 'Done', '#198754', 4],
        ]
    );
}

public function safeDown()
{
    $this->dropTable('{{%kanban_status}}');
}
```

### 4.2 Create Migration for Tasks Table
```bash
php yii migrate/create create_kanban_task_table
```

Migration content:
```php
public function safeUp()
{
    $this->createTable('{{%kanban_task}}', [
        'id' => $this->primaryKey(),
        'title' => $this->string(255)->notNull(),
        'description' => $this->text(),
        'status_id' => $this->integer()->notNull(),
        'sort_order' => $this->integer()->defaultValue(0),
        'created_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP'),
        'updated_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP'),
    ]);

    $this->addForeignKey(
        'fk-kanban_task-status_id',
        '{{%kanban_task}}',
        'status_id',
        '{{%kanban_status}}',
        'id',
        'RESTRICT',
        'CASCADE'
    );

    $this->createIndex('idx-kanban_task-status_id', '{{%kanban_task}}', 'status_id');
}

public function safeDown()
{
    $this->dropForeignKey('fk-kanban_task-status_id', '{{%kanban_task}}');
    $this->dropTable('{{%kanban_task}}');
}
```

### 4.3 Run Migrations
```bash
php yii migrate
```

---

## Phase 5: Create Models

### 5.1 KanbanStatus Model (`models/KanbanStatus.php`)
- Fields: id, key, label, color, sort_order
- Relation: hasMany → KanbanTask

### 5.2 KanbanTask Model (`models/KanbanTask.php`)
- Fields: id, title, description, status_id, sort_order
- Relation: belongsTo → KanbanStatus

---

## Phase 6: Update Controller

### 6.1 Modify KanbanController
Update `controllers/KanbanController.php` to:

1. **actionIndex()**: Fetch tasks from database grouped by status
2. **actionMoveTask()**: Update task's status_id in database
3. **actionCreateTask()**: Insert new task (optional)
4. **actionDeleteTask()**: Remove task (optional)

---

## Phase 7: Update View

### 7.1 Modify View to Use Database Data
Update `views/kanban/index.php` to:
- Loop through database records instead of hardcoded array
- Use model IDs for drag-and-drop identification

---

## Phase 8: Testing Checklist

- [ ] Docker is running
- [ ] PostgreSQL container is up (`docker ps`)
- [ ] Database connection works (`php yii migrate/history`)
- [ ] Migrations applied successfully (`php yii migrate`)
- [ ] Kanban board loads with database tasks
- [ ] Drag-and-drop updates persist after page refresh
- [ ] New tasks can be created (if implemented)
- [ ] Tasks can be deleted (if implemented)

---

## Quick Start Commands Summary

```bash
# 1. Start PostgreSQL container
docker-compose up -d

# 2. Verify container is running
docker ps

# 3. Run migrations (after creating migration files)
php yii migrate

# 4. Start Yii2 server
php yii serve

# 5. Open browser
open http://localhost:8080/kanban/index
```

---

## Troubleshooting

### Container won't start
```bash
# Check if port 5433 is already in use
lsof -i :5433

# View container logs
docker logs kanban_postgres
```

### Connection refused
```bash
# Ensure container is running
docker ps -a

# Restart container
docker-compose restart
```

### Reset database completely
```bash
# Remove container and volume
docker-compose down -v

# Start fresh
docker-compose up -d
```

---

## File Changes Summary

| File | Action |
|------|--------|
| `docker-compose.yml` | Modify - Add PostgreSQL service |
| `config/db.php` | Modify - PostgreSQL connection |
| `migrations/*_create_kanban_status_table.php` | Create |
| `migrations/*_create_kanban_task_table.php` | Create |
| `models/KanbanStatus.php` | Create |
| `models/KanbanTask.php` | Create |
| `controllers/KanbanController.php` | Modify |
| `views/kanban/index.php` | Modify |

---

## Estimated Implementation Steps: 7

1. Update docker-compose.yml and start PostgreSQL container
2. Configure Yii2 database connection
3. Create and run migrations
4. Create models
5. Update controller
6. Update view
7. Test end-to-end
