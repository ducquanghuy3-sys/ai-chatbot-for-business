<?php
/**
 * AI Adapter - Support multiple AI providers
 *
 * @package AI_Chatbot
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class AI_Chatbot_AI_Adapter {

    /**
     * AI provider
     *
     * @var string
     */
    private $provider;

    /**
     * API key
     *
     * @var string
     */
    private $api_key;

    /**
     * Model name
     *
     * @var string
     */
    private $model;

    /**
     * Constructor
     */
    public function __construct($provider = null, $api_key = null, $model = null) {
        $this->provider = $provider ?? get_option('ai_chatbot_ai_provider', 'kimi');
        $this->api_key = $api_key ?? get_option('ai_chatbot_api_key', '');
        $this->model = $model ?? get_option('ai_chatbot_model', 'moonshot-v1-128k');
    }

    /**
     * Chat method
     *
     * @param string $message User message
     * @param array $history Chat history
     * @param string $language Language code
     * @return string AI response
     */
    public function chat($message, $history = array(), $language = 'en') {
        if (empty($this->api_key)) {
            return __('Please configure API Key in settings.', 'ai-chatbot');
        }

        $messages = $this->build_messages($message, $history, $language);

        switch ($this->provider) {
            case 'zhipu':
                return $this->chat_zhipu($messages);
            case 'kimi':
                return $this->chat_kimi($messages);
            case 'openai':
                return $this->chat_openai($messages);
            default:
                return __('Unknown AI provider.', 'ai-chatbot');
        }
    }

    /**
     * Build messages array
     */
    private function build_messages($message, $history, $language) {
        $messages = array();

        // Add system prompt
        $system_prompt = get_option('ai_chatbot_system_prompt', '');
        if (!empty($system_prompt)) {
            $messages[] = array(
                'role' => 'system',
                'content' => $system_prompt
            );
        } else {
            $messages[] = array(
                'role' => 'system',
                'content' => $this->get_default_system_prompt($language)
            );
        }

        // Add history
        foreach ($history as $item) {
            $messages[] = array(
                'role' => sanitize_text_field($item['role']),
                'content' => sanitize_text_field($item['content'])
            );
        }

        // Add current message
        $messages[] = array(
            'role' => 'user',
            'content' => sanitize_text_field($message)
        );

        return $messages;
    }

    /**
     * Get default system prompt
     */
    private function get_default_system_prompt($language) {
        $prompts = array(
            'zh' => __('You are a professional customer service assistant. Answer user questions in a friendly and professional manner. If users ask about pricing, orders, or cooperation, guide them to leave contact information.', 'ai-chatbot'),
            'en' => __('You are a professional customer service assistant. Answer user questions in a friendly and professional manner. If users ask about pricing, orders, or cooperation, guide them to leave contact information.', 'ai-chatbot'),
            'ar' => __('أنت مساعد خدمة عملاء محترف. أجب على أسئلة المستخدمين بطريقة ودودة واحترافية.', 'ai-chatbot'),
        );

        return isset($prompts[$language]) ? $prompts[$language] : $prompts['en'];
    }

    /**
     * Zhipu AI chat
     */
    private function chat_zhipu($messages) {
        $url = 'https://open.bigmodel.cn/api/paas/v4/chat/completions';

        $response = wp_remote_post($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode(array(
                'model' => $this->model,
                'messages' => $messages,
                'temperature' => 0.7,
                'max_tokens' => 2000,
            )),
            'timeout' => 30,
        ));

        if (is_wp_error($response)) {
            return __('Error: ', 'ai-chatbot') . $response->get_error_message();
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['choices'][0]['message']['content'])) {
            return wp_kses_post($body['choices'][0]['message']['content']);
        }

        return __('API request failed. Please check your configuration.', 'ai-chatbot');
    }

    /**
     * Kimi chat
     */
    private function chat_kimi($messages) {
        $url = 'https://api.moonshot.cn/v1/chat/completions';

        $response = wp_remote_post($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode(array(
                'model' => $this->model,
                'messages' => $messages,
                'temperature' => 0.7,
                'max_tokens' => 2000,
            )),
            'timeout' => 30,
        ));

        if (is_wp_error($response)) {
            return __('Error: ', 'ai-chatbot') . $response->get_error_message();
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['choices'][0]['message']['content'])) {
            return wp_kses_post($body['choices'][0]['message']['content']);
        }

        return __('API request failed. Please check your configuration.', 'ai-chatbot');
    }

    /**
     * OpenAI chat
     */
    private function chat_openai($messages) {
        $url = 'https://api.openai.com/v1/chat/completions';

        $response = wp_remote_post($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode(array(
                'model' => $this->model,
                'messages' => $messages,
                'temperature' => 0.7,
                'max_tokens' => 2000,
            )),
            'timeout' => 30,
        ));

        if (is_wp_error($response)) {
            return __('Error: ', 'ai-chatbot') . $response->get_error_message();
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['choices'][0]['message']['content'])) {
            return wp_kses_post($body['choices'][0]['message']['content']);
        }

        return __('API request failed. Please check your configuration.', 'ai-chatbot');
    }
}
