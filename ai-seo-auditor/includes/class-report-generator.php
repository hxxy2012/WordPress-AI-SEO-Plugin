<?php
/**
 * HTML报告生成器类
 *
 * @package AI_SEO_Auditor
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AI_SEO_Report_Generator类
 *
 * 负责生成专业的HTML格式SEO审计报告
 */
class AI_SEO_Report_Generator {

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
        // AJAX处理
        add_action('wp_ajax_ai_seo_generate_report', array($this, 'ajax_generate_report'));
        add_action('wp_ajax_ai_seo_export_report_pdf', array($this, 'ajax_export_pdf'));

        // 添加Meta Box中的导出按钮
        add_action('ai_seo_meta_box_actions', array($this, 'add_export_button'));
    }

    /**
     * 添加导出按钮到Meta Box
     */
    public function add_export_button($post_id) {
        $score = get_post_meta($post_id, '_seo_audit_score', true);

        if ($score) {
            ?>
            <button type="button" class="button ai-seo-export-report" data-post-id="<?php echo esc_attr($post_id); ?>">
                <span class="dashicons dashicons-media-document"></span>
                <?php _e('导出HTML报告', 'ai-seo-auditor'); ?>
            </button>
            <?php
        }
    }

    /**
     * AJAX生成报告
     */
    public function ajax_generate_report() {
        check_ajax_referer('ai_seo_nonce', 'nonce');

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

        if (!$post_id) {
            wp_send_json_error(array('message' => __('无效的文章ID', 'ai-seo-auditor')));
        }

        $html = $this->generate_report($post_id);

        if (is_wp_error($html)) {
            wp_send_json_error(array('message' => $html->get_error_message()));
        }

        wp_send_json_success(array('html' => $html));
    }

    /**
     * 生成HTML报告
     *
     * @param int $post_id 文章ID
     * @param bool $standalone 是否生成独立HTML（包含完整HTML结构）
     * @return string|WP_Error HTML内容
     */
    public function generate_report($post_id, $standalone = true) {
        $post = get_post($post_id);

        if (!$post) {
            return new WP_Error('invalid_post', __('文章不存在', 'ai-seo-auditor'));
        }

        $score = get_post_meta($post_id, '_seo_audit_score', true);
        $results_json = get_post_meta($post_id, '_seo_audit_results', true);
        $timestamp = get_post_meta($post_id, '_seo_audit_timestamp', true);

        if (!$score || !$results_json) {
            return new WP_Error('no_data', __('该文章暂无SEO审计数据', 'ai-seo-auditor'));
        }

        $results = json_decode($results_json, true);

        if ($standalone) {
            return $this->generate_standalone_html($post, $score, $results, $timestamp);
        } else {
            return $this->generate_report_body($post, $score, $results, $timestamp);
        }
    }

    /**
     * 生成独立HTML文档
     *
     * @param WP_Post $post 文章对象
     * @param int $score 评分
     * @param array $results 审计结果
     * @param string $timestamp 审计时间
     * @return string HTML内容
     */
    private function generate_standalone_html($post, $score, $results, $timestamp) {
        $body = $this->generate_report_body($post, $score, $results, $timestamp);
        $css = $this->get_report_css();

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SEO审计报告 - <?php echo esc_html($post->post_title); ?></title>
    <style><?php echo $css; ?></style>
</head>
<body>
    <?php echo $body; ?>
    <script>
    // 打印功能
    function printReport() {
        window.print();
    }

    // 下载为HTML
    function downloadReport() {
        const html = document.documentElement.outerHTML;
        const blob = new Blob([html], { type: 'text/html' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'seo-audit-report-<?php echo sanitize_file_name($post->post_name); ?>.html';
        a.click();
        URL.revokeObjectURL(url);
    }
    </script>
</body>
</html>
        <?php
        return ob_get_clean();
    }

    /**
     * 生成报告主体内容
     *
     * @param WP_Post $post 文章对象
     * @param int $score 评分
     * @param array $results 审计结果
     * @param string $timestamp 审计时间
     * @return string HTML内容
     */
    private function generate_report_body($post, $score, $results, $timestamp) {
        ob_start();
        ?>
        <div class="seo-report">
            <!-- 报告头部 -->
            <div class="report-header">
                <div class="report-logo">
                    <h1>AI SEO 审计报告</h1>
                </div>
                <div class="report-meta">
                    <div class="meta-item">
                        <strong><?php _e('文章标题:', 'ai-seo-auditor'); ?></strong>
                        <?php echo esc_html($post->post_title); ?>
                    </div>
                    <div class="meta-item">
                        <strong><?php _e('审计时间:', 'ai-seo-auditor'); ?></strong>
                        <?php echo esc_html(mysql2date('Y年m月d日 H:i', $timestamp)); ?>
                    </div>
                    <div class="meta-item">
                        <strong><?php _e('文章链接:', 'ai-seo-auditor'); ?></strong>
                        <a href="<?php echo esc_url(get_permalink($post->ID)); ?>" target="_blank">
                            <?php echo esc_url(get_permalink($post->ID)); ?>
                        </a>
                    </div>
                </div>
            </div>

            <!-- 总体评分 -->
            <div class="report-section overall-score-section">
                <h2><?php _e('总体评分', 'ai-seo-auditor'); ?></h2>
                <div class="overall-score">
                    <div class="score-circle <?php echo esc_attr($this->get_score_class($score)); ?>">
                        <div class="score-number"><?php echo esc_html($score); ?></div>
                        <div class="score-total">/100</div>
                    </div>
                    <div class="score-description">
                        <h3><?php echo esc_html($this->get_score_label($score)); ?></h3>
                        <p><?php echo esc_html($this->get_score_description($score)); ?></p>
                    </div>
                </div>
            </div>

            <!-- 详细评分 -->
            <div class="report-section details-section">
                <h2><?php _e('详细分析', 'ai-seo-auditor'); ?></h2>

                <?php
                $sections = array(
                    'title' => __('标题优化', 'ai-seo-auditor'),
                    'meta_description' => __('Meta描述', 'ai-seo-auditor'),
                    'keyword' => __('关键词使用', 'ai-seo-auditor'),
                    'content' => __('内容质量', 'ai-seo-auditor'),
                    'readability' => __('可读性', 'ai-seo-auditor'),
                    'technical' => __('技术SEO', 'ai-seo-auditor'),
                    'images' => __('图片SEO', 'ai-seo-auditor'),
                    'social_media' => __('社交媒体', 'ai-seo-auditor'),
                    'external_links' => __('外部链接', 'ai-seo-auditor')
                );

                foreach ($sections as $key => $label) {
                    if (isset($results[$key])) {
                        $this->render_section($key, $label, $results[$key]);
                    }
                }
                ?>
            </div>

            <!-- 改进建议汇总 -->
            <div class="report-section recommendations-section">
                <h2><?php _e('改进建议汇总', 'ai-seo-auditor'); ?></h2>
                <?php $this->render_all_recommendations($results); ?>
            </div>

            <!-- 报告尾部 -->
            <div class="report-footer">
                <div class="footer-actions">
                    <button onclick="printReport()" class="btn btn-primary">
                        <?php _e('打印报告', 'ai-seo-auditor'); ?>
                    </button>
                    <button onclick="downloadReport()" class="btn btn-secondary">
                        <?php _e('下载HTML', 'ai-seo-auditor'); ?>
                    </button>
                </div>
                <div class="footer-info">
                    <p><?php printf(__('生成于 %s', 'ai-seo-auditor'), date('Y年m月d日 H:i:s')); ?></p>
                    <p><?php _e('由 AI SEO Auditor 插件提供支持', 'ai-seo-auditor'); ?></p>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * 渲染单个分析部分
     *
     * @param string $key 部分键名
     * @param string $label 部分标签
     * @param array $data 部分数据
     */
    private function render_section($key, $label, $data) {
        $section_score = isset($data['score']) ? intval($data['score']) : 0;
        ?>
        <div class="detail-section">
            <div class="section-header">
                <h3><?php echo esc_html($label); ?></h3>
                <div class="section-score <?php echo esc_attr($this->get_score_class($section_score)); ?>">
                    <?php echo esc_html($section_score); ?>/100
                </div>
            </div>

            <div class="section-content">
                <?php if (isset($data['issues']) && !empty($data['issues'])): ?>
                    <div class="issues-list">
                        <h4><?php _e('发现的问题', 'ai-seo-auditor'); ?></h4>
                        <ul>
                        <?php foreach ($data['issues'] as $issue): ?>
                            <li class="issue-item"><?php echo esc_html($issue); ?></li>
                        <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if (isset($data['suggestions']) && !empty($data['suggestions'])): ?>
                    <div class="suggestions-list">
                        <h4><?php _e('优化建议', 'ai-seo-auditor'); ?></h4>
                        <ul>
                        <?php foreach ($data['suggestions'] as $suggestion): ?>
                            <li class="suggestion-item"><?php echo esc_html($suggestion); ?></li>
                        <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php
                // 特殊处理：显示具体数据
                if ($key === 'keyword' && isset($data['density'])) {
                    echo '<div class="data-point"><strong>' . __('关键词密度:', 'ai-seo-auditor') . '</strong> ' . esc_html($data['density']) . '%</div>';
                }

                if ($key === 'content' && isset($data['word_count'])) {
                    echo '<div class="data-point"><strong>' . __('字数统计:', 'ai-seo-auditor') . '</strong> ' . esc_html($data['word_count']) . ' ' . __('字', 'ai-seo-auditor') . '</div>';
                }

                if ($key === 'images' && isset($data['total_images'])) {
                    echo '<div class="data-point"><strong>' . __('图片数量:', 'ai-seo-auditor') . '</strong> ' . esc_html($data['total_images']) . '</div>';
                    if (isset($data['images_without_alt'])) {
                        echo '<div class="data-point"><strong>' . __('缺少Alt文本:', 'ai-seo-auditor') . '</strong> ' . esc_html($data['images_without_alt']) . '</div>';
                    }
                }

                if ($key === 'external_links' && isset($data['total_links'])) {
                    echo '<div class="data-point"><strong>' . __('外链数量:', 'ai-seo-auditor') . '</strong> ' . esc_html($data['total_links']) . '</div>';
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * 渲染所有改进建议
     *
     * @param array $results 审计结果
     */
    private function render_all_recommendations($results) {
        $all_suggestions = array();

        // 收集所有建议
        foreach ($results as $key => $data) {
            if (isset($data['suggestions']) && !empty($data['suggestions'])) {
                foreach ($data['suggestions'] as $suggestion) {
                    $all_suggestions[] = array(
                        'category' => $key,
                        'suggestion' => $suggestion,
                        'priority' => $this->get_suggestion_priority($key, $data)
                    );
                }
            }
        }

        // 按优先级排序
        usort($all_suggestions, function($a, $b) {
            return $b['priority'] - $a['priority'];
        });

        if (empty($all_suggestions)) {
            echo '<p>' . __('恭喜！暂无需要改进的地方。', 'ai-seo-auditor') . '</p>';
            return;
        }

        ?>
        <div class="recommendations-list">
            <?php foreach ($all_suggestions as $item): ?>
                <div class="recommendation-item priority-<?php echo esc_attr($item['priority']); ?>">
                    <div class="priority-badge">
                        <?php echo esc_html($this->get_priority_label($item['priority'])); ?>
                    </div>
                    <div class="recommendation-content">
                        <?php echo esc_html($item['suggestion']); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    /**
     * 获取建议优先级
     *
     * @param string $category 类别
     * @param array $data 数据
     * @return int 优先级（1-3）
     */
    private function get_suggestion_priority($category, $data) {
        $score = isset($data['score']) ? intval($data['score']) : 100;

        // 根据评分确定优先级
        if ($score < 40) {
            return 3; // 高优先级
        } elseif ($score < 70) {
            return 2; // 中优先级
        } else {
            return 1; // 低优先级
        }
    }

    /**
     * 获取优先级标签
     *
     * @param int $priority 优先级
     * @return string 标签
     */
    private function get_priority_label($priority) {
        switch ($priority) {
            case 3:
                return __('高优先级', 'ai-seo-auditor');
            case 2:
                return __('中优先级', 'ai-seo-auditor');
            case 1:
                return __('低优先级', 'ai-seo-auditor');
            default:
                return __('普通', 'ai-seo-auditor');
        }
    }

    /**
     * 获取评分等级类名
     */
    private function get_score_class($score) {
        if ($score >= 80) return 'score-excellent';
        if ($score >= 60) return 'score-good';
        if ($score >= 40) return 'score-fair';
        return 'score-poor';
    }

    /**
     * 获取评分标签
     */
    private function get_score_label($score) {
        if ($score >= 80) return __('优秀', 'ai-seo-auditor');
        if ($score >= 60) return __('良好', 'ai-seo-auditor');
        if ($score >= 40) return __('需要改进', 'ai-seo-auditor');
        return __('急需优化', 'ai-seo-auditor');
    }

    /**
     * 获取评分描述
     */
    private function get_score_description($score) {
        if ($score >= 80) {
            return __('您的SEO优化工作做得非常出色，继续保持！', 'ai-seo-auditor');
        } elseif ($score >= 60) {
            return __('整体SEO优化良好，还有一些可以提升的空间。', 'ai-seo-auditor');
        } elseif ($score >= 40) {
            return __('SEO优化需要改进，建议重点关注标题、关键词和内容质量。', 'ai-seo-auditor');
        } else {
            return __('SEO优化急需优化，请尽快按照建议进行改进。', 'ai-seo-auditor');
        }
    }

    /**
     * 获取报告CSS样式
     *
     * @return string CSS内容
     */
    private function get_report_css() {
        ob_start();
        ?>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
    line-height: 1.6;
    color: #333;
    background: #f5f5f5;
    padding: 20px;
}

.seo-report {
    max-width: 1200px;
    margin: 0 auto;
    background: #fff;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.report-header {
    background: linear-gradient(135deg, #0073aa 0%, #005177 100%);
    color: #fff;
    padding: 40px;
}

.report-logo h1 {
    font-size: 32px;
    margin-bottom: 20px;
}

.report-meta {
    display: grid;
    gap: 10px;
    font-size: 14px;
}

.meta-item {
    display: flex;
    gap: 10px;
}

.meta-item strong {
    min-width: 100px;
}

.meta-item a {
    color: #fff;
    text-decoration: underline;
}

.report-section {
    padding: 40px;
    border-bottom: 1px solid #eee;
}

.report-section h2 {
    font-size: 24px;
    margin-bottom: 30px;
    color: #0073aa;
    border-bottom: 3px solid #0073aa;
    padding-bottom: 10px;
}

.overall-score {
    display: flex;
    align-items: center;
    gap: 40px;
}

.score-circle {
    width: 200px;
    height: 200px;
    border-radius: 50%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    border: 10px solid;
}

.score-circle.score-excellent { border-color: #46b450; color: #46b450; }
.score-circle.score-good { border-color: #00a0d2; color: #00a0d2; }
.score-circle.score-fair { border-color: #ffb900; color: #ffb900; }
.score-circle.score-poor { border-color: #dc3232; color: #dc3232; }

.score-number {
    font-size: 64px;
    font-weight: 700;
    line-height: 1;
}

.score-total {
    font-size: 24px;
    opacity: 0.7;
}

.score-description h3 {
    font-size: 28px;
    margin-bottom: 15px;
}

.detail-section {
    margin-bottom: 30px;
    padding: 25px;
    background: #f9f9f9;
    border-radius: 8px;
    border-left: 4px solid #0073aa;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.section-header h3 {
    font-size: 20px;
    color: #333;
}

.section-score {
    font-size: 18px;
    font-weight: 700;
    padding: 8px 16px;
    border-radius: 4px;
}

.section-score.score-excellent { background: #46b450; color: #fff; }
.section-score.score-good { background: #00a0d2; color: #fff; }
.section-score.score-fair { background: #ffb900; color: #fff; }
.section-score.score-poor { background: #dc3232; color: #fff; }

.section-content {
    margin-top: 15px;
}

.issues-list, .suggestions-list {
    margin-bottom: 20px;
}

.section-content h4 {
    font-size: 16px;
    margin-bottom: 10px;
    color: #555;
}

.section-content ul {
    list-style: none;
    padding-left: 0;
}

.issue-item, .suggestion-item {
    padding: 10px 15px;
    margin-bottom: 8px;
    background: #fff;
    border-left: 3px solid;
    border-radius: 4px;
}

.issue-item {
    border-left-color: #dc3232;
}

.suggestion-item {
    border-left-color: #00a0d2;
}

.data-point {
    padding: 8px 0;
    font-size: 14px;
}

.recommendations-list {
    display: grid;
    gap: 15px;
}

.recommendation-item {
    display: flex;
    gap: 15px;
    padding: 15px;
    background: #f9f9f9;
    border-radius: 4px;
    border-left: 4px solid;
}

.recommendation-item.priority-3 { border-left-color: #dc3232; }
.recommendation-item.priority-2 { border-left-color: #ffb900; }
.recommendation-item.priority-1 { border-left-color: #00a0d2; }

.priority-badge {
    padding: 4px 12px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
    height: fit-content;
}

.priority-3 .priority-badge { background: #dc3232; color: #fff; }
.priority-2 .priority-badge { background: #ffb900; color: #fff; }
.priority-1 .priority-badge { background: #00a0d2; color: #fff; }

.recommendation-content {
    flex: 1;
}

.report-footer {
    padding: 40px;
    background: #f5f5f5;
    text-align: center;
}

.footer-actions {
    margin-bottom: 20px;
    display: flex;
    gap: 15px;
    justify-content: center;
}

.btn {
    padding: 12px 24px;
    border: none;
    border-radius: 4px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-primary {
    background: #0073aa;
    color: #fff;
}

.btn-primary:hover {
    background: #005177;
}

.btn-secondary {
    background: #fff;
    color: #0073aa;
    border: 2px solid #0073aa;
}

.btn-secondary:hover {
    background: #0073aa;
    color: #fff;
}

.footer-info {
    color: #666;
    font-size: 13px;
}

@media print {
    body {
        background: #fff;
        padding: 0;
    }

    .seo-report {
        box-shadow: none;
    }

    .footer-actions {
        display: none;
    }
}

@media screen and (max-width: 768px) {
    .overall-score {
        flex-direction: column;
        text-align: center;
    }

    .score-circle {
        width: 150px;
        height: 150px;
    }

    .score-number {
        font-size: 48px;
    }

    .report-section {
        padding: 20px;
    }

    .section-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }

    .footer-actions {
        flex-direction: column;
    }
}
        <?php
        return ob_get_clean();
    }
}
