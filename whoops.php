<?php
/**
 * Whoops
 *
 * @package     Whoops
 * @author      bombig.net
 * @copyright   2024 bombig.net
 * @license     GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name: Whoops
 * Plugin URI: https://example.com/plugins/whoops
 * Description: The simple way to catch easy-to-miss tasks before important events in your website's lifecycle. 
 * Version: 0.0.1
 * Requires at least: 5.2
 * Requires PHP: 7.4
 * Author: bombig.net
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    die;
}

// Define plugin constants
define('WHOOPS_VERSION', '0.0.1');
define('WHOOPS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WHOOPS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WHOOPS_DB_VERSION', '1.0');

// Include the database class
require_once WHOOPS_PLUGIN_DIR . 'includes/class-whoops-db.php';

/**
 * The code that runs during plugin activation.
 */
function activate_whoops() {
    // Create the database table
    Whoops_DB::create_table();
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_whoops() {
    // Clear any temporary data, cache, etc.
    // But do NOT delete any data - that's for uninstall
}

register_activation_hook(__FILE__, 'activate_whoops');
register_deactivation_hook(__FILE__, 'deactivate_whoops');

/**
 * Check DB version and update if needed when plugins are loaded
 */
function whoops_update_db_check() {
    Whoops_DB::check_version();
}
add_action('plugins_loaded', 'whoops_update_db_check');

/**
 * Begin execution of the plugin.
 */
function run_whoops() {
    // Initialize database class
    $whoops_db = new Whoops_DB();
    
    // Store the database instance globally if needed
    global $whoops_plugin;
    $whoops_plugin = (object) array(
        'db' => $whoops_db
    );
}

run_whoops();