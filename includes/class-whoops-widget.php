<?php
/**
 * The dashboard widget functionality of the plugin.
 *
 * @package    Whoops
 * @subpackage Whoops/includes
 */

class Whoops_Widget {

    /**
     * The database instance
     *
     * @var Whoops_DB
     */
    private $db;

    /**
     * Initialize the class
     *
     * @param Whoops_DB $db Database instance
     */
    public function __construct($db) {
        $this->db = $db;
        $this->init();
    }

    /**
     * Initialize the widget
     */
    private function init() {
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widget'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_styles'));
    }

    /**
     * Register the dashboard widget
     */
    public function add_dashboard_widget() {
        wp_add_dashboard_widget(
            'whoops_dashboard_widget',
            'Whoops - Task Checklist',
            array($this, 'render_widget'),
            array($this, 'configure_widget')
        );

        // Add our custom class to the widget container
        add_filter('postbox_classes_dashboard_whoops_dashboard_widget', function($classes) {
            $classes[] = 'whoops-container';
            return $classes;
        });
    }

    /**
     * Render the widget content
     */
    public function render_widget() {
        // Get all tasks
        $tasks = $this->db->get_tasks();
        
        // Output the widget HTML
        ?>
        <div class="whoops-widget">
            <!-- Loading Overlay -->
            <div class="whoops-loading-overlay">
                <span class="spinner is-active"></span>
            </div>

            <div class="whoops-tasks">
                <?php if (empty($tasks)) : ?>
                    <p class="no-tasks">No tasks yet. Add your first task below!</p>
                <?php else : ?>
                    <ul class="task-list">
                        <?php foreach ($tasks as $task) : ?>
                            <li class="task-item <?php echo $task->completed ? 'completed' : ''; ?>" 
                                data-task-id="<?php echo esc_attr($task->id); ?>">
                                <label>
                                    <input type="checkbox" 
                                           class="task-checkbox" 
                                           <?php checked($task->completed, 1); ?>>
                                    <span class="task-description">
                                        <?php echo esc_html($task->task_description); ?>
                                    </span>
                                </label>
                                <button class="delete-task" title="Delete task">×</button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            
            <div class="whoops-actions">
                <form class="add-task-form">
                    <input type="text" 
                           id="new-task" 
                           placeholder="Add a new task..." 
                           required>
                    <button type="submit">Add</button>
                </form>
                
                <div class="whoops-buttons">
                    <button class="load-list-button button">Load Predefined List</button>
                    <?php if (!empty($tasks)) : ?>
                        <button class="clear-completed-button button">Clear Completed</button>
                        <button class="clear-all-button button button-link-delete">Clear All</button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Predefined Lists Modal -->
            <div id="whoops-lists-modal" class="whoops-modal">
                <div class="whoops-modal-content">
                    <div class="whoops-modal-header">
                        <h3>Load Predefined List</h3>
                        <button class="close-modal" aria-label="Close modal">&times;</button>
                    </div>
                    <div class="whoops-modal-body">
                        <div class="lists-container">
                            <!-- Lists will be loaded here dynamically -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Enqueue widget styles and scripts
     */
    public function enqueue_styles($hook) {
        if ('index.php' !== $hook) {
            return;
        }

        wp_enqueue_style('dashicons');
        
        wp_enqueue_style(
            'whoops-admin',
            WHOOPS_PLUGIN_URL . 'assets/css/whoops-admin.css',
            array('dashicons'),
            WHOOPS_VERSION
        );

        // Enqueue WP API and its dependencies
        wp_enqueue_script('wp-api');

        // Enqueue JavaScript
        wp_enqueue_script(
            'whoops-admin',
            WHOOPS_PLUGIN_URL . 'assets/js/whoops-admin.js',
            array('wp-api'),
            WHOOPS_VERSION,
            true
        );

        // Add Whoops specific settings
        wp_localize_script(
            'whoops-admin',
            'whoopsSettings',
            array(
                'apiToken' => Whoops_Settings::get_api_token()
            )
        );
    }
} 