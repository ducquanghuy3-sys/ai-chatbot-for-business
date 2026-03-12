<?php
/**
 * Chatbot Widget Template
 *
 * @package AI_Chatbot
 */

if (!defined('ABSPATH')) exit;

// Get settings from WordPress
$position = get_option('ai_chatbot_position', 'right');
$placeholder = get_option('ai_chatbot_placeholder', __('Type your message...', 'ai-chatbot'));
?>

<div id="ai-chatbot-container" class="ai-chatbot-position-<?php echo esc_attr($position); ?>">

    <!-- 悬浮按钮 -->
    <button id="ai-chatbot-toggle" aria-label="Open chat">
        <svg viewBox="0 0 24 24" fill="currentColor">
            <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"/>
            <circle cx="9" cy="10" r="1.5"/>
            <circle cx="15" cy="10" r="1.5"/>
            <circle cx="12" cy="14" r="1.5"/>
        </svg>
    </button>

    <!-- 聊天窗口 -->
    <div id="ai-chatbot-window">

        <!-- 头部 -->
        <div id="ai-chatbot-header">
            <div id="ai-chatbot-header-title">
                <div id="ai-chatbot-avatar">🤖</div>
                <div>
                    <div id="ai-chatbot-name">AI Assistant</div>
                    <div id="ai-chatbot-status">Online</div>
                </div>
            </div>
            <div id="ai-chatbot-header-buttons">
                <button class="ai-chatbot-header-btn" id="ai-chatbot-minimize" aria-label="Minimize">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M5 12h14"/>
                    </svg>
                </button>
                <button class="ai-chatbot-header-btn" id="ai-chatbot-close" aria-label="Close">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 6L6 18M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- 消息区 -->
        <div id="ai-chatbot-body">
            <div id="ai-chatbot-messages">
                <!-- 消息会动态添加到这里 -->
            </div>

            <!-- 输入状态 -->
            <div id="ai-chatbot-typing">
                <div class="ai-chatbot-typing-dot"></div>
                <div class="ai-chatbot-typing-dot"></div>
                <div class="ai-chatbot-typing-dot"></div>
            </div>
        </div>

        <!-- 输入区 -->
        <div id="ai-chatbot-input-area">
            <textarea
                id="ai-chatbot-input"
                placeholder="<?php echo esc_attr($placeholder); ?>"
                rows="1"
                aria-label="Type your message"></textarea>
            <button id="ai-chatbot-send" aria-label="Send">
                <svg viewBox="0 0 24 24" fill="currentColor">
                    <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                </svg>
            </button>
        </div>

    </div>

</div>
