<?php

/** @var yii\web\View $this */
/** @var app\models\KanbanStatus[] $statuses */

use yii\helpers\Html;

$this->title = 'Kanban Board';
$this->params['breadcrumbs'][] = $this->title;

// Register WebSocket client script
$this->registerJsFile('@web/js/kanban-websocket.js', ['position' => \yii\web\View::POS_HEAD]);
?>

<div class="kanban-index">
    <div class="kanban-header">
        <h1><?= Html::encode($this->title) ?></h1>
        <div class="ws-status ws-status--connecting">
            <span class="ws-status__dot"></span>
            <span class="ws-status__text">Connecting...</span>
            <span class="ws-status__clients"></span>
        </div>
    </div>

    <div class="kanban-board">
        <?php foreach ($statuses as $status): ?>
            <div class="kanban-column" data-status="<?= $status->key ?>">
                <div class="kanban-column-header" style="background-color: <?= $status->color ?>">
                    <span class="column-title"><?= Html::encode($status->label) ?></span>
                    <span class="task-count"><?= count($status->tasks) ?></span>
                </div>
                <div class="kanban-column-body" data-status="<?= $status->key ?>">
                    <?php foreach ($status->tasks as $task): ?>
                        <div class="kanban-task" draggable="true" data-task-id="<?= $task->id ?>">
                            <div class="task-title"><?= Html::encode($task->title) ?></div>
                            <div class="task-description"><?= Html::encode($task->description) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="kanban-column-footer">
                    <button class="btn-add-task" data-status="<?= $status->key ?>">
                        <span class="btn-add-task__icon">+</span>
                        <span class="btn-add-task__text">Add Task</span>
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Add Task Modal -->
<div class="modal-overlay" id="taskModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Add New Task</h3>
            <button class="modal-close" id="modalClose">&times;</button>
        </div>
        <form id="addTaskForm">
            <div class="modal-body">
                <div class="form-group">
                    <label for="taskTitle">Title <span class="required">*</span></label>
                    <input type="text" id="taskTitle" name="title" class="form-control" required placeholder="Enter task title">
                </div>
                <div class="form-group">
                    <label for="taskDescription">Description</label>
                    <textarea id="taskDescription" name="description" class="form-control" rows="3" placeholder="Enter task description (optional)"></textarea>
                </div>
                <input type="hidden" id="taskStatus" name="status" value="">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="modalCancel">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Task</button>
            </div>
        </form>
    </div>
</div>

<?php
$moveTaskUrl = \yii\helpers\Url::to(['kanban/move-task']);
$createTaskUrl = \yii\helpers\Url::to(['kanban/create-task']);
$wsUrl = 'ws://localhost:8081';

$css = <<<CSS
.kanban-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.kanban-header h1 {
    margin: 0;
}

.ws-status {
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 13px;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
}

.ws-status__dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    transition: background-color 0.3s ease;
}

.ws-status__clients {
    font-size: 11px;
    opacity: 0.8;
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

.ws-status--connecting,
.ws-status--reconnecting {
    background: #fff3cd;
    color: #856404;
}
.ws-status--connecting .ws-status__dot,
.ws-status--reconnecting .ws-status__dot {
    background: #ffc107;
    animation: pulse 1s infinite;
}

.ws-status--error,
.ws-status--failed {
    background: #f8d7da;
    color: #721c24;
}
.ws-status--error .ws-status__dot,
.ws-status--failed .ws-status__dot {
    background: #dc3545;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.3; }
}

.kanban-board {
    display: flex;
    gap: 16px;
    padding: 20px 0;
    overflow-x: auto;
    min-height: 70vh;
}

.kanban-column {
    flex: 1;
    min-width: 280px;
    max-width: 320px;
    background: #f8f9fa;
    border-radius: 8px;
    display: flex;
    flex-direction: column;
}

.kanban-column-header {
    padding: 12px 16px;
    border-radius: 8px 8px 0 0;
    color: white;
    font-weight: 600;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.kanban-column-header .task-count {
    background: rgba(255,255,255,0.3);
    padding: 2px 10px;
    border-radius: 12px;
    font-size: 0.85em;
}

.kanban-column-body {
    padding: 12px;
    flex: 1;
    min-height: 200px;
    transition: background-color 0.2s ease;
}

.kanban-column-body.drag-over {
    background-color: #e9ecef;
}

.kanban-column-footer {
    padding: 12px;
    border-top: 1px solid #e9ecef;
}

.btn-add-task {
    width: 100%;
    padding: 10px 16px;
    background: transparent;
    border: 2px dashed #dee2e6;
    border-radius: 6px;
    color: #6c757d;
    font-size: 14px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: all 0.2s ease;
}

.btn-add-task:hover {
    border-color: #0d6efd;
    color: #0d6efd;
    background: rgba(13, 110, 253, 0.05);
}

.btn-add-task__icon {
    font-size: 18px;
    font-weight: bold;
}

.kanban-task {
    background: white;
    border-radius: 6px;
    padding: 12px;
    margin-bottom: 10px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    cursor: grab;
    transition: transform 0.2s ease, box-shadow 0.2s ease, opacity 0.3s ease;
    border-left: 3px solid #dee2e6;
}

.kanban-task:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.kanban-task.dragging {
    opacity: 0.5;
    transform: rotate(3deg);
    cursor: grabbing;
}

.kanban-task.remote-update {
    animation: highlightTask 1s ease;
}

.kanban-task.new-task {
    animation: slideIn 0.3s ease;
}

@keyframes highlightTask {
    0% { background-color: #fff3cd; }
    100% { background-color: white; }
}

@keyframes slideIn {
    0% { opacity: 0; transform: translateY(-10px); }
    100% { opacity: 1; transform: translateY(0); }
}

.kanban-task .task-title {
    font-weight: 600;
    margin-bottom: 6px;
    color: #212529;
}

.kanban-task .task-description {
    font-size: 0.875em;
    color: #6c757d;
}

.kanban-column[data-status="backlog"] .kanban-task { border-left-color: #6c757d; }
.kanban-column[data-status="todo"] .kanban-task { border-left-color: #0d6efd; }
.kanban-column[data-status="in_progress"] .kanban-task { border-left-color: #ffc107; }
.kanban-column[data-status="done"] .kanban-task { border-left-color: #198754; }

/* Modal Styles */
.modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 10000;
    justify-content: center;
    align-items: center;
    animation: fadeIn 0.2s ease;
}

.modal-overlay.active {
    display: flex;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.modal-content {
    background: white;
    border-radius: 12px;
    width: 100%;
    max-width: 480px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    animation: slideUp 0.3s ease;
}

@keyframes slideUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    border-bottom: 1px solid #e9ecef;
}

.modal-title {
    margin: 0;
    font-size: 1.25rem;
    color: #212529;
}

.modal-close {
    background: none;
    border: none;
    font-size: 28px;
    color: #6c757d;
    cursor: pointer;
    padding: 0;
    line-height: 1;
    transition: color 0.2s;
}

.modal-close:hover {
    color: #212529;
}

.modal-body {
    padding: 24px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group:last-child {
    margin-bottom: 0;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #212529;
}

.form-group .required {
    color: #dc3545;
}

.form-control {
    width: 100%;
    padding: 10px 14px;
    font-size: 14px;
    border: 1px solid #ced4da;
    border-radius: 6px;
    transition: border-color 0.2s, box-shadow 0.2s;
    box-sizing: border-box;
}

.form-control:focus {
    outline: none;
    border-color: #0d6efd;
    box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.15);
}

.form-control::placeholder {
    color: #adb5bd;
}

textarea.form-control {
    resize: vertical;
    min-height: 80px;
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding: 16px 24px;
    border-top: 1px solid #e9ecef;
    background: #f8f9fa;
    border-radius: 0 0 12px 12px;
}

.btn {
    padding: 10px 20px;
    font-size: 14px;
    font-weight: 500;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s;
    border: none;
}

.btn-secondary {
    background: #e9ecef;
    color: #495057;
}

.btn-secondary:hover {
    background: #dee2e6;
}

.btn-primary {
    background: #0d6efd;
    color: white;
}

.btn-primary:hover {
    background: #0b5ed7;
}

.btn-primary:disabled {
    background: #6c757d;
    cursor: not-allowed;
}
CSS;

$js = <<<JS
document.addEventListener('DOMContentLoaded', function() {
    const moveTaskUrl = '{$moveTaskUrl}';
    const createTaskUrl = '{$createTaskUrl}';
    const wsUrl = '{$wsUrl}';

    // Initialize WebSocket
    const ws = new KanbanWebSocket(wsUrl);

    // Modal elements
    const modal = document.getElementById('taskModal');
    const modalClose = document.getElementById('modalClose');
    const modalCancel = document.getElementById('modalCancel');
    const addTaskForm = document.getElementById('addTaskForm');
    const taskTitleInput = document.getElementById('taskTitle');
    const taskDescriptionInput = document.getElementById('taskDescription');
    const taskStatusInput = document.getElementById('taskStatus');

    // Status indicator elements
    const statusEl = document.querySelector('.ws-status');
    const statusText = document.querySelector('.ws-status__text');
    const statusClients = document.querySelector('.ws-status__clients');

    // WebSocket event handlers
    ws.on('status', function(status) {
        statusEl.className = 'ws-status ws-status--' + status;
        const statusMessages = {
            'connecting': 'Connecting...',
            'connected': 'Connected',
            'disconnected': 'Disconnected',
            'reconnecting': 'Reconnecting...',
            'error': 'Connection Error',
            'failed': 'Connection Failed'
        };
        statusText.textContent = statusMessages[status] || status;
    });

    ws.on('connected', function(data) {
        console.log('Connected with client ID:', data.clientId);
        updateClientsCount(data.totalClients);
    });

    ws.on('clients:changed', function(data) {
        updateClientsCount(data.totalClients);
    });

    // Handle remote task moved
    ws.on('task:moved', function(data) {
        console.log('Remote task moved:', data);
        moveTaskInDOM(data.taskId, data.toStatus, true);
    });

    // Handle remote task created
    ws.on('task:created', function(data) {
        console.log('Remote task created:', data);
        addTaskToDOM(data.task, true);
    });

    // Connect to WebSocket server
    ws.connect();

    function updateClientsCount(count) {
        if (count > 1) {
            statusClients.textContent = '(' + count + ' users)';
        } else {
            statusClients.textContent = '';
        }
    }

    // Add Task Button Click Handlers
    document.querySelectorAll('.btn-add-task').forEach(button => {
        button.addEventListener('click', function() {
            const status = this.dataset.status;
            openModal(status);
        });
    });

    // Modal functions
    function openModal(status) {
        taskStatusInput.value = status;
        taskTitleInput.value = '';
        taskDescriptionInput.value = '';
        modal.classList.add('active');
        taskTitleInput.focus();
    }

    function closeModal() {
        modal.classList.remove('active');
        addTaskForm.reset();
    }

    modalClose.addEventListener('click', closeModal);
    modalCancel.addEventListener('click', closeModal);

    // Close modal on overlay click
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeModal();
        }
    });

    // Close modal on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal.classList.contains('active')) {
            closeModal();
        }
    });

    // Form submission
    addTaskForm.addEventListener('submit', function(e) {
        e.preventDefault();

        const title = taskTitleInput.value.trim();
        const description = taskDescriptionInput.value.trim();
        const status = taskStatusInput.value;

        if (!title) {
            taskTitleInput.focus();
            return;
        }

        const submitBtn = addTaskForm.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Adding...';

        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const csrfParam = document.querySelector('meta[name="csrf-param"]').getAttribute('content');

        fetch(createTaskUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-Token': csrfToken
            },
            body: csrfParam + '=' + encodeURIComponent(csrfToken) +
                  '&title=' + encodeURIComponent(title) +
                  '&description=' + encodeURIComponent(description) +
                  '&status=' + encodeURIComponent(status)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Add task to DOM
                addTaskToDOM(data.task, false);

                // Broadcast to other clients
                ws.taskCreated(data.task);

                // Close modal
                closeModal();
            } else {
                alert('Failed to create task: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Request failed:', error);
            alert('Failed to create task. Please try again.');
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Add Task';
        });
    });

    // Add task to DOM
    function addTaskToDOM(task, isRemote) {
        const column = document.querySelector('[data-status="' + task.status + '"] .kanban-column-body');
        if (!column) return;

        const taskEl = document.createElement('div');
        taskEl.className = 'kanban-task' + (isRemote ? ' remote-update' : ' new-task');
        taskEl.draggable = true;
        taskEl.dataset.taskId = task.id;
        taskEl.innerHTML = '<div class="task-title">' + escapeHtml(task.title) + '</div>' +
                          '<div class="task-description">' + escapeHtml(task.description || '') + '</div>';

        // Add drag event listeners
        taskEl.addEventListener('dragstart', handleDragStart);
        taskEl.addEventListener('dragend', handleDragEnd);

        column.appendChild(taskEl);
        updateTaskCounts();

        // Remove animation class after animation completes
        setTimeout(() => {
            taskEl.classList.remove('new-task', 'remote-update');
        }, 1000);
    }

    // Helper function to escape HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Move task in DOM (for remote updates)
    function moveTaskInDOM(taskId, toStatus, isRemote) {
        const task = document.querySelector('[data-task-id="' + taskId + '"]');
        const targetColumn = document.querySelector('[data-status="' + toStatus + '"] .kanban-column-body');

        if (task && targetColumn) {
            targetColumn.appendChild(task);
            updateTaskCounts();

            // Add highlight animation for remote updates
            if (isRemote) {
                task.classList.add('remote-update');
                setTimeout(() => task.classList.remove('remote-update'), 1000);
            }
        }
    }

    // Drag and drop setup
    const columns = document.querySelectorAll('.kanban-column-body');
    let draggedTask = null;

    function initDragAndDrop() {
        document.querySelectorAll('.kanban-task').forEach(task => {
            task.addEventListener('dragstart', handleDragStart);
            task.addEventListener('dragend', handleDragEnd);
        });
    }

    initDragAndDrop();

    columns.forEach(column => {
        column.addEventListener('dragover', handleDragOver);
        column.addEventListener('dragenter', handleDragEnter);
        column.addEventListener('dragleave', handleDragLeave);
        column.addEventListener('drop', handleDrop);
    });

    function handleDragStart(e) {
        draggedTask = this;
        this.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', this.dataset.taskId);
    }

    function handleDragEnd(e) {
        this.classList.remove('dragging');
        columns.forEach(col => col.classList.remove('drag-over'));
        draggedTask = null;
    }

    function handleDragOver(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
    }

    function handleDragEnter(e) {
        e.preventDefault();
        this.classList.add('drag-over');
    }

    function handleDragLeave(e) {
        if (!this.contains(e.relatedTarget)) {
            this.classList.remove('drag-over');
        }
    }

    function handleDrop(e) {
        e.preventDefault();
        this.classList.remove('drag-over');

        if (draggedTask) {
            const newStatus = this.dataset.status;
            const oldStatus = draggedTask.closest('.kanban-column').dataset.status;
            const taskId = draggedTask.dataset.taskId;
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            const csrfParam = document.querySelector('meta[name="csrf-param"]').getAttribute('content');

            // Only process if status actually changed
            if (oldStatus === newStatus) {
                return;
            }

            this.appendChild(draggedTask);
            updateTaskCounts();

            // Save to server
            fetch(moveTaskUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-Token': csrfToken
                },
                body: csrfParam + '=' + encodeURIComponent(csrfToken) + '&taskId=' + taskId + '&newStatus=' + newStatus
            }).then(response => response.json())
              .then(data => {
                  if (data.success) {
                      // Broadcast to other clients via WebSocket
                      ws.taskMoved(taskId, oldStatus, newStatus);
                  } else {
                      console.error('Failed to move task:', data.error);
                      // Revert the move in UI
                      const originalColumn = document.querySelector('[data-status="' + oldStatus + '"] .kanban-column-body');
                      if (originalColumn) {
                          originalColumn.appendChild(draggedTask);
                          updateTaskCounts();
                      }
                  }
              })
              .catch(error => {
                  console.error('Request failed:', error);
              });
        }
    }

    function updateTaskCounts() {
        document.querySelectorAll('.kanban-column').forEach(column => {
            const count = column.querySelector('.kanban-column-body').children.length;
            column.querySelector('.task-count').textContent = count;
        });
    }
});
JS;

$this->registerCss($css);
$this->registerJs($js, \yii\web\View::POS_END);
?>
