<?php
/**
 * 后台UI类
 *
 * @package AI_SEO_Auditor
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AI_SEO_Admin_UI类
 *
 * 负责处理批量审计和仪表盘功能
 */
class AI_SEO_Admin_UI {

    /**
     * 单例实例
     */
    private static $instance = null;

    /**
     * SEO分析器
     */
    private $analyzer;

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
        $this->analyzer = AI_SEO_Analyzer::get_instance();
        $this->init_hooks();
    }

    /**
     * 初始化钩子
     */
    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_batch_audit_page'));
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widget'));
        add_action('wp_ajax_ai_seo_batch_analyze', array($this, 'ajax_batch_analyze'));
        add_action('admin_post_ai_seo_export_csv', array($this, 'export_csv'));
    }

    /**
     * 添加批量审计页面
     */
    public function add_batch_audit_page() {
        add_submenu_page(
            'ai-seo-auditor',
            __('批量审计', 'ai-seo-auditor'),
            __('批量审计', 'ai-seo-auditor'),
            'manage_options',
            'ai-seo-batch-audit',
            array($this, 'render_batch_audit_page')
        );
    }

    /**
     * 渲染批量审计页面
     */
    public function render_batch_audit_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // 获取所有分类
        $categories = get_categories(array(
            'hide_empty' => false
        ));

        // 获取所有标签
        $tags = get_tags(array(
            'hide_empty' => false
        ));

        ?>
        <div class="wrap">
            <h1><?php _e('批量 SEO 审计', 'ai-seo-auditor'); ?></h1>

            <div class="ai-seo-batch-container">
                <!-- 筛选器 -->
                <div class="ai-seo-batch-filters">
                    <h2><?php _e('选择要审计的文章', 'ai-seo-auditor'); ?></h2>

                    <form id="ai-seo-batch-form">
                        <?php wp_nonce_field('ai_seo_nonce', 'ai_seo_nonce'); ?>

                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label><?php _e('筛选方式', 'ai-seo-auditor'); ?></label>
                                </th>
                                <td>
                                    <label>
                                        <input type="radio" name="filter_type" value="all" checked>
                                        <?php _e('所有已发布文章', 'ai-seo-auditor'); ?>
                                    </label><br>
                                    <label>
                                        <input type="radio" name="filter_type" value="category">
                                        <?php _e('按分类', 'ai-seo-auditor'); ?>
                                    </label><br>
                                    <label>
                                        <input type="radio" name="filter_type" value="tag">
                                        <?php _e('按标签', 'ai-seo-auditor'); ?>
                                    </label><br>
                                    <label>
                                        <input type="radio" name="filter_type" value="no_audit">
                                        <?php _e('仅未审计的文章', 'ai-seo-auditor'); ?>
                                    </label><br>
                                    <label>
                                        <input type="radio" name="filter_type" value="low_score">
                                        <?php _e('评分低于60的文章', 'ai-seo-auditor'); ?>
                                    </label>
                                </td>
                            </tr>

                            <tr class="category-filter" style="display: none;">
                                <th scope="row">
                                    <label for="category_id"><?php _e('选择分类', 'ai-seo-auditor'); ?></label>
                                </th>
                                <td>
                                    <select name="category_id" id="category_id" class="regular-text">
                                        <option value=""><?php _e('-- 选择分类 --', 'ai-seo-auditor'); ?></option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo esc_attr($category->term_id); ?>">
                                                <?php echo esc_html($category->name); ?> (<?php echo $category->count; ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>

                            <tr class="tag-filter" style="display: none;">
                                <th scope="row">
                                    <label for="tag_id"><?php _e('选择标签', 'ai-seo-auditor'); ?></label>
                                </th>
                                <td>
                                    <select name="tag_id" id="tag_id" class="regular-text">
                                        <option value=""><?php _e('-- 选择标签 --', 'ai-seo-auditor'); ?></option>
                                        <?php foreach ($tags as $tag): ?>
                                            <option value="<?php echo esc_attr($tag->term_id); ?>">
                                                <?php echo esc_html($tag->name); ?> (<?php echo $tag->count; ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="limit"><?php _e('限制数量', 'ai-seo-auditor'); ?></label>
                                </th>
                                <td>
                                    <input
                                        type="number"
                                        name="limit"
                                        id="limit"
                                        value="10"
                                        min="1"
                                        max="100"
                                        class="small-text"
                                    />
                                    <p class="description">
                                        <?php _e('一次最多审计100篇文章', 'ai-seo-auditor'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>

                        <p class="submit">
                            <button type="submit" class="button button-primary button-large">
                                <?php _e('开始批量审计', 'ai-seo-auditor'); ?>
                            </button>
                        </p>
                    </form>
                </div>

                <!-- 进度显示 -->
                <div class="ai-seo-batch-progress" style="display: none;">
                    <h2><?php _e('审计进度', 'ai-seo-auditor'); ?></h2>
                    <div class="progress-bar-container">
                        <div class="progress-bar">
                            <div class="progress-bar-fill" style="width: 0%;"></div>
                        </div>
                        <div class="progress-text">0%</div>
                    </div>
                    <div class="progress-stats">
                        <p>
                            <?php _e('总数:', 'ai-seo-auditor'); ?> <span class="total-count">0</span> |
                            <?php _e('成功:', 'ai-seo-auditor'); ?> <span class="success-count">0</span> |
                            <?php _e('失败:', 'ai-seo-auditor'); ?> <span class="failed-count">0</span>
                        </p>
                    </div>
                    <div class="progress-log"></div>
                </div>

                <!-- 结果显示 -->
                <div class="ai-seo-batch-results" style="display: none;">
                    <h2><?php _e('审计结果', 'ai-seo-auditor'); ?></h2>
                    <div class="results-summary"></div>
                    <div class="results-actions">
                        <a href="#" class="button" id="view-results-btn">
                            <?php _e('查看详细结果', 'ai-seo-auditor'); ?>
                        </a>
                        <a href="<?php echo esc_url(admin_url('admin-post.php?action=ai_seo_export_csv')); ?>" class="button">
                            <?php _e('导出 CSV 报告', 'ai-seo-auditor'); ?>
                        </a>
                    </div>
                </div>

                <!-- 所有文章SEO评分列表 -->
                <div class="ai-seo-all-scores">
                    <h2><?php _e('所有文章 SEO 评分', 'ai-seo-auditor'); ?></h2>
                    <?php $this->render_all_scores_table(); ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * 渲染所有文章评分表格
     */
    private function render_all_scores_table() {
        global $wpdb;

        $query = "SELECT p.ID, p.post_title, p.post_date, pm.meta_value as score, pm2.meta_value as timestamp
                FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_seo_audit_score'
                LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_seo_audit_timestamp'
                WHERE p.post_type = 'post' AND p.post_status = 'publish'
                ORDER BY pm.meta_value ASC, p.post_date DESC
                LIMIT 50";

        $posts = $wpdb->get_results($query);

        if (empty($posts)) {
            echo '<p>' . __('暂无数据', 'ai-seo-auditor') . '</p>';
            return;
        }

        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('文章标题', 'ai-seo-auditor'); ?></th>
                    <th><?php _e('发布日期', 'ai-seo-auditor'); ?></th>
                    <th><?php _e('SEO 评分', 'ai-seo-auditor'); ?></th>
                    <th><?php _e('审计时间', 'ai-seo-auditor'); ?></th>
                    <th><?php _e('操作', 'ai-seo-auditor'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($posts as $post): ?>
                    <tr>
                        <td>
                            <strong>
                                <a href="<?php echo esc_url(get_edit_post_link($post->ID)); ?>">
                                    <?php echo esc_html($post->post_title); ?>
                                </a>
                            </strong>
                        </td>
                        <td>
                            <?php echo esc_html(mysql2date(get_option('date_format'), $post->post_date)); ?>
                        </td>
                        <td>
                            <?php if ($post->score): ?>
                                <?php
                                $score = intval($post->score);
                                $class = 'score-badge ';
                                if ($score >= 80) {
                                    $class .= 'score-excellent';
                                } elseif ($score >= 60) {
                                    $class .= 'score-good';
                                } elseif ($score >= 40) {
                                    $class .= 'score-fair';
                                } else {
                                    $class .= 'score-poor';
                                }
                                ?>
                                <span class="<?php echo esc_attr($class); ?>">
                                    <?php echo esc_html($score); ?>/100
                                </span>
                            <?php else: ?>
                                <span class="score-badge score-none">
                                    <?php _e('未审计', 'ai-seo-auditor'); ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            if ($post->timestamp) {
                                echo esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $post->timestamp));
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td>
                            <a href="<?php echo esc_url(get_edit_post_link($post->ID)); ?>" class="button button-small">
                                <?php _e('编辑', 'ai-seo-auditor'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * 添加仪表盘Widget
     */
    public function add_dashboard_widget() {
        wp_add_dashboard_widget(
            'ai_seo_dashboard_widget',
            __('AI SEO 审计统计', 'ai-seo-auditor'),
            array($this, 'render_dashboard_widget')
        );
    }

    /**
     * 渲染仪表盘Widget
     */
    public function render_dashboard_widget() {
        $avg_score = $this->analyzer->get_average_seo_score();
        $posts_need_optimization = $this->analyzer->get_posts_need_optimization(60, 5);

        // 获取已审计文章总数
        global $wpdb;
        $audited_count = $wpdb->get_var(
            "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = '_seo_audit_score'"
        );

        ?>
        <div class="ai-seo-dashboard-widget">
            <div class="ai-seo-widget-stats">
                <div class="widget-stat">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-chart-line"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo esc_html($avg_score); ?></div>
                        <div class="stat-label"><?php _e('平均 SEO 评分', 'ai-seo-auditor'); ?></div>
                    </div>
                </div>

                <div class="widget-stat">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-list-view"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo esc_html($audited_count); ?></div>
                        <div class="stat-label"><?php _e('已审计文章', 'ai-seo-auditor'); ?></div>
                    </div>
                </div>

                <div class="widget-stat">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-warning"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo count($posts_need_optimization); ?></div>
                        <div class="stat-label"><?php _e('需要优化', 'ai-seo-auditor'); ?></div>
                    </div>
                </div>
            </div>

            <?php if (!empty($posts_need_optimization)): ?>
                <div class="ai-seo-widget-posts">
                    <h4><?php _e('需要优化的文章', 'ai-seo-auditor'); ?></h4>
                    <ul>
                        <?php foreach ($posts_need_optimization as $post): ?>
                            <li>
                                <a href="<?php echo esc_url(get_edit_post_link($post->ID)); ?>">
                                    <?php echo esc_html($post->post_title); ?>
                                </a>
                                <span class="score-badge score-<?php echo $post->score < 40 ? 'poor' : 'fair'; ?>">
                                    <?php echo esc_html($post->score); ?>/100
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="ai-seo-widget-actions">
                <a href="<?php echo esc_url(admin_url('admin.php?page=ai-seo-batch-audit')); ?>" class="button button-primary">
                    <?php _e('批量审计', 'ai-seo-auditor'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=ai-seo-auditor')); ?>" class="button">
                    <?php _e('插件设置', 'ai-seo-auditor'); ?>
                </a>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX批量分析
     */
    public function ajax_batch_analyze() {
        check_ajax_referer('ai_seo_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('权限不足', 'ai-seo-auditor')
            ));
        }

        $filter_type = isset($_POST['filter_type']) ? sanitize_text_field($_POST['filter_type']) : 'all';
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 10;
        $limit = min($limit, 100); // 最多100篇

        // 构建查询参数
        $args = array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'orderby' => 'date',
            'order' => 'DESC'
        );

        // 根据筛选类型调整查询
        switch ($filter_type) {
            case 'category':
                $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
                if ($category_id) {
                    $args['cat'] = $category_id;
                }
                break;

            case 'tag':
                $tag_id = isset($_POST['tag_id']) ? intval($_POST['tag_id']) : 0;
                if ($tag_id) {
                    $args['tag_id'] = $tag_id;
                }
                break;

            case 'no_audit':
                $args['meta_query'] = array(
                    array(
                        'key' => '_seo_audit_score',
                        'compare' => 'NOT EXISTS'
                    )
                );
                break;

            case 'low_score':
                $args['meta_query'] = array(
                    array(
                        'key' => '_seo_audit_score',
                        'value' => 60,
                        'compare' => '<',
                        'type' => 'NUMERIC'
                    )
                );
                break;
        }

        $query = new WP_Query($args);
        $post_ids = wp_list_pluck($query->posts, 'ID');

        if (empty($post_ids)) {
            wp_send_json_error(array(
                'message' => __('没有找到符合条件的文章', 'ai-seo-auditor')
            ));
        }

        // 执行批量分析
        $results = $this->analyzer->batch_analyze($post_ids);

        wp_send_json_success($results);
    }

    /**
     * 导出CSV报告
     */
    public function export_csv() {
        if (!current_user_can('manage_options')) {
            wp_die(__('权限不足', 'ai-seo-auditor'));
        }

        global $wpdb;

        $query = "SELECT p.ID, p.post_title, p.post_date, pm.meta_value as score
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = 'post'
                AND p.post_status = 'publish'
                AND pm.meta_key = '_seo_audit_score'
                ORDER BY p.post_date DESC";

        $posts = $wpdb->get_results($query);

        // 设置CSV头
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="seo-audit-report-' . date('Y-m-d') . '.csv"');

        // 输出BOM以支持Excel中文显示
        echo "\xEF\xBB\xBF";

        $output = fopen('php://output', 'w');

        // 写入表头
        fputcsv($output, array(
            __('文章ID', 'ai-seo-auditor'),
            __('文章标题', 'ai-seo-auditor'),
            __('发布日期', 'ai-seo-auditor'),
            __('SEO评分', 'ai-seo-auditor'),
            __('文章链接', 'ai-seo-auditor')
        ));

        // 写入数据
        foreach ($posts as $post) {
            fputcsv($output, array(
                $post->ID,
                $post->post_title,
                $post->post_date,
                $post->score,
                get_permalink($post->ID)
            ));
        }

        fclose($output);
        exit;
    }
}
