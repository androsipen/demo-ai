/**
 * Kanban WebSocket Client
 * Handles real-time synchronization between multiple clients
 */
class KanbanWebSocket {
    constructor(url) {
        this.url = url;
        this.socket = null;
        this.clientId = null;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 10;
        this.reconnectDelay = 1000;
        this.listeners = {};
        this.isConnected = false;
    }

    /**
     * Connect to WebSocket server
     */
    connect() {
        try {
            this.socket = new WebSocket(this.url);
            this.emit('status', 'connecting');

            this.socket.onopen = () => {
                console.log('[WS] Connected to server');
                this.isConnected = true;
                this.reconnectAttempts = 0;
                this.emit('status', 'connected');
            };

            this.socket.onmessage = (event) => {
                try {
                    const data = JSON.parse(event.data);
                    this.handleMessage(data);
                } catch (e) {
                    console.error('[WS] Failed to parse message:', e);
                }
            };

            this.socket.onclose = (event) => {
                console.log('[WS] Disconnected from server');
                this.isConnected = false;
                this.emit('status', 'disconnected');
                this.attemptReconnect();
            };

            this.socket.onerror = (error) => {
                console.error('[WS] Error:', error);
                this.emit('status', 'error');
            };
        } catch (e) {
            console.error('[WS] Failed to connect:', e);
            this.emit('status', 'error');
            this.attemptReconnect();
        }
    }

    /**
     * Attempt to reconnect after disconnection
     */
    attemptReconnect() {
        if (this.reconnectAttempts < this.maxReconnectAttempts) {
            this.reconnectAttempts++;
            const delay = this.reconnectDelay * this.reconnectAttempts;
            console.log(`[WS] Reconnecting in ${delay}ms... (Attempt ${this.reconnectAttempts})`);
            this.emit('status', 'reconnecting');
            setTimeout(() => this.connect(), delay);
        } else {
            console.error('[WS] Max reconnection attempts reached');
            this.emit('status', 'failed');
        }
    }

    /**
     * Send message to server
     */
    send(type, payload) {
        if (this.socket && this.socket.readyState === WebSocket.OPEN) {
            const message = JSON.stringify({ type, payload });
            this.socket.send(message);
            console.log('[WS] Sent:', type, payload);
        } else {
            console.warn('[WS] Cannot send message - not connected');
        }
    }

    /**
     * Register event listener
     */
    on(event, callback) {
        if (!this.listeners[event]) {
            this.listeners[event] = [];
        }
        this.listeners[event].push(callback);
        return this;
    }

    /**
     * Remove event listener
     */
    off(event, callback) {
        if (this.listeners[event]) {
            this.listeners[event] = this.listeners[event].filter(cb => cb !== callback);
        }
        return this;
    }

    /**
     * Emit event to listeners
     */
    emit(event, data) {
        if (this.listeners[event]) {
            this.listeners[event].forEach(callback => {
                try {
                    callback(data);
                } catch (e) {
                    console.error('[WS] Error in listener:', e);
                }
            });
        }
    }

    /**
     * Handle incoming message from server
     */
    handleMessage(data) {
        console.log('[WS] Received:', data.type, data.payload);

        switch (data.type) {
            case 'connected':
                this.clientId = data.payload.clientId;
                this.emit('connected', data.payload);
                break;

            case 'client:joined':
            case 'client:left':
                this.emit('clients:changed', data.payload);
                break;

            case 'task:moved':
                this.emit('task:moved', data.payload);
                break;

            case 'task:created':
                this.emit('task:created', data.payload);
                break;

            case 'task:deleted':
                this.emit('task:deleted', data.payload);
                break;

            case 'task:updated':
                this.emit('task:updated', data.payload);
                break;

            case 'error':
                console.error('[WS] Server error:', data.payload.message);
                this.emit('error', data.payload);
                break;

            default:
                console.log('[WS] Unknown message type:', data.type);
        }
    }

    /**
     * Broadcast task moved event
     */
    taskMoved(taskId, fromStatus, toStatus) {
        this.send('task:moved', {
            taskId: taskId,
            fromStatus: fromStatus,
            toStatus: toStatus,
            timestamp: Date.now()
        });
    }

    /**
     * Broadcast task created event
     */
    taskCreated(task) {
        this.send('task:created', {
            task: task,
            timestamp: Date.now()
        });
    }

    /**
     * Broadcast task deleted event
     */
    taskDeleted(taskId) {
        this.send('task:deleted', {
            taskId: taskId,
            timestamp: Date.now()
        });
    }

    /**
     * Broadcast task updated event
     */
    taskUpdated(task) {
        this.send('task:updated', {
            task: task,
            timestamp: Date.now()
        });
    }

    /**
     * Disconnect from server
     */
    disconnect() {
        if (this.socket) {
            this.socket.close();
            this.socket = null;
        }
    }
}

// Export for use in modules or make global
if (typeof module !== 'undefined' && module.exports) {
    module.exports = KanbanWebSocket;
} else {
    window.KanbanWebSocket = KanbanWebSocket;
}
