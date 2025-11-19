<?php
/**
 * 快速操作和快捷代码类
 *
 * @package AI_SEO_Auditor
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AI_SEO_Quick_Actions类
 *
 * 负责文章列表快速操作和快捷代码
 */
class AI_SEO_Quick_Actions {

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
        $this->register_shortcodes();
    }

    /**
     * 初始化钩子
     */
    private function init_hooks() {
        // 文章列表添加SEO评分列
        add_filter('manage_posts_columns', array($this, 'add_seo_score_column'));
        add_action('manage_posts_custom_column', array($this, 'render_seo_score_column'), 10, 2);
        add_filter('manage_edit-post_sortable_columns', array($this, 'make_seo_score_sortable'));

        // 文章列表快速操作链接
        add_filter('post_row_actions', array($this, 'add_quick_action_links'), 10, 2);

        // 处理快速审计
        add_action('admin_init', array($this, 'handle_quick_audit'));
    }

    /**
     * 注册快捷代码
     */
    private function register_shortcodes() {
        add_shortcode('seo_score', array($this, 'seo_score_shortcode'));
        add_shortcode('seo_badge', array($this, 'seo_badge_shortcode'));
        add_shortcode('seo_report', array($this, 'seo_report_shortcode'));
    }

    /**
     * 添加SEO评分列
     *
     * @param array $columns 现有列
     * @return array 更新后的列
     */
    public function add_seo_score_column($columns) {
        $new_columns = array();

        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;

            // 在标题列后添加SEO评分列
            if ($key === 'title') {
                $new_columns['seo_score'] = __('SEO评分', 'ai-seo-auditor');
            }
        }

        return $new_columns;
    }

    /**
     * 渲染SEO评分列
     *
     * @param string $column_name 列名
     * @param int $post_id 文章ID
     */
    public function render_seo_score_column($column_name, $post_id) {
        if ($column_name !== 'seo_score') {
            return;
        }

        $score = get_post_meta($post_id, '_seo_audit_score', true);

        if ($score) {
            $score = intval($score);
            $class = $this->get_score_class($score);
            $label = $this->get_score_label($score);

            echo '<div class="seo-score-badge ' . esc_attr($class) . '" title="' . esc_attr($label) . '">';
            echo '<strong>' . esc_html($score) . '</strong>/100';
            echo '</div>';

            $timestamp = get_post_meta($post_id, '_seo_audit_timestamp', true);
            if ($timestamp) {
                echo '<br><small>' . human_time_diff(strtotime($timestamp), current_time('timestamp')) . __('前', 'ai-seo-auditor') . '</small>';
            }
        } else {
            echo '<span class="seo-score-none">' . __('未审计', 'ai-seo-auditor') . '</span>';
        }
    }

    /**
     * 使SEO评分列可排序
     *
     * @param array $columns 可排序列
     * @return array 更新后的列
     */
    public function make_seo_score_sortable($columns) {
        $columns['seo_score'] = 'seo_score';
        return $columns;
    }

    /**
     * 添加快速操作链接
     *
     * @param array $actions 现有操作
     * @param WP_Post $post 文章对象
     * @return array 更新后的操作
     */
    public function add_quick_action_links($actions, $post) {
        if ($post->post_type === 'post' && current_user_can('edit_post', $post->ID)) {
            $audit_url = wp_nonce_url(
                add_query_arg(array(
                    'action' => 'ai_seo_quick_audit',
                    'post_id' => $post->ID
                ), admin_url('admin.php')),
                'ai_seo_quick_audit_' . $post->ID
            );

            $score = get_post_meta($post->ID, '_seo_audit_score', true);
            $action_text = $score ? __('重新审计', 'ai-seo-auditor') : __('SEO审计', 'ai-seo-auditor');

            $actions['ai_seo_audit'] = sprintf(
                '<a href="%s" class="ai-seo-quick-audit">%s</a>',
                esc_url($audit_url),
                $action_text
            );
        }

        return $actions;
    }

    /**
     * 处理快速审计请求
     */
    public function handle_quick_audit() {
        if (!isset($_GET['action']) || $_GET['action'] !== 'ai_seo_quick_audit') {
            return;
        }

        if (!isset($_GET['post_id'])) {
            return;
        }

        $post_id = intval($_GET['post_id']);

        // 验证nonce
        if (!wp_verify_nonce($_GET['_wpnonce'], 'ai_seo_quick_audit_' . $post_id)) {
            wp_die(__('安全验证失败', 'ai-seo-auditor'));
        }

        // 检查权限
        if (!current_user_can('edit_post', $post_id)) {
            wp_die(__('权限不足', 'ai-seo-auditor'));
        }

        // 执行审计
        $analyzer = AI_SEO_Analyzer::get_instance();
        $result = $analyzer->analyze_post($post_id);

        if (is_wp_error($result)) {
            wp_redirect(add_query_arg(array(
                'ai_seo_error' => urlencode($result->get_error_message())
            ), wp_get_referer()));
            exit;
        }

        // 重定向回文章列表
        wp_redirect(add_query_arg(array(
            'ai_seo_audited' => 1,
            'score' => $result['overall_score']
        ), wp_get_referer()));
        exit;
    }

    /**
     * SEO评分快捷代码
     *
     * 用法: [seo_score]
     *
     * @param array $atts 属性
     * @return string 输出HTML
     */
    public function seo_score_shortcode($atts) {
        $atts = shortcode_atts(array(
            'post_id' => get_the_ID()
        ), $atts);

        $post_id = intval($atts['post_id']);
        $score = get_post_meta($post_id, '_seo_audit_score', true);

        if (!$score) {
            return '<span class="seo-score-none">' . __('未审计', 'ai-seo-auditor') . '</span>';
        }

        $score = intval($score);
        $class = $this->get_score_class($score);
        $label = $this->get_score_label($score);

        return sprintf(
            '<span class="seo-score-shortcode %s" title="%s">%d</span>',
            esc_attr($class),
            esc_attr($label),
            $score
        );
    }

    /**
     * SEO徽章快捷代码
     *
     * 用法: [seo_badge]
     *
     * @param array $atts 属性
     * @return string 输出HTML
     */
    public function seo_badge_shortcode($atts) {
        $atts = shortcode_atts(array(
            'post_id' => get_the_ID(),
            'show_label' => 'yes'
        ), $atts);

        $post_id = intval($atts['post_id']);
        $score = get_post_meta($post_id, '_seo_audit_score', true);

        if (!$score) {
            return '';
        }

        $score = intval($score);
        $class = $this->get_score_class($score);
        $label = $this->get_score_label($score);

        $html = '<div class="seo-badge-shortcode ' . esc_attr($class) . '">';
        $html .= '<span class="badge-score">' . $score . '</span>';

        if ($atts['show_label'] === 'yes') {
            $html .= '<span class="badge-label">' . esc_html($label) . '</span>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * SEO报告快捷代码
     *
     * 用法: [seo_report]
     *
     * @param array $atts 属性
     * @return string 输出HTML
     */
    public function seo_report_shortcode($atts) {
        $atts = shortcode_atts(array(
            'post_id' => get_the_ID()
        ), $atts);

        $post_id = intval($atts['post_id']);
        $results_json = get_post_meta($post_id, '_seo_audit_results', true);

        if (!$results_json) {
            return '<p class="seo-report-none">' . __('暂无SEO审计数据', 'ai-seo-auditor') . '</p>';
        }

        $results = json_decode($results_json, true);

        if (!$results) {
            return '';
        }

        $html = '<div class="seo-report-shortcode">';
        $html .= '<h3>' . __('SEO审计报告', 'ai-seo-auditor') . '</h3>';

        $html .= '<div class="report-overall">';
        $html .= '<div class="overall-score">' . __('总分: ', 'ai-seo-auditor') . '<strong>' . $results['overall_score'] . '/100</strong></div>';
        $html .= '</div>';

        $html .= '<div class="report-details">';

        $sections = array(
            'title' => __('标题优化', 'ai-seo-auditor'),
            'meta_description' => __('Meta描述', 'ai-seo-auditor'),
            'keyword' => __('关键词使用', 'ai-seo-auditor'),
            'content' => __('内容质量', 'ai-seo-auditor'),
            'readability' => __('可读性', 'ai-seo-auditor'),
            'images' => __('图片SEO', 'ai-seo-auditor'),
            'social_media' => __('社交媒体', 'ai-seo-auditor'),
            'external_links' => __('外链', 'ai-seo-auditor')
        );

        foreach ($sections as $key => $label) {
            if (isset($results[$key]['score'])) {
                $score = intval($results[$key]['score']);
                $class = $this->get_score_class($score);

                $html .= '<div class="report-item">';
                $html .= '<div class="item-header">';
                $html .= '<span class="item-label">' . esc_html($label) . '</span>';
                $html .= '<span class="item-score ' . esc_attr($class) . '">' . $score . '/100</span>';
                $html .= '</div>';
                $html .= '</div>';
            }
        }

        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * 获取评分等级类名
     *
     * @param int $score 评分
     * @return string 类名
     */
    private function get_score_class($score) {
        if ($score >= 80) {
            return 'score-excellent';
        } elseif ($score >= 60) {
            return 'score-good';
        } elseif ($score >= 40) {
            return 'score-fair';
        } else {
            return 'score-poor';
        }
    }

    /**
     * 获取评分标签
     *
     * @param int $score 评分
     * @return string 标签
     */
    private function get_score_label($score) {
        if ($score >= 80) {
            return __('优秀', 'ai-seo-auditor');
        } elseif ($score >= 60) {
            return __('良好', 'ai-seo-auditor');
        } elseif ($score >= 40) {
            return __('需要改进', 'ai-seo-auditor');
        } else {
            return __('急需优化', 'ai-seo-auditor');
        }
    }
}
