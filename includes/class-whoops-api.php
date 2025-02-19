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
        register_rest_route('whoops/v1', '/tasks', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_tasks'),
                'permission_callback' => array($this, 'check_admin_permissions'),
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'create_task'),
                'permission_callback' => array($this, 'check_admin_permissions'),
            ),
        ));

        register_rest_route('whoops/v1', '/checklists', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_checklists'),
                'permission_callback' => array($this, 'check_admin_permissions'),
            ),
        ));

        register_rest_route('whoops/v1', '/checklists/(?P<list>[a-z-]+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_checklist'),
                'permission_callback' => array($this, 'check_admin_permissions'),
            ),
        ));

        register_rest_route('whoops/v1', '/tasks/(?P<id>\d+)', array(
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

        register_rest_route('whoops/v1', '/tasks/clear-completed', array(
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array($this, 'clear_completed'),
                'permission_callback' => array($this, 'check_admin_permissions'),
            ),
        ));
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
        return rest_ensure_response($this->db->get_tasks());
    }

    /**
     * Create a new task
     */
    public function create_task($request) {
        $task_description = sanitize_text_field($request->get_param('task_description'));
        
        if (empty($task_description)) {
            return new WP_Error('invalid_task', 'Task description is required', array('status' => 400));
        }

        $task_id = $this->db->create_task($task_description);
        
        if (!$task_id) {
            return new WP_Error('task_creation_failed', 'Failed to create task', array('status' => 500));
        }

        return rest_ensure_response($this->db->get_task($task_id));
    }

    /**
     * Update a task
     */
    public function update_task($request) {
        $task_id = (int) $request->get_param('id');
        $task = $this->db->get_task($task_id);

        if (!$task) {
            return new WP_Error('task_not_found', 'Task not found', array('status' => 404));
        }

        $data = array();
        
        if ($request->has_param('completed')) {
            // Convert boolean to integer (0 or 1)
            $data['completed'] = (int) $request->get_param('completed');
        }
        
        if ($request->has_param('task_description')) {
            $data['task_description'] = sanitize_text_field($request->get_param('task_description'));
        }

        $updated = $this->db->update_task($task_id, $data);
        
        if (!$updated) {
            return new WP_Error('task_update_failed', 'Failed to update task', array('status' => 500));
        }

        return rest_ensure_response($this->db->get_task($task_id));
    }

    /**
     * Delete a task
     */
    public function delete_task($request) {
        $task_id = (int) $request->get_param('id');
        $task = $this->db->get_task($task_id);

        if (!$task) {
            return new WP_Error('task_not_found', 'Task not found', array('status' => 404));
        }

        $deleted = $this->db->delete_task($task_id);
        
        if (!$deleted) {
            return new WP_Error('task_deletion_failed', 'Failed to delete task', array('status' => 500));
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
     * Get all available checklists
     */
    public function get_checklists($request) {
        $response = wp_remote_get(
            'https://whoopskjvmldv3-whoops-checklists.functions.fnc.fr-par.scw.cloud/',
            array(
                'headers' => array(
                    'X-Auth-Token' => Whoops_Settings::get_api_token()
                )
            )
        );

        if (is_wp_error($response)) {
            return new WP_Error(
                'api_error',
                'Failed to fetch checklists: ' . $response->get_error_message(),
                array('status' => 500)
            );
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error(
                'api_error',
                'Invalid response from checklist service',
                array('status' => 500)
            );
        }

        return rest_ensure_response($data);
    }

    /**
     * Get a specific checklist
     */
    public function get_checklist($request) {
        $list = $request->get_param('list');
        
        $response = wp_remote_get(
            'https://whoopskjvmldv3-whoops-checklists.functions.fnc.fr-par.scw.cloud/?list=' . urlencode($list),
            array(
                'headers' => array(
                    'X-Auth-Token' => Whoops_Settings::get_api_token()
                )
            )
        );

        if (is_wp_error($response)) {
            return new WP_Error(
                'api_error',
                'Failed to fetch checklist: ' . $response->get_error_message(),
                array('status' => 500)
            );
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error(
                'api_error',
                'Invalid response from checklist service',
                array('status' => 500)
            );
        }

        return rest_ensure_response($data);
    }
} 