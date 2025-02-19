jQuery(document).ready(function ($) {
    // Override Backbone's sync method to include WP REST API nonce
    const originalSync = Backbone.sync;
    Backbone.sync = function (method, model, options) {
        options.beforeSend = function (xhr) {
            xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
        };
        return originalSync.apply(this, arguments);
    };

    // Task Model
    const Task = Backbone.Model.extend({
        defaults: {
            task_description: '',
            completed: 0
        },
        urlRoot: wpApiSettings.root + 'whoops/v1/tasks',

        initialize: function () {
            // No need for the change:completed handler as toggle() handles it
        },

        toggle: function () {
            const currentState = parseInt(this.get('completed'), 10);
            this.save({
                completed: currentState === 1 ? 0 : 1
            }, {
                patch: true,
                wait: true
            });
        }
    });

    // Task Collection
    const TaskCollection = Backbone.Collection.extend({
        model: Task,
        url: wpApiSettings.root + 'whoops/v1/tasks',

        initialize: function () {
            // Remove the add/remove handlers as they're causing double saves
        },

        clearCompleted: function () {
            $.ajax({
                url: this.url + '/clear-completed',
                method: 'DELETE',
                beforeSend: (xhr) => {
                    xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
                }
            }).done(() => {
                this.fetch({ reset: true });
            });
        }
    });

    // Single Task View
    const TaskView = Backbone.View.extend({
        tagName: 'li',
        className: 'task-item',

        template: _.template(`
            <label>
                <input type="checkbox" class="task-checkbox" <%= completed == 1 ? 'checked="checked"' : '' %>>
                <span class="task-description"><%= task_description %></span>
            </label>
            <button class="delete-task" title="Delete task">×</button>
        `),

        events: {
            'change .task-checkbox': 'toggleComplete',
            'click .delete-task': 'deleteTask'
        },

        initialize: function () {
            this.listenTo(this.model, 'change', this.render);
            this.listenTo(this.model, 'destroy', this.remove);
        },

        render: function () {
            this.$el.html(this.template(this.model.toJSON()));
            this.$el.toggleClass('completed', this.model.get('completed') == 1);
            return this;
        },

        toggleComplete: function () {
            this.model.toggle();
        },

        deleteTask: function (e) {
            e.preventDefault();
            if (confirm('Are you sure you want to delete this task?')) {
                this.model.destroy({
                    wait: true,
                    error: function (model, response) {
                        if (response.status === 404) {
                            // Item already deleted, just remove the view
                            model.trigger('destroy', model);
                        }
                    }
                });
            }
        }
    });

    // Main App View
    const AppView = Backbone.View.extend({
        el: '.whoops-widget',

        events: {
            'submit .add-task-form': 'createTask',
            'click .clear-completed-button': 'clearCompleted',
            'click .clear-all-button': 'clearAll',
            'click .load-list-button': 'openListsModal',
            'click .close-modal': 'closeModal',
            'click #whoops-lists-modal': 'handleModalClick'
        },

        initialize: function () {
            this.tasks = new TaskCollection();
            this.$input = this.$('#new-task');
            this.$list = this.$('.whoops-tasks');
            this.$clearButton = this.$('.clear-completed-button');
            this.$loadingOverlay = this.$('.whoops-loading-overlay');
            this.$modal = this.$('#whoops-lists-modal');
            this.$listsContainer = this.$('.lists-container');

            this.listenTo(this.tasks, 'add', this.addOne);
            this.listenTo(this.tasks, 'reset', this.addAll);
            this.listenTo(this.tasks, 'all', this.render);

            this.tasks.fetch({ reset: true });
        },

        render: function () {
            const hasItems = this.tasks.length > 0;
            this.$clearButton.toggle(hasItems);

            if (!hasItems) {
                this.$list.html('<p class="no-tasks">No tasks yet. Add your first task below!</p>');
            }
        },

        addOne: function (task) {
            if (!this.$list.find('.task-list').length) {
                this.$list.html('<ul class="task-list"></ul>');
            }
            const view = new TaskView({ model: task });
            this.$list.find('.task-list').append(view.render().el);
        },

        addAll: function () {
            this.$list.empty();
            if (this.tasks.length > 0) {
                this.$list.html('<ul class="task-list"></ul>');
                this.tasks.each(this.addOne, this);
            } else {
                this.render();
            }
        },

        createTask: function (e) {
            e.preventDefault();
            const description = this.$input.val().trim();

            if (!description) {
                return;
            }

            this.tasks.create({
                task_description: description,
                completed: 0
            }, { wait: true });

            this.$input.val('');
        },

        clearCompleted: function () {
            if (confirm('Are you sure you want to clear all completed tasks?')) {
                this.tasks.clearCompleted();
            }
        },

        clearAll: function () {
            if (confirm('Are you sure you want to clear all tasks? This cannot be undone.')) {
                this.showLoading();

                $.ajax({
                    url: wpApiSettings.root + 'whoops/v1/tasks/clear-all',
                    method: 'DELETE',
                    beforeSend: (xhr) => {
                        xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
                    }
                })
                    .done(() => {
                        // Clear the collection after successful database clear
                        this.tasks.reset();
                    })
                    .fail((jqXHR) => {
                        console.error('Failed to clear tasks:', jqXHR.responseText || 'Unknown error');
                        // If we can't clear tasks, try to fetch them again to sync the UI
                        this.tasks.fetch({ reset: true });
                        alert('Failed to clear tasks. Please try again.');
                    })
                    .always(() => {
                        this.hideLoading();
                    });
            }
        },

        openListsModal: function () {
            this.showLoading();
            this.$modal.show();

            $.ajax({
                url: wpApiSettings.root + 'whoops/v1/checklists',
                method: 'GET',
                beforeSend: (xhr) => {
                    xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
                }
            })
                .done((response) => {
                    this.renderLists(response.lists);
                    this.bindListClickHandlers();
                })
                .fail((jqXHR) => {
                    this.$modal.hide();
                    if (jqXHR.status === 401) {
                        alert('Please configure your API token in Whoops settings.');
                    } else {
                        alert('Failed to load predefined lists. Please try again.');
                    }
                })
                .always(() => {
                    this.hideLoading();
                });
        },

        closeModal: function () {
            this.$modal.hide();
        },

        handleModalClick: function (e) {
            if ($(e.target).is(this.$modal)) {
                this.closeModal();
            }
        },

        renderLists: function (lists) {
            const listsHtml = lists.map(list => `
                <div class="list-item" data-list-name="${list.name}">
                    <h4>${list.name}</h4>
                    <p>${list.description}</p>
                </div>
            `).join('');

            this.$listsContainer.html(listsHtml);
        },

        bindListClickHandlers: function () {
            this.$listsContainer.find('.list-item').on('click', (e) => {
                const listName = $(e.currentTarget).data('list-name');
                this.loadSpecificList(listName);
            });
        },

        loadSpecificList: function (listName) {
            this.showLoading();

            // First fetch the list details
            $.ajax({
                url: wpApiSettings.root + 'whoops/v1/checklists/' + listName,
                method: 'GET',
                beforeSend: (xhr) => {
                    xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
                }
            })
                .done((response) => {
                    // Store tasks for later use
                    const tasks = response.tasks;

                    // Clear existing tasks using the collection's method
                    this.tasks.reset();

                    // Function to create tasks sequentially
                    const createTasks = () => {
                        let currentIndex = 0;

                        const createNextTask = () => {
                            if (currentIndex >= tasks.length) {
                                this.closeModal();
                                this.hideLoading();
                                return;
                            }

                            const taskDescription = tasks[currentIndex];

                            // Create task through the collection
                            this.tasks.create(
                                { task_description: taskDescription, completed: 0 },
                                {
                                    wait: true,
                                    success: () => {
                                        currentIndex++;
                                        createNextTask();
                                    },
                                    error: (model, xhr) => {
                                        console.error('Failed to create task:', taskDescription, xhr);
                                        currentIndex++;
                                        createNextTask();
                                    }
                                }
                            );
                        };

                        createNextTask();
                    };

                    // Delete all existing tasks first
                    $.ajax({
                        url: wpApiSettings.root + 'whoops/v1/tasks/clear-all',
                        method: 'DELETE',
                        beforeSend: (xhr) => {
                            xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
                        }
                    })
                        .done(() => {
                            // Start creating new tasks
                            createTasks();
                        })
                        .fail((jqXHR) => {
                            console.error('Failed to clear tasks:', jqXHR.responseText || 'Unknown error');
                            // If we can't clear tasks, try to fetch them again to sync the UI
                            this.tasks.fetch({ reset: true });
                            alert('Failed to update tasks. Please try again.');
                            this.closeModal();
                            this.hideLoading();
                        });
                })
                .fail((jqXHR) => {
                    if (jqXHR.status === 401) {
                        alert('Please configure your API token in Whoops settings.');
                    } else {
                        alert('Failed to load the selected list. Please try again.');
                    }
                    this.hideLoading();
                });
        },

        showLoading: function () {
            this.$loadingOverlay.css('display', 'flex');
        },

        hideLoading: function () {
            this.$loadingOverlay.css('display', 'none');
        }
    });

    // Initialize the app
    new AppView();
}); 