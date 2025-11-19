<?php
/**
 * 图片SEO检查类
 *
 * @package AI_SEO_Auditor
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AI_SEO_Image_SEO类
 *
 * 负责检查图片的SEO优化情况
 */
class AI_SEO_Image_SEO {

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
        // 可以添加钩子来自动检查上传的图片
    }

    /**
     * 分析文章中的图片SEO
     *
     * @param int $post_id 文章ID
     * @return array 分析结果
     */
    public function analyze_post_images($post_id) {
        $post = get_post($post_id);

        if (!$post) {
            return new WP_Error('invalid_post', __('文章不存在', 'ai-seo-auditor'));
        }

        $content = $post->post_content;

        // 提取所有图片
        $images = $this->extract_images_from_content($content);

        $results = array(
            'total_images' => count($images),
            'images_with_alt' => 0,
            'images_without_alt' => 0,
            'oversized_images' => 0,
            'images' => array(),
            'score' => 0,
            'issues' => array(),
            'suggestions' => array()
        );

        foreach ($images as $image) {
            $image_data = $this->analyze_image($image);
            $results['images'][] = $image_data;

            if (!empty($image_data['alt'])) {
                $results['images_with_alt']++;
            } else {
                $results['images_without_alt']++;
            }

            if (isset($image_data['file_size']) && $image_data['file_size'] > 500000) {
                $results['oversized_images']++;
            }
        }

        // 计算评分
        $results['score'] = $this->calculate_image_score($results);

        // 生成问题和建议
        $this->generate_issues_and_suggestions($results);

        return $results;
    }

    /**
     * 从内容中提取图片
     *
     * @param string $content HTML内容
     * @return array 图片数组
     */
    private function extract_images_from_content($content) {
        $images = array();

        preg_match_all('/<img[^>]+>/i', $content, $matches);

        if (empty($matches[0])) {
            return $images;
        }

        foreach ($matches[0] as $img_tag) {
            $image = array();

            // 提取src
            if (preg_match('/src=["\']([^"\']+)["\']/i', $img_tag, $src_match)) {
                $image['src'] = $src_match[1];
            }

            // 提取alt
            if (preg_match('/alt=["\']([^"\']*)["\']/', $img_tag, $alt_match)) {
                $image['alt'] = $alt_match[1];
            } else {
                $image['alt'] = '';
            }

            // 提取title
            if (preg_match('/title=["\']([^"\']*)["\']/i', $img_tag, $title_match)) {
                $image['title'] = $title_match[1];
            } else {
                $image['title'] = '';
            }

            // 提取width和height
            if (preg_match('/width=["\']([^"\']+)["\']/i', $img_tag, $width_match)) {
                $image['width'] = $width_match[1];
            }

            if (preg_match('/height=["\']([^"\']+)["\']/i', $img_tag, $height_match)) {
                $image['height'] = $height_match[1];
            }

            $images[] = $image;
        }

        return $images;
    }

    /**
     * 分析单个图片
     *
     * @param array $image 图片数据
     * @return array 分析结果
     */
    private function analyze_image($image) {
        $result = $image;
        $result['issues'] = array();

        // 检查alt标签
        if (empty($image['alt'])) {
            $result['issues'][] = __('缺少alt属性', 'ai-seo-auditor');
        } elseif (strlen($image['alt']) > 125) {
            $result['issues'][] = __('alt文本过长（建议不超过125字符）', 'ai-seo-auditor');
        }

        // 检查是否是本地图片
        if (isset($image['src'])) {
            $upload_dir = wp_upload_dir();

            if (strpos($image['src'], $upload_dir['baseurl']) !== false) {
                // 是本地图片，获取更多信息
                $attachment_id = $this->get_attachment_id_by_url($image['src']);

                if ($attachment_id) {
                    $metadata = wp_get_attachment_metadata($attachment_id);

                    if ($metadata) {
                        $result['attachment_id'] = $attachment_id;
                        $result['file_size'] = isset($metadata['filesize']) ? $metadata['filesize'] : filesize(get_attached_file($attachment_id));
                        $result['dimensions'] = array(
                            'width' => isset($metadata['width']) ? $metadata['width'] : null,
                            'height' => isset($metadata['height']) ? $metadata['height'] : null
                        );

                        // 检查文件大小
                        if ($result['file_size'] > 500000) {
                            $result['issues'][] = sprintf(
                                __('图片过大（%s），建议压缩到500KB以下', 'ai-seo-auditor'),
                                size_format($result['file_size'])
                            );
                        }

                        // 检查图片格式
                        $mime_type = get_post_mime_type($attachment_id);
                        $result['mime_type'] = $mime_type;

                        if ($mime_type === 'image/bmp') {
                            $result['issues'][] = __('建议使用WebP、JPEG或PNG格式', 'ai-seo-auditor');
                        }
                    }
                }
            }
        }

        return $result;
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
     * 计算图片SEO评分
     *
     * @param array $results 分析结果
     * @return int 评分
     */
    private function calculate_image_score($results) {
        if ($results['total_images'] === 0) {
            return 100; // 没有图片，给满分
        }

        $score = 100;

        // alt标签缺失扣分
        $alt_ratio = $results['images_with_alt'] / $results['total_images'];
        if ($alt_ratio < 1) {
            $score -= (1 - $alt_ratio) * 40; // 最多扣40分
        }

        // 过大图片扣分
        if ($results['oversized_images'] > 0) {
            $oversize_ratio = $results['oversized_images'] / $results['total_images'];
            $score -= $oversize_ratio * 30; // 最多扣30分
        }

        return max(0, round($score));
    }

    /**
     * 生成问题和建议
     *
     * @param array &$results 分析结果（引用传递）
     */
    private function generate_issues_and_suggestions(&$results) {
        if ($results['images_without_alt'] > 0) {
            $results['issues'][] = sprintf(
                __('%d张图片缺少alt属性', 'ai-seo-auditor'),
                $results['images_without_alt']
            );
            $results['suggestions'][] = __('为所有图片添加描述性的alt文本，有助于SEO和无障碍访问', 'ai-seo-auditor');
        }

        if ($results['oversized_images'] > 0) {
            $results['issues'][] = sprintf(
                __('%d张图片文件过大', 'ai-seo-auditor'),
                $results['oversized_images']
            );
            $results['suggestions'][] = __('压缩图片以提升页面加载速度，建议使用WebP格式', 'ai-seo-auditor');
        }

        if ($results['total_images'] === 0) {
            $results['suggestions'][] = __('考虑添加相关图片，可以提升用户体验和SEO效果', 'ai-seo-auditor');
        } elseif ($results['total_images'] > 20) {
            $results['suggestions'][] = __('图片数量较多，确保都已优化以避免影响加载速度', 'ai-seo-auditor');
        }

        if (empty($results['issues'])) {
            $results['issues'][] = __('图片SEO优化良好', 'ai-seo-auditor');
        }
    }

    /**
     * 批量优化图片alt标签
     *
     * @param int $post_id 文章ID
     * @param array $alt_mapping alt标签映射
     * @return bool 是否成功
     */
    public function batch_update_alt_tags($post_id, $alt_mapping) {
        $post = get_post($post_id);

        if (!$post) {
            return false;
        }

        $content = $post->post_content;

        foreach ($alt_mapping as $src => $new_alt) {
            // 替换alt标签
            $content = preg_replace(
                '/(<img[^>]*src=["\']' . preg_quote($src, '/') . '["\'][^>]*alt=["\'])[^"\']*(["\'])/i',
                '${1}' . esc_attr($new_alt) . '${2}',
                $content
            );

            // 如果没有alt标签，添加一个
            $content = preg_replace(
                '/(<img[^>]*src=["\']' . preg_quote($src, '/') . '["\'])([^>]*>)/i',
                '${1} alt="' . esc_attr($new_alt) . '"${2}',
                $content
            );
        }

        // 更新文章内容
        return wp_update_post(array(
            'ID' => $post_id,
            'post_content' => $content
        )) !== 0;
    }
}
