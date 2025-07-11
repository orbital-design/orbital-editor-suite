<?php
/**
 * Admin pages functionality.
 *
 * @package    Orbital_Editor_Suite
 * @subpackage Orbital_Editor_Suite/includes/admin
 */

namespace Orbital\Editor_Suite\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin pages functionality.
 *
 * Handles all admin page creation and management.
 */
class Admin_Pages {

    /**
     * The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     */
    private $version;

    /**
     * Plugin options.
     */
    private $options;

    /**
     * Initialize the class and set its properties.
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->options = get_option('orbital_editor_suite_options', array());
    }

    /**
     * Initialize admin pages.
     */
    public function init() {
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_notices', array($this, 'activation_notice'));
        
        $this->add_menu_pages();
    }

    /**
     * Add menu pages.
     */
    private function add_menu_pages() {
        add_menu_page(
            __('Orbital Editor Suite', 'orbital-editor-suite'),
            __('Orbital Editor', 'orbital-editor-suite'),
            'manage_options',
            'orbital-editor-suite',
            array($this, 'render_main_page'),
            'dashicons-admin-customizer',
            30
        );

        add_submenu_page(
            'orbital-editor-suite',
            __('Settings', 'orbital-editor-suite'),
            __('Settings', 'orbital-editor-suite'),
            'manage_options',
            'orbital-editor-suite',
            array($this, 'render_main_page')
        );

        // Allow modules to register their own admin pages
        do_action('orbital_editor_suite_admin_pages');

        add_submenu_page(
            'orbital-editor-suite',
            __('Updates', 'orbital-editor-suite'),
            __('Updates', 'orbital-editor-suite'),
            'manage_options',
            'orbital-editor-suite-updates',
            array($this, 'render_updates_page')
        );
    }

    /**
     * Register plugin settings.
     */
    public function register_settings() {
        register_setting(
            'orbital_editor_suite_settings',
            'orbital_editor_suite_options',
            array($this, 'sanitize_options')
        );
    }

    /**
     * Sanitize options.
     */
    public function sanitize_options($options) {
        // Get current options to preserve existing data
        $current_options = get_option('orbital_editor_suite_options', array());
        $sanitized = $current_options;
        
        // Handle legacy settings
        if (isset($options['settings'])) {
            $settings = $options['settings'];
            
            $sanitized['settings'] = array(
                'enable_debug' => !empty($settings['enable_debug']),
                'enabled_modules' => isset($settings['enabled_modules']) ? 
                    array_map('sanitize_text_field', (array) $settings['enabled_modules']) : array()
            );
        }

        // Handle module settings
        if (isset($options['modules'])) {
            if (!isset($sanitized['modules'])) {
                $sanitized['modules'] = array();
            }
            
            foreach ($options['modules'] as $module_slug => $module_settings) {
                $sanitized['modules'][$module_slug] = $this->sanitize_module_settings($module_slug, $module_settings);
            }
        }

        $sanitized['version'] = ORBITAL_EDITOR_SUITE_VERSION;
        
        return $sanitized;
    }

    /**
     * Sanitize module-specific settings.
     */
    private function sanitize_module_settings($module_slug, $settings) {
        $sanitized = array();
        
        switch ($module_slug) {
            case 'typography-presets':
                $sanitized = array(
                    'preset_generation_method' => in_array($settings['preset_generation_method'] ?? 'admin', ['admin', 'theme_json']) ? 
                        $settings['preset_generation_method'] : 'admin',
                    'replace_core_controls' => !empty($settings['replace_core_controls']),
                    'show_groups' => !empty($settings['show_groups']),
                    'output_preset_css' => !empty($settings['output_preset_css']),
                    'allowed_blocks' => isset($settings['allowed_blocks']) ? 
                        $this->sanitize_allowed_blocks($settings['allowed_blocks']) : array(
                            'core/paragraph', 'core/heading', 'core/list', 'core/quote', 'core/button'
                        )
                );
                break;
            
            default:
                // Generic sanitization for unknown modules
                foreach ($settings as $key => $value) {
                    if (is_array($value)) {
                        $sanitized[$key] = array_map('sanitize_text_field', $value);
                    } elseif (is_bool($value) || $value === '1' || $value === '') {
                        $sanitized[$key] = !empty($value);
                    } else {
                        $sanitized[$key] = sanitize_text_field($value);
                    }
                }
                break;
        }
        
        return $sanitized;
    }

    /**
     * Sanitize allowed blocks array.
     */
    private function sanitize_allowed_blocks($blocks) {
        if (!is_array($blocks)) {
            return array();
        }
        
        // Remove the dummy field if present
        if (isset($blocks['_dummy'])) {
            unset($blocks['_dummy']);
        }
        
        // Sanitize and filter valid block names
        $sanitized = array();
        foreach ($blocks as $block) {
            $block = sanitize_text_field($block);
            // Only allow valid core block names
            if (strpos($block, 'core/') === 0) {
                $sanitized[] = $block;
            }
        }
        
        return array_values($sanitized); // Re-index array
    }

    /**
     * Show activation notice.
     */
    public function activation_notice() {
        if (get_transient('orbital_editor_suite_activation_notice')) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p>
                    <?php _e('Orbital Editor Suite has been activated successfully!', 'orbital-editor-suite'); ?>
                    <a href="<?php echo admin_url('admin.php?page=orbital-editor-suite'); ?>">
                        <?php _e('Configure settings', 'orbital-editor-suite'); ?>
                    </a>
                </p>
            </div>
            <?php
            delete_transient('orbital_editor_suite_activation_notice');
        }
    }

    /**
     * Render the main admin page.
     */
    public function render_main_page() {
        require_once plugin_dir_path(__FILE__) . 'partials/main-page.php';
    }


    /**
     * Render the updates page.
     */
    public function render_updates_page() {
        require_once plugin_dir_path(__FILE__) . 'partials/updates-page.php';
    }
}