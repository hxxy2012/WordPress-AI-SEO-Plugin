<?php
/**
 * 定时任务调度类
 *
 * @package AI_SEO_Auditor
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AI_SEO_Scheduler类
 *
 * 负责管理定时任务和邮件通知
 */
class AI_SEO_Scheduler {

    /**
     * 单例实例
     */
    private static $instance = null;

    /**
     * 定时任务钩子名称
     */
    const CRON_HOOK = 'ai_seo_scheduled_audit';
    const EMAIL_HOOK = 'ai_seo_weekly_report';

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
        // 注册定时任务
        add_action(self::CRON_HOOK, array($this, 'run_scheduled_audit'));
        add_action(self::EMAIL_HOOK, array($this, 'send_weekly_report'));

        // 管理界面设置
        add_action('admin_menu', array($this, 'add_scheduler_page'), 30);
        add_action('admin_post_ai_seo_schedule_audit', array($this, 'handle_schedule_submit'));
    }

    /**
     * 激活定时任务
     *
     * @param string $frequency 频率 (hourly, twicedaily, daily, weekly)
     */
    public function activate_scheduled_audit($frequency = 'weekly') {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time(), $frequency, self::CRON_HOOK);
        }
    }

    /**
     * 停用定时任务
     */
    public function deactivate_scheduled_audit() {
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::CRON_HOOK);
        }
    }

    /**
     * 激活周报邮件
     */
    public function activate_weekly_report() {
        if (!wp_next_scheduled(self::EMAIL_HOOK)) {
            // 每周一上午9点发送
            $next_monday = strtotime('next monday 09:00:00');
            wp_schedule_event($next_monday, 'weekly', self::EMAIL_HOOK);
        }
    }

    /**
     * 停用周报邮件
     */
    public function deactivate_weekly_report() {
        $timestamp = wp_next_scheduled(self::EMAIL_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::EMAIL_HOOK);
        }
    }

    /**
     * 执行定时审计
     */
    public function run_scheduled_audit() {
        $analyzer = AI_SEO_Analyzer::get_instance();

        // 获取配置的审计选项
        $options = get_option('ai_seo_scheduler_options', array());
        $limit = isset($options['audit_limit']) ? intval($options['audit_limit']) : 10;
        $score_threshold = isset($options['score_threshold']) ? intval($options['score_threshold']) : 60;

        // 查询需要审计的文章
        $args = array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => '_seo_audit_score',
                    'compare' => 'NOT EXISTS'
                ),
                array(
                    'key' => '_seo_audit_score',
                    'value' => $score_threshold,
                    'compare' => '<',
                    'type' => 'NUMERIC'
                )
            ),
            'orderby' => 'date',
            'order' => 'DESC'
        );

        $posts = get_posts($args);

        $results = array(
            'total' => 0,
            'success' => 0,
            'failed' => 0,
            'posts' => array()
        );

        foreach ($posts as $post) {
            $results['total']++;

            $result = $analyzer->analyze_post($post->ID);

            if (!is_wp_error($result)) {
                $results['success']++;
                $results['posts'][] = array(
                    'id' => $post->ID,
                    'title' => $post->post_title,
                    'score' => $result['overall_score']
                );
            } else {
                $results['failed']++;
            }

            // 延迟以避免API限流
            sleep(2);
        }

        // 记录审计结果
        $this->log_audit_result($results);

        // 如果启用了邮件通知，发送结果
        if (isset($options['enable_email']) && $options['enable_email']) {
            $this->send_audit_notification($results);
        }
    }

    /**
     * 发送周报
     */
    public function send_weekly_report() {
        $analyzer = AI_SEO_Analyzer::get_instance();

        // 获取统计数据
        $avg_score = $analyzer->get_average_seo_score();
        $posts_need_optimization = $analyzer->get_posts_need_optimization(60, 10);

        // 获取本周审计的文章
        global $wpdb;
        $week_ago = date('Y-m-d H:i:s', strtotime('-7 days'));
        $weekly_audits = $wpdb->get_results($wpdb->prepare(
            "SELECT COUNT(*) as count, AVG(overall_score) as avg_score
            FROM {$wpdb->prefix}ai_seo_audit_history
            WHERE audit_date >= %s",
            $week_ago
        ));

        // 构建邮件内容
        $to = get_option('admin_email');
        $subject = sprintf(__('[%s] SEO周报 - %s', 'ai-seo-auditor'),
            get_bloginfo('name'),
            date('Y-m-d')
        );

        $message = $this->build_weekly_report_html(array(
            'avg_score' => $avg_score,
            'posts_need_optimization' => $posts_need_optimization,
            'weekly_audits' => $weekly_audits
        ));

        $headers = array('Content-Type: text/html; charset=UTF-8');

        wp_mail($to, $subject, $message, $headers);
    }

    /**
     * 构建周报HTML
     *
     * @param array $data 报告数据
     * @return string HTML内容
     */
    private function build_weekly_report_html($data) {
        $html = '<html><body style="font-family: Arial, sans-serif; line-height: 1.6;">';
        $html .= '<div style="max-width: 600px; margin: 0 auto; padding: 20px;">';

        $html .= '<h1 style="color: #0073aa;">SEO 审计周报</h1>';
        $html .= '<p>您好！这是本周的SEO审计报告。</p>';

        $html .= '<div style="background: #f8f9fa; padding: 15px; margin: 20px 0; border-left: 4px solid #0073aa;">';
        $html .= '<h2 style="margin-top: 0;">网站概览</h2>';
        $html .= '<p><strong>平均SEO评分：</strong> ' . esc_html($data['avg_score']) . '/100</p>';

        if (!empty($data['weekly_audits'])) {
            $audit_data = $data['weekly_audits'][0];
            $html .= '<p><strong>本周审计文章：</strong> ' . intval($audit_data->count) . ' 篇</p>';
            if ($audit_data->count > 0) {
                $html .= '<p><strong>本周平均评分：</strong> ' . round($audit_data->avg_score, 1) . '/100</p>';
            }
        }
        $html .= '</div>';

        if (!empty($data['posts_need_optimization'])) {
            $html .= '<h2>需要优化的文章</h2>';
            $html .= '<table style="width: 100%; border-collapse: collapse;">';
            $html .= '<thead><tr style="background: #0073aa; color: white;">';
            $html .= '<th style="padding: 10px; text-align: left;">文章标题</th>';
            $html .= '<th style="padding: 10px; text-align: center;">评分</th>';
            $html .= '</tr></thead><tbody>';

            foreach ($data['posts_need_optimization'] as $post) {
                $html .= '<tr style="border-bottom: 1px solid #ddd;">';
                $html .= '<td style="padding: 10px;">';
                $html .= '<a href="' . get_edit_post_link($post->ID) . '">' . esc_html($post->post_title) . '</a>';
                $html .= '</td>';
                $html .= '<td style="padding: 10px; text-align: center;">' . esc_html($post->score) . '/100</td>';
                $html .= '</tr>';
            }

            $html .= '</tbody></table>';
        }

        $html .= '<div style="margin-top: 30px; padding: 15px; background: #e9ecef; border-radius: 4px;">';
        $html .= '<p style="margin: 0;">登录WordPress后台查看详细报告：';
        $html .= '<a href="' . admin_url('admin.php?page=ai-seo-auditor') . '" style="color: #0073aa;">查看详情</a></p>';
        $html .= '</div>';

        $html .= '</div></body></html>';

        return $html;
    }

    /**
     * 发送审计通知
     *
     * @param array $results 审计结果
     */
    private function send_audit_notification($results) {
        $to = get_option('admin_email');
        $subject = sprintf(__('[%s] 定时SEO审计完成', 'ai-seo-auditor'), get_bloginfo('name'));

        $message = '<html><body style="font-family: Arial, sans-serif;">';
        $message .= '<h2>定时SEO审计已完成</h2>';
        $message .= '<p>审计结果如下：</p>';
        $message .= '<ul>';
        $message .= '<li>总计：' . $results['total'] . ' 篇文章</li>';
        $message .= '<li>成功：' . $results['success'] . ' 篇</li>';
        $message .= '<li>失败：' . $results['failed'] . ' 篇</li>';
        $message .= '</ul>';

        if (!empty($results['posts'])) {
            $message .= '<h3>审计文章列表：</h3>';
            $message .= '<ul>';
            foreach ($results['posts'] as $post) {
                $message .= '<li>' . esc_html($post['title']) . ' - 评分：' . $post['score'] . '/100</li>';
            }
            $message .= '</ul>';
        }

        $message .= '</body></html>';

        $headers = array('Content-Type: text/html; charset=UTF-8');

        wp_mail($to, $subject, $message, $headers);
    }

    /**
     * 记录审计结果
     *
     * @param array $results 结果数据
     */
    private function log_audit_result($results) {
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'results' => $results
        );

        $log = get_option('ai_seo_scheduler_log', array());
        array_unshift($log, $log_entry);

        // 只保留最近10条记录
        $log = array_slice($log, 0, 10);

        update_option('ai_seo_scheduler_log', $log);
    }

    /**
     * 添加调度管理页面
     */
    public function add_scheduler_page() {
        add_submenu_page(
            'ai-seo-auditor',
            __('定时任务', 'ai-seo-auditor'),
            __('定时任务', 'ai-seo-auditor'),
            'manage_options',
            'ai-seo-scheduler',
            array($this, 'render_scheduler_page')
        );
    }

    /**
     * 渲染调度管理页面
     */
    public function render_scheduler_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $options = get_option('ai_seo_scheduler_options', array(
            'enable_audit' => false,
            'audit_frequency' => 'weekly',
            'audit_limit' => 10,
            'score_threshold' => 60,
            'enable_email' => true,
            'enable_weekly_report' => false
        ));

        $next_audit = wp_next_scheduled(self::CRON_HOOK);
        $next_report = wp_next_scheduled(self::EMAIL_HOOK);

        ?>
        <div class="wrap">
            <h1><?php _e('定时任务管理', 'ai-seo-auditor'); ?></h1>

            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <?php wp_nonce_field('ai_seo_scheduler', 'ai_seo_scheduler_nonce'); ?>
                <input type="hidden" name="action" value="ai_seo_schedule_audit">

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('启用定时审计', 'ai-seo-auditor'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="enable_audit" value="1"
                                    <?php checked($options['enable_audit'], true); ?>>
                                <?php _e('自动审计文章', 'ai-seo-auditor'); ?>
                            </label>
                            <p class="description">
                                <?php _e('启用后将自动审计低评分或未审计的文章', 'ai-seo-auditor'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('审计频率', 'ai-seo-auditor'); ?></th>
                        <td>
                            <select name="audit_frequency">
                                <option value="daily" <?php selected($options['audit_frequency'], 'daily'); ?>>
                                    <?php _e('每天', 'ai-seo-auditor'); ?>
                                </option>
                                <option value="twicedaily" <?php selected($options['audit_frequency'], 'twicedaily'); ?>>
                                    <?php _e('每天两次', 'ai-seo-auditor'); ?>
                                </option>
                                <option value="weekly" <?php selected($options['audit_frequency'], 'weekly'); ?>>
                                    <?php _e('每周', 'ai-seo-auditor'); ?>
                                </option>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('每次审计文章数', 'ai-seo-auditor'); ?></th>
                        <td>
                            <input type="number" name="audit_limit"
                                value="<?php echo esc_attr($options['audit_limit']); ?>"
                                min="1" max="50" class="small-text">
                            <p class="description">
                                <?php _e('每次运行时审计的文章数量（建议不超过20篇）', 'ai-seo-auditor'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('评分阈值', 'ai-seo-auditor'); ?></th>
                        <td>
                            <input type="number" name="score_threshold"
                                value="<?php echo esc_attr($options['score_threshold']); ?>"
                                min="0" max="100" class="small-text">
                            <p class="description">
                                <?php _e('优先审计评分低于此值的文章', 'ai-seo-auditor'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('邮件通知', 'ai-seo-auditor'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="enable_email" value="1"
                                    <?php checked($options['enable_email'], true); ?>>
                                <?php _e('审计完成后发送邮件通知', 'ai-seo-auditor'); ?>
                            </label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('周报邮件', 'ai-seo-auditor'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="enable_weekly_report" value="1"
                                    <?php checked($options['enable_weekly_report'], true); ?>>
                                <?php _e('每周一发送SEO周报', 'ai-seo-auditor'); ?>
                            </label>
                        </td>
                    </tr>
                </table>

                <?php submit_button(__('保存设置', 'ai-seo-auditor')); ?>
            </form>

            <hr>

            <h2><?php _e('任务状态', 'ai-seo-auditor'); ?></h2>
            <table class="widefat">
                <tr>
                    <th><?php _e('定时审计', 'ai-seo-auditor'); ?></th>
                    <td>
                        <?php if ($next_audit): ?>
                            <?php _e('下次运行：', 'ai-seo-auditor'); ?>
                            <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $next_audit); ?>
                        <?php else: ?>
                            <?php _e('未启用', 'ai-seo-auditor'); ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('周报邮件', 'ai-seo-auditor'); ?></th>
                    <td>
                        <?php if ($next_report): ?>
                            <?php _e('下次发送：', 'ai-seo-auditor'); ?>
                            <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $next_report); ?>
                        <?php else: ?>
                            <?php _e('未启用', 'ai-seo-auditor'); ?>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>

            <?php $this->render_audit_log(); ?>
        </div>
        <?php
    }

    /**
     * 渲染审计日志
     */
    private function render_audit_log() {
        $log = get_option('ai_seo_scheduler_log', array());

        if (empty($log)) {
            return;
        }

        ?>
        <h2><?php _e('最近的审计记录', 'ai-seo-auditor'); ?></h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('时间', 'ai-seo-auditor'); ?></th>
                    <th><?php _e('总计', 'ai-seo-auditor'); ?></th>
                    <th><?php _e('成功', 'ai-seo-auditor'); ?></th>
                    <th><?php _e('失败', 'ai-seo-auditor'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($log as $entry): ?>
                    <tr>
                        <td><?php echo esc_html($entry['timestamp']); ?></td>
                        <td><?php echo esc_html($entry['results']['total']); ?></td>
                        <td><?php echo esc_html($entry['results']['success']); ?></td>
                        <td><?php echo esc_html($entry['results']['failed']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * 处理调度设置提交
     */
    public function handle_schedule_submit() {
        if (!current_user_can('manage_options')) {
            wp_die(__('权限不足', 'ai-seo-auditor'));
        }

        check_admin_referer('ai_seo_scheduler', 'ai_seo_scheduler_nonce');

        $options = array(
            'enable_audit' => isset($_POST['enable_audit']),
            'audit_frequency' => sanitize_text_field($_POST['audit_frequency']),
            'audit_limit' => intval($_POST['audit_limit']),
            'score_threshold' => intval($_POST['score_threshold']),
            'enable_email' => isset($_POST['enable_email']),
            'enable_weekly_report' => isset($_POST['enable_weekly_report'])
        );

        update_option('ai_seo_scheduler_options', $options);

        // 更新定时任务
        if ($options['enable_audit']) {
            $this->deactivate_scheduled_audit();
            $this->activate_scheduled_audit($options['audit_frequency']);
        } else {
            $this->deactivate_scheduled_audit();
        }

        // 更新周报
        if ($options['enable_weekly_report']) {
            $this->activate_weekly_report();
        } else {
            $this->deactivate_weekly_report();
        }

        wp_redirect(add_query_arg(
            array('page' => 'ai-seo-scheduler', 'updated' => 'true'),
            admin_url('admin.php')
        ));
        exit;
    }
}
