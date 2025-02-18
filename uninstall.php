<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package Whoops
 */

// If uninstall not called from WordPress, then exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete the plugin's database table
global $wpdb;
$table_name = $wpdb->prefix . 'whoops_tasks';
$sql = "DROP TABLE IF EXISTS $table_name";
$wpdb->query($sql);

// Delete all plugin options
delete_option('whoops_db_version');