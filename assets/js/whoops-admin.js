jQuery(document).ready(function ($) {
    const TaskManager = {
        state: {
            tasks: [],
            loading: false,
            error: null
        },

        // Initialize the task manager
        init() {
            this.setupDOMElements();
            this.bindEvents();
            this.refreshState();
        },

        // Cache DOM elements
        setupDOMElements() {
            this.elements = {
                widget: $('.whoops-widget'),
                tasksContainer: $('.whoops-tasks'),
                addForm: $('.add-task-form'),
                newTaskInput: $('#new-task'),
                clearCompletedBtn: $('.clear-completed-button'),
                loadingOverlay: $('<div class="whoops-loading-overlay"><div class="spinner"></div></div>')
            };

            // Add loading overlay to widget
            this.elements.widget.append(this.elements.loadingOverlay);
            this.elements.loadingOverlay.hide();
        },

        // Bind event handlers
        bindEvents() {
            // Add task
            this.elements.addForm.on('submit', (e) => {
                e.preventDefault();
                const description = this.elements.newTaskInput.val().trim();
                if (description) {
                    this.createTask(description);
                }
            });

            // Task actions (using event delegation)
            this.elements.tasksContainer.on('change', '.task-checkbox', (e) => {
                const checkbox = $(e.target);
                const taskId = checkbox.closest('.task-item').data('task-id');
                this.updateTask(taskId, { completed: checkbox.prop('checked') ? 1 : 0 });
            });

            this.elements.tasksContainer.on('click', '.delete-task', (e) => {
                e.preventDefault();
                const taskId = $(e.target).closest('.task-item').data('task-id');
                if (confirm('Are you sure you want to delete this task?')) {
                    this.deleteTask(taskId);
                }
            });

            // Clear completed
            this.elements.clearCompletedBtn.on('click', (e) => {
                e.preventDefault();
                if (confirm('Are you sure you want to clear all completed tasks?')) {
                    this.clearCompleted();
                }
            });
        },

        // UI Rendering Methods
        render() {
            if (this.state.loading) {
                this.elements.loadingOverlay.fadeIn(200);
            } else {
                this.elements.loadingOverlay.fadeOut(200);
            }

            if (this.state.error) {
                alert(this.state.error);
                this.state.error = null;
            }

            this.renderTasks();
        },

        renderTasks() {
            if (this.state.tasks.length === 0) {
                this.elements.tasksContainer.html('<p class="no-tasks">No tasks yet. Add your first task below!</p>');
                this.elements.clearCompletedBtn.hide();
            } else {
                const taskListHtml = '<ul class="task-list">' +
                    this.state.tasks.map(task => this.createTaskElement(task)).join('') +
                    '</ul>';
                this.elements.tasksContainer.html(taskListHtml);
                this.elements.clearCompletedBtn.show();
            }
        },

        createTaskElement(task) {
            const isCompleted = parseInt(task.completed) === 1;
            return `
                <li class="task-item ${isCompleted ? 'completed' : ''}" data-task-id="${task.id}">
                    <label>
                        <input type="checkbox" 
                               class="task-checkbox"
                               ${isCompleted ? 'checked="checked"' : ''}>
                        <span class="task-description">
                            ${this.escapeHtml(task.task_description)}
                        </span>
                    </label>
                    <button class="delete-task" title="Delete task">×</button>
                </li>
            `;
        },

        // State Management Methods
        async refreshState() {
            try {
                const response = await $.ajax({
                    url: wpApiSettings.root + 'whoops/v1/tasks',
                    method: 'GET',
                    beforeSend: (xhr) => {
                        xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
                    }
                });

                this.state.tasks = response || [];
                this.render();
            } catch (error) {
                console.error('Refresh state error:', error);
                this.state.error = 'Failed to load tasks. Please refresh the page.';
                this.state.tasks = [];
                this.render();
            }
        },

        // Task Operations
        async createTask(description) {
            this.state.loading = true;
            this.render();

            try {
                await $.ajax({
                    url: wpApiSettings.root + 'whoops/v1/tasks',
                    method: 'POST',
                    beforeSend: (xhr) => {
                        xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
                    },
                    data: {
                        task_description: description
                    }
                });

                this.elements.newTaskInput.val('');
                await this.refreshState();
            } catch (error) {
                console.error('Create task error:', error);
                this.state.error = 'Failed to create task. Please try again.';
                await this.refreshState();
            } finally {
                this.state.loading = false;
                this.render();
            }
        },

        async updateTask(taskId, data) {
            this.state.loading = true;
            this.render();

            try {
                await $.ajax({
                    url: wpApiSettings.root + 'whoops/v1/tasks/' + taskId,
                    method: 'PUT',
                    beforeSend: (xhr) => {
                        xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
                    },
                    data: data
                });
                await this.refreshState();
            } catch (error) {
                console.error('Update task error:', error);
                this.state.error = 'Failed to update task. Please try again.';
                await this.refreshState();
            } finally {
                this.state.loading = false;
                this.render();
            }
        },

        async deleteTask(taskId) {
            this.state.loading = true;
            this.render();

            try {
                await $.ajax({
                    url: wpApiSettings.root + 'whoops/v1/tasks/' + taskId,
                    method: 'DELETE',
                    beforeSend: (xhr) => {
                        xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
                    }
                });
                await this.refreshState();
            } catch (error) {
                console.error('Delete task error:', error);
                this.state.error = 'Failed to delete task. Please try again.';
                await this.refreshState();
            } finally {
                this.state.loading = false;
                this.render();
            }
        },

        async clearCompleted() {
            this.state.loading = true;
            this.render();

            try {
                await $.ajax({
                    url: wpApiSettings.root + 'whoops/v1/tasks/clear-completed',
                    method: 'DELETE',
                    beforeSend: (xhr) => {
                        xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
                    }
                });
                await this.refreshState();
            } catch (error) {
                console.error('Clear completed error:', error);
                this.state.error = 'Failed to clear completed tasks. Please try again.';
                await this.refreshState();
            } finally {
                this.state.loading = false;
                this.render();
            }
        },

        // Utility Methods
        escapeHtml(unsafe) {
            return unsafe
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
    };

    // Initialize the TaskManager
    TaskManager.init();
}); 