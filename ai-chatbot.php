<?php
/**
 * Plugin Name: AI Chatbot for Business
 * Plugin URI: https://github.com/yourusername/ai-chatbot
 * Description: AI-powered chatbot for WordPress with multi-provider support (OpenAI, Kimi, Zhipu). Features inquiry detection, email + WeChat notifications, and multilingual support (EN/CN/AR).
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ai-chatbot
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * @package AI_Chatbot
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Plugin version
define('AI_CHATBOT_VERSION', '1.0.0');

// Plugin paths
define('AI_CHATBOT_FILE', __FILE__);
define('AI_CHATBOT_DIR', plugin_dir_path(__FILE__));
define('AI_CHATBOT_URI', plugin_dir_url(__FILE__));
define('AI_CHATBOT_BASENAME', plugin_basename(__FILE__));

// Load core classes
require_once AI_CHATBOT_DIR . 'includes/class-main.php';
require_once AI_CHATBOT_DIR . 'includes/class-ai-adapter.php';
require_once AI_CHATBOT_DIR . 'includes/class-notifier.php';

// Initialize plugin
function ai_chatbot_init() {
    return AI_Chatbot_Main::get_instance();
}
add_action('plugins_loaded', 'ai_chatbot_init');

// Activation hook
register_activation_hook(__FILE__, 'ai_chatbot_activate');
function ai_chatbot_activate() {
    // Set default options
    $defaults = array(
        'ai_chatbot_enabled' => 1,
        'ai_chatbot_position' => 'right',
        'ai_chatbot_ai_provider' => 'kimi',
        'ai_chatbot_model' => 'moonshot-v1-128k',
        'ai_chatbot_welcome_message' => 'Hi! 👋 Welcome to our website. How can I help you today?',
        'ai_chatbot_placeholder' => 'Type your message...',
    );

    foreach ($defaults as $key => $value) {
        if (get_option($key) === false) {
            update_option($key, $value);
        }
    }
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'ai_chatbot_deactivate');
function ai_chatbot_deactivate() {
    // Flush rewrite rules if needed
    flush_rewrite_rules();
}

// Load text domain for translations
add_action('init', 'ai_chatbot_load_textdomain');
function ai_chatbot_load_textdomain() {
    load_plugin_textdomain('ai-chatbot', false, dirname(AI_CHATBOT_BASENAME) . '/languages');
}
