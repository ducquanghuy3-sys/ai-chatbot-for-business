/**
 * AI Chatbot - 前端脚本
 * @version 1.0.1
 */

(function() {
    'use strict';

    const config = window.aiChatbot || {};
    let chatHistory = [];
    let isOpen = false;
    let elements = {};

    function init() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', setupChatbot);
        } else {
            setupChatbot();
        }
    }

    function setupChatbot() {
        elements = {
            container: document.getElementById('ai-chatbot-container'),
            toggle: document.getElementById('ai-chatbot-toggle'),
            close: document.getElementById('ai-chatbot-close'),
            minimize: document.getElementById('ai-chatbot-minimize'),
            send: document.getElementById('ai-chatbot-send'),
            input: document.getElementById('ai-chatbot-input'),
            messages: document.getElementById('ai-chatbot-messages'),
            typing: document.getElementById('ai-chatbot-typing')
        };

        if (!elements.container) return;

        bindEvents();

        setTimeout(() => {
            addMessage(config.welcomeMessage || 'Hello! How can I help you?', 'bot');
        }, 500);
    }

    function bindEvents() {
        if (elements.toggle) {
            elements.toggle.addEventListener('click', toggleChat);
        }
        if (elements.close) {
            elements.close.addEventListener('click', closeChat);
        }
        if (elements.minimize) {
            elements.minimize.addEventListener('click', minimizeChat);
        }
        if (elements.send) {
            elements.send.addEventListener('click', sendMessage);
        }
        if (elements.input) {
            elements.input.addEventListener('keypress', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    sendMessage();
                }
            });
        }
    }

    function toggleChat() {
        isOpen = !isOpen;
        elements.container.classList.toggle('ai-chatbot-open', isOpen);
    }

    function closeChat() {
        isOpen = false;
        elements.container.classList.remove('ai-chatbot-open');
    }

    function minimizeChat() {
        elements.container.classList.toggle('ai-chatbot-minimized');
    }

    function sendMessage() {
        const message = elements.input.value.trim();
        if (!message) return;

        elements.input.value = '';
        addMessage(message, 'user');
        showTyping();

        fetch(config.ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'ai_chatbot_chat',
                nonce: config.nonce,
                message: message,
                history: JSON.stringify(chatHistory),
                language: config.language || 'en',
                page_url: window.location.href
            })
        })
        .then(response => response.json())
        .then(data => {
            hideTyping();
            if (data.success) {
                addMessage(data.data.reply, 'bot');
                chatHistory.push({ role: 'user', content: message });
                chatHistory.push({ role: 'assistant', content: data.data.reply });

                if (data.data.is_inquiry) {
                    showInquiryNotice();
                }
            } else {
                addMessage('Sorry, an error occurred. Please try again.', 'bot');
            }
        })
        .catch(error => {
            hideTyping();
            console.error('Chatbot Error:', error);
            addMessage('Network error. Please check your connection.', 'bot');
        });
    }

    function addMessage(text, type) {
        const messageDiv = document.createElement('div');
        messageDiv.className = 'ai-chatbot-message ai-chatbot-message-' + type;

        const formattedText = formatMessage(text);

        messageDiv.innerHTML = `
            <div class="ai-chatbot-message-content">${formattedText}</div>
            <div class="ai-chatbot-message-time">${formatTime(new Date())}</div>
        `;

        if (elements.typing) {
            elements.messages.insertBefore(messageDiv, elements.typing);
        } else {
            elements.messages.appendChild(messageDiv);
        }

        scrollToBottom();
    }

    function formatMessage(text) {
        return text
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
            .replace(/\n/g, '<br>')
            .replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>')
            .replace(/([\w.]+@[\w.]+)/g, '<a href="mailto:$1">$1</a>');
    }

    function formatTime(date) {
        return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    function showTyping() {
        if (elements.typing) {
            elements.typing.style.display = 'flex';
            scrollToBottom();
        }
    }

    function hideTyping() {
        if (elements.typing) {
            elements.typing.style.display = 'none';
        }
    }

    function scrollToBottom() {
        if (elements.messages) {
            elements.messages.scrollTop = elements.messages.scrollHeight;
        }
    }

    function showInquiryNotice() {
        const notice = document.createElement('div');
        notice.className = 'ai-chatbot-notice';
        notice.style.cssText = 'position:absolute;bottom:80px;left:50%;transform:translateX(-50%);background:#4CAF50;color:white;padding:10px 16px;border-radius:8px;font-size:13px;white-space:nowrap;display:flex;align-items:center;gap:10px;';
        notice.innerHTML = `
            <span>Your message has been sent to our team!</span>
            <button class="ai-chatbot-notice-close" style="background:none;border:none;color:white;font-size:18px;cursor:pointer;">×</button>
        `;

        elements.container.appendChild(notice);

        setTimeout(() => {
            notice.style.opacity = '0';
            setTimeout(() => notice.remove(), 300);
        }, 5000);

        notice.querySelector('.ai-chatbot-notice-close').addEventListener('click', () => {
            notice.remove();
        });
    }

    init();
})();
