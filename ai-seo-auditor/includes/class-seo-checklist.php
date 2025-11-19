<?php
/**
 * SEO检查清单类
 *
 * @package AI_SEO_Auditor
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AI_SEO_Checklist类
 *
 * 提供SEO检查清单和快速修复功能
 */
class AI_SEO_Checklist {

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
        add_action('wp_ajax_ai_seo_run_checklist', array($this, 'ajax_run_checklist'));
        add_action('wp_ajax_ai_seo_quick_fix', array($this, 'ajax_quick_fix'));

        // 添加到Meta Box
        add_action('ai_seo_before_results', array($this, 'render_checklist'), 10, 1);
    }

    /**
     * AJAX运行检查清单
     */
    public function ajax_run_checklist() {
        check_ajax_referer('ai_seo_nonce', 'nonce');

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

        if (!$post_id) {
            wp_send_json_error(array('message' => __('无效的文章ID', 'ai-seo-auditor')));
        }

        $checklist = $this->run_checklist($post_id);

        if (is_wp_error($checklist)) {
            wp_send_json_error(array('message' => $checklist->get_error_message()));
        }

        wp_send_json_success($checklist);
    }

    /**
     * AJAX快速修复
     */
    public function ajax_quick_fix() {
        check_ajax_referer('ai_seo_nonce', 'nonce');

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $fix_type = isset($_POST['fix_type']) ? sanitize_text_field($_POST['fix_type']) : '';

        if (!$post_id || !$fix_type) {
            wp_send_json_error(array('message' => __('参数不完整', 'ai-seo-auditor')));
        }

        $result = $this->apply_quick_fix($post_id, $fix_type);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success($result);
    }

    /**
     * 运行检查清单
     *
     * @param int $post_id 文章ID
     * @return array 检查结果
     */
    public function run_checklist($post_id) {
        $post = get_post($post_id);

        if (!$post) {
            return new WP_Error('invalid_post', __('文章不存在', 'ai-seo-auditor'));
        }

        $checklist = array();

        // 1. 检查标题
        $checklist[] = $this->check_title($post);

        // 2. 检查Meta描述
        $checklist[] = $this->check_meta_description($post);

        // 3. 检查永久链接
        $checklist[] = $this->check_permalink($post);

        // 4. 检查内容长度
        $checklist[] = $this->check_content_length($post);

        // 5. 检查标题层级
        $checklist[] = $this->check_heading_structure($post);

        // 6. 检查图片Alt属性
        $checklist[] = $this->check_image_alt($post);

        // 7. 检查内部链接
        $checklist[] = $this->check_internal_links($post);

        // 8. 检查外部链接
        $checklist[] = $this->check_external_links($post);

        // 9. 检查关键词密度
        $checklist[] = $this->check_keyword_density($post);

        // 10. 检查可读性
        $checklist[] = $this->check_readability($post);

        // 11. 检查特色图片
        $checklist[] = $this->check_featured_image($post);

        // 12. 检查分类和标签
        $checklist[] = $this->check_taxonomies($post);

        // 统计通过和失败的项目
        $passed = count(array_filter($checklist, function($item) {
            return $item['status'] === 'passed';
        }));

        $total = count($checklist);

        return array(
            'items' => $checklist,
            'summary' => array(
                'total' => $total,
                'passed' => $passed,
                'failed' => $total - $passed,
                'percentage' => round(($passed / $total) * 100)
            )
        );
    }

    /**
     * 检查标题
     */
    private function check_title($post) {
        $title = $post->post_title;
        $length = mb_strlen($title);

        if (empty($title)) {
            return array(
                'id' => 'title_missing',
                'name' => __('文章标题', 'ai-seo-auditor'),
                'status' => 'failed',
                'message' => __('文章缺少标题', 'ai-seo-auditor'),
                'fixable' => false
            );
        }

        if ($length < 30) {
            return array(
                'id' => 'title_short',
                'name' => __('文章标题', 'ai-seo-auditor'),
                'status' => 'warning',
                'message' => sprintf(__('标题长度为%d字符，建议至少30字符', 'ai-seo-auditor'), $length),
                'fixable' => false
            );
        }

        if ($length > 70) {
            return array(
                'id' => 'title_long',
                'name' => __('文章标题', 'ai-seo-auditor'),
                'status' => 'warning',
                'message' => sprintf(__('标题长度为%d字符，建议不超过70字符', 'ai-seo-auditor'), $length),
                'fixable' => false
            );
        }

        return array(
            'id' => 'title_ok',
            'name' => __('文章标题', 'ai-seo-auditor'),
            'status' => 'passed',
            'message' => sprintf(__('标题长度适中（%d字符）', 'ai-seo-auditor'), $length),
            'fixable' => false
        );
    }

    /**
     * 检查Meta描述
     */
    private function check_meta_description($post) {
        // 检查常见SEO插件的Meta描述字段
        $meta_desc = '';

        // Yoast SEO
        if (empty($meta_desc)) {
            $meta_desc = get_post_meta($post->ID, '_yoast_wpseo_metadesc', true);
        }

        // Rank Math
        if (empty($meta_desc)) {
            $meta_desc = get_post_meta($post->ID, 'rank_math_description', true);
        }

        // All in One SEO
        if (empty($meta_desc)) {
            $meta_desc = get_post_meta($post->ID, '_aioseo_description', true);
        }

        if (empty($meta_desc)) {
            return array(
                'id' => 'meta_missing',
                'name' => __('Meta描述', 'ai-seo-auditor'),
                'status' => 'failed',
                'message' => __('未设置Meta描述', 'ai-seo-auditor'),
                'fixable' => true,
                'fix_type' => 'add_meta_description'
            );
        }

        $length = mb_strlen($meta_desc);

        if ($length < 120) {
            return array(
                'id' => 'meta_short',
                'name' => __('Meta描述', 'ai-seo-auditor'),
                'status' => 'warning',
                'message' => sprintf(__('Meta描述较短（%d字符），建议120-160字符', 'ai-seo-auditor'), $length),
                'fixable' => false
            );
        }

        if ($length > 160) {
            return array(
                'id' => 'meta_long',
                'name' => __('Meta描述', 'ai-seo-auditor'),
                'status' => 'warning',
                'message' => sprintf(__('Meta描述过长（%d字符），可能被截断', 'ai-seo-auditor'), $length),
                'fixable' => false
            );
        }

        return array(
            'id' => 'meta_ok',
            'name' => __('Meta描述', 'ai-seo-auditor'),
            'status' => 'passed',
            'message' => sprintf(__('Meta描述长度适中（%d字符）', 'ai-seo-auditor'), $length),
            'fixable' => false
        );
    }

    /**
     * 检查永久链接
     */
    private function check_permalink($post) {
        $permalink = get_permalink($post->ID);
        $url_path = parse_url($permalink, PHP_URL_PATH);
        $length = strlen($url_path);

        if ($length > 100) {
            return array(
                'id' => 'permalink_long',
                'name' => __('永久链接', 'ai-seo-auditor'),
                'status' => 'warning',
                'message' => sprintf(__('URL较长（%d字符），建议简化', 'ai-seo-auditor'), $length),
                'fixable' => false
            );
        }

        // 检查是否包含中文字符（会被编码）
        if (preg_match('/[\x{4e00}-\x{9fa5}]/u', $url_path)) {
            return array(
                'id' => 'permalink_chinese',
                'name' => __('永久链接', 'ai-seo-auditor'),
                'status' => 'warning',
                'message' => __('URL包含中文字符，建议使用英文', 'ai-seo-auditor'),
                'fixable' => false
            );
        }

        return array(
            'id' => 'permalink_ok',
            'name' => __('永久链接', 'ai-seo-auditor'),
            'status' => 'passed',
            'message' => __('URL结构良好', 'ai-seo-auditor'),
            'fixable' => false
        );
    }

    /**
     * 检查内容长度
     */
    private function check_content_length($post) {
        $content = wp_strip_all_tags($post->post_content);
        $word_count = mb_strlen($content);

        if ($word_count < 300) {
            return array(
                'id' => 'content_short',
                'name' => __('内容长度', 'ai-seo-auditor'),
                'status' => 'failed',
                'message' => sprintf(__('内容仅%d字，建议至少300字', 'ai-seo-auditor'), $word_count),
                'fixable' => false
            );
        }

        if ($word_count < 600) {
            return array(
                'id' => 'content_medium',
                'name' => __('内容长度', 'ai-seo-auditor'),
                'status' => 'warning',
                'message' => sprintf(__('内容%d字，建议增加到1000字以上', 'ai-seo-auditor'), $word_count),
                'fixable' => false
            );
        }

        return array(
            'id' => 'content_ok',
            'name' => __('内容长度', 'ai-seo-auditor'),
            'status' => 'passed',
            'message' => sprintf(__('内容充实（%d字）', 'ai-seo-auditor'), $word_count),
            'fixable' => false
        );
    }

    /**
     * 检查标题层级
     */
    private function check_heading_structure($post) {
        $content = $post->post_content;

        // 提取所有标题
        preg_match_all('/<h([1-6])[^>]*>(.*?)<\/h\1>/i', $content, $matches);

        if (empty($matches[1])) {
            return array(
                'id' => 'headings_missing',
                'name' => __('标题层级', 'ai-seo-auditor'),
                'status' => 'warning',
                'message' => __('内容缺少小标题，建议添加H2-H6标题', 'ai-seo-auditor'),
                'fixable' => false
            );
        }

        $headings = $matches[1];

        // 检查是否有H1（文章标题已经是H1，内容不应该再有）
        if (in_array('1', $headings)) {
            return array(
                'id' => 'h1_duplicate',
                'name' => __('标题层级', 'ai-seo-auditor'),
                'status' => 'failed',
                'message' => __('内容中包含H1标题，应该使用H2-H6', 'ai-seo-auditor'),
                'fixable' => true,
                'fix_type' => 'fix_h1_tags'
            );
        }

        return array(
            'id' => 'headings_ok',
            'name' => __('标题层级', 'ai-seo-auditor'),
            'status' => 'passed',
            'message' => sprintf(__('标题结构良好（%d个小标题）', 'ai-seo-auditor'), count($headings)),
            'fixable' => false
        );
    }

    /**
     * 检查图片Alt属性
     */
    private function check_image_alt($post) {
        $content = $post->post_content;

        preg_match_all('/<img[^>]+>/i', $content, $images);

        if (empty($images[0])) {
            return array(
                'id' => 'images_none',
                'name' => __('图片优化', 'ai-seo-auditor'),
                'status' => 'info',
                'message' => __('内容不包含图片', 'ai-seo-auditor'),
                'fixable' => false
            );
        }

        $total_images = count($images[0]);
        $images_without_alt = 0;

        foreach ($images[0] as $img) {
            if (!preg_match('/alt=["\'][^"\']*["\']/i', $img)) {
                $images_without_alt++;
            }
        }

        if ($images_without_alt > 0) {
            return array(
                'id' => 'alt_missing',
                'name' => __('图片优化', 'ai-seo-auditor'),
                'status' => 'failed',
                'message' => sprintf(__('%d/%d图片缺少Alt属性', 'ai-seo-auditor'), $images_without_alt, $total_images),
                'fixable' => false
            );
        }

        return array(
            'id' => 'images_ok',
            'name' => __('图片优化', 'ai-seo-auditor'),
            'status' => 'passed',
            'message' => sprintf(__('所有图片都有Alt属性（%d张）', 'ai-seo-auditor'), $total_images),
            'fixable' => false
        );
    }

    /**
     * 检查内部链接
     */
    private function check_internal_links($post) {
        $content = $post->post_content;
        $site_url = get_site_url();

        preg_match_all('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>/i', $content, $links);

        if (empty($links[1])) {
            return array(
                'id' => 'links_none',
                'name' => __('内部链接', 'ai-seo-auditor'),
                'status' => 'warning',
                'message' => __('内容缺少内部链接，建议添加3-5个相关链接', 'ai-seo-auditor'),
                'fixable' => false
            );
        }

        $internal_links = 0;
        foreach ($links[1] as $url) {
            if (strpos($url, $site_url) === 0 || strpos($url, '/') === 0) {
                $internal_links++;
            }
        }

        if ($internal_links === 0) {
            return array(
                'id' => 'internal_links_none',
                'name' => __('内部链接', 'ai-seo-auditor'),
                'status' => 'warning',
                'message' => __('没有内部链接，建议添加相关文章链接', 'ai-seo-auditor'),
                'fixable' => false
            );
        }

        if ($internal_links < 3) {
            return array(
                'id' => 'internal_links_few',
                'name' => __('内部链接', 'ai-seo-auditor'),
                'status' => 'warning',
                'message' => sprintf(__('内部链接较少（%d个），建议增加到3-5个', 'ai-seo-auditor'), $internal_links),
                'fixable' => false
            );
        }

        return array(
            'id' => 'internal_links_ok',
            'name' => __('内部链接', 'ai-seo-auditor'),
            'status' => 'passed',
            'message' => sprintf(__('内部链接良好（%d个）', 'ai-seo-auditor'), $internal_links),
            'fixable' => false
        );
    }

    /**
     * 检查外部链接
     */
    private function check_external_links($post) {
        $content = $post->post_content;
        $site_url = get_site_url();

        preg_match_all('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>/i', $content, $links);

        if (empty($links[1])) {
            return array(
                'id' => 'external_links_ok',
                'name' => __('外部链接', 'ai-seo-auditor'),
                'status' => 'passed',
                'message' => __('无外部链接', 'ai-seo-auditor'),
                'fixable' => false
            );
        }

        $external_links = 0;
        $external_nofollow = 0;

        foreach ($links[0] as $index => $link_html) {
            $url = $links[1][$index];

            // 检查是否是外部链接
            if (strpos($url, 'http') === 0 && strpos($url, $site_url) === false) {
                $external_links++;

                // 检查是否有nofollow
                if (preg_match('/rel=["\'][^"\']*nofollow[^"\']*["\']/i', $link_html)) {
                    $external_nofollow++;
                }
            }
        }

        if ($external_links > 0 && $external_nofollow < $external_links) {
            $without_nofollow = $external_links - $external_nofollow;
            return array(
                'id' => 'external_links_nofollow',
                'name' => __('外部链接', 'ai-seo-auditor'),
                'status' => 'warning',
                'message' => sprintf(__('%d个外部链接缺少nofollow属性', 'ai-seo-auditor'), $without_nofollow),
                'fixable' => true,
                'fix_type' => 'add_nofollow'
            );
        }

        return array(
            'id' => 'external_links_ok',
            'name' => __('外部链接', 'ai-seo-auditor'),
            'status' => 'passed',
            'message' => sprintf(__('外部链接设置正确（%d个）', 'ai-seo-auditor'), $external_links),
            'fixable' => false
        );
    }

    /**
     * 检查关键词密度
     */
    private function check_keyword_density($post) {
        $target_keyword = get_post_meta($post->ID, '_ai_seo_target_keyword', true);

        if (empty($target_keyword)) {
            return array(
                'id' => 'keyword_not_set',
                'name' => __('关键词密度', 'ai-seo-auditor'),
                'status' => 'info',
                'message' => __('未设置目标关键词', 'ai-seo-auditor'),
                'fixable' => false
            );
        }

        $content = wp_strip_all_tags($post->post_content);
        $keyword_count = mb_substr_count(mb_strtolower($content), mb_strtolower($target_keyword));
        $total_words = mb_strlen($content);

        if ($total_words === 0) {
            $density = 0;
        } else {
            $density = ($keyword_count * mb_strlen($target_keyword) / $total_words) * 100;
        }

        if ($density < 0.5) {
            return array(
                'id' => 'keyword_low',
                'name' => __('关键词密度', 'ai-seo-auditor'),
                'status' => 'warning',
                'message' => sprintf(__('关键词密度过低（%.2f%%），建议1-3%%', 'ai-seo-auditor'), $density),
                'fixable' => false
            );
        }

        if ($density > 3) {
            return array(
                'id' => 'keyword_high',
                'name' => __('关键词密度', 'ai-seo-auditor'),
                'status' => 'warning',
                'message' => sprintf(__('关键词密度过高（%.2f%%），可能被视为堆砌', 'ai-seo-auditor'), $density),
                'fixable' => false
            );
        }

        return array(
            'id' => 'keyword_ok',
            'name' => __('关键词密度', 'ai-seo-auditor'),
            'status' => 'passed',
            'message' => sprintf(__('关键词密度适中（%.2f%%）', 'ai-seo-auditor'), $density),
            'fixable' => false
        );
    }

    /**
     * 检查可读性
     */
    private function check_readability($post) {
        $content = wp_strip_all_tags($post->post_content);

        // 简单的句子长度检查
        $sentences = preg_split('/[。！？.!?]+/', $content, -1, PREG_SPLIT_NO_EMPTY);

        if (empty($sentences)) {
            return array(
                'id' => 'readability_unknown',
                'name' => __('可读性', 'ai-seo-auditor'),
                'status' => 'info',
                'message' => __('无法评估可读性', 'ai-seo-auditor'),
                'fixable' => false
            );
        }

        $long_sentences = 0;
        foreach ($sentences as $sentence) {
            if (mb_strlen(trim($sentence)) > 100) {
                $long_sentences++;
            }
        }

        $percentage = ($long_sentences / count($sentences)) * 100;

        if ($percentage > 30) {
            return array(
                'id' => 'readability_poor',
                'name' => __('可读性', 'ai-seo-auditor'),
                'status' => 'warning',
                'message' => sprintf(__('%.0f%%的句子过长（超过100字），建议简化', 'ai-seo-auditor'), $percentage),
                'fixable' => false
            );
        }

        return array(
            'id' => 'readability_ok',
            'name' => __('可读性', 'ai-seo-auditor'),
            'status' => 'passed',
            'message' => __('句子长度适中，可读性良好', 'ai-seo-auditor'),
            'fixable' => false
        );
    }

    /**
     * 检查特色图片
     */
    private function check_featured_image($post) {
        if (!has_post_thumbnail($post->ID)) {
            return array(
                'id' => 'featured_image_missing',
                'name' => __('特色图片', 'ai-seo-auditor'),
                'status' => 'warning',
                'message' => __('未设置特色图片，建议添加', 'ai-seo-auditor'),
                'fixable' => false
            );
        }

        return array(
            'id' => 'featured_image_ok',
            'name' => __('特色图片', 'ai-seo-auditor'),
            'status' => 'passed',
            'message' => __('已设置特色图片', 'ai-seo-auditor'),
            'fixable' => false
        );
    }

    /**
     * 检查分类和标签
     */
    private function check_taxonomies($post) {
        $categories = wp_get_post_categories($post->ID);
        $tags = wp_get_post_tags($post->ID);

        if (empty($categories) && empty($tags)) {
            return array(
                'id' => 'taxonomies_missing',
                'name' => __('分类标签', 'ai-seo-auditor'),
                'status' => 'warning',
                'message' => __('未设置分类和标签，建议添加', 'ai-seo-auditor'),
                'fixable' => false
            );
        }

        if (empty($categories)) {
            return array(
                'id' => 'category_missing',
                'name' => __('分类标签', 'ai-seo-auditor'),
                'status' => 'warning',
                'message' => __('未设置分类，建议添加', 'ai-seo-auditor'),
                'fixable' => false
            );
        }

        if (empty($tags)) {
            return array(
                'id' => 'tags_missing',
                'name' => __('分类标签', 'ai-seo-auditor'),
                'status' => 'warning',
                'message' => sprintf(__('已设置%d个分类，建议添加2-5个标签', 'ai-seo-auditor'), count($categories)),
                'fixable' => false
            );
        }

        return array(
            'id' => 'taxonomies_ok',
            'name' => __('分类标签', 'ai-seo-auditor'),
            'status' => 'passed',
            'message' => sprintf(__('已设置%d个分类和%d个标签', 'ai-seo-auditor'), count($categories), count($tags)),
            'fixable' => false
        );
    }

    /**
     * 应用快速修复
     *
     * @param int $post_id 文章ID
     * @param string $fix_type 修复类型
     * @return array|WP_Error 修复结果
     */
    private function apply_quick_fix($post_id, $fix_type) {
        $post = get_post($post_id);

        if (!$post) {
            return new WP_Error('invalid_post', __('文章不存在', 'ai-seo-auditor'));
        }

        switch ($fix_type) {
            case 'fix_h1_tags':
                return $this->fix_h1_tags($post);

            case 'add_nofollow':
                return $this->add_nofollow_to_external_links($post);

            default:
                return new WP_Error('invalid_fix_type', __('无效的修复类型', 'ai-seo-auditor'));
        }
    }

    /**
     * 修复H1标签
     */
    private function fix_h1_tags($post) {
        $content = $post->post_content;
        $new_content = preg_replace('/<h1([^>]*)>(.*?)<\/h1>/i', '<h2$1>$2</h2>', $content);

        $updated = wp_update_post(array(
            'ID' => $post->ID,
            'post_content' => $new_content
        ), true);

        if (is_wp_error($updated)) {
            return $updated;
        }

        return array(
            'message' => __('已将所有H1标题转换为H2', 'ai-seo-auditor')
        );
    }

    /**
     * 为外部链接添加nofollow
     */
    private function add_nofollow_to_external_links($post) {
        $content = $post->post_content;
        $site_url = get_site_url();

        // 匹配所有链接
        $new_content = preg_replace_callback(
            '/<a([^>]+)href=["\']([^"\']+)["\']([^>]*)>/i',
            function($matches) use ($site_url) {
                $url = $matches[2];

                // 如果是外部链接且没有nofollow
                if (strpos($url, 'http') === 0 && strpos($url, $site_url) === false) {
                    $attrs = $matches[1] . $matches[3];

                    // 检查是否已有rel属性
                    if (preg_match('/rel=["\']([^"\']*)["\']/', $attrs, $rel_matches)) {
                        // 如果没有nofollow，添加
                        if (strpos($rel_matches[1], 'nofollow') === false) {
                            $new_rel = trim($rel_matches[1] . ' nofollow');
                            $attrs = preg_replace('/rel=["\'][^"\']*["\']/', 'rel="' . $new_rel . '"', $attrs);
                        }
                    } else {
                        // 没有rel属性，添加
                        $attrs .= ' rel="nofollow"';
                    }

                    return '<a' . $attrs . ' href="' . $url . '">';
                }

                return $matches[0];
            },
            $content
        );

        $updated = wp_update_post(array(
            'ID' => $post->ID,
            'post_content' => $new_content
        ), true);

        if (is_wp_error($updated)) {
            return $updated;
        }

        return array(
            'message' => __('已为所有外部链接添加nofollow属性', 'ai-seo-auditor')
        );
    }

    /**
     * 渲染检查清单
     *
     * @param int $post_id 文章ID
     */
    public function render_checklist($post_id) {
        ?>
        <div class="ai-seo-checklist-container" style="display: none;">
            <div class="checklist-header">
                <h3><?php _e('SEO检查清单', 'ai-seo-auditor'); ?></h3>
                <button type="button" class="button" id="run-checklist-btn" data-post-id="<?php echo esc_attr($post_id); ?>">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <?php _e('运行检查', 'ai-seo-auditor'); ?>
                </button>
            </div>

            <div class="checklist-loading" style="display: none;">
                <div class="spinner is-active"></div>
                <p><?php _e('正在检查...', 'ai-seo-auditor'); ?></p>
            </div>

            <div class="checklist-results"></div>
        </div>
        <?php
    }
}
