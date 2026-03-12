<?php
/**
 * Uninstall plugin
 *
 * @package AI_Chatbot
 */

// Exit if accessed directly
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Check if user has proper permissions
if (!defined('WP_UNINSTALL_PLUGIN') || !current_user_can('delete_plugins')) {
    exit;
}

// Delete all plugin options
$options = array(
    'ai_chatbot_enabled',
    'ai_chatbot_welcome_message',
    'ai_chatbot_placeholder',
    'ai_chatbot_position',
    'ai_chatbot_ai_provider',
    'ai_chatbot_api_key',
    'ai_chatbot_model',
    'ai_chatbot_system_prompt',
    'ai_chatbot_notification_email',
    'ai_chatbot_serverchan_key',
);

foreach ($options as $option) {
    delete_option($option);
}

// Delete custom database tables if they exist
global $wpdb;
$table_name = $wpdb->prefix . 'ai_chatbot_inquiries';

$wpdb->query("DROP TABLE IF EXISTS {$table_name}");

// Clear any cached data
wp_cache_flush();
