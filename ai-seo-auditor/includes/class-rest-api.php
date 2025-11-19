<?php
/**
 * REST API端点类
 *
 * @package AI_SEO_Auditor
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AI_SEO_REST_API类
 *
 * 提供REST API端点用于第三方集成
 */
class AI_SEO_REST_API {

    /**
     * 单例实例
     */
    private static $instance = null;

    /**
     * API命名空间
     */
    const NAMESPACE = 'ai-seo/v1';

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
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    /**
     * 注册REST路由
     */
    public function register_routes() {
        // 获取文章SEO评分
        register_rest_route(self::NAMESPACE, '/posts/(?P<id>\d+)/score', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_post_score'),
            'permission_callback' => array($this, 'check_read_permission'),
            'args' => array(
                'id' => array(
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                )
            )
        ));

        // 分析文章
        register_rest_route(self::NAMESPACE, '/posts/(?P<id>\d+)/analyze', array(
            'methods' => 'POST',
            'callback' => array($this, 'analyze_post'),
            'permission_callback' => array($this, 'check_edit_permission'),
            'args' => array(
                'id' => array(
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                )
            )
        ));

        // 批量获取评分
        register_rest_route(self::NAMESPACE, '/posts/scores', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_batch_scores'),
            'permission_callback' => array($this, 'check_read_permission'),
            'args' => array(
                'ids' => array(
                    'required' => false,
                    'type' => 'array'
                ),
                'limit' => array(
                    'default' => 10,
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0 && $param <= 100;
                    }
                )
            )
        ));

        // 获取趋势数据
        register_rest_route(self::NAMESPACE, '/trends', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_trends'),
            'permission_callback' => array($this, 'check_read_permission'),
            'args' => array(
                'post_id' => array(
                    'required' => false,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                ),
                'days' => array(
                    'default' => 30,
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0;
                    }
                )
            )
        ));

        // 获取统计数据
        register_rest_route(self::NAMESPACE, '/stats', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_stats'),
            'permission_callback' => array($this, 'check_read_permission')
        ));

        // 运行SEO检查清单
        register_rest_route(self::NAMESPACE, '/posts/(?P<id>\d+)/checklist', array(
            'methods' => 'GET',
            'callback' => array($this, 'run_checklist'),
            'permission_callback' => array($this, 'check_read_permission'),
            'args' => array(
                'id' => array(
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                )
            )
        ));

        // 导出报告
        register_rest_route(self::NAMESPACE, '/posts/(?P<id>\d+)/report', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_report'),
            'permission_callback' => array($this, 'check_read_permission'),
            'args' => array(
                'id' => array(
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                ),
                'format' => array(
                    'default' => 'json',
                    'enum' => array('json', 'html')
                )
            )
        ));

        // 更新设置
        register_rest_route(self::NAMESPACE, '/settings', array(
            'methods' => 'POST',
            'callback' => array($this, 'update_settings'),
            'permission_callback' => array($this, 'check_manage_permission')
        ));

        // 获取通知
        register_rest_route(self::NAMESPACE, '/notifications', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_notifications'),
            'permission_callback' => array($this, 'check_read_permission'),
            'args' => array(
                'unread_only' => array(
                    'default' => false,
                    'validate_callback' => function($param) {
                        return is_bool($param);
                    }
                )
            )
        ));
    }

    /**
     * 检查读取权限
     */
    public function check_read_permission() {
        return current_user_can('edit_posts');
    }

    /**
     * 检查编辑权限
     */
    public function check_edit_permission() {
        return current_user_can('edit_posts');
    }

    /**
     * 检查管理权限
     */
    public function check_manage_permission() {
        return current_user_can('manage_options');
    }

    /**
     * 获取文章SEO评分
     */
    public function get_post_score($request) {
        $post_id = $request['id'];
        $post = get_post($post_id);

        if (!$post) {
            return new WP_Error('post_not_found', __('文章不存在', 'ai-seo-auditor'), array('status' => 404));
        }

        $score = get_post_meta($post_id, '_seo_audit_score', true);
        $results = get_post_meta($post_id, '_seo_audit_results', true);
        $timestamp = get_post_meta($post_id, '_seo_audit_timestamp', true);

        if (!$score) {
            return new WP_Error('no_audit_data', __('该文章尚未审计', 'ai-seo-auditor'), array('status' => 404));
        }

        return array(
            'post_id' => $post_id,
            'post_title' => $post->post_title,
            'score' => intval($score),
            'results' => json_decode($results, true),
            'audited_at' => $timestamp,
            'permalink' => get_permalink($post_id)
        );
    }

    /**
     * 分析文章
     */
    public function analyze_post($request) {
        $post_id = $request['id'];
        $post = get_post($post_id);

        if (!$post) {
            return new WP_Error('post_not_found', __('文章不存在', 'ai-seo-auditor'), array('status' => 404));
        }

        $analyzer = AI_SEO_Analyzer::get_instance();
        $result = $analyzer->analyze_post($post_id);

        if (is_wp_error($result)) {
            return $result;
        }

        return array(
            'success' => true,
            'post_id' => $post_id,
            'score' => $result['overall_score'],
            'message' => __('分析完成', 'ai-seo-auditor')
        );
    }

    /**
     * 批量获取评分
     */
    public function get_batch_scores($request) {
        $ids = $request->get_param('ids');
        $limit = $request->get_param('limit');

        if (empty($ids)) {
            // 获取最近的文章
            $posts = get_posts(array(
                'post_type' => 'post',
                'post_status' => 'publish',
                'posts_per_page' => $limit,
                'meta_query' => array(
                    array(
                        'key' => '_seo_audit_score',
                        'compare' => 'EXISTS'
                    )
                )
            ));

            $ids = wp_list_pluck($posts, 'ID');
        }

        $scores = array();

        foreach ($ids as $post_id) {
            $score = get_post_meta($post_id, '_seo_audit_score', true);

            if ($score) {
                $post = get_post($post_id);
                $scores[] = array(
                    'post_id' => $post_id,
                    'post_title' => $post->post_title,
                    'score' => intval($score),
                    'permalink' => get_permalink($post_id)
                );
            }
        }

        return array(
            'total' => count($scores),
            'scores' => $scores
        );
    }

    /**
     * 获取趋势数据
     */
    public function get_trends($request) {
        $post_id = $request->get_param('post_id');
        $days = $request->get_param('days');

        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_seo_audit_history';

        $where = "WHERE audit_date >= DATE_SUB(NOW(), INTERVAL {$days} DAY)";

        if ($post_id) {
            $where .= $wpdb->prepare(" AND post_id = %d", $post_id);
        }

        $results = $wpdb->get_results(
            "SELECT post_id, overall_score, audit_date
             FROM {$table_name}
             {$where}
             ORDER BY audit_date ASC"
        );

        $trends = array();
        foreach ($results as $row) {
            $trends[] = array(
                'post_id' => intval($row->post_id),
                'score' => intval($row->overall_score),
                'date' => $row->audit_date
            );
        }

        return array(
            'period_days' => $days,
            'data_points' => count($trends),
            'trends' => $trends
        );
    }

    /**
     * 获取统计数据
     */
    public function get_stats() {
        global $wpdb;

        // 获取所有审计过的文章
        $total_audited = $wpdb->get_var(
            "SELECT COUNT(DISTINCT post_id)
             FROM {$wpdb->postmeta}
             WHERE meta_key = '_seo_audit_score'"
        );

        // 计算平均分
        $average_score = $wpdb->get_var(
            "SELECT AVG(CAST(meta_value AS UNSIGNED))
             FROM {$wpdb->postmeta}
             WHERE meta_key = '_seo_audit_score'"
        );

        // 低分文章数量
        $low_score_count = $wpdb->get_var(
            "SELECT COUNT(DISTINCT post_id)
             FROM {$wpdb->postmeta}
             WHERE meta_key = '_seo_audit_score'
             AND CAST(meta_value AS UNSIGNED) < 60"
        );

        // 优秀文章数量
        $excellent_count = $wpdb->get_var(
            "SELECT COUNT(DISTINCT post_id)
             FROM {$wpdb->postmeta}
             WHERE meta_key = '_seo_audit_score'
             AND CAST(meta_value AS UNSIGNED) >= 80"
        );

        return array(
            'total_audited' => intval($total_audited),
            'average_score' => round(floatval($average_score), 1),
            'low_score_posts' => intval($low_score_count),
            'excellent_posts' => intval($excellent_count),
            'needs_improvement_percentage' => $total_audited > 0 ? round(($low_score_count / $total_audited) * 100, 1) : 0
        );
    }

    /**
     * 运行检查清单
     */
    public function run_checklist($request) {
        $post_id = $request['id'];

        $checklist = AI_SEO_Checklist::get_instance();
        $result = $checklist->run_checklist($post_id);

        if (is_wp_error($result)) {
            return $result;
        }

        return $result;
    }

    /**
     * 获取报告
     */
    public function get_report($request) {
        $post_id = $request['id'];
        $format = $request->get_param('format');

        $generator = AI_SEO_Report_Generator::get_instance();

        if ($format === 'html') {
            $html = $generator->generate_report($post_id, true);

            if (is_wp_error($html)) {
                return $html;
            }

            return new WP_REST_Response($html, 200, array(
                'Content-Type' => 'text/html'
            ));
        }

        // JSON格式
        $post = get_post($post_id);
        if (!$post) {
            return new WP_Error('post_not_found', __('文章不存在', 'ai-seo-auditor'), array('status' => 404));
        }

        $score = get_post_meta($post_id, '_seo_audit_score', true);
        $results = get_post_meta($post_id, '_seo_audit_results', true);
        $timestamp = get_post_meta($post_id, '_seo_audit_timestamp', true);

        if (!$score) {
            return new WP_Error('no_audit_data', __('该文章尚未审计', 'ai-seo-auditor'), array('status' => 404));
        }

        return array(
            'post' => array(
                'id' => $post_id,
                'title' => $post->post_title,
                'permalink' => get_permalink($post_id)
            ),
            'score' => intval($score),
            'results' => json_decode($results, true),
            'audited_at' => $timestamp
        );
    }

    /**
     * 更新设置
     */
    public function update_settings($request) {
        $params = $request->get_json_params();

        if (empty($params)) {
            return new WP_Error('no_data', __('没有提供设置数据', 'ai-seo-auditor'), array('status' => 400));
        }

        $settings = get_option('ai_seo_settings', array());

        // 允许更新的设置项
        $allowed_keys = array(
            'keyword_density_min',
            'keyword_density_max',
            'title_length_min',
            'title_length_max',
            'meta_length_min',
            'meta_length_max',
            'min_word_count',
            'language',
            'model'
        );

        foreach ($params as $key => $value) {
            if (in_array($key, $allowed_keys)) {
                $settings[$key] = sanitize_text_field($value);
            }
        }

        update_option('ai_seo_settings', $settings);

        return array(
            'success' => true,
            'message' => __('设置已更新', 'ai-seo-auditor'),
            'settings' => $settings
        );
    }

    /**
     * 获取通知
     */
    public function get_notifications($request) {
        $unread_only = $request->get_param('unread_only');

        $notification_center = AI_SEO_Notification_Center::get_instance();

        if ($unread_only) {
            // 使用反射调用私有方法
            $reflection = new ReflectionClass($notification_center);
            $method = $reflection->getMethod('get_unread_notifications');
            $method->setAccessible(true);
            $notifications = $method->invoke($notification_center);
        } else {
            $reflection = new ReflectionClass($notification_center);
            $method = $reflection->getMethod('get_all_notifications');
            $method->setAccessible(true);
            $notifications = $method->invoke($notification_center, 50);
        }

        return array(
            'total' => count($notifications),
            'notifications' => $notifications
        );
    }
}
