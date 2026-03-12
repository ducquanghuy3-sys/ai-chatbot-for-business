<?php
/**
 * Main plugin class
 *
 * @package AI_Chatbot
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class AI_Chatbot_Main {

    /**
     * Single instance of the class
     *
     * @var AI_Chatbot_Main|null
     */
    private static $instance = null;

    /**
     * AI adapter instance
     *
     * @var AI_Chatbot_AI_Adapter
     */
    private $ai_adapter;

    /**
     * Notifier instance
     *
     * @var AI_Chatbot_Notifier
     */
    private $notifier;

    /**
     * Get single instance
     *
     * @return AI_Chatbot_Main
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
        $this->ai_adapter = new AI_Chatbot_AI_Adapter();
        $this->notifier = new AI_Chatbot_Notifier();

        // Register AJAX handlers
        add_action('wp_ajax_ai_chatbot_chat', array($this, 'ajax_chat'));
        add_action('wp_ajax_nopriv_ai_chatbot_chat', array($this, 'ajax_chat'));
        add_action('wp_ajax_ai_chatbot_test', array($this, 'ajax_test_api'));

        // Load frontend assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));

        // Render chat widget
        add_action('wp_footer', array($this, 'render_chatbot_widget'));

        // Admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'admin_assets'));
    }

    /**
     * AJAX chat handler
     */
    public function ajax_chat() {
        // Verify nonce
        check_ajax_referer('ai_chatbot_nonce', 'nonce');

        // Get and sanitize parameters
        $message = sanitize_text_field(wp_unslash($_POST['message'] ?? ''));
        $history = $_POST['history'] ?? array();
        $language = sanitize_text_field($_POST['language'] ?? 'en');
        $page_url = esc_url_raw($_POST['page_url'] ?? '');

        if (empty($message)) {
            wp_send_json_error(array(
                'message' => __('Message cannot be empty.', 'ai-chatbot')
            ));
        }

        // Get AI reply
        $reply = $this->ai_adapter->chat($message, $history, $language);

        // Detect inquiry
        $is_inquiry = $this->notifier->detect_inquiry($message);

        if ($is_inquiry) {
            $this->notifier->send_inquiry_notification(array(
                'message' => $message,
                'page_url' => $page_url,
            ));
        }

        wp_send_json_success(array(
            'reply' => $reply,
            'is_inquiry' => $is_inquiry
        ));
    }

    /**
     * AJAX test API handler
     */
    public function ajax_test_api() {
        // Verify nonce and capabilities
        check_ajax_referer('ai_chatbot_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('You do not have permission to test API.', 'ai-chatbot')
            ));
        }

        $provider = sanitize_text_field($_POST['provider'] ?? get_option('ai_chatbot_ai_provider', 'kimi'));
        $api_key = sanitize_text_field($_POST['api_key'] ?? get_option('ai_chatbot_api_key', ''));
        $model = sanitize_text_field($_POST['model'] ?? get_option('ai_chatbot_model', 'moonshot-v1-128k'));

        if (empty($api_key)) {
            wp_send_json_error(array(
                'message' => __('Please enter API Key first.', 'ai-chatbot')
            ));
        }

        // Create test instance
        $test_adapter = new AI_Chatbot_AI_Adapter($provider, $api_key, $model);
        $reply = $test_adapter->chat('Hello, please briefly introduce yourself.', array(), 'en');

        if ($reply && !empty($reply)) {
            wp_send_json_success(array(
                'message' => __('API test successful!', 'ai-chatbot'),
                'reply' => substr($reply, 0, 200) . '...'
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('API test failed. Please check API Key and model name.', 'ai-chatbot'),
                'reply' => $reply
            ));
        }
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueue_assets() {
        // Only load on frontend
        if (is_admin()) {
            return;
        }

        // Check if enabled
        if (!get_option('ai_chatbot_enabled', 1)) {
            return;
        }

        // Enqueue CSS
        wp_enqueue_style(
            'ai-chatbot',
            AI_CHATBOT_URI . 'assets/css/ai-chatbot.css',
            array(),
            AI_CHATBOT_VERSION
        );

        // Enqueue JS
        wp_enqueue_script(
            'ai-chatbot',
            AI_CHATBOT_URI . 'assets/js/ai-chatbot.js',
            array(),
            AI_CHATBOT_VERSION,
            true
        );

        // Localize script
        wp_localize_script('ai-chatbot', 'aiChatbot', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ai_chatbot_nonce'),
            'welcomeMessage' => get_option('ai_chatbot_welcome_message', $this->get_default_welcome()),
            'placeholder' => get_option('ai_chatbot_placeholder', __('Type your message...', 'ai-chatbot')),
            'language' => $this->detect_language(),
        ));
    }

    /**
     * Detect browser language
     */
    private function detect_language() {
        $browser_lang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en', 0, 2);
        $supported = array('zh', 'en', 'ar');
        return in_array($browser_lang, $supported) ? $browser_lang : 'en';
    }

    /**
     * Get default welcome message
     */
    private function get_default_welcome() {
        return __('Hi! 👋 Welcome to our website. How can I help you today?', 'ai-chatbot');
    }

    /**
     * Render chat widget
     */
    public function render_chatbot_widget() {
        if (is_admin() || !get_option('ai_chatbot_enabled', 1)) {
            return;
        }

        require AI_CHATBOT_DIR . 'templates/chatbot-widget.php';
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('AI Chatbot', 'ai-chatbot'),
            __('AI Chatbot', 'ai-chatbot'),
            'manage_options',
            'ai-chatbot',
            array($this, 'render_admin_page'),
            'dashicons-format-chat',
            30
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        // Basic settings
        register_setting('ai_chatbot_settings', 'ai_chatbot_enabled', array(
            'type' => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
        ));
        register_setting('ai_chatbot_settings', 'ai_chatbot_welcome_message', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ));
        register_setting('ai_chatbot_settings', 'ai_chatbot_placeholder', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ));
        register_setting('ai_chatbot_settings', 'ai_chatbot_position', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ));

        // AI settings
        register_setting('ai_chatbot_settings', 'ai_chatbot_ai_provider', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ));
        register_setting('ai_chatbot_settings', 'ai_chatbot_api_key', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ));
        register_setting('ai_chatbot_settings', 'ai_chatbot_model', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ));
        register_setting('ai_chatbot_settings', 'ai_chatbot_system_prompt', array(
            'type' => 'string',
            'sanitize_callback' => 'wp_kses_post',
        ));

        // Notification settings
        register_setting('ai_chatbot_settings', 'ai_chatbot_notification_email', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_email',
        ));
        register_setting('ai_chatbot_settings', 'ai_chatbot_serverchan_key', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ));
    }

    /**
     * Enqueue admin assets
     */
    public function admin_assets($hook) {
        if ('toplevel_page_ai-chatbot' !== $hook) {
            return;
        }

        wp_enqueue_script('jquery');
    }

    /**
     * Render admin page
     */
    public function render_admin_page() {
        if (isset($_POST['ai_chatbot_save'])) {
            check_admin_referer('ai_chatbot_settings');

            // Save all settings
            $options = array(
                'ai_chatbot_enabled' => isset($_POST['enabled']),
                'ai_chatbot_welcome_message' => sanitize_text_field(wp_unslash($_POST['welcome_message'] ?? '')),
                'ai_chatbot_placeholder' => sanitize_text_field(wp_unslash($_POST['placeholder'] ?? '')),
                'ai_chatbot_position' => sanitize_text_field(wp_unslash($_POST['position'] ?? 'right')),
                'ai_chatbot_ai_provider' => sanitize_text_field(wp_unslash($_POST['ai_provider'] ?? 'kimi')),
                'ai_chatbot_api_key' => sanitize_text_field(wp_unslash($_POST['api_key'] ?? '')),
                'ai_chatbot_model' => sanitize_text_field(wp_unslash($_POST['model'] ?? '')),
                'ai_chatbot_system_prompt' => wp_kses_post(wp_unslash($_POST['system_prompt'] ?? '')),
                'ai_chatbot_notification_email' => sanitize_email($_POST['notification_email'] ?? ''),
                'ai_chatbot_serverchan_key' => sanitize_text_field(wp_unslash($_POST['serverchan_key'] ?? '')),
            );

            foreach ($options as $key => $value) {
                update_option($key, $value);
            }

            echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved!', 'ai-chatbot') . '</p></div>';
        }

        // Get current settings
        $enabled = get_option('ai_chatbot_enabled', 1);
        $welcome = get_option('ai_chatbot_welcome_message', $this->get_default_welcome());
        $placeholder = get_option('ai_chatbot_placeholder', __('Type your message...', 'ai-chatbot'));
        $position = get_option('ai_chatbot_position', 'right');
        $provider = get_option('ai_chatbot_ai_provider', 'kimi');
        $api_key = get_option('ai_chatbot_api_key', '');
        $model = get_option('ai_chatbot_model', 'moonshot-v1-128k');
        $system_prompt = get_option('ai_chatbot_system_prompt', '');
        $email = get_option('ai_chatbot_notification_email', get_option('admin_email'));
        $sendkey = get_option('ai_chatbot_serverchan_key', '');

        ?>
        <div class="wrap">
            <h1>🤖 <?php echo esc_html__('AI Chatbot Settings', 'ai-chatbot'); ?></h1>

            <form method="post" action="">
                <?php wp_nonce_field('ai_chatbot_settings'); ?>

                <h2 class="nav-tab-wrapper">
                    <a href="#basic" class="nav-tab nav-tab-active"><?php echo esc_html__('Basic Settings', 'ai-chatbot'); ?></a>
                    <a href="#ai" class="nav-tab"><?php echo esc_html__('AI Configuration', 'ai-chatbot'); ?></a>
                    <a href="#notification" class="nav-tab"><?php echo esc_html__('Notification Settings', 'ai-chatbot'); ?></a>
                </h2>

                <!-- Basic Settings -->
                <div id="basic" class="tab-content">
                    <table class="form-table">
                        <tr>
                            <th><label for="enabled"><?php echo esc_html__('Enable Chatbot', 'ai-chatbot'); ?></label></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="enabled" value="1" <?php checked($enabled, 1); ?>>
                                    <?php echo esc_html__('Enabled', 'ai-chatbot'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="welcome_message"><?php echo esc_html__('Welcome Message', 'ai-chatbot'); ?></label></th>
                            <td>
                                <input type="text" name="welcome_message" id="welcome_message" value="<?php echo esc_attr($welcome); ?>" class="regular-text">
                                <p class="description"><?php echo esc_html__('Message shown when visitors first open the chat', 'ai-chatbot'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="placeholder"><?php echo esc_html__('Input Placeholder', 'ai-chatbot'); ?></label></th>
                            <td>
                                <input type="text" name="placeholder" id="placeholder" value="<?php echo esc_attr($placeholder); ?>" class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th><label for="position"><?php echo esc_html__('Position', 'ai-chatbot'); ?></label></th>
                            <td>
                                <select name="position" id="position">
                                    <option value="left" <?php selected($position, 'left'); ?>><?php echo esc_html__('Bottom Left', 'ai-chatbot'); ?></option>
                                    <option value="right" <?php selected($position, 'right'); ?>><?php echo esc_html__('Bottom Right', 'ai-chatbot'); ?></option>
                                </select>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- AI Configuration -->
                <div id="ai" class="tab-content" style="display:none;">
                    <table class="form-table">
                        <tr>
                            <th><label for="ai_provider"><?php echo esc_html__('AI Provider', 'ai-chatbot'); ?></label></th>
                            <td>
                                <select name="ai_provider" id="ai_provider">
                                    <option value="zhipu" <?php selected($provider, 'zhipu'); ?>><?php echo esc_html__('Zhipu AI (GLM)', 'ai-chatbot'); ?></option>
                                    <option value="kimi" <?php selected($provider, 'kimi'); ?>><?php echo esc_html__('Kimi (Moonshot)', 'ai-chatbot'); ?></option>
                                    <option value="openai" <?php selected($provider, 'openai'); ?>><?php echo esc_html__('OpenAI (GPT)', 'ai-chatbot'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="api_key"><?php echo esc_html__('API Key', 'ai-chatbot'); ?></label></th>
                            <td>
                                <input type="password" name="api_key" id="api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th><label for="model"><?php echo esc_html__('Model Name', 'ai-chatbot'); ?></label></th>
                            <td>
                                <input type="text" name="model" id="model" value="<?php echo esc_attr($model); ?>" class="regular-text">
                                <p class="description">
                                    <strong><?php echo esc_html__('Zhipu:', 'ai-chatbot'); ?></strong> glm-4, glm-3-turbo<br>
                                    <strong><?php echo esc_html__('Kimi:', 'ai-chatbot'); ?></strong> moonshot-v1-128k, moonshot-v1-32k, moonshot-v1-8k<br>
                                    <strong><?php echo esc_html__('OpenAI:', 'ai-chatbot'); ?></strong> gpt-4, gpt-3.5-turbo
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th><label><?php echo esc_html__('Test Connection', 'ai-chatbot'); ?></label></th>
                            <td>
                                <button type="button" id="ai-chatbot-test-btn" class="button">
                                    <span class="dashicons dashicons-admin-generic"></span> <?php echo esc_html__('Test API Connection', 'ai-chatbot'); ?>
                                </button>
                                <span id="ai-chatbot-test-result" style="margin-left: 10px;"></span>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="system_prompt"><?php echo esc_html__('System Prompt', 'ai-chatbot'); ?></label></th>
                            <td>
                                <textarea name="system_prompt" id="system_prompt" rows="10" class="large-text"><?php echo esc_textarea($system_prompt); ?></textarea>
                                <p class="description"><?php echo esc_html__('Customize AI\'s role and behavior. Leave empty to use default settings.', 'ai-chatbot'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Notification Settings -->
                <div id="notification" class="tab-content" style="display:none;">
                    <table class="form-table">
                        <tr>
                            <th><label for="notification_email"><?php echo esc_html__('Email Notification', 'ai-chatbot'); ?></label></th>
                            <td>
                                <input type="email" name="notification_email" id="notification_email" value="<?php echo esc_attr($email); ?>" class="regular-text">
                                <p class="description"><?php echo esc_html__('Email address to receive inquiry notifications', 'ai-chatbot'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="serverchan_key"><?php echo esc_html__('WeChat Notification (ServerChan)', 'ai-chatbot'); ?></label></th>
                            <td>
                                <input type="text" name="serverchan_key" id="serverchan_key" value="<?php echo esc_attr($sendkey); ?>" class="regular-text">
                                <p class="description">
                                    <a href="https://sct.ftqq.com/" target="_blank"><?php echo esc_html__('Get SendKey', 'ai-chatbot'); ?></a>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>

                <?php submit_button(__('Save Settings', 'ai-chatbot'), 'primary', 'ai_chatbot_save'); ?>
            </form>

            <style>
                .tab-content { margin-top: 20px; }
                .nav-tab { cursor: pointer; }
            </style>

            <script>
                jQuery(document).ready(function($) {
                    $('.nav-tab').on('click', function(e) {
                        e.preventDefault();
                        $('.nav-tab').removeClass('nav-tab-active');
                        $(this).addClass('nav-tab-active');

                        var target = $(this).attr('href');
                        $('.tab-content').hide();
                        $(target).show();
                    });

                    $('#ai-chatbot-test-btn').on('click', function() {
                        var $btn = $(this);
                        var $result = $('#ai-chatbot-test-result');

                        $btn.prop('disabled', true).text('<?php echo esc_js__('Testing...', 'ai-chatbot'); ?>');
                        $result.html('<span style="color: #999;"><?php echo esc_js__('Connecting to API...', 'ai-chatbot'); ?></span>');

                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'ai_chatbot_test',
                                nonce: '<?php echo wp_create_nonce('ai_chatbot_nonce'); ?>',
                                provider: $('select[name="ai_provider"]').val(),
                                api_key: $('input[name="api_key"]').val(),
                                model: $('input[name="model"]').val()
                            },
                            success: function(response) {
                                if (response.success) {
                                    $result.html('<span style="color: green;">✓ ' + response.data.message + '</span>');
                                    if (response.data.reply) {
                                        $result.append('<br><small style="color: #666;">' + response.data.reply + '</small>');
                                    }
                                } else {
                                    $result.html('<span style="color: red;">✗ ' + response.data.message + '</span>');
                                }
                            },
                            error: function() {
                                $result.html('<span style="color: red;">✗ <?php echo esc_js__('Network error', 'ai-chatbot'); ?></span>');
                            },
                            complete: function() {
                                $btn.prop('disabled', false).html('<span class="dashicons dashicons-admin-generic"></span> <?php echo esc_js__('Test API Connection', 'ai-chatbot'); ?>');
                            }
                        });
                    });
                });
            </script>
        </div>
        <?php
    }
}
