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
} 