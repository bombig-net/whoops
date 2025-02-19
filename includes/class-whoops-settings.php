<?php
/**
 * The settings functionality of the plugin.
 *
 * @package    Whoops
 * @subpackage Whoops/includes
 */

class Whoops_Settings {

    /**
     * Initialize the class
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    /**
     * Add the settings page to the WordPress admin menu
     */
    public function add_settings_page() {
        add_options_page(
            'Whoops Settings',
            'Whoops',
            'manage_options',
            'whoops-settings',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting(
            'whoops_settings',
            'whoops_api_token',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => ''
            )
        );

        add_settings_section(
            'whoops_main_section',
            'API Configuration',
            array($this, 'render_section_info'),
            'whoops-settings'
        );

        add_settings_field(
            'whoops_api_token',
            'API Token',
            array($this, 'render_api_token_field'),
            'whoops-settings',
            'whoops_main_section'
        );
    }

    /**
     * Render the settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Show success message if settings were updated
        if (isset($_GET['settings-updated'])) {
            add_settings_error(
                'whoops_messages',
                'whoops_message',
                'Settings Saved',
                'updated'
            );
        }

        // Settings form ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <?php settings_errors('whoops_messages'); ?>

            <form action="options.php" method="post">
                <?php
                settings_fields('whoops_settings');
                do_settings_sections('whoops-settings');
                submit_button('Save Settings');
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render section info
     */
    public function render_section_info() {
        echo '<p>Enter your Whoops API token below. You can find this in your Whoops account settings.</p>';
    }

    /**
     * Render API token field
     */
    public function render_api_token_field() {
        $token = get_option('whoops_api_token');
        ?>
        <input type="text"
               name="whoops_api_token"
               id="whoops_api_token"
               value="<?php echo esc_attr($token); ?>"
               class="regular-text"
               required>
        <p class="description">
            This token is required to load predefined task lists from the Whoops service.
        </p>
        <?php
    }

    /**
     * Get the API token
     */
    public static function get_api_token() {
        return get_option('whoops_api_token', '');
    }
} 