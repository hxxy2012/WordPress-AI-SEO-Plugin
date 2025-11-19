<?php
/**
 * Meta Box类
 *
 * @package AI_SEO_Auditor
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AI_SEO_Meta_Box类
 *
 * 负责在文章编辑页面显示SEO审计Meta Box
 */
class AI_SEO_Meta_Box {

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
        add_action('add_meta_boxes', array($this, 'register_meta_box'));
        add_action('save_post', array($this, 'save_meta_box_data'));

        // AJAX处理
        add_action('wp_ajax_ai_seo_analyze', array($this, 'ajax_analyze_post'));
        add_action('wp_ajax_ai_seo_get_suggestions', array($this, 'ajax_get_internal_links'));
    }

    /**
     * 注册Meta Box
     */
    public function register_meta_box() {
        add_meta_box(
            'ai-seo-auditor',
            __('AI SEO 审计', 'ai-seo-auditor'),
            array($this, 'render_meta_box'),
            'post',
            'normal',
            'high'
        );
    }

    /**
     * 渲染Meta Box
     *
     * @param WP_Post $post 文章对象
     */
    public function render_meta_box($post) {
        // 添加nonce字段
        wp_nonce_field('ai_seo_meta_box', 'ai_seo_meta_box_nonce');

        // 获取已保存的数据
        $results = $this->analyzer->get_audit_results($post->ID);
        $target_keyword = get_post_meta($post->ID, '_ai_seo_target_keyword', true);

        ?>
        <div class="ai-seo-meta-box">
            <!-- 设置区域 -->
            <div class="ai-seo-settings">
                <div class="ai-seo-field">
                    <label for="ai_seo_target_keyword">
                        <?php _e('目标关键词（可选）:', 'ai-seo-auditor'); ?>
                    </label>
                    <input
                        type="text"
                        id="ai_seo_target_keyword"
                        name="ai_seo_target_keyword"
                        value="<?php echo esc_attr($target_keyword); ?>"
                        class="widefat"
                        placeholder="<?php _e('输入目标关键词', 'ai-seo-auditor'); ?>"
                    />
                    <p class="description">
                        <?php _e('设置目标关键词可以获得更精准的SEO分析', 'ai-seo-auditor'); ?>
                    </p>
                </div>
            </div>

            <!-- 操作按钮 -->
            <div class="ai-seo-actions">
                <button
                    type="button"
                    id="ai-seo-analyze-btn"
                    class="button button-primary button-large"
                    data-post-id="<?php echo esc_attr($post->ID); ?>"
                >
                    <span class="dashicons dashicons-search"></span>
                    <?php _e('开始 SEO 审计', 'ai-seo-auditor'); ?>
                </button>

                <button
                    type="button"
                    id="ai-seo-refresh-btn"
                    class="button button-secondary"
                    style="display: none;"
                >
                    <span class="dashicons dashicons-update"></span>
                    <?php _e('刷新结果', 'ai-seo-auditor'); ?>
                </button>
            </div>

            <!-- 加载提示 -->
            <div class="ai-seo-loading" style="display: none;">
                <div class="spinner is-active"></div>
                <p><?php _e('正在分析中，请稍候...', 'ai-seo-auditor'); ?></p>
            </div>

            <!-- 错误提示 -->
            <div class="ai-seo-error" style="display: none;">
                <div class="notice notice-error">
                    <p class="error-message"></p>
                </div>
            </div>

            <!-- 结果显示区域 -->
            <div class="ai-seo-results" <?php echo $results ? '' : 'style="display: none;"'; ?>>
                <?php if ($results): ?>
                    <?php $this->render_results($results); ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * 渲染分析结果
     *
     * @param array $results 分析结果
     */
    private function render_results($results) {
        $overall_score = isset($results['overall_score']) ? intval($results['overall_score']) : 0;
        $score_class = $this->get_score_class($overall_score);
        $score_label = $this->get_score_label($overall_score);

        ?>
        <div class="ai-seo-overall-score">
            <div class="score-circle <?php echo esc_attr($score_class); ?>">
                <span class="score-number"><?php echo esc_html($overall_score); ?></span>
                <span class="score-total">/100</span>
            </div>
            <div class="score-info">
                <h3><?php _e('SEO 总评分', 'ai-seo-auditor'); ?></h3>
                <p class="score-label"><?php echo esc_html($score_label); ?></p>
                <?php if (isset($results['timestamp'])): ?>
                    <p class="score-time">
                        <?php
                        printf(
                            __('最后更新: %s', 'ai-seo-auditor'),
                            esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $results['timestamp']))
                        );
                        ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <div class="ai-seo-details">
            <?php
            // 渲染各项评分卡片
            $sections = array(
                'title' => __('标题优化', 'ai-seo-auditor'),
                'meta_description' => __('Meta 描述', 'ai-seo-auditor'),
                'keyword' => __('关键词使用', 'ai-seo-auditor'),
                'content' => __('内容质量', 'ai-seo-auditor'),
                'readability' => __('可读性', 'ai-seo-auditor'),
                'technical' => __('技术 SEO', 'ai-seo-auditor')
            );

            foreach ($sections as $key => $label) {
                if (isset($results[$key])) {
                    $this->render_section_card($key, $label, $results[$key]);
                }
            }
            ?>
        </div>
        <?php
    }

    /**
     * 渲染单个评分卡片
     *
     * @param string $key 键名
     * @param string $label 标签
     * @param array $data 数据
     */
    private function render_section_card($key, $label, $data) {
        $score = isset($data['score']) ? intval($data['score']) : 0;
        $score_class = $this->get_score_class($score);
        $icon = $this->get_score_icon($score);
        $issues = isset($data['issues']) ? $data['issues'] : array();
        $suggestions = isset($data['suggestions']) ? $data['suggestions'] : array();

        ?>
        <div class="ai-seo-card">
            <div class="card-header">
                <h4>
                    <span class="dashicons <?php echo esc_attr($icon); ?>"></span>
                    <?php echo esc_html($label); ?>
                </h4>
                <span class="card-score <?php echo esc_attr($score_class); ?>">
                    <?php echo esc_html($score); ?>/100
                </span>
            </div>

            <div class="card-body">
                <?php if (!empty($issues) && is_array($issues)): ?>
                    <div class="card-issues">
                        <strong><?php _e('发现的问题:', 'ai-seo-auditor'); ?></strong>
                        <ul>
                            <?php foreach ($issues as $issue): ?>
                                <li><?php echo esc_html($issue); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if (!empty($suggestions) && is_array($suggestions)): ?>
                    <div class="card-suggestions">
                        <strong><?php _e('优化建议:', 'ai-seo-auditor'); ?></strong>
                        <?php if ($key === 'title'): ?>
                            <div class="title-suggestions">
                                <?php foreach ($suggestions as $index => $suggestion): ?>
                                    <div class="suggestion-item">
                                        <label>
                                            <input type="radio" name="suggested_title" value="<?php echo esc_attr($suggestion); ?>">
                                            <span><?php echo esc_html($suggestion); ?></span>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                                <button type="button" class="button button-small apply-title-btn">
                                    <?php _e('应用选中的标题', 'ai-seo-auditor'); ?>
                                </button>
                            </div>
                        <?php elseif ($key === 'meta_description'): ?>
                            <div class="meta-suggestions">
                                <?php foreach ($suggestions as $index => $suggestion): ?>
                                    <div class="suggestion-item">
                                        <label>
                                            <input type="radio" name="suggested_meta" value="<?php echo esc_attr($suggestion); ?>">
                                            <span><?php echo esc_html($suggestion); ?></span>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                                <button type="button" class="button button-small apply-meta-btn">
                                    <?php _e('应用选中的描述', 'ai-seo-auditor'); ?>
                                </button>
                            </div>
                        <?php else: ?>
                            <ul>
                                <?php foreach ($suggestions as $suggestion): ?>
                                    <li><?php echo esc_html($suggestion); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php
                // 显示额外信息
                if ($key === 'title' && isset($data['current_length'])) {
                    echo '<p class="meta-info">' . sprintf(__('当前长度: %d 字符', 'ai-seo-auditor'), $data['current_length']) . '</p>';
                }
                if ($key === 'meta_description' && isset($data['current_length'])) {
                    echo '<p class="meta-info">' . sprintf(__('当前长度: %d 字符', 'ai-seo-auditor'), $data['current_length']) . '</p>';
                }
                if ($key === 'keyword' && isset($data['density'])) {
                    echo '<p class="meta-info">' . sprintf(__('关键词密度: %.2f%%', 'ai-seo-auditor'), $data['density']) . '</p>';
                }
                if ($key === 'content' && isset($data['word_count'])) {
                    echo '<p class="meta-info">' . sprintf(__('文章字数: %d', 'ai-seo-auditor'), $data['word_count']) . '</p>';
                }
                if ($key === 'readability' && isset($data['level'])) {
                    echo '<p class="meta-info">' . sprintf(__('可读性等级: %d/10', 'ai-seo-auditor'), $data['level']) . '</p>';
                }
                ?>
            </div>
        </div>
        <?php
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

    /**
     * 获取评分图标
     *
     * @param int $score 评分
     * @return string 图标类名
     */
    private function get_score_icon($score) {
        if ($score >= 80) {
            return 'dashicons-yes-alt';
        } elseif ($score >= 60) {
            return 'dashicons-warning';
        } else {
            return 'dashicons-dismiss';
        }
    }

    /**
     * 保存Meta Box数据
     *
     * @param int $post_id 文章ID
     */
    public function save_meta_box_data($post_id) {
        // 检查nonce
        if (!isset($_POST['ai_seo_meta_box_nonce']) ||
            !wp_verify_nonce($_POST['ai_seo_meta_box_nonce'], 'ai_seo_meta_box')) {
            return;
        }

        // 检查自动保存
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // 检查权限
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // 保存目标关键词
        if (isset($_POST['ai_seo_target_keyword'])) {
            $keyword = sanitize_text_field($_POST['ai_seo_target_keyword']);
            update_post_meta($post_id, '_ai_seo_target_keyword', $keyword);
        }
    }

    /**
     * AJAX处理分析请求
     */
    public function ajax_analyze_post() {
        // 验证nonce
        check_ajax_referer('ai_seo_nonce', 'nonce');

        // 检查权限
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array(
                'message' => __('权限不足', 'ai-seo-auditor')
            ));
        }

        // 获取文章ID
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

        if (!$post_id) {
            wp_send_json_error(array(
                'message' => __('无效的文章ID', 'ai-seo-auditor')
            ));
        }

        // 执行分析
        $result = $this->analyzer->analyze_post($post_id);

        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => $result->get_error_message()
            ));
        }

        wp_send_json_success(array(
            'message' => __('分析完成', 'ai-seo-auditor'),
            'results' => $result
        ));
    }

    /**
     * AJAX获取内链建议
     */
    public function ajax_get_internal_links() {
        check_ajax_referer('ai_seo_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array(
                'message' => __('权限不足', 'ai-seo-auditor')
            ));
        }

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

        if (!$post_id) {
            wp_send_json_error(array(
                'message' => __('无效的文章ID', 'ai-seo-auditor')
            ));
        }

        $suggestions = $this->analyzer->get_internal_link_suggestions($post_id);

        wp_send_json_success(array(
            'suggestions' => $suggestions
        ));
    }
}
