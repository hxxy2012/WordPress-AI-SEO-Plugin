<?php
/**
 * SEO分析器类
 *
 * @package AI_SEO_Auditor
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AI_SEO_Analyzer类
 *
 * 负责执行SEO分析和提供优化建议
 */
class AI_SEO_Analyzer {

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
    }

    /**
     * 分析文章
     *
     * @param int $post_id 文章ID
     * @return array|WP_Error 分析结果或错误
     */
    public function analyze_post($post_id) {
        $post = get_post($post_id);

        if (!$post) {
            return new WP_Error('invalid_post', __('文章不存在', 'ai-seo-auditor'));
        }

        // 准备分析数据
        $data = $this->prepare_post_data($post);

        // 调用API分析
        $result = $this->api_handler->analyze_content($data);

        if (is_wp_error($result)) {
            return $result;
        }

        // 添加图片SEO分析
        $image_seo = AI_SEO_Image_SEO::get_instance();
        $image_analysis = $image_seo->analyze_post_images($post_id);
        if (!is_wp_error($image_analysis)) {
            $result['images'] = $image_analysis;
        }

        // 添加社交媒体优化分析
        $social_media = AI_SEO_Social_Media::get_instance();
        $social_analysis = $social_media->analyze_social_media($post_id);
        if (!is_wp_error($social_analysis)) {
            $result['social_media'] = $social_analysis;
        }

        // 添加外链分析
        $result['external_links'] = $this->analyze_external_links($post->post_content);

        // 重新计算总分（考虑新增的维度）
        $result['overall_score'] = $this->recalculate_overall_score($result);

        // 保存分析结果
        $this->save_audit_results($post_id, $result);

        // 保存历史记录
        $this->save_audit_history($post_id, $result);

        return $result;
    }

    /**
     * 准备文章数据
     *
     * @param WP_Post $post 文章对象
     * @return array 文章数据
     */
    private function prepare_post_data($post) {
        // 获取文章内容
        $content = apply_filters('the_content', $post->post_content);

        // 获取Meta描述
        $meta_description = get_post_meta($post->ID, '_yoast_wpseo_metadesc', true);
        if (empty($meta_description)) {
            $meta_description = get_post_meta($post->ID, '_aioseop_description', true);
        }

        // 获取目标关键词
        $target_keyword = get_post_meta($post->ID, '_ai_seo_target_keyword', true);

        // 获取URL
        $url = get_permalink($post->ID);

        return array(
            'title' => $post->post_title,
            'content' => $content,
            'meta_description' => $meta_description,
            'target_keyword' => $target_keyword,
            'url' => $url
        );
    }

    /**
     * 保存审计结果
     *
     * @param int $post_id 文章ID
     * @param array $results 分析结果
     */
    private function save_audit_results($post_id, $results) {
        // 保存整体评分
        update_post_meta($post_id, '_seo_audit_score', intval($results['overall_score']));

        // 保存完整结果
        update_post_meta($post_id, '_seo_audit_results', wp_json_encode($results));

        // 保存时间戳
        update_post_meta($post_id, '_seo_audit_timestamp', current_time('mysql'));
    }

    /**
     * 保存审计历史
     *
     * @param int $post_id 文章ID
     * @param array $results 分析结果
     */
    private function save_audit_history($post_id, $results) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ai_seo_audit_history';

        $wpdb->insert(
            $table_name,
            array(
                'post_id' => $post_id,
                'overall_score' => intval($results['overall_score']),
                'results' => wp_json_encode($results),
                'audit_date' => current_time('mysql')
            ),
            array('%d', '%d', '%s', '%s')
        );
    }

    /**
     * 获取审计结果
     *
     * @param int $post_id 文章ID
     * @return array|null 分析结果或null
     */
    public function get_audit_results($post_id) {
        $results_json = get_post_meta($post_id, '_seo_audit_results', true);

        if (empty($results_json)) {
            return null;
        }

        $results = json_decode($results_json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        // 添加时间戳
        $results['timestamp'] = get_post_meta($post_id, '_seo_audit_timestamp', true);

        return $results;
    }

    /**
     * 获取审计历史
     *
     * @param int $post_id 文章ID
     * @param int $limit 限制数量
     * @return array 历史记录
     */
    public function get_audit_history($post_id, $limit = 10) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ai_seo_audit_history';

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE post_id = %d ORDER BY audit_date DESC LIMIT %d",
                $post_id,
                $limit
            )
        );

        return $results;
    }

    /**
     * 获取内链建议
     *
     * @param int $post_id 文章ID
     * @param int $limit 建议数量
     * @return array 相关文章列表
     */
    public function get_internal_link_suggestions($post_id, $limit = 5) {
        $post = get_post($post_id);

        if (!$post) {
            return array();
        }

        // 获取文章分类和标签
        $categories = wp_get_post_categories($post_id);
        $tags = wp_get_post_tags($post_id, array('fields' => 'ids'));

        // 查询相关文章
        $args = array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'post__not_in' => array($post_id),
            'orderby' => 'relevance',
            'tax_query' => array(
                'relation' => 'OR',
            )
        );

        // 添加分类查询
        if (!empty($categories)) {
            $args['tax_query'][] = array(
                'taxonomy' => 'category',
                'field' => 'term_id',
                'terms' => $categories
            );
        }

        // 添加标签查询
        if (!empty($tags)) {
            $args['tax_query'][] = array(
                'taxonomy' => 'post_tag',
                'field' => 'term_id',
                'terms' => $tags
            );
        }

        $related_posts = get_posts($args);

        $suggestions = array();

        foreach ($related_posts as $related_post) {
            $suggestions[] = array(
                'id' => $related_post->ID,
                'title' => $related_post->post_title,
                'url' => get_permalink($related_post->ID),
                'excerpt' => wp_trim_words($related_post->post_content, 20),
                'score' => $this->calculate_relevance_score($post, $related_post)
            );
        }

        // 按相关性评分排序
        usort($suggestions, function($a, $b) {
            return $b['score'] - $a['score'];
        });

        return $suggestions;
    }

    /**
     * 计算文章相关性评分
     *
     * @param WP_Post $post1 文章1
     * @param WP_Post $post2 文章2
     * @return int 相关性评分
     */
    private function calculate_relevance_score($post1, $post2) {
        $score = 0;

        // 检查共同分类
        $cat1 = wp_get_post_categories($post1->ID);
        $cat2 = wp_get_post_categories($post2->ID);
        $common_cats = array_intersect($cat1, $cat2);
        $score += count($common_cats) * 10;

        // 检查共同标签
        $tags1 = wp_get_post_tags($post1->ID, array('fields' => 'ids'));
        $tags2 = wp_get_post_tags($post2->ID, array('fields' => 'ids'));
        $common_tags = array_intersect($tags1, $tags2);
        $score += count($common_tags) * 5;

        return $score;
    }

    /**
     * 计算关键词密度
     *
     * @param string $content 内容
     * @param string $keyword 关键词
     * @return float 密度百分比
     */
    public function calculate_keyword_density($content, $keyword) {
        if (empty($keyword)) {
            return 0;
        }

        $content = wp_strip_all_tags($content);
        $content = strtolower($content);
        $keyword = strtolower($keyword);

        // 计算总词数
        $words = preg_split('/\s+/', $content);
        $total_words = count($words);

        if ($total_words === 0) {
            return 0;
        }

        // 计算关键词出现次数
        $keyword_count = substr_count($content, $keyword);

        // 计算密度
        $density = ($keyword_count / $total_words) * 100;

        return round($density, 2);
    }

    /**
     * 检查标题结构
     *
     * @param string $content HTML内容
     * @return array 标题结构信息
     */
    public function check_heading_structure($content) {
        $structure = array(
            'h1_count' => 0,
            'h2_count' => 0,
            'h3_count' => 0,
            'h4_count' => 0,
            'h5_count' => 0,
            'h6_count' => 0,
            'issues' => array(),
            'headings' => array()
        );

        // 匹配所有标题
        preg_match_all('/<h([1-6])[^>]*>(.*?)<\/h\1>/i', $content, $matches);

        if (empty($matches[1])) {
            $structure['issues'][] = __('未发现任何标题标签', 'ai-seo-auditor');
            return $structure;
        }

        foreach ($matches[1] as $index => $level) {
            $key = "h{$level}_count";
            $structure[$key]++;

            $text = wp_strip_all_tags($matches[2][$index]);
            $structure['headings'][] = array(
                'level' => intval($level),
                'text' => $text
            );
        }

        // 检查问题
        if ($structure['h1_count'] === 0) {
            $structure['issues'][] = __('缺少H1标题', 'ai-seo-auditor');
        } elseif ($structure['h1_count'] > 1) {
            $structure['issues'][] = __('存在多个H1标题，建议只使用一个', 'ai-seo-auditor');
        }

        if ($structure['h2_count'] === 0) {
            $structure['issues'][] = __('建议添加H2标题以改善内容结构', 'ai-seo-auditor');
        }

        return $structure;
    }

    /**
     * 获取需要优化的文章列表
     *
     * @param int $threshold 评分阈值
     * @param int $limit 限制数量
     * @return array 文章列表
     */
    public function get_posts_need_optimization($threshold = 60, $limit = 10) {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT p.ID, p.post_title, pm.meta_value as score
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_status = 'publish'
            AND p.post_type = 'post'
            AND pm.meta_key = '_seo_audit_score'
            AND CAST(pm.meta_value AS UNSIGNED) < %d
            ORDER BY CAST(pm.meta_value AS UNSIGNED) ASC
            LIMIT %d",
            $threshold,
            $limit
        );

        $results = $wpdb->get_results($query);

        return $results;
    }

    /**
     * 获取网站平均SEO评分
     *
     * @return float 平均评分
     */
    public function get_average_seo_score() {
        global $wpdb;

        $query = "SELECT AVG(CAST(pm.meta_value AS UNSIGNED)) as avg_score
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_status = 'publish'
                AND p.post_type = 'post'
                AND pm.meta_key = '_seo_audit_score'";

        $result = $wpdb->get_var($query);

        return $result ? round($result, 1) : 0;
    }

    /**
     * 批量分析文章
     *
     * @param array $post_ids 文章ID数组
     * @return array 分析结果统计
     */
    public function batch_analyze($post_ids) {
        $results = array(
            'total' => count($post_ids),
            'success' => 0,
            'failed' => 0,
            'errors' => array()
        );

        foreach ($post_ids as $post_id) {
            $result = $this->analyze_post($post_id);

            if (is_wp_error($result)) {
                $results['failed']++;
                $results['errors'][$post_id] = $result->get_error_message();
            } else {
                $results['success']++;
            }

            // 防止API限流，添加延迟
            sleep(1);
        }

        return $results;
    }

    /**
     * 分析外链质量
     *
     * @param string $content HTML内容
     * @return array 外链分析结果
     */
    private function analyze_external_links($content) {
        $analysis = array(
            'total_links' => 0,
            'external_links' => 0,
            'nofollow_links' => 0,
            'dofollow_links' => 0,
            'broken_links' => array(),
            'score' => 100,
            'issues' => array(),
            'suggestions' => array()
        );

        // 提取所有链接
        preg_match_all('/<a\s+[^>]*href=["\'](.*?)["\'][^>]*>(.*?)<\/a>/i', $content, $matches);

        if (empty($matches[1])) {
            $analysis['suggestions'][] = __('考虑添加相关的外部链接以提供更多参考资源', 'ai-seo-auditor');
            return $analysis;
        }

        $site_url = get_site_url();
        $analysis['total_links'] = count($matches[1]);

        foreach ($matches[0] as $index => $full_tag) {
            $url = $matches[1][$index];

            // 检查是否是外链
            if (strpos($url, $site_url) === false && strpos($url, 'http') === 0) {
                $analysis['external_links']++;

                // 检查nofollow
                if (strpos($full_tag, 'nofollow') !== false) {
                    $analysis['nofollow_links']++;
                } else {
                    $analysis['dofollow_links']++;
                }
            }
        }

        // 生成问题和建议
        if ($analysis['external_links'] > 10) {
            $analysis['issues'][] = sprintf(
                __('外链数量较多（%d个），可能影响页面权重', 'ai-seo-auditor'),
                $analysis['external_links']
            );
            $analysis['score'] -= 10;
        }

        if ($analysis['dofollow_links'] > $analysis['nofollow_links'] && $analysis['external_links'] > 5) {
            $analysis['issues'][] = __('建议为部分外链添加nofollow属性', 'ai-seo-auditor');
            $analysis['suggestions'][] = __('对不重要的外链使用rel="nofollow"以保护页面权重', 'ai-seo-auditor');
            $analysis['score'] -= 5;
        }

        if (empty($analysis['issues'])) {
            $analysis['suggestions'][] = __('外链使用合理', 'ai-seo-auditor');
        }

        $analysis['score'] = max(0, $analysis['score']);

        return $analysis;
    }

    /**
     * 重新计算总分
     *
     * @param array $result 分析结果
     * @return int 总分
     */
    private function recalculate_overall_score($result) {
        $scores = array();
        $weights = array(
            'title' => 0.15,
            'meta_description' => 0.10,
            'keyword' => 0.15,
            'content' => 0.20,
            'readability' => 0.15,
            'technical' => 0.10,
            'images' => 0.08,
            'social_media' => 0.05,
            'external_links' => 0.02
        );

        foreach ($weights as $key => $weight) {
            if (isset($result[$key]['score'])) {
                $scores[$key] = $result[$key]['score'] * $weight;
            }
        }

        // 如果某些维度不存在，重新分配权重
        $total_weight = array_sum(array_intersect_key($weights, $scores));

        if ($total_weight > 0) {
            $weighted_score = array_sum($scores) / $total_weight * 100;
            return round($weighted_score);
        }

        // 如果没有任何评分数据，返回原始overall_score
        return isset($result['overall_score']) ? $result['overall_score'] : 0;
    }
}
