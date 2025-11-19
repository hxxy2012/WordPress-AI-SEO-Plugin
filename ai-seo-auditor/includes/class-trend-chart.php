<?php
/**
 * SEO评分趋势图表类
 *
 * @package AI_SEO_Auditor
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AI_SEO_Trend_Chart类
 *
 * 负责生成和显示SEO评分趋势图表
 */
class AI_SEO_Trend_Chart {

    /**
     * 单例实例
     */
    private static $instance = null;

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
        // 添加菜单页面
        add_action('admin_menu', array($this, 'add_menu_page'));

        // 加载Chart.js
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));

        // AJAX处理
        add_action('wp_ajax_ai_seo_get_trend_data', array($this, 'ajax_get_trend_data'));

        // 添加仪表板小部件
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widget'));
    }

    /**
     * 添加菜单页面
     */
    public function add_menu_page() {
        add_submenu_page(
            'ai-seo-auditor',
            __('SEO趋势分析', 'ai-seo-auditor'),
            __('趋势分析', 'ai-seo-auditor'),
            'manage_options',
            'ai-seo-trends',
            array($this, 'render_trends_page')
        );
    }

    /**
     * 加载脚本
     */
    public function enqueue_scripts($hook) {
        // 仅在趋势页面和仪表板加载
        if ($hook !== 'ai-seo-auditor_page_ai-seo-trends' && $hook !== 'index.php') {
            return;
        }

        // Chart.js from CDN
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
            array(),
            '4.4.0',
            true
        );

        // 自定义趋势脚本
        wp_enqueue_script(
            'ai-seo-trends',
            AI_SEO_PLUGIN_URL . 'assets/js/trends.js',
            array('jquery', 'chartjs'),
            AI_SEO_VERSION,
            true
        );

        wp_enqueue_style(
            'ai-seo-trends',
            AI_SEO_PLUGIN_URL . 'assets/css/trends.css',
            array(),
            AI_SEO_VERSION
        );

        // 本地化脚本
        wp_localize_script('ai-seo-trends', 'aiSeoTrends', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ai_seo_trends_nonce')
        ));
    }

    /**
     * 渲染趋势页面
     */
    public function render_trends_page() {
        ?>
        <div class="wrap ai-seo-trends-page">
            <h1><?php echo esc_html__('SEO评分趋势分析', 'ai-seo-auditor'); ?></h1>

            <div class="trends-filters">
                <div class="filter-group">
                    <label for="trend-post-select"><?php _e('选择文章:', 'ai-seo-auditor'); ?></label>
                    <select id="trend-post-select" class="trend-filter">
                        <option value="all"><?php _e('所有文章平均', 'ai-seo-auditor'); ?></option>
                        <?php
                        $posts = get_posts(array(
                            'post_type' => 'post',
                            'posts_per_page' => -1,
                            'orderby' => 'date',
                            'order' => 'DESC'
                        ));
                        foreach ($posts as $post) {
                            echo '<option value="' . esc_attr($post->ID) . '">' . esc_html($post->post_title) . '</option>';
                        }
                        ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="trend-period-select"><?php _e('时间范围:', 'ai-seo-auditor'); ?></label>
                    <select id="trend-period-select" class="trend-filter">
                        <option value="7"><?php _e('最近7天', 'ai-seo-auditor'); ?></option>
                        <option value="30" selected><?php _e('最近30天', 'ai-seo-auditor'); ?></option>
                        <option value="90"><?php _e('最近90天', 'ai-seo-auditor'); ?></option>
                        <option value="365"><?php _e('最近一年', 'ai-seo-auditor'); ?></option>
                        <option value="all"><?php _e('全部时间', 'ai-seo-auditor'); ?></option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="trend-metric-select"><?php _e('指标:', 'ai-seo-auditor'); ?></label>
                    <select id="trend-metric-select" class="trend-filter">
                        <option value="overall"><?php _e('总体评分', 'ai-seo-auditor'); ?></option>
                        <option value="title"><?php _e('标题优化', 'ai-seo-auditor'); ?></option>
                        <option value="content"><?php _e('内容质量', 'ai-seo-auditor'); ?></option>
                        <option value="readability"><?php _e('可读性', 'ai-seo-auditor'); ?></option>
                        <option value="keyword"><?php _e('关键词', 'ai-seo-auditor'); ?></option>
                    </select>
                </div>

                <button id="update-trend-chart" class="button button-primary">
                    <?php _e('更新图表', 'ai-seo-auditor'); ?>
                </button>
            </div>

            <div class="trends-loading" style="display:none;">
                <span class="spinner is-active"></span>
                <p><?php _e('加载趋势数据...', 'ai-seo-auditor'); ?></p>
            </div>

            <div class="trends-error" style="display:none;">
                <div class="notice notice-error">
                    <p class="error-message"></p>
                </div>
            </div>

            <div class="trends-chart-container">
                <canvas id="seo-trend-chart"></canvas>
            </div>

            <div class="trends-stats">
                <div class="stat-card">
                    <h3><?php _e('当前评分', 'ai-seo-auditor'); ?></h3>
                    <div class="stat-value" id="current-score">-</div>
                </div>
                <div class="stat-card">
                    <h3><?php _e('平均评分', 'ai-seo-auditor'); ?></h3>
                    <div class="stat-value" id="average-score">-</div>
                </div>
                <div class="stat-card">
                    <h3><?php _e('最高评分', 'ai-seo-auditor'); ?></h3>
                    <div class="stat-value" id="highest-score">-</div>
                </div>
                <div class="stat-card">
                    <h3><?php _e('改进幅度', 'ai-seo-auditor'); ?></h3>
                    <div class="stat-value" id="improvement">-</div>
                </div>
            </div>

            <div class="trends-insights">
                <h2><?php _e('趋势洞察', 'ai-seo-auditor'); ?></h2>
                <div id="insights-content"></div>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX获取趋势数据
     */
    public function ajax_get_trend_data() {
        check_ajax_referer('ai_seo_trends_nonce', 'nonce');

        $post_id = isset($_POST['post_id']) ? sanitize_text_field($_POST['post_id']) : 'all';
        $period = isset($_POST['period']) ? sanitize_text_field($_POST['period']) : '30';
        $metric = isset($_POST['metric']) ? sanitize_text_field($_POST['metric']) : 'overall';

        $data = $this->get_trend_data($post_id, $period, $metric);

        if (is_wp_error($data)) {
            wp_send_json_error(array(
                'message' => $data->get_error_message()
            ));
        }

        wp_send_json_success($data);
    }

    /**
     * 获取趋势数据
     *
     * @param string $post_id 文章ID或'all'
     * @param string $period 时间范围
     * @param string $metric 指标类型
     * @return array|WP_Error 趋势数据
     */
    private function get_trend_data($post_id, $period, $metric) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_seo_audit_history';

        // 构建时间条件
        $date_condition = '';
        if ($period !== 'all') {
            $days = intval($period);
            $date_condition = $wpdb->prepare(
                " AND audit_date >= DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            );
        }

        // 构建文章条件
        $post_condition = '';
        if ($post_id !== 'all') {
            $post_condition = $wpdb->prepare(" AND post_id = %d", intval($post_id));
        }

        // 查询数据
        $query = "SELECT post_id, overall_score, results, audit_date
                  FROM {$table_name}
                  WHERE 1=1 {$post_condition} {$date_condition}
                  ORDER BY audit_date ASC";

        $results = $wpdb->get_results($query);

        if (empty($results)) {
            return new WP_Error('no_data', __('没有找到趋势数据', 'ai-seo-auditor'));
        }

        // 处理数据
        $trend_data = array(
            'labels' => array(),
            'scores' => array(),
            'stats' => array()
        );

        $scores_for_stats = array();

        foreach ($results as $row) {
            $date = mysql2date('Y-m-d', $row->audit_date);
            $score = 0;

            if ($metric === 'overall') {
                $score = intval($row->overall_score);
            } else {
                // 从results JSON中提取特定指标
                $result_data = json_decode($row->results, true);
                if ($result_data && isset($result_data[$metric]['score'])) {
                    $score = intval($result_data[$metric]['score']);
                }
            }

            // 如果是all posts，需要按日期分组平均
            if ($post_id === 'all') {
                if (!isset($trend_data['temp'][$date])) {
                    $trend_data['temp'][$date] = array();
                }
                $trend_data['temp'][$date][] = $score;
            } else {
                $trend_data['labels'][] = $date;
                $trend_data['scores'][] = $score;
                $scores_for_stats[] = $score;
            }
        }

        // 处理all posts的平均值
        if ($post_id === 'all' && isset($trend_data['temp'])) {
            foreach ($trend_data['temp'] as $date => $scores) {
                $trend_data['labels'][] = $date;
                $avg_score = round(array_sum($scores) / count($scores));
                $trend_data['scores'][] = $avg_score;
                $scores_for_stats[] = $avg_score;
            }
            unset($trend_data['temp']);
        }

        // 计算统计数据
        if (!empty($scores_for_stats)) {
            $trend_data['stats'] = array(
                'current' => end($scores_for_stats),
                'average' => round(array_sum($scores_for_stats) / count($scores_for_stats)),
                'highest' => max($scores_for_stats),
                'lowest' => min($scores_for_stats),
                'improvement' => end($scores_for_stats) - reset($scores_for_stats),
                'trend' => $this->calculate_trend($scores_for_stats)
            );
        }

        // 生成洞察
        $trend_data['insights'] = $this->generate_insights($trend_data['stats'], $post_id);

        return $trend_data;
    }

    /**
     * 计算趋势方向
     *
     * @param array $scores 评分数组
     * @return string 趋势方向
     */
    private function calculate_trend($scores) {
        if (count($scores) < 2) {
            return 'stable';
        }

        $first_half = array_slice($scores, 0, ceil(count($scores) / 2));
        $second_half = array_slice($scores, ceil(count($scores) / 2));

        $avg_first = array_sum($first_half) / count($first_half);
        $avg_second = array_sum($second_half) / count($second_half);

        $diff = $avg_second - $avg_first;

        if ($diff > 5) {
            return 'improving';
        } elseif ($diff < -5) {
            return 'declining';
        } else {
            return 'stable';
        }
    }

    /**
     * 生成趋势洞察
     *
     * @param array $stats 统计数据
     * @param string $post_id 文章ID
     * @return array 洞察内容
     */
    private function generate_insights($stats, $post_id) {
        $insights = array();

        if (empty($stats)) {
            return $insights;
        }

        // 改进趋势
        if ($stats['improvement'] > 10) {
            $insights[] = array(
                'type' => 'success',
                'message' => sprintf(
                    __('太棒了！SEO评分提升了 %d 分，持续优化效果显著。', 'ai-seo-auditor'),
                    $stats['improvement']
                )
            );
        } elseif ($stats['improvement'] < -10) {
            $insights[] = array(
                'type' => 'warning',
                'message' => sprintf(
                    __('注意：SEO评分下降了 %d 分，建议检查最近的内容变更。', 'ai-seo-auditor'),
                    abs($stats['improvement'])
                )
            );
        }

        // 评分水平
        if ($stats['current'] >= 80) {
            $insights[] = array(
                'type' => 'success',
                'message' => __('当前SEO评分优秀，保持现有的优化策略。', 'ai-seo-auditor')
            );
        } elseif ($stats['current'] < 60) {
            $insights[] = array(
                'type' => 'error',
                'message' => __('当前SEO评分较低，建议优先优化标题、关键词和内容质量。', 'ai-seo-auditor')
            );
        }

        // 趋势方向
        switch ($stats['trend']) {
            case 'improving':
                $insights[] = array(
                    'type' => 'info',
                    'message' => __('整体趋势向上，继续保持优化工作。', 'ai-seo-auditor')
                );
                break;
            case 'declining':
                $insights[] = array(
                    'type' => 'warning',
                    'message' => __('整体趋势向下，建议审查并改进内容策略。', 'ai-seo-auditor')
                );
                break;
            case 'stable':
                $insights[] = array(
                    'type' => 'info',
                    'message' => __('评分保持稳定，可以尝试新的优化策略以进一步提升。', 'ai-seo-auditor')
                );
                break;
        }

        return $insights;
    }

    /**
     * 添加仪表板小部件
     */
    public function add_dashboard_widget() {
        wp_add_dashboard_widget(
            'ai_seo_trend_widget',
            __('SEO评分趋势', 'ai-seo-auditor'),
            array($this, 'render_dashboard_widget')
        );
    }

    /**
     * 渲染仪表板小部件
     */
    public function render_dashboard_widget() {
        ?>
        <div class="ai-seo-trend-widget">
            <div class="trend-widget-chart">
                <canvas id="seo-trend-widget-chart"></canvas>
            </div>
            <div class="trend-widget-stats">
                <div class="widget-stat">
                    <span class="stat-label"><?php _e('平均评分', 'ai-seo-auditor'); ?>:</span>
                    <span class="stat-value" id="widget-avg-score">-</span>
                </div>
                <div class="widget-stat">
                    <span class="stat-label"><?php _e('改进', 'ai-seo-auditor'); ?>:</span>
                    <span class="stat-value" id="widget-improvement">-</span>
                </div>
            </div>
            <p class="trend-widget-link">
                <a href="<?php echo admin_url('admin.php?page=ai-seo-trends'); ?>">
                    <?php _e('查看详细趋势分析 →', 'ai-seo-auditor'); ?>
                </a>
            </p>
        </div>
        <script>
        jQuery(document).ready(function($) {
            // 加载小部件数据
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ai_seo_get_trend_data',
                    nonce: '<?php echo wp_create_nonce('ai_seo_trends_nonce'); ?>',
                    post_id: 'all',
                    period: '30',
                    metric: 'overall'
                },
                success: function(response) {
                    if (response.success) {
                        const data = response.data;

                        // 渲染简化图表
                        const ctx = document.getElementById('seo-trend-widget-chart').getContext('2d');
                        new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: data.labels,
                                datasets: [{
                                    label: '<?php _e('SEO评分', 'ai-seo-auditor'); ?>',
                                    data: data.scores,
                                    borderColor: '#0073aa',
                                    backgroundColor: 'rgba(0, 115, 170, 0.1)',
                                    tension: 0.4
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: {
                                        display: false
                                    }
                                },
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        max: 100
                                    }
                                }
                            }
                        });

                        // 更新统计
                        $('#widget-avg-score').text(data.stats.average);
                        const improvement = data.stats.improvement;
                        const improvementText = improvement >= 0 ? '+' + improvement : improvement;
                        $('#widget-improvement').text(improvementText).css('color', improvement >= 0 ? '#46b450' : '#dc3232');
                    }
                }
            });
        });
        </script>
        <?php
    }
}
