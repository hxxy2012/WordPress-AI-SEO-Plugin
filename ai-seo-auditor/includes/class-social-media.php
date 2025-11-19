<?php
/**
 * 社交媒体优化类
 *
 * @package AI_SEO_Auditor
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AI_SEO_Social_Media类
 *
 * 负责检查Open Graph和Twitter Card标签
 */
class AI_SEO_Social_Media {

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
        // 可以添加Meta Box显示社交媒体预览
    }

    /**
     * 分析文章的社交媒体优化
     *
     * @param int $post_id 文章ID
     * @return array 分析结果
     */
    public function analyze_social_media($post_id) {
        $post = get_post($post_id);

        if (!$post) {
            return new WP_Error('invalid_post', __('文章不存在', 'ai-seo-auditor'));
        }

        $results = array(
            'og_tags' => array(),
            'twitter_tags' => array(),
            'score' => 0,
            'issues' => array(),
            'suggestions' => array()
        );

        // 检查Open Graph标签
        $results['og_tags'] = $this->check_open_graph_tags($post_id);

        // 检查Twitter Card标签
        $results['twitter_tags'] = $this->check_twitter_card_tags($post_id);

        // 计算评分
        $results['score'] = $this->calculate_social_score($results);

        // 生成问题和建议
        $this->generate_issues_and_suggestions($results);

        return $results;
    }

    /**
     * 检查Open Graph标签
     *
     * @param int $post_id 文章ID
     * @return array OG标签信息
     */
    private function check_open_graph_tags($post_id) {
        $post = get_post($post_id);

        $og_tags = array(
            'og:title' => array(
                'value' => '',
                'exists' => false,
                'optimal' => false
            ),
            'og:description' => array(
                'value' => '',
                'exists' => false,
                'optimal' => false
            ),
            'og:image' => array(
                'value' => '',
                'exists' => false,
                'optimal' => false
            ),
            'og:url' => array(
                'value' => '',
                'exists' => false,
                'optimal' => true
            ),
            'og:type' => array(
                'value' => '',
                'exists' => false,
                'optimal' => true
            )
        );

        // 检查Yoast SEO的OG设置
        if (class_exists('WPSEO_Meta')) {
            $og_title = get_post_meta($post_id, '_yoast_wpseo_opengraph-title', true);
            $og_desc = get_post_meta($post_id, '_yoast_wpseo_opengraph-description', true);
            $og_image = get_post_meta($post_id, '_yoast_wpseo_opengraph-image', true);

            if ($og_title) {
                $og_tags['og:title']['value'] = $og_title;
                $og_tags['og:title']['exists'] = true;
                $og_tags['og:title']['optimal'] = strlen($og_title) <= 60;
            }

            if ($og_desc) {
                $og_tags['og:description']['value'] = $og_desc;
                $og_tags['og:description']['exists'] = true;
                $og_tags['og:description']['optimal'] = strlen($og_desc) >= 50 && strlen($og_desc) <= 200;
            }

            if ($og_image) {
                $og_tags['og:image']['value'] = $og_image;
                $og_tags['og:image']['exists'] = true;
                $og_tags['og:image']['optimal'] = $this->check_image_dimensions($og_image, 1200, 630);
            }
        }

        // 如果没有设置OG标签，使用默认值
        if (!$og_tags['og:title']['exists']) {
            $og_tags['og:title']['value'] = $post->post_title;
            $og_tags['og:title']['optimal'] = strlen($post->post_title) <= 60;
        }

        if (!$og_tags['og:description']['exists']) {
            $excerpt = wp_trim_words($post->post_content, 30);
            $og_tags['og:description']['value'] = $excerpt;
            $og_tags['og:description']['optimal'] = strlen($excerpt) >= 50 && strlen($excerpt) <= 200;
        }

        if (!$og_tags['og:image']['exists']) {
            // 尝试获取特色图片
            $thumbnail_id = get_post_thumbnail_id($post_id);
            if ($thumbnail_id) {
                $image_url = wp_get_attachment_url($thumbnail_id);
                $og_tags['og:image']['value'] = $image_url;
                $og_tags['og:image']['exists'] = true;
                $og_tags['og:image']['optimal'] = $this->check_image_dimensions($image_url, 1200, 630);
            }
        }

        $og_tags['og:url']['value'] = get_permalink($post_id);
        $og_tags['og:url']['exists'] = true;

        $og_tags['og:type']['value'] = 'article';
        $og_tags['og:type']['exists'] = true;

        return $og_tags;
    }

    /**
     * 检查Twitter Card标签
     *
     * @param int $post_id 文章ID
     * @return array Twitter Card标签信息
     */
    private function check_twitter_card_tags($post_id) {
        $post = get_post($post_id);

        $twitter_tags = array(
            'twitter:card' => array(
                'value' => '',
                'exists' => false,
                'optimal' => true
            ),
            'twitter:title' => array(
                'value' => '',
                'exists' => false,
                'optimal' => false
            ),
            'twitter:description' => array(
                'value' => '',
                'exists' => false,
                'optimal' => false
            ),
            'twitter:image' => array(
                'value' => '',
                'exists' => false,
                'optimal' => false
            )
        );

        // 检查Yoast SEO的Twitter设置
        if (class_exists('WPSEO_Meta')) {
            $twitter_title = get_post_meta($post_id, '_yoast_wpseo_twitter-title', true);
            $twitter_desc = get_post_meta($post_id, '_yoast_wpseo_twitter-description', true);
            $twitter_image = get_post_meta($post_id, '_yoast_wpseo_twitter-image', true);

            if ($twitter_title) {
                $twitter_tags['twitter:title']['value'] = $twitter_title;
                $twitter_tags['twitter:title']['exists'] = true;
                $twitter_tags['twitter:title']['optimal'] = strlen($twitter_title) <= 70;
            }

            if ($twitter_desc) {
                $twitter_tags['twitter:description']['value'] = $twitter_desc;
                $twitter_tags['twitter:description']['exists'] = true;
                $twitter_tags['twitter:description']['optimal'] = strlen($twitter_desc) <= 200;
            }

            if ($twitter_image) {
                $twitter_tags['twitter:image']['value'] = $twitter_image;
                $twitter_tags['twitter:image']['exists'] = true;
                $twitter_tags['twitter:image']['optimal'] = $this->check_image_dimensions($twitter_image, 1200, 600);
            }
        }

        // 如果没有设置Twitter标签，使用默认值
        if (!$twitter_tags['twitter:title']['exists']) {
            $twitter_tags['twitter:title']['value'] = $post->post_title;
            $twitter_tags['twitter:title']['optimal'] = strlen($post->post_title) <= 70;
        }

        if (!$twitter_tags['twitter:description']['exists']) {
            $excerpt = wp_trim_words($post->post_content, 25);
            $twitter_tags['twitter:description']['value'] = $excerpt;
            $twitter_tags['twitter:description']['optimal'] = strlen($excerpt) <= 200;
        }

        if (!$twitter_tags['twitter:image']['exists']) {
            $thumbnail_id = get_post_thumbnail_id($post_id);
            if ($thumbnail_id) {
                $image_url = wp_get_attachment_url($thumbnail_id);
                $twitter_tags['twitter:image']['value'] = $image_url;
                $twitter_tags['twitter:image']['exists'] = true;
                $twitter_tags['twitter:image']['optimal'] = $this->check_image_dimensions($image_url, 1200, 600);
            }
        }

        $twitter_tags['twitter:card']['value'] = 'summary_large_image';
        $twitter_tags['twitter:card']['exists'] = true;

        return $twitter_tags;
    }

    /**
     * 检查图片尺寸
     *
     * @param string $image_url 图片URL
     * @param int $min_width 最小宽度
     * @param int $min_height 最小高度
     * @return bool 是否符合要求
     */
    private function check_image_dimensions($image_url, $min_width, $min_height) {
        $attachment_id = $this->get_attachment_id_by_url($image_url);

        if (!$attachment_id) {
            return false;
        }

        $metadata = wp_get_attachment_metadata($attachment_id);

        if (!$metadata || !isset($metadata['width']) || !isset($metadata['height'])) {
            return false;
        }

        return $metadata['width'] >= $min_width && $metadata['height'] >= $min_height;
    }

    /**
     * 通过URL获取附件ID
     *
     * @param string $url 图片URL
     * @return int|false 附件ID或false
     */
    private function get_attachment_id_by_url($url) {
        global $wpdb;

        $attachment = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE guid='%s';",
            $url
        ));

        return !empty($attachment) ? $attachment[0] : false;
    }

    /**
     * 计算社交媒体优化评分
     *
     * @param array $results 分析结果
     * @return int 评分
     */
    private function calculate_social_score($results) {
        $score = 0;
        $total_checks = 0;

        // 检查Open Graph标签
        foreach ($results['og_tags'] as $tag => $data) {
            $total_checks++;
            if ($data['exists'] && $data['optimal']) {
                $score += 10;
            } elseif ($data['exists']) {
                $score += 5;
            }
        }

        // 检查Twitter Card标签
        foreach ($results['twitter_tags'] as $tag => $data) {
            $total_checks++;
            if ($data['exists'] && $data['optimal']) {
                $score += 10;
            } elseif ($data['exists']) {
                $score += 5;
            }
        }

        return min(100, round(($score / ($total_checks * 10)) * 100));
    }

    /**
     * 生成问题和建议
     *
     * @param array &$results 分析结果（引用传递）
     */
    private function generate_issues_and_suggestions(&$results) {
        // 检查Open Graph
        if (!$results['og_tags']['og:title']['optimal']) {
            $results['issues'][] = __('Open Graph标题长度不理想（建议60字符以内）', 'ai-seo-auditor');
            $results['suggestions'][] = __('优化OG标题，使其简洁有力且不超过60字符', 'ai-seo-auditor');
        }

        if (!$results['og_tags']['og:description']['optimal']) {
            $results['issues'][] = __('Open Graph描述长度不理想（建议50-200字符）', 'ai-seo-auditor');
            $results['suggestions'][] = __('优化OG描述，确保长度在50-200字符之间', 'ai-seo-auditor');
        }

        if (!$results['og_tags']['og:image']['exists']) {
            $results['issues'][] = __('缺少Open Graph图片', 'ai-seo-auditor');
            $results['suggestions'][] = __('添加特色图片或设置OG图片（建议尺寸1200x630px）', 'ai-seo-auditor');
        } elseif (!$results['og_tags']['og:image']['optimal']) {
            $results['issues'][] = __('Open Graph图片尺寸不理想', 'ai-seo-auditor');
            $results['suggestions'][] = __('使用1200x630px的图片以获得最佳显示效果', 'ai-seo-auditor');
        }

        // 检查Twitter Card
        if (!$results['twitter_tags']['twitter:title']['optimal']) {
            $results['issues'][] = __('Twitter标题长度不理想（建议70字符以内）', 'ai-seo-auditor');
            $results['suggestions'][] = __('优化Twitter标题，使其不超过70字符', 'ai-seo-auditor');
        }

        if (!$results['twitter_tags']['twitter:description']['optimal']) {
            $results['issues'][] = __('Twitter描述长度不理想（建议200字符以内）', 'ai-seo-auditor');
            $results['suggestions'][] = __('优化Twitter描述，确保不超过200字符', 'ai-seo-auditor');
        }

        if (!$results['twitter_tags']['twitter:image']['exists']) {
            $results['issues'][] = __('缺少Twitter Card图片', 'ai-seo-auditor');
            $results['suggestions'][] = __('添加Twitter Card图片（建议尺寸1200x600px）', 'ai-seo-auditor');
        } elseif (!$results['twitter_tags']['twitter:image']['optimal']) {
            $results['issues'][] = __('Twitter Card图片尺寸不理想', 'ai-seo-auditor');
            $results['suggestions'][] = __('使用1200x600px的图片以获得最佳显示效果', 'ai-seo-auditor');
        }

        if (empty($results['issues'])) {
            $results['issues'][] = __('社交媒体优化良好', 'ai-seo-auditor');
        }
    }

    /**
     * 生成社交媒体预览
     *
     * @param int $post_id 文章ID
     * @return array 预览数据
     */
    public function generate_social_preview($post_id) {
        $og_tags = $this->check_open_graph_tags($post_id);
        $twitter_tags = $this->check_twitter_card_tags($post_id);

        return array(
            'facebook' => array(
                'title' => $og_tags['og:title']['value'],
                'description' => $og_tags['og:description']['value'],
                'image' => $og_tags['og:image']['value']
            ),
            'twitter' => array(
                'title' => $twitter_tags['twitter:title']['value'],
                'description' => $twitter_tags['twitter:description']['value'],
                'image' => $twitter_tags['twitter:image']['value']
            )
        );
    }
}
