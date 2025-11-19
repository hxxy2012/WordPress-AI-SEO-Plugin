<?php
/**
 * 设置页面类
 *
 * @package AI_SEO_Auditor
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AI_SEO_Settings类
 *
 * 负责处理插件设置
 */
class AI_SEO_Settings {

    /**
     * 单例实例
     */
    private static $instance = null;

    /**
     * 设置选项组
     */
    private $option_group = 'ai_seo_settings_group';

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
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_ajax_ai_seo_test_api', array($this, 'ajax_test_api'));
    }

    /**
     * 添加设置页面
     */
    public function add_settings_page() {
        add_menu_page(
            __('AI SEO Auditor', 'ai-seo-auditor'),
            __('AI SEO Auditor', 'ai-seo-auditor'),
            'manage_options',
            'ai-seo-auditor',
            array($this, 'render_settings_page'),
            'dashicons-search',
            66
        );
    }

    /**
     * 注册设置
     */
    public function register_settings() {
        // 注册API设置
        register_setting(
            $this->option_group,
            'ai_seo_api_key',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => ''
            )
        );

        register_setting(
            $this->option_group,
            'ai_seo_settings',
            array(
                'type' => 'array',
                'sanitize_callback' => array($this, 'sanitize_settings'),
                'default' => array(
                    'keyword_density_min' => 1,
                    'keyword_density_max' => 3,
                    'title_length_min' => 50,
                    'title_length_max' => 60,
                    'meta_length_min' => 150,
                    'meta_length_max' => 160,
                    'min_word_count' => 300,
                    'language' => 'zh',
                    'model' => 'claude-sonnet-4-5-20250929'
                )
            )
        );

        // 添加设置部分
        add_settings_section(
            'ai_seo_api_section',
            __('API 配置', 'ai-seo-auditor'),
            array($this, 'render_api_section'),
            'ai-seo-auditor'
        );

        add_settings_section(
            'ai_seo_rules_section',
            __('审计规则配置', 'ai-seo-auditor'),
            array($this, 'render_rules_section'),
            'ai-seo-auditor'
        );

        // 添加设置字段
        add_settings_field(
            'api_key',
            __('Claude API Key', 'ai-seo-auditor'),
            array($this, 'render_api_key_field'),
            'ai-seo-auditor',
            'ai_seo_api_section'
        );

        add_settings_field(
            'model',
            __('AI 模型', 'ai-seo-auditor'),
            array($this, 'render_model_field'),
            'ai-seo-auditor',
            'ai_seo_api_section'
        );

        add_settings_field(
            'keyword_density',
            __('关键词密度范围 (%)', 'ai-seo-auditor'),
            array($this, 'render_keyword_density_field'),
            'ai-seo-auditor',
            'ai_seo_rules_section'
        );

        add_settings_field(
            'title_length',
            __('标题长度范围', 'ai-seo-auditor'),
            array($this, 'render_title_length_field'),
            'ai-seo-auditor',
            'ai_seo_rules_section'
        );

        add_settings_field(
            'meta_length',
            __('Meta描述长度范围', 'ai-seo-auditor'),
            array($this, 'render_meta_length_field'),
            'ai-seo-auditor',
            'ai_seo_rules_section'
        );

        add_settings_field(
            'min_word_count',
            __('最小文章字数', 'ai-seo-auditor'),
            array($this, 'render_min_word_count_field'),
            'ai-seo-auditor',
            'ai_seo_rules_section'
        );

        add_settings_field(
            'language',
            __('分析语言', 'ai-seo-auditor'),
            array($this, 'render_language_field'),
            'ai-seo-auditor',
            'ai_seo_rules_section'
        );
    }

    /**
     * 渲染设置页面
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // 显示保存消息
        if (isset($_GET['settings-updated'])) {
            add_settings_error(
                'ai_seo_messages',
                'ai_seo_message',
                __('设置已保存', 'ai-seo-auditor'),
                'updated'
            );
        }

        settings_errors('ai_seo_messages');

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <div class="ai-seo-settings-container">
                <form action="options.php" method="post">
                    <?php
                    settings_fields($this->option_group);
                    do_settings_sections('ai-seo-auditor');
                    submit_button(__('保存设置', 'ai-seo-auditor'));
                    ?>
                </form>

                <!-- 统计信息 -->
                <div class="ai-seo-stats">
                    <h2><?php _e('统计信息', 'ai-seo-auditor'); ?></h2>
                    <?php $this->render_stats(); ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * 渲染API部分说明
     */
    public function render_api_section() {
        echo '<p>' . __('配置Claude API以启用SEO审计功能。', 'ai-seo-auditor') . '</p>';
        echo '<p>' . sprintf(
            __('获取API Key: <a href="%s" target="_blank">https://console.anthropic.com/</a>', 'ai-seo-auditor'),
            'https://console.anthropic.com/'
        ) . '</p>';
    }

    /**
     * 渲染规则部分说明
     */
    public function render_rules_section() {
        echo '<p>' . __('自定义SEO审计规则和标准。', 'ai-seo-auditor') . '</p>';
    }

    /**
     * 渲染API Key字段
     */
    public function render_api_key_field() {
        $api_key = get_option('ai_seo_api_key', '');
        $masked_key = !empty($api_key) ? substr($api_key, 0, 10) . '...' : '';

        ?>
        <input
            type="password"
            name="ai_seo_api_key"
            id="ai_seo_api_key"
            value="<?php echo esc_attr($api_key); ?>"
            class="regular-text"
            placeholder="sk-ant-api..."
        />
        <?php if (!empty($api_key)): ?>
            <p class="description">
                <?php printf(__('当前: %s', 'ai-seo-auditor'), esc_html($masked_key)); ?>
            </p>
        <?php endif; ?>
        <p>
            <button type="button" id="ai-seo-test-api-btn" class="button button-secondary">
                <?php _e('测试API连接', 'ai-seo-auditor'); ?>
            </button>
            <span id="ai-seo-test-result"></span>
        </p>
        <?php
    }

    /**
     * 渲染模型字段
     */
    public function render_model_field() {
        $settings = get_option('ai_seo_settings', array());
        $model = isset($settings['model']) ? $settings['model'] : 'claude-sonnet-4-5-20250929';

        ?>
        <select name="ai_seo_settings[model]" id="ai_seo_model">
            <option value="claude-sonnet-4-5-20250929" <?php selected($model, 'claude-sonnet-4-5-20250929'); ?>>
                Claude Sonnet 4.5 (推荐)
            </option>
            <option value="claude-3-5-sonnet-20241022" <?php selected($model, 'claude-3-5-sonnet-20241022'); ?>>
                Claude 3.5 Sonnet
            </option>
        </select>
        <p class="description">
            <?php _e('选择使用的Claude模型', 'ai-seo-auditor'); ?>
        </p>
        <?php
    }

    /**
     * 渲染关键词密度字段
     */
    public function render_keyword_density_field() {
        $settings = get_option('ai_seo_settings', array());
        $min = isset($settings['keyword_density_min']) ? $settings['keyword_density_min'] : 1;
        $max = isset($settings['keyword_density_max']) ? $settings['keyword_density_max'] : 3;

        ?>
        <input
            type="number"
            name="ai_seo_settings[keyword_density_min]"
            value="<?php echo esc_attr($min); ?>"
            min="0"
            max="10"
            step="0.1"
            class="small-text"
        />
        <span> - </span>
        <input
            type="number"
            name="ai_seo_settings[keyword_density_max]"
            value="<?php echo esc_attr($max); ?>"
            min="0"
            max="10"
            step="0.1"
            class="small-text"
        />
        <p class="description">
            <?php _e('建议范围: 1-3%', 'ai-seo-auditor'); ?>
        </p>
        <?php
    }

    /**
     * 渲染标题长度字段
     */
    public function render_title_length_field() {
        $settings = get_option('ai_seo_settings', array());
        $min = isset($settings['title_length_min']) ? $settings['title_length_min'] : 50;
        $max = isset($settings['title_length_max']) ? $settings['title_length_max'] : 60;

        ?>
        <input
            type="number"
            name="ai_seo_settings[title_length_min]"
            value="<?php echo esc_attr($min); ?>"
            min="0"
            max="200"
            class="small-text"
        />
        <span> - </span>
        <input
            type="number"
            name="ai_seo_settings[title_length_max]"
            value="<?php echo esc_attr($max); ?>"
            min="0"
            max="200"
            class="small-text"
        />
        <span> <?php _e('字符', 'ai-seo-auditor'); ?></span>
        <p class="description">
            <?php _e('建议范围: 50-60字符', 'ai-seo-auditor'); ?>
        </p>
        <?php
    }

    /**
     * 渲染Meta描述长度字段
     */
    public function render_meta_length_field() {
        $settings = get_option('ai_seo_settings', array());
        $min = isset($settings['meta_length_min']) ? $settings['meta_length_min'] : 150;
        $max = isset($settings['meta_length_max']) ? $settings['meta_length_max'] : 160;

        ?>
        <input
            type="number"
            name="ai_seo_settings[meta_length_min]"
            value="<?php echo esc_attr($min); ?>"
            min="0"
            max="300"
            class="small-text"
        />
        <span> - </span>
        <input
            type="number"
            name="ai_seo_settings[meta_length_max]"
            value="<?php echo esc_attr($max); ?>"
            min="0"
            max="300"
            class="small-text"
        />
        <span> <?php _e('字符', 'ai-seo-auditor'); ?></span>
        <p class="description">
            <?php _e('建议范围: 150-160字符', 'ai-seo-auditor'); ?>
        </p>
        <?php
    }

    /**
     * 渲染最小字数字段
     */
    public function render_min_word_count_field() {
        $settings = get_option('ai_seo_settings', array());
        $min_words = isset($settings['min_word_count']) ? $settings['min_word_count'] : 300;

        ?>
        <input
            type="number"
            name="ai_seo_settings[min_word_count]"
            value="<?php echo esc_attr($min_words); ?>"
            min="0"
            max="10000"
            class="small-text"
        />
        <span> <?php _e('字', 'ai-seo-auditor'); ?></span>
        <p class="description">
            <?php _e('建议至少300字', 'ai-seo-auditor'); ?>
        </p>
        <?php
    }

    /**
     * 渲染语言字段
     */
    public function render_language_field() {
        $settings = get_option('ai_seo_settings', array());
        $language = isset($settings['language']) ? $settings['language'] : 'zh';

        ?>
        <select name="ai_seo_settings[language]" id="ai_seo_language">
            <option value="zh" <?php selected($language, 'zh'); ?>>
                <?php _e('中文', 'ai-seo-auditor'); ?>
            </option>
            <option value="en" <?php selected($language, 'en'); ?>>
                <?php _e('英文', 'ai-seo-auditor'); ?>
            </option>
            <option value="both" <?php selected($language, 'both'); ?>>
                <?php _e('中英双语', 'ai-seo-auditor'); ?>
            </option>
        </select>
        <p class="description">
            <?php _e('选择分析时使用的语言', 'ai-seo-auditor'); ?>
        </p>
        <?php
    }

    /**
     * 渲染统计信息
     */
    private function render_stats() {
        $analyzer = AI_SEO_Analyzer::get_instance();

        // 获取平均评分
        $avg_score = $analyzer->get_average_seo_score();

        // 获取需要优化的文章
        $posts_need_optimization = $analyzer->get_posts_need_optimization(60, 5);

        // 获取已审计文章总数
        global $wpdb;
        $audited_count = $wpdb->get_var(
            "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = '_seo_audit_score'"
        );

        ?>
        <div class="ai-seo-stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo esc_html($avg_score); ?></div>
                <div class="stat-label"><?php _e('平均 SEO 评分', 'ai-seo-auditor'); ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-value"><?php echo esc_html($audited_count); ?></div>
                <div class="stat-label"><?php _e('已审计文章', 'ai-seo-auditor'); ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-value"><?php echo count($posts_need_optimization); ?></div>
                <div class="stat-label"><?php _e('需要优化', 'ai-seo-auditor'); ?></div>
            </div>
        </div>

        <?php if (!empty($posts_need_optimization)): ?>
            <div class="posts-need-optimization">
                <h3><?php _e('Top 5 需要优化的文章', 'ai-seo-auditor'); ?></h3>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php _e('文章标题', 'ai-seo-auditor'); ?></th>
                            <th><?php _e('SEO 评分', 'ai-seo-auditor'); ?></th>
                            <th><?php _e('操作', 'ai-seo-auditor'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($posts_need_optimization as $post): ?>
                            <tr>
                                <td>
                                    <a href="<?php echo esc_url(get_edit_post_link($post->ID)); ?>">
                                        <?php echo esc_html($post->post_title); ?>
                                    </a>
                                </td>
                                <td>
                                    <span class="score-badge score-<?php echo $post->score < 40 ? 'poor' : 'fair'; ?>">
                                        <?php echo esc_html($post->score); ?>/100
                                    </span>
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
            </div>
        <?php endif; ?>
        <?php
    }

    /**
     * 清理设置数据
     *
     * @param array $input 输入数据
     * @return array 清理后的数据
     */
    public function sanitize_settings($input) {
        $sanitized = array();

        $sanitized['keyword_density_min'] = isset($input['keyword_density_min'])
            ? floatval($input['keyword_density_min'])
            : 1;

        $sanitized['keyword_density_max'] = isset($input['keyword_density_max'])
            ? floatval($input['keyword_density_max'])
            : 3;

        $sanitized['title_length_min'] = isset($input['title_length_min'])
            ? intval($input['title_length_min'])
            : 50;

        $sanitized['title_length_max'] = isset($input['title_length_max'])
            ? intval($input['title_length_max'])
            : 60;

        $sanitized['meta_length_min'] = isset($input['meta_length_min'])
            ? intval($input['meta_length_min'])
            : 150;

        $sanitized['meta_length_max'] = isset($input['meta_length_max'])
            ? intval($input['meta_length_max'])
            : 160;

        $sanitized['min_word_count'] = isset($input['min_word_count'])
            ? intval($input['min_word_count'])
            : 300;

        $sanitized['language'] = isset($input['language'])
            ? sanitize_text_field($input['language'])
            : 'zh';

        $sanitized['model'] = isset($input['model'])
            ? sanitize_text_field($input['model'])
            : 'claude-sonnet-4-5-20250929';

        return $sanitized;
    }

    /**
     * AJAX测试API连接
     */
    public function ajax_test_api() {
        check_ajax_referer('ai_seo_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('权限不足', 'ai-seo-auditor')
            ));
        }

        $api_handler = new AI_SEO_API_Handler();
        $result = $api_handler->test_connection();

        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => $result->get_error_message()
            ));
        }

        wp_send_json_success($result);
    }
}
