<?php
/**
 * The database-specific functionality of the plugin.
 *
 * @package    Whoops
 * @subpackage Whoops/includes
 */

class Whoops_DB {

    /**
     * The table name for tasks (without prefix)
     *
     * @var string
     */
    private $table_name = 'whoops_tasks';

    /**
     * Initialize the class
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . $this->table_name;
    }

    /**
     * Create the database table
     *
     * @return void
     */
    public static function create_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'whoops_tasks';
        
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            task_description text NOT NULL,
            completed tinyint(1) DEFAULT 0 NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        add_option('whoops_db_version', WHOOPS_DB_VERSION);
    }

    /**
     * Check if database needs upgrade
     *
     * @return void
     */
    public static function check_version() {
        if (get_site_option('whoops_db_version') != WHOOPS_DB_VERSION) {
            self::create_table();
        }
    }

    /**
     * Add a new task
     *
     * @param string $task_description The description of the task
     * @return int|false The id of the inserted task or false on failure
     */
    public function create_task($task_description) {
        global $wpdb;
        
        $result = $wpdb->insert(
            $this->table_name,
            array(
                'task_description' => sanitize_text_field($task_description),
                'completed' => 0
            ),
            array('%s', '%d')
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Get all tasks
     *
     * @param array $args Optional. Arguments to filter tasks
     * @return array Array of tasks
     */
    public function get_tasks($args = array()) {
        global $wpdb;

        $defaults = array(
            'completed' => null,
            'orderby' => 'created_at',
            'order' => 'DESC'
        );

        $args = wp_parse_args($args, $defaults);
        
        $sql = "SELECT * FROM {$this->table_name}";
        
        if (isset($args['completed'])) {
            $sql .= $wpdb->prepare(" WHERE completed = %d", $args['completed']);
        }

        $sql .= " ORDER BY {$args['orderby']} {$args['order']}";
        
        return $wpdb->get_results($sql);
    }

    /**
     * Get a single task by ID
     *
     * @param int $id The task ID
     * @return object|null Task object or null if not found
     */
    public function get_task($id) {
        global $wpdb;
        
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE id = %d",
                $id
            )
        );
    }

    /**
     * Update a task
     *
     * @param int $id The task ID
     * @param array $data The data to update
     * @return bool True on success, false on failure
     */
    public function update_task($id, $data) {
        global $wpdb;

        $allowed_fields = array(
            'task_description' => '%s',
            'completed' => '%d'
        );

        $update_data = array();
        $update_format = array();

        foreach ($data as $field => $value) {
            if (array_key_exists($field, $allowed_fields)) {
                $update_data[$field] = $field === 'task_description' 
                    ? sanitize_text_field($value) 
                    : $value;
                $update_format[] = $allowed_fields[$field];
            }
        }

        if (empty($update_data)) {
            return false;
        }

        return $wpdb->update(
            $this->table_name,
            $update_data,
            array('id' => $id),
            $update_format,
            array('%d')
        );
    }

    /**
     * Delete a task
     *
     * @param int $id The task ID
     * @return bool True on success, false on failure
     */
    public function delete_task($id) {
        global $wpdb;
        
        return $wpdb->delete(
            $this->table_name,
            array('id' => $id),
            array('%d')
        );
    }

    /**
     * Delete all completed tasks
     *
     * @return int|false The number of rows deleted, or false on error
     */
    public function delete_completed_tasks() {
        global $wpdb;
        
        return $wpdb->delete(
            $this->table_name,
            array('completed' => 1),
            array('%d')
        );
    }

    /**
     * Delete all tasks
     *
     * @return bool True on success, false on failure
     */
    public function delete_all_tasks() {
        global $wpdb;
        
        $result = $wpdb->query("TRUNCATE TABLE {$this->table_name}");
        
        if ($result === false) {
            error_log('Whoops DB Error: Failed to delete all tasks - ' . $wpdb->last_error);
        }
        
        return $result !== false;
    }

    /**
     * Get the table name
     *
     * @return string
     */
    private function get_table_name() {
        return $this->table_name;
    }
} 