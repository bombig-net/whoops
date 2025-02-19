<?php
/**
 * The REST API functionality of the plugin.
 *
 * @package    Whoops
 * @subpackage Whoops/includes
 */

class Whoops_API {

    /**
     * The database instance
     *
     * @var Whoops_DB
     */
    private $db;

    private $namespace = 'whoops/v1';
    private $rest_base = 'tasks';

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
     * Initialize the API
     */
    private function init() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    /**
     * Register REST API routes
     */
    public function register_routes() {
        // Tasks endpoints
        register_rest_route($this->namespace, '/' . $this->rest_base, array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_tasks'),
                'permission_callback' => array($this, 'check_admin_permissions'),
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'create_task'),
                'permission_callback' => array($this, 'check_admin_permissions'),
                'args' => array(
                    'task_description' => array(
                        'required' => true,
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                ),
            ),
        ));

        register_rest_route($this->namespace, '/' . $this->rest_base . '/clear-all', array(
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array($this, 'clear_all_tasks'),
                'permission_callback' => array($this, 'check_admin_permissions'),
            ),
        ));

        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_task'),
                'permission_callback' => array($this, 'check_admin_permissions'),
            ),
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this, 'update_task'),
                'permission_callback' => array($this, 'check_admin_permissions'),
            ),
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array($this, 'delete_task'),
                'permission_callback' => array($this, 'check_admin_permissions'),
            ),
        ));

        register_rest_route($this->namespace, '/' . $this->rest_base . '/clear-completed', array(
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array($this, 'clear_completed'),
                'permission_callback' => array($this, 'check_admin_permissions'),
            ),
        ));

        // Checklists endpoints
        register_rest_route($this->namespace, '/checklists', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_predefined_lists'),
                'permission_callback' => array($this, 'check_admin_permissions'),
            ),
        ));

        register_rest_route($this->namespace, '/checklists/(?P<list_name>[a-z-]+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_specific_list'),
                'permission_callback' => array($this, 'check_admin_permissions'),
            ),
        ));
    }

    public function get_item_schema() {
        return array(
            '$schema'              => 'http://json-schema.org/draft-04/schema#',
            'title'                => 'task',
            'type'                 => 'object',
            'properties'           => array(
                'id' => array(
                    'description'  => 'Unique identifier for the task.',
                    'type'        => 'integer',
                    'context'     => array('view', 'edit'),
                    'readonly'    => true,
                ),
                'task_description' => array(
                    'description'  => 'The description of the task.',
                    'type'        => 'string',
                    'context'     => array('view', 'edit'),
                    'required'    => true,
                ),
                'completed' => array(
                    'description'  => 'Whether the task is completed.',
                    'type'        => 'boolean',
                    'context'     => array('view', 'edit'),
                    'default'     => false,
                ),
            ),
        );
    }

    /**
     * Check if user has admin permissions
     */
    public function check_admin_permissions() {
        return current_user_can('manage_options');
    }

    /**
     * Get tasks
     */
    public function get_tasks($request) {
        $tasks = $this->db->get_tasks();
        return rest_ensure_response($tasks);
    }

    /**
     * Get a specific task
     */
    public function get_task($request) {
        $task = $this->db->get_task($request['id']);
        if (!$task) {
            return new WP_Error('task_not_found', 'Task not found', array('status' => 404));
        }
        return rest_ensure_response($task);
    }

    /**
     * Create a new task
     */
    public function create_task($request) {
        $task_id = $this->db->create_task($request['task_description']);
        if (!$task_id) {
            return new WP_Error('task_creation_failed', 'Failed to create task', array('status' => 500));
        }
        $task = $this->db->get_task($task_id);
        return rest_ensure_response($task);
    }

    /**
     * Update a task
     */
    public function update_task($request) {
        $task = $this->db->get_task($request['id']);
        if (!$task) {
            return new WP_Error('task_not_found', 'Task not found', array('status' => 404));
        }

        $data = array();
        
        // Handle completed state
        if (isset($request['completed'])) {
            $completed = $request['completed'];
            // Convert various truthy/falsy values to 0 or 1
            if (is_bool($completed)) {
                $data['completed'] = $completed ? 1 : 0;
            } else {
                $data['completed'] = $completed ? 1 : 0;
            }
        }

        // Handle task description
        if (isset($request['task_description'])) {
            $data['task_description'] = sanitize_text_field($request['task_description']);
        }

        if (empty($data)) {
            return rest_ensure_response($task);
        }

        $updated = $this->db->update_task($request['id'], $data);
        if (!$updated) {
            return new WP_Error('task_update_failed', 'Failed to update task', array('status' => 500));
        }

        $updated_task = $this->db->get_task($request['id']);
        return rest_ensure_response($updated_task);
    }

    /**
     * Delete a task
     */
    public function delete_task($request) {
        $deleted = $this->db->delete_task($request['id']);
        if (!$deleted) {
            return new WP_Error('task_deletion_failed', 'Failed to delete task', array('status' => 404));
        }
        return rest_ensure_response(array('deleted' => true));
    }

    /**
     * Clear completed tasks
     */
    public function clear_completed($request) {
        $deleted = $this->db->delete_completed_tasks();
        if ($deleted === false) {
            return new WP_Error('clear_completed_failed', 'Failed to clear completed tasks', array('status' => 500));
        }
        return rest_ensure_response(array('deleted' => true));
    }

    /**
     * Get all predefined lists
     */
    public function get_predefined_lists($request) {
        $api_token = Whoops_Settings::get_api_token();
        
        if (empty($api_token)) {
            return new WP_Error('no_api_token', 'API token is not configured', array('status' => 401));
        }

        $response = wp_remote_get('https://whoopskjvmldv3-whoops-checklists.functions.fnc.fr-par.scw.cloud/', array(
            'headers' => array(
                'X-Auth-Token' => $api_token,
                'Content-Type' => 'application/json'
            ),
            'timeout' => 15 // Increase timeout for potentially slow first response
        ));

        if (is_wp_error($response)) {
            error_log('Whoops API Error: ' . $response->get_error_message());
            return new WP_Error(
                'api_error',
                'Failed to fetch predefined lists: ' . $response->get_error_message(),
                array('status' => 500)
            );
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            $error_message = wp_remote_retrieve_response_message($response);
            error_log('Whoops API Error: ' . $error_message);
            return new WP_Error(
                'api_error',
                'Failed to fetch predefined lists: ' . $error_message,
                array('status' => $code)
            );
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('Whoops API Error: Invalid JSON response');
            return new WP_Error(
                'api_error',
                'Invalid response from checklist service',
                array('status' => 500)
            );
        }

        return rest_ensure_response($data);
    }

    /**
     * Get a specific predefined list
     */
    public function get_specific_list($request) {
        $api_token = Whoops_Settings::get_api_token();
        
        if (empty($api_token)) {
            return new WP_Error('no_api_token', 'API token is not configured', array('status' => 401));
        }

        $list_name = $request['list_name'];
        $url = add_query_arg('list', $list_name, 'https://whoopskjvmldv3-whoops-checklists.functions.fnc.fr-par.scw.cloud/');

        $response = wp_remote_get($url, array(
            'headers' => array(
                'X-Auth-Token' => $api_token,
                'Content-Type' => 'application/json'
            ),
            'timeout' => 15 // Increase timeout for potentially slow first response
        ));

        if (is_wp_error($response)) {
            error_log('Whoops API Error: ' . $response->get_error_message());
            return new WP_Error(
                'api_error',
                'Failed to fetch the specified list: ' . $response->get_error_message(),
                array('status' => 500)
            );
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            $error_message = wp_remote_retrieve_response_message($response);
            error_log('Whoops API Error: ' . $error_message);
            return new WP_Error(
                'api_error',
                'Failed to fetch the specified list: ' . $error_message,
                array('status' => $code)
            );
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('Whoops API Error: Invalid JSON response');
            return new WP_Error(
                'api_error',
                'Invalid response from checklist service',
                array('status' => 500)
            );
        }

        return rest_ensure_response($data);
    }

    /**
     * Clear all tasks
     */
    public function clear_all_tasks($request) {
        $deleted = $this->db->delete_all_tasks();
        if ($deleted === false) {
            return new WP_Error('clear_all_failed', 'Failed to clear all tasks', array('status' => 500));
        }
        return rest_ensure_response(array('deleted' => true));
    }
} 