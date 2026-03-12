<?php
/**
 * Notifier - Email + WeChat notifications
 *
 * @package AI_Chatbot
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class AI_Chatbot_Notifier {

    /**
     * Inquiry keywords
     *
     * @var array
     */
    private $inquiry_keywords = array(
        // English
        'price', 'pricing', 'cost', 'how much', 'quote', 'quotation',
        'order', 'purchase', 'buy', 'moq', 'minimum order',
        'cooperation', 'partner', 'distributor', 'wholesale',
        'sample', 'catalog', 'brochure',

        // Chinese
        '价格', '报价', '多少钱', '成本',
        '订单', '采购', '购买', '起订量',
        '合作', '代理', '批发',
        '样品', '目录', '画册',

        // Arabic
        'سعر', 'تكلفة', 'طلب', 'شراء',
        'تعاون', 'موزع', 'جملة',
    );

    /**
     * Detect inquiry intent
     *
     * @param string $message Message content
     * @return bool True if inquiry detected
     */
    public function detect_inquiry($message) {
        $message_lower = strtolower($message);

        foreach ($this->inquiry_keywords as $keyword) {
            if (strpos($message_lower, strtolower($keyword)) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Send inquiry notification
     *
     * @param array $data Inquiry data
     */
    public function send_inquiry_notification($data) {
        // Send email notification
        $this->send_email_notification($data);

        // Send WeChat notification
        $this->send_wechat_notification($data);

        // Save to database
        $this->save_inquiry($data);
    }

    /**
     * Send email notification
     */
    private function send_email_notification($data) {
        $email = get_option('ai_chatbot_notification_email', get_option('admin_email'));

        if (empty($email)) {
            return;
        }

        $subject = sprintf(
            /* translators: %s: site name */
            __('[%s] New Inquiry from Website Chatbot', 'ai-chatbot'),
            get_bloginfo('name')
        );

        $message = $this->format_email_message($data);

        $headers = array('Content-Type: text/plain; charset=UTF-8');
        wp_mail($email, $subject, $message, $headers);
    }

    /**
     * Format email message
     */
    private function format_email_message($data) {
        $message = sprintf(
            /* translators: %s: site name */
            __("You have received a new inquiry from the website chatbot.\n\n", 'ai-chatbot')
        );

        $message .= sprintf(
            /* translators: %s: message content */
            __("Message: %s\n", 'ai-chatbot'),
            $data['message']
        );

        if (!empty($data['page_url'])) {
            $message .= sprintf(
                /* translators: %s: page URL */
                __("Page: %s\n", 'ai-chatbot'),
                $data['page_url']
            );
        }

        $message .= sprintf(
            /* translators: %s: time */
            __("\nTime: %s\n", 'ai-chatbot'),
            current_time('Y-m-d H:i:s')
        );

        return $message;
    }

    /**
     * Send WeChat notification (ServerChan)
     */
    private function send_wechat_notification($data) {
        $sendkey = get_option('ai_chatbot_serverchan_key', '');

        if (empty($sendkey)) {
            return;
        }

        $title = sprintf(
            /* translators: %s: site name */
            __('[%s] New Website Inquiry', 'ai-chatbot'),
            get_bloginfo('name')
        );

        $desp = $this->format_wechat_message($data);

        $url = 'https://sctapi.ftqq.com/' . $sendkey . '.send';

        wp_remote_post($url, array(
            'body' => array(
                'title' => $title,
                'desp' => $desp,
            ),
            'timeout' => 10,
        ));
    }

    /**
     * Format WeChat message
     */
    private function format_wechat_message($data) {
        $message = "### " . __("New Inquiry", 'ai-chatbot') . "\n\n";
        $message .= "**" . __("Message:", 'ai-chatbot') . "** " . $data['message'] . "\n\n";

        if (!empty($data['page_url'])) {
            $message .= "**" . __("Page:", 'ai-chatbot') . "** " . $data['page_url'] . "\n\n";
        }

        $message .= "**" . __("Time:", 'ai-chatbot') . "** " . current_time('Y-m-d H:i:s') . "\n";

        return $message;
    }

    /**
     * Save inquiry to database
     */
    private function save_inquiry($data) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ai_chatbot_inquiries';

        // Check if table exists
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table_name
        ));

        if (!$table_exists) {
            $this->create_inquiries_table();
        }

        $wpdb->insert(
            $table_name,
            array(
                'message' => $data['message'],
                'page_url' => $data['page_url'] ?? '',
                'created_at' => current_time('mysql'),
            ),
            array('%s', '%s', '%s')
        );
    }

    /**
     * Create inquiries table
     */
    private function create_inquiries_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ai_chatbot_inquiries';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            message text NOT NULL,
            page_url varchar(500) DEFAULT '',
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}
