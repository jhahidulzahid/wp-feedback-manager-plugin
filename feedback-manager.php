<?php
/**
 * Plugin Name: Feedback Manager
 * Plugin URI: https://github.com/jhahidulzahid/wp-feedback-manager-plugin
 * Description: A comprehensive feedback management system with REST API integration and admin dashboard.
 * Version: 1.0.0
 * Author: Jhahidul Zahid
 * Author URI: jhahidulzahid-portfolio-2.vercel.app
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: feedback-manager
 * Domain Path: /languages
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('FEEDBACK_MANAGER_VERSION', '1.0.0');
define('FEEDBACK_MANAGER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FEEDBACK_MANAGER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('FEEDBACK_MANAGER_PLUGIN_FILE', __FILE__);

/**
 * Main Feedback Manager Class
 */
class Feedback_Manager {
    
    /**
     * Instance of this class
     */
    private static $instance = null;
    
    /**
     * Database table name
     */
    private $table_name;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'feedback_manager';
        
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        
        // Admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Register REST API routes
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        
        // Register shortcode
        add_shortcode('feedback_form', array($this, 'render_feedback_form')); 
       
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Handle AJAX delete action
        add_action('wp_ajax_delete_feedback', array($this, 'ajax_delete_feedback'));
        
        // Handle CSV export
        add_action('admin_init', array($this, 'handle_csv_export'));
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        $this->create_database_table();
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    /**
     * Create database table
     */
    private function create_database_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            email varchar(255) NOT NULL,
            message text NOT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY email (email),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Store database version
        add_option('feedback_manager_db_version', FEEDBACK_MANAGER_VERSION);
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_management_page(
            __('Feedback Manager', 'feedback-manager'),
            __('Feedback Manager', 'feedback-manager'),
            'manage_options',
            'feedback-manager',
            array($this, 'render_admin_page')
        );
    }
    
    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        register_rest_route('feedback-manager/v1', '/submit', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_feedback_submission'),
            'permission_callback' => '__return_true',
            'args' => array(
                'name' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function($param) {
                        return !empty($param) && strlen($param) <= 255;
                    }
                ),
                'email' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_email',
                    'validate_callback' => function($param) {
                        return is_email($param);
                    }
                ),
                'message' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_textarea_field',
                    'validate_callback' => function($param) {
                        return !empty($param) && strlen($param) <= 5000;
                    }
                ),
                'nonce' => array(
                    'required' => true,
                    'type' => 'string'
                )
            )
        ));
    }
    
    /**
     * Handle feedback submission via REST API
     */
    public function handle_feedback_submission($request) {
        global $wpdb;
        
        // Verify nonce - check both custom nonce and REST nonce
        $nonce = $request->get_param('nonce');
        $verified = false;
        
        // Try custom nonce first
        if (!empty($nonce) && wp_verify_nonce($nonce, 'feedback_form_nonce')) {
            $verified = true;
        }
        
        // If custom nonce fails, try REST nonce from header
        if (!$verified) {
            $rest_nonce = $request->get_header('X-WP-Nonce');
            if (!empty($rest_nonce) && wp_verify_nonce($rest_nonce, 'wp_rest')) {
                $verified = true;
            }
        }
        
        if (!$verified) {
            return new WP_Error(
                'invalid_nonce',
                __('Security check failed. Please refresh the page and try again.', 'feedback-manager'),
                array('status' => 403)
            );
        }
        
        // Get sanitized data
        $name = $request->get_param('name');
        $email = $request->get_param('email');
        $message = $request->get_param('message');
        
        // Additional validation
        if (empty($name) || empty($email) || empty($message)) {
            return new WP_Error(
                'missing_fields',
                __('All fields are required.', 'feedback-manager'),
                array('status' => 400)
            );
        }
        
        // Rate limiting: Check if same IP submitted in last 30 SECOND
        $ip_address = $this->get_client_ip();
        $recent_submission = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} 
            WHERE ip_address = %s 
            AND created_at > DATE_SUB(NOW(), INTERVAL 30 SECOND)",
            $ip_address
        ));
        
        if ($recent_submission > 0) {
            return new WP_Error(
                'rate_limit',
                __('Please wait 30 seconds before submitting another feedback.', 'feedback-manager'),
                array('status' => 429)
            );
        }
        
        // Insert feedback
        $result = $wpdb->insert(
            $this->table_name,
            array(
                'name' => $name,
                'email' => $email,
                'message' => $message,
                'ip_address' => $ip_address,
                'user_agent' => $this->get_user_agent()
            ),
            array('%s', '%s', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            return new WP_Error(
                'database_error',
                __('Failed to save feedback. Please try again.', 'feedback-manager'),
                array('status' => 500)
            );
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'message' => __('Thank you for your feedback!', 'feedback-manager')
        ));
    }
    
    /**
     * Render feedback form shortcode
     */
    public function render_feedback_form($atts) {
        $atts = shortcode_atts(array(
            'title' => __('Send Us Your Feedback', 'feedback-manager')
        ), $atts);
        
        ob_start();
        ?>
        <div class="feedback-manager-form-wrapper">
            <h3 class="feedback-form-title"><?php echo esc_html($atts['title']); ?></h3>
            
            <div id="feedback-message" class="feedback-message" style="display:none;"></div>
            
            <form id="feedback-form" class="feedback-form">
                <div class="form-group">
                    <label for="feedback-name"><?php _e('Name', 'feedback-manager'); ?> <span class="required">*</span></label>
                    <input type="text" id="feedback-name" name="name" required maxlength="255" />
                </div>
                
                <div class="form-group">
                    <label for="feedback-email"><?php _e('Email', 'feedback-manager'); ?> <span class="required">*</span></label>
                    <input type="email" id="feedback-email" name="email" required maxlength="255" />
                </div>
                
                <div class="form-group">
                    <label for="feedback-message"><?php _e('Message', 'feedback-manager'); ?> <span class="required">*</span></label>
                    <textarea id="feedback-message-field" name="message" rows="6" required maxlength="5000"></textarea>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="feedback-submit-btn">
                        <span class="btn-text"><?php _e('Submit Feedback', 'feedback-manager'); ?></span>
                        <span class="btn-loading" style="display:none;"><?php _e('Submitting...', 'feedback-manager'); ?></span>
                    </button>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'feedback-manager'));
        }
        
        global $wpdb;
        
        // Handle bulk delete
        if (isset($_POST['action']) && $_POST['action'] === 'bulk_delete' && check_admin_referer('bulk_delete_feedback')) {
            if (!empty($_POST['feedback_ids'])) {
                $ids = array_map('intval', $_POST['feedback_ids']);
                $placeholders = implode(',', array_fill(0, count($ids), '%d'));
                $wpdb->query($wpdb->prepare("DELETE FROM {$this->table_name} WHERE id IN ($placeholders)", $ids));
                echo '<div class="notice notice-success"><p>' . __('Selected feedback deleted successfully.', 'feedback-manager') . '</p></div>';
            }
        }
        
        // Pagination
        $per_page = 20;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $per_page;
        
        // Search and Filter
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $date_filter = isset($_GET['date_filter']) ? sanitize_text_field($_GET['date_filter']) : '';
        $order_by = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'created_at';
        $order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'DESC';
        
        // Validate order by and order
        $allowed_orderby = array('id', 'name', 'email', 'created_at');
        $allowed_order = array('ASC', 'DESC');
        
        if (!in_array($order_by, $allowed_orderby)) {
            $order_by = 'created_at';
        }
        if (!in_array($order, $allowed_order)) {
            $order = 'DESC';
        }
        
        $where_conditions = array();
        $where = '';
        
        // Search condition
        if (!empty($search)) {
            $where_conditions[] = $wpdb->prepare(
                "(name LIKE %s OR email LIKE %s OR message LIKE %s)", 
                '%' . $wpdb->esc_like($search) . '%',
                '%' . $wpdb->esc_like($search) . '%',
                '%' . $wpdb->esc_like($search) . '%'
            );
        }
        
        // Date filter condition
        if (!empty($date_filter)) {
            switch ($date_filter) {
                case 'today':
                    $where_conditions[] = "DATE(created_at) = CURDATE()";
                    break;
                case 'yesterday':
                    $where_conditions[] = "DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
                    break;
                case 'week':
                    $where_conditions[] = "created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                    break;
                case 'month':
                    $where_conditions[] = "created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                    break;
                case 'year':
                    $where_conditions[] = "created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
                    break;
            }
        }
        
        // Build WHERE clause
        if (!empty($where_conditions)) {
            $where = " WHERE " . implode(' AND ', $where_conditions);
        }
        
        // Get total count
        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}" . $where);
        $total_pages = ceil($total_items / $per_page);
        
        // Get feedback entries
        $feedbacks = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name}" . $where . " ORDER BY {$order_by} {$order} LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ));
        
        include FEEDBACK_MANAGER_PLUGIN_DIR . 'templates/admin-page.php';
    }
    
    /**
     * AJAX handler for deleting feedback
     */
    public function ajax_delete_feedback() {
        check_ajax_referer('delete_feedback_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'feedback-manager')));
        }
        
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        if ($id <= 0) {
            wp_send_json_error(array('message' => __('Invalid feedback ID.', 'feedback-manager')));
        }
        
        global $wpdb;
        $result = $wpdb->delete($this->table_name, array('id' => $id), array('%d'));
        
        if ($result) {
            wp_send_json_success(array('message' => __('Feedback deleted successfully.', 'feedback-manager')));
        } else {
            wp_send_json_error(array('message' => __('Failed to delete feedback.', 'feedback-manager')));
        }
    }
    
    /**
     * Handle CSV export
     */
    public function handle_csv_export() {
        if (!isset($_GET['action']) || $_GET['action'] !== 'export_feedback_csv') {
            return;
        }
        
        if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'export_feedback_csv')) {
            wp_die(__('Security check failed.', 'feedback-manager'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Permission denied.', 'feedback-manager'));
        }
        
        global $wpdb;
        $feedbacks = $wpdb->get_results("SELECT * FROM {$this->table_name} ORDER BY created_at DESC", ARRAY_A);
        
        // Set headers for CSV download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=feedback-export-' . date('Y-m-d-H-i-s') . '.csv');
        
        $output = fopen('php://output', 'w');
        
        // Add BOM for UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Add headers
        fputcsv($output, array('ID', 'Name', 'Email', 'Message', 'IP Address', 'User Agent', 'Submitted At'));
        
        // Add data
        foreach ($feedbacks as $feedback) {
            fputcsv($output, array(
                $feedback['id'],
                $feedback['name'],
                $feedback['email'],
                $feedback['message'],
                $feedback['ip_address'],
                $feedback['user_agent'],
                $feedback['created_at']
            ));
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        global $post;
        
        // Check if current post/page has the shortcode
        $has_shortcode = false;
        
        if (is_a($post, 'WP_Post')) {
            $has_shortcode = has_shortcode($post->post_content, 'feedback_form');
        }
        
        // Also check in widgets and other areas
        if (!$has_shortcode && function_exists('is_active_widget')) {
            $has_shortcode = is_active_widget(false, false, 'text');
        }
        
        // Force load on all pages for now (can be optimized later)
        if (true) { // Change back to: if ($has_shortcode)
            wp_enqueue_style(
                'feedback-manager-frontend',
                FEEDBACK_MANAGER_PLUGIN_URL . 'assets/css/frontend.css',
                array(),
                FEEDBACK_MANAGER_VERSION
            );
            
            wp_enqueue_script(
                'feedback-manager-frontend',
                FEEDBACK_MANAGER_PLUGIN_URL . 'assets/js/frontend.js',
                array('jquery'),
                FEEDBACK_MANAGER_VERSION,
                true
            );
            
            wp_localize_script('feedback-manager-frontend', 'feedbackManager', array(
                'ajaxUrl' => rest_url('feedback-manager/v1/submit'),
                'nonce' => wp_create_nonce('feedback_form_nonce'),
                'restNonce' => wp_create_nonce('wp_rest'),
                'strings' => array(
                    'success' => __('Thank you for your feedback!', 'feedback-manager'),
                    'error' => __('Something went wrong. Please try again.', 'feedback-manager'),
                    'required' => __('This field is required.', 'feedback-manager'),
                    'invalidEmail' => __('Please enter a valid email address.', 'feedback-manager')
                )
            ));
        }
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if ($hook !== 'tools_page_feedback-manager') {
            return;
        }
        
        wp_enqueue_style(
            'feedback-manager-admin',
            FEEDBACK_MANAGER_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            FEEDBACK_MANAGER_VERSION
        );
        
        wp_enqueue_script(
            'feedback-manager-admin',
            FEEDBACK_MANAGER_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            FEEDBACK_MANAGER_VERSION,
            true
        );
        
        wp_localize_script('feedback-manager-admin', 'feedbackManagerAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('delete_feedback_nonce'),
            'strings' => array(
                'confirmDelete' => __('Are you sure you want to delete this feedback?', 'feedback-manager'),
                'confirmBulkDelete' => __('Are you sure you want to delete selected feedback?', 'feedback-manager'),
                'deleteSuccess' => __('Feedback deleted successfully.', 'feedback-manager'),
                'deleteError' => __('Failed to delete feedback.', 'feedback-manager')
            )
        ));
    }
    
    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip = '';
        
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        
        return sanitize_text_field($ip);
    }
    
    /**
     * Get user agent
     */
    private function get_user_agent() {
        return isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '';
    }
}

// Initialize the plugin
function feedback_manager_init() {
    return Feedback_Manager::get_instance();
}

// Start the plugin
add_action('plugins_loaded', 'feedback_manager_init');

// Register activation 
register_activation_hook(__FILE__, array(Feedback_Manager::get_instance(), 'activate'));

// Register deactivation
register_deactivation_hook(__FILE__, array(Feedback_Manager::get_instance(), 'deactivate'));