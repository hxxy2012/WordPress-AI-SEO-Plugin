<?php
/**
 * 通知中心类
 *
 * @package AI_SEO_Auditor
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AI_SEO_Notification_Center类
 *
 * 管理SEO改进提醒和通知
 */
class AI_SEO_Notification_Center {

    /**
     * 单例实例
     */
    private static $instance = null;

    /**
     * 通知类型
     */
    const TYPE_INFO = 'info';
    const TYPE_SUCCESS = 'success';
    const TYPE_WARNING = 'warning';
    const TYPE_ERROR = 'error';

    /**
     * 获取单例实例
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 构造函数
     */
    private function __construct() {
        $this->init_hooks();
    }

    /**
     * 初始化钩子
     */
    private function init_hooks() {
        // 添加管理栏菜单
        add_action('admin_bar_menu', array($this, 'add_admin_bar_menu'), 100);

        // 添加通知中心页面
        add_action('admin_menu', array($this, 'add_notification_page'));

        // AJAX处理
        add_action('wp_ajax_ai_seo_get_notifications', array($this, 'ajax_get_notifications'));
        add_action('wp_ajax_ai_seo_dismiss_notification', array($this, 'ajax_dismiss_notification'));
        add_action('wp_ajax_ai_seo_mark_all_read', array($this, 'ajax_mark_all_read'));

        // 定期检查和生成通知
        add_action('ai_seo_check_notifications', array($this, 'check_and_generate_notifications'));

        // 每日检查任务
        if (!wp_next_scheduled('ai_seo_check_notifications')) {
            wp_schedule_event(time(), 'daily', 'ai_seo_check_notifications');
        }

        // 加载通知中心资源
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    /**
     * 添加管理栏菜单
     */
    public function add_admin_bar_menu($admin_bar) {
        if (!current_user_can('edit_posts')) {
            return;
        }

        $notifications = $this->get_unread_notifications();
        $count = count($notifications);

        $admin_bar->add_node(array(
            'id' => 'ai-seo-notifications',
            'title' => '<span class="ab-icon dashicons dashicons-bell"></span>' .
                       ($count > 0 ? '<span class="ai-seo-notification-count">' . $count . '</span>' : ''),
            'href' => admin_url('admin.php?page=ai-seo-notifications'),
            'meta' => array(
                'class' => 'ai-seo-notification-menu',
                'title' => __('SEO通知', 'ai-seo-auditor')
            )
        ));

        // 添加最近的3条通知
        if (!empty($notifications)) {
            $recent = array_slice($notifications, 0, 3);

            foreach ($recent as $notification) {
                $admin_bar->add_node(array(
                    'parent' => 'ai-seo-notifications',
                    'id' => 'ai-seo-notification-' . $notification['id'],
                    'title' => wp_trim_words($notification['message'], 10),
                    'href' => $notification['link'],
                    'meta' => array(
                        'class' => 'ai-seo-notification-item notification-' . $notification['type']
                    )
                ));
            }

            // 查看全部链接
            $admin_bar->add_node(array(
                'parent' => 'ai-seo-notifications',
                'id' => 'ai-seo-view-all',
                'title' => __('查看全部通知', 'ai-seo-auditor'),
                'href' => admin_url('admin.php?page=ai-seo-notifications'),
                'meta' => array(
                    'class' => 'ai-seo-view-all-notifications'
                )
            ));
        }
    }

    /**
     * 添加通知中心页面
     */
    public function add_notification_page() {
        add_submenu_page(
            'ai-seo-auditor',
            __('通知中心', 'ai-seo-auditor'),
            __('通知中心', 'ai-seo-auditor'),
            'edit_posts',
            'ai-seo-notifications',
            array($this, 'render_notification_page')
        );
    }

    /**
     * 渲染通知中心页面
     */
    public function render_notification_page() {
        $notifications = $this->get_all_notifications();
        $stats = $this->get_notification_stats();

        ?>
        <div class="wrap ai-seo-notifications-page">
            <h1>
                <?php _e('SEO通知中心', 'ai-seo-auditor'); ?>
                <button type="button" class="button" id="mark-all-read-btn">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <?php _e('全部标记为已读', 'ai-seo-auditor'); ?>
                </button>
            </h1>

            <!-- 统计卡片 -->
            <div class="notification-stats">
                <div class="stat-card">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-bell"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo esc_html($stats['total']); ?></div>
                        <div class="stat-label"><?php _e('总通知', 'ai-seo-auditor'); ?></div>
                    </div>
                </div>

                <div class="stat-card unread">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-marker"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo esc_html($stats['unread']); ?></div>
                        <div class="stat-label"><?php _e('未读', 'ai-seo-auditor'); ?></div>
                    </div>
                </div>

                <div class="stat-card warnings">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-warning"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo esc_html($stats['warnings']); ?></div>
                        <div class="stat-label"><?php _e('警告', 'ai-seo-auditor'); ?></div>
                    </div>
                </div>

                <div class="stat-card improvements">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-chart-line"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo esc_html($stats['improvements']); ?></div>
                        <div class="stat-label"><?php _e('待改进', 'ai-seo-auditor'); ?></div>
                    </div>
                </div>
            </div>

            <!-- 通知列表 -->
            <div class="notifications-container">
                <?php if (empty($notifications)): ?>
                    <div class="no-notifications">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <p><?php _e('太棒了！目前没有通知。', 'ai-seo-auditor'); ?></p>
                    </div>
                <?php else: ?>
                    <div class="notifications-list">
                        <?php foreach ($notifications as $notification): ?>
                            <?php $this->render_notification_item($notification); ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * 渲染单个通知项
     */
    private function render_notification_item($notification) {
        $is_read = !empty($notification['is_read']);
        $type_class = 'notification-' . $notification['type'];
        $read_class = $is_read ? 'notification-read' : 'notification-unread';

        ?>
        <div class="notification-item <?php echo esc_attr($type_class . ' ' . $read_class); ?>"
             data-notification-id="<?php echo esc_attr($notification['id']); ?>">

            <div class="notification-icon">
                <?php echo $this->get_notification_icon($notification['type']); ?>
            </div>

            <div class="notification-content">
                <div class="notification-header">
                    <h3 class="notification-title"><?php echo esc_html($notification['title']); ?></h3>
                    <span class="notification-time">
                        <?php echo esc_html(human_time_diff(strtotime($notification['created_at']), current_time('timestamp'))); ?>
                        <?php _e('前', 'ai-seo-auditor'); ?>
                    </span>
                </div>

                <div class="notification-message">
                    <?php echo wp_kses_post($notification['message']); ?>
                </div>

                <?php if (!empty($notification['link'])): ?>
                    <div class="notification-actions">
                        <a href="<?php echo esc_url($notification['link']); ?>" class="button button-primary button-small">
                            <?php echo esc_html($notification['action_text'] ?? __('查看详情', 'ai-seo-auditor')); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <div class="notification-dismiss">
                <button type="button" class="dismiss-notification-btn"
                        data-notification-id="<?php echo esc_attr($notification['id']); ?>"
                        title="<?php esc_attr_e('忽略', 'ai-seo-auditor'); ?>">
                    <span class="dashicons dashicons-no-alt"></span>
                </button>
            </div>
        </div>
        <?php
    }

    /**
     * 获取通知图标
     */
    private function get_notification_icon($type) {
        $icons = array(
            self::TYPE_INFO => '<span class="dashicons dashicons-info"></span>',
            self::TYPE_SUCCESS => '<span class="dashicons dashicons-yes-alt"></span>',
            self::TYPE_WARNING => '<span class="dashicons dashicons-warning"></span>',
            self::TYPE_ERROR => '<span class="dashicons dashicons-dismiss"></span>'
        );

        return $icons[$type] ?? $icons[self::TYPE_INFO];
    }

    /**
     * 获取所有通知
     */
    private function get_all_notifications($limit = 50) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_seo_notifications';

        // 确保表存在
        $this->create_notifications_table();

        $notifications = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table_name}
                 WHERE user_id = %d
                 ORDER BY created_at DESC
                 LIMIT %d",
                get_current_user_id(),
                $limit
            ),
            ARRAY_A
        );

        return $notifications ?: array();
    }

    /**
     * 获取未读通知
     */
    private function get_unread_notifications() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_seo_notifications';

        // 确保表存在
        $this->create_notifications_table();

        $notifications = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table_name}
                 WHERE user_id = %d AND is_read = 0
                 ORDER BY created_at DESC",
                get_current_user_id()
            ),
            ARRAY_A
        );

        return $notifications ?: array();
    }

    /**
     * 获取通知统计
     */
    private function get_notification_stats() {
        $all = $this->get_all_notifications(1000);

        $stats = array(
            'total' => count($all),
            'unread' => 0,
            'warnings' => 0,
            'improvements' => 0
        );

        foreach ($all as $notification) {
            if (empty($notification['is_read'])) {
                $stats['unread']++;
            }

            if ($notification['type'] === self::TYPE_WARNING || $notification['type'] === self::TYPE_ERROR) {
                $stats['warnings']++;
            }

            if (strpos($notification['message'], '改进') !== false || strpos($notification['message'], '优化') !== false) {
                $stats['improvements']++;
            }
        }

        return $stats;
    }

    /**
     * 创建通知
     */
    public function create_notification($title, $message, $type = self::TYPE_INFO, $link = '', $action_text = '', $user_id = null) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_seo_notifications';

        // 确保表存在
        $this->create_notifications_table();

        if ($user_id === null) {
            $user_id = get_current_user_id();
        }

        $data = array(
            'user_id' => $user_id,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'link' => $link,
            'action_text' => $action_text,
            'created_at' => current_time('mysql'),
            'is_read' => 0
        );

        $wpdb->insert($table_name, $data);

        return $wpdb->insert_id;
    }

    /**
     * 创建通知表
     */
    private function create_notifications_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_seo_notifications';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            title varchar(255) NOT NULL,
            message text NOT NULL,
            type varchar(20) NOT NULL DEFAULT 'info',
            link varchar(500) DEFAULT '',
            action_text varchar(100) DEFAULT '',
            created_at datetime NOT NULL,
            is_read tinyint(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY is_read (is_read),
            KEY created_at (created_at)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * 检查并生成通知
     */
    public function check_and_generate_notifications() {
        global $wpdb;

        // 查找低分文章
        $low_score_posts = $wpdb->get_results(
            "SELECT post_id, meta_value as score
             FROM {$wpdb->postmeta}
             WHERE meta_key = '_seo_audit_score'
             AND CAST(meta_value AS UNSIGNED) < 60
             ORDER BY CAST(meta_value AS UNSIGNED) ASC
             LIMIT 10"
        );

        if (!empty($low_score_posts)) {
            foreach ($low_score_posts as $post_data) {
                $post = get_post($post_data->post_id);
                if (!$post) continue;

                // 检查是否已有相同通知
                $existing = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}ai_seo_notifications
                     WHERE user_id = %d
                     AND link LIKE %s
                     AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)",
                    $post->post_author,
                    '%post=' . $post->ID . '%'
                ));

                if (!$existing) {
                    $this->create_notification(
                        __('文章SEO评分较低', 'ai-seo-auditor'),
                        sprintf(
                            __('文章「%s」的SEO评分仅为 %d 分，建议尽快优化。', 'ai-seo-auditor'),
                            $post->post_title,
                            $post_data->score
                        ),
                        self::TYPE_WARNING,
                        admin_url('post.php?post=' . $post->ID . '&action=edit'),
                        __('立即优化', 'ai-seo-auditor'),
                        $post->post_author
                    );
                }
            }
        }

        // 查找未设置Meta描述的文章
        $this->check_missing_meta_descriptions();

        // 查找未设置特色图片的文章
        $this->check_missing_featured_images();
    }

    /**
     * 检查缺少Meta描述的文章
     */
    private function check_missing_meta_descriptions() {
        global $wpdb;

        $posts = $wpdb->get_results(
            "SELECT ID, post_title, post_author
             FROM {$wpdb->posts}
             WHERE post_status = 'publish'
             AND post_type = 'post'
             AND ID NOT IN (
                 SELECT post_id FROM {$wpdb->postmeta}
                 WHERE meta_key IN ('_yoast_wpseo_metadesc', 'rank_math_description', '_aioseo_description')
                 AND meta_value != ''
             )
             LIMIT 5"
        );

        foreach ($posts as $post) {
            $this->create_notification(
                __('缺少Meta描述', 'ai-seo-auditor'),
                sprintf(
                    __('文章「%s」未设置Meta描述，这会影响搜索引擎显示效果。', 'ai-seo-auditor'),
                    $post->post_title
                ),
                self::TYPE_WARNING,
                admin_url('post.php?post=' . $post->ID . '&action=edit#ai-seo-auditor'),
                __('添加描述', 'ai-seo-auditor'),
                $post->post_author
            );
        }
    }

    /**
     * 检查缺少特色图片的文章
     */
    private function check_missing_featured_images() {
        $posts = get_posts(array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => 5,
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => '_thumbnail_id',
                    'compare' => 'NOT EXISTS'
                )
            )
        ));

        foreach ($posts as $post) {
            $this->create_notification(
                __('缺少特色图片', 'ai-seo-auditor'),
                sprintf(
                    __('文章「%s」未设置特色图片，建议添加以提升视觉吸引力。', 'ai-seo-auditor'),
                    $post->post_title
                ),
                self::TYPE_INFO,
                admin_url('post.php?post=' . $post->ID . '&action=edit'),
                __('添加图片', 'ai-seo-auditor'),
                $post->post_author
            );
        }
    }

    /**
     * AJAX获取通知
     */
    public function ajax_get_notifications() {
        check_ajax_referer('ai_seo_nonce', 'nonce');

        $notifications = $this->get_all_notifications();
        wp_send_json_success($notifications);
    }

    /**
     * AJAX忽略通知
     */
    public function ajax_dismiss_notification() {
        check_ajax_referer('ai_seo_nonce', 'nonce');

        $notification_id = isset($_POST['notification_id']) ? intval($_POST['notification_id']) : 0;

        if (!$notification_id) {
            wp_send_json_error(array('message' => __('无效的通知ID', 'ai-seo-auditor')));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_seo_notifications';

        $updated = $wpdb->update(
            $table_name,
            array('is_read' => 1),
            array(
                'id' => $notification_id,
                'user_id' => get_current_user_id()
            ),
            array('%d'),
            array('%d', '%d')
        );

        if ($updated !== false) {
            wp_send_json_success(array('message' => __('通知已标记为已读', 'ai-seo-auditor')));
        } else {
            wp_send_json_error(array('message' => __('操作失败', 'ai-seo-auditor')));
        }
    }

    /**
     * AJAX全部标记为已读
     */
    public function ajax_mark_all_read() {
        check_ajax_referer('ai_seo_nonce', 'nonce');

        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_seo_notifications';

        $updated = $wpdb->update(
            $table_name,
            array('is_read' => 1),
            array('user_id' => get_current_user_id()),
            array('%d'),
            array('%d')
        );

        wp_send_json_success(array(
            'message' => __('所有通知已标记为已读', 'ai-seo-auditor'),
            'count' => $updated
        ));
    }

    /**
     * 加载脚本
     */
    public function enqueue_scripts($hook) {
        // 在所有管理页面加载管理栏样式
        wp_add_inline_style('admin-bar', '
            #wpadminbar .ai-seo-notification-count {
                position: absolute;
                top: 5px;
                right: 0;
                min-width: 18px;
                height: 18px;
                line-height: 18px;
                padding: 0 5px;
                background: #dc3232;
                color: #fff;
                font-size: 11px;
                font-weight: 600;
                border-radius: 10px;
                text-align: center;
            }
            #wpadminbar .ai-seo-notification-item {
                border-left: 3px solid transparent;
            }
            #wpadminbar .notification-warning {
                border-left-color: #ffb900;
            }
            #wpadminbar .notification-error {
                border-left-color: #dc3232;
            }
            #wpadminbar .notification-success {
                border-left-color: #46b450;
            }
        ');

        // 仅在通知页面加载完整资源
        if ($hook === 'ai-seo-auditor_page_ai-seo-notifications') {
            wp_enqueue_style(
                'ai-seo-notifications',
                AI_SEO_PLUGIN_URL . 'assets/css/notifications.css',
                array(),
                AI_SEO_VERSION
            );

            wp_enqueue_script(
                'ai-seo-notifications',
                AI_SEO_PLUGIN_URL . 'assets/js/notifications.js',
                array('jquery'),
                AI_SEO_VERSION,
                true
            );

            wp_localize_script('ai-seo-notifications', 'aiSeoNotifications', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ai_seo_nonce')
            ));
        }
    }
}
