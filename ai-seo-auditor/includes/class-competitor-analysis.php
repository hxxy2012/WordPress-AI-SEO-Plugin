<?php
/**
 * 竞品分析类
 *
 * @package AI_SEO_Auditor
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AI_SEO_Competitor_Analysis类
 *
 * 负责竞品URL分析和对比
 */
class AI_SEO_Competitor_Analysis {

    /**
     * 单例实例
     */
    private static $instance = null;

    /**
     * API处理器
     */
    private $api_handler;

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
        $this->api_handler = new AI_SEO_API_Handler();
        $this->init_hooks();
    }

    /**
     * 初始化钩子
     */
    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_competitor_page'), 20);
        add_action('wp_ajax_ai_seo_analyze_competitor', array($this, 'ajax_analyze_competitor'));
    }

    /**
     * 添加竞品分析页面
     */
    public function add_competitor_page() {
        add_submenu_page(
            'ai-seo-auditor',
            __('竞品分析', 'ai-seo-auditor'),
            __('竞品分析', 'ai-seo-auditor'),
            'manage_options',
            'ai-seo-competitor',
            array($this, 'render_competitor_page')
        );
    }

    /**
     * 渲染竞品分析页面
     */
    public function render_competitor_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        ?>
        <div class="wrap">
            <h1><?php _e('竞品SEO分析', 'ai-seo-auditor'); ?></h1>

            <div class="ai-seo-competitor-container">
                <!-- 输入表单 -->
                <div class="competitor-input-section">
                    <h2><?php _e('输入竞品URL进行分析', 'ai-seo-auditor'); ?></h2>

                    <form id="competitor-analysis-form">
                        <?php wp_nonce_field('ai_seo_nonce', 'ai_seo_nonce'); ?>

                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="competitor_url"><?php _e('竞品URL', 'ai-seo-auditor'); ?></label>
                                </th>
                                <td>
                                    <input
                                        type="url"
                                        name="competitor_url"
                                        id="competitor_url"
                                        class="regular-text"
                                        placeholder="https://example.com/article"
                                        required
                                    />
                                    <p class="description">
                                        <?php _e('输入竞争对手的文章URL', 'ai-seo-auditor'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="compare_post_id"><?php _e('对比文章（可选）', 'ai-seo-auditor'); ?></label>
                                </th>
                                <td>
                                    <?php
                                    wp_dropdown_pages(array(
                                        'post_type' => 'post',
                                        'selected' => 0,
                                        'name' => 'compare_post_id',
                                        'id' => 'compare_post_id',
                                        'show_option_none' => __('-- 选择文章 --', 'ai-seo-auditor'),
                                        'option_none_value' => '0'
                                    ));
                                    ?>
                                    <p class="description">
                                        <?php _e('选择您的文章进行对比分析', 'ai-seo-auditor'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>

                        <p class="submit">
                            <button type="submit" class="button button-primary button-large">
                                <?php _e('开始分析', 'ai-seo-auditor'); ?>
                            </button>
                        </p>
                    </form>
                </div>

                <!-- 加载提示 -->
                <div class="competitor-loading" style="display: none;">
                    <div class="spinner is-active"></div>
                    <p><?php _e('正在抓取和分析竞品内容...', 'ai-seo-auditor'); ?></p>
                </div>

                <!-- 错误提示 -->
                <div class="competitor-error" style="display: none;">
                    <div class="notice notice-error">
                        <p class="error-message"></p>
                    </div>
                </div>

                <!-- 分析结果 -->
                <div class="competitor-results" style="display: none;">
                    <h2><?php _e('分析结果', 'ai-seo-auditor'); ?></h2>
                    <div class="results-content"></div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX分析竞品
     */
    public function ajax_analyze_competitor() {
        check_ajax_referer('ai_seo_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('权限不足', 'ai-seo-auditor')
            ));
        }

        $url = isset($_POST['competitor_url']) ? esc_url_raw($_POST['competitor_url']) : '';
        $compare_post_id = isset($_POST['compare_post_id']) ? intval($_POST['compare_post_id']) : 0;

        if (empty($url)) {
            wp_send_json_error(array(
                'message' => __('请输入有效的URL', 'ai-seo-auditor')
            ));
        }

        // 抓取竞品内容
        $competitor_data = $this->fetch_url_content($url);

        if (is_wp_error($competitor_data)) {
            wp_send_json_error(array(
                'message' => $competitor_data->get_error_message()
            ));
        }

        // 使用AI分析竞品
        $analysis = $this->analyze_competitor($competitor_data);

        if (is_wp_error($analysis)) {
            wp_send_json_error(array(
                'message' => $analysis->get_error_message()
            ));
        }

        $result = array(
            'competitor' => $analysis
        );

        // 如果指定了对比文章，也分析自己的文章
        if ($compare_post_id > 0) {
            $analyzer = AI_SEO_Analyzer::get_instance();
            $own_analysis = $analyzer->get_audit_results($compare_post_id);

            if (!$own_analysis) {
                // 如果没有缓存，现场分析
                $own_analysis = $analyzer->analyze_post($compare_post_id);
            }

            if (!is_wp_error($own_analysis)) {
                $result['own'] = $own_analysis;
                $result['comparison'] = $this->compare_results($own_analysis, $analysis);
            }
        }

        wp_send_json_success($result);
    }

    /**
     * 抓取URL内容
     *
     * @param string $url URL地址
     * @return array|WP_Error 页面数据或错误
     */
    private function fetch_url_content($url) {
        // 发送HTTP请求
        $response = wp_remote_get($url, array(
            'timeout' => 30,
            'user-agent' => 'Mozilla/5.0 (compatible; AI-SEO-Auditor/1.0)'
        ));

        if (is_wp_error($response)) {
            return new WP_Error('fetch_failed', __('无法访问该URL', 'ai-seo-auditor'));
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return new WP_Error('invalid_response', sprintf(__('HTTP错误: %d', 'ai-seo-auditor'), $code));
        }

        $html = wp_remote_retrieve_body($response);

        // 解析HTML
        $data = $this->parse_html($html, $url);

        return $data;
    }

    /**
     * 解析HTML内容
     *
     * @param string $html HTML内容
     * @param string $url 原始URL
     * @return array 解析后的数据
     */
    private function parse_html($html, $url) {
        // 使用DOMDocument解析HTML
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        libxml_clear_errors();

        $data = array(
            'url' => $url,
            'title' => '',
            'meta_description' => '',
            'content' => '',
            'headings' => array(),
            'images' => array(),
            'word_count' => 0
        );

        // 提取标题
        $titleElements = $dom->getElementsByTagName('title');
        if ($titleElements->length > 0) {
            $data['title'] = $titleElements->item(0)->textContent;
        }

        // 提取Meta描述
        $metaTags = $dom->getElementsByTagName('meta');
        foreach ($metaTags as $meta) {
            $name = $meta->getAttribute('name');
            $property = $meta->getAttribute('property');

            if (strtolower($name) === 'description') {
                $data['meta_description'] = $meta->getAttribute('content');
            }

            // 提取Open Graph数据
            if ($property === 'og:title') {
                $data['og_title'] = $meta->getAttribute('content');
            }
            if ($property === 'og:description') {
                $data['og_description'] = $meta->getAttribute('content');
            }
            if ($property === 'og:image') {
                $data['og_image'] = $meta->getAttribute('content');
            }
        }

        // 提取主要内容（尝试找到article或main标签）
        $content = '';
        $articleElements = $dom->getElementsByTagName('article');
        if ($articleElements->length > 0) {
            $content = $this->get_element_text($articleElements->item(0));
        } else {
            $mainElements = $dom->getElementsByTagName('main');
            if ($mainElements->length > 0) {
                $content = $this->get_element_text($mainElements->item(0));
            } else {
                // 如果都没有，尝试获取body
                $bodyElements = $dom->getElementsByTagName('body');
                if ($bodyElements->length > 0) {
                    $content = $this->get_element_text($bodyElements->item(0));
                }
            }
        }

        $data['content'] = $content;
        $data['word_count'] = str_word_count(strip_tags($content));

        // 提取标题层次
        for ($i = 1; $i <= 6; $i++) {
            $headings = $dom->getElementsByTagName('h' . $i);
            foreach ($headings as $heading) {
                $data['headings'][] = array(
                    'level' => $i,
                    'text' => $heading->textContent
                );
            }
        }

        // 提取图片信息
        $imgElements = $dom->getElementsByTagName('img');
        foreach ($imgElements as $img) {
            $data['images'][] = array(
                'src' => $img->getAttribute('src'),
                'alt' => $img->getAttribute('alt')
            );
        }

        return $data;
    }

    /**
     * 获取DOM元素的文本内容
     *
     * @param DOMNode $element DOM元素
     * @return string 文本内容
     */
    private function get_element_text($element) {
        return $element->textContent;
    }

    /**
     * 分析竞品数据
     *
     * @param array $data 竞品数据
     * @return array|WP_Error 分析结果
     */
    private function analyze_competitor($data) {
        // 构建prompt
        $prompt = "你是一位专业的SEO专家。请分析以下竞品网页的SEO质量。\n\n";
        $prompt .= "**竞品信息：**\n";
        $prompt .= "URL: {$data['url']}\n";
        $prompt .= "标题: {$data['title']}\n";
        $prompt .= "Meta描述: {$data['meta_description']}\n";
        $prompt .= "文章字数: {$data['word_count']}\n";
        $prompt .= "标题数量: " . count($data['headings']) . "\n";
        $prompt .= "图片数量: " . count($data['images']) . "\n\n";

        $prompt .= "**内容摘要（前500字）：**\n";
        $prompt .= mb_substr($data['content'], 0, 500) . "\n\n";

        $prompt .= "请分析并返回JSON格式结果：\n";
        $prompt .= "```json\n";
        $prompt .= "{\n";
        $prompt .= '  "overall_score": 85,' . "\n";
        $prompt .= '  "strengths": ["优势1", "优势2", "优势3"],' . "\n";
        $prompt .= '  "weaknesses": ["不足1", "不足2"],' . "\n";
        $prompt .= '  "opportunities": ["可以学习的地方1", "可以学习的地方2"],' . "\n";
        $prompt .= '  "title": {' . "\n";
        $prompt .= '    "score": 90,' . "\n";
        $prompt .= '    "analysis": "分析内容"' . "\n";
        $prompt .= '  },' . "\n";
        $prompt .= '  "meta_description": {' . "\n";
        $prompt .= '    "score": 85,' . "\n";
        $prompt .= '    "analysis": "分析内容"' . "\n";
        $prompt .= '  },' . "\n";
        $prompt .= '  "content": {' . "\n";
        $prompt .= '    "score": 80,' . "\n";
        $prompt .= '    "analysis": "分析内容"' . "\n";
        $prompt .= '  },' . "\n";
        $prompt .= '  "recommendations": ["建议1", "建议2", "建议3"]' . "\n";
        $prompt .= "}\n";
        $prompt .= "```\n";

        // 调用API
        $response = $this->api_handler->analyze_content(array(
            'title' => $data['title'],
            'content' => $data['content'],
            'meta_description' => $data['meta_description'],
            'url' => $data['url']
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        // 添加额外的竞品数据
        $response['url'] = $data['url'];
        $response['word_count'] = $data['word_count'];
        $response['heading_count'] = count($data['headings']);
        $response['image_count'] = count($data['images']);

        return $response;
    }

    /**
     * 对比分析结果
     *
     * @param array $own 自己的分析结果
     * @param array $competitor 竞品分析结果
     * @return array 对比结果
     */
    private function compare_results($own, $competitor) {
        $comparison = array(
            'score_diff' => $own['overall_score'] - $competitor['overall_score'],
            'advantages' => array(),
            'disadvantages' => array(),
            'details' => array()
        );

        // 比较各个维度
        $dimensions = array('title', 'meta_description', 'keyword', 'content', 'readability', 'technical');

        foreach ($dimensions as $dim) {
            if (isset($own[$dim]['score']) && isset($competitor[$dim]['score'])) {
                $own_score = $own[$dim]['score'];
                $comp_score = $competitor[$dim]['score'];
                $diff = $own_score - $comp_score;

                $comparison['details'][$dim] = array(
                    'own_score' => $own_score,
                    'competitor_score' => $comp_score,
                    'difference' => $diff,
                    'status' => $diff > 0 ? 'better' : ($diff < 0 ? 'worse' : 'equal')
                );

                if ($diff > 10) {
                    $comparison['advantages'][] = $dim;
                } elseif ($diff < -10) {
                    $comparison['disadvantages'][] = $dim;
                }
            }
        }

        return $comparison;
    }
}
