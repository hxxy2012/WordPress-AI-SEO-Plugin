<?php
/**
 * Claude API处理类
 *
 * @package AI_SEO_Auditor
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AI_SEO_API_Handler类
 *
 * 负责与Claude API进行通信
 */
class AI_SEO_API_Handler {

    /**
     * API URL
     */
    private $api_url = 'https://api.anthropic.com/v1/messages';

    /**
     * API Key
     */
    private $api_key;

    /**
     * API 版本
     */
    private $api_version = '2023-06-01';

    /**
     * Claude模型
     */
    private $model;

    /**
     * 最大重试次数
     */
    private $max_retries = 3;

    /**
     * 超时时间（秒）
     */
    private $timeout = 30;

    /**
     * 构造函数
     */
    public function __construct() {
        $this->api_key = get_option('ai_seo_api_key', '');
        $settings = get_option('ai_seo_settings', array());
        $this->model = isset($settings['model']) ? $settings['model'] : 'claude-sonnet-4-5-20250929';
    }

    /**
     * 分析文章内容
     *
     * @param array $data 文章数据
     * @return array|WP_Error 分析结果或错误
     */
    public function analyze_content($data) {
        // 验证API Key
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('未配置Claude API Key', 'ai-seo-auditor'));
        }

        // 构建提示词
        $prompt = $this->build_prompt($data);

        // 调用API
        $response = $this->call_api($prompt);

        if (is_wp_error($response)) {
            return $response;
        }

        // 解析响应
        $result = $this->parse_response($response);

        return $result;
    }

    /**
     * 构建分析提示词
     *
     * @param array $data 文章数据
     * @return string 提示词
     */
    private function build_prompt($data) {
        $settings = get_option('ai_seo_settings', array());
        $language = isset($settings['language']) ? $settings['language'] : 'zh';

        $title = isset($data['title']) ? $data['title'] : '';
        $content = isset($data['content']) ? $data['content'] : '';
        $meta_description = isset($data['meta_description']) ? $data['meta_description'] : '';
        $target_keyword = isset($data['target_keyword']) ? $data['target_keyword'] : '';
        $url = isset($data['url']) ? $data['url'] : '';

        // 移除HTML标签，保留文本
        $plain_content = wp_strip_all_tags($content);

        // 提取标题标签结构
        $headings = $this->extract_headings($content);

        $prompt = "你是一位专业的SEO专家。请分析以下WordPress文章的SEO质量，并提供详细的优化建议。\n\n";
        $prompt .= "**文章信息：**\n";
        $prompt .= "标题: {$title}\n";
        $prompt .= "URL: {$url}\n";
        $prompt .= "Meta描述: " . ($meta_description ?: '未设置') . "\n";
        $prompt .= "目标关键词: " . ($target_keyword ?: '未设置') . "\n";
        $prompt .= "文章字数: " . mb_strlen($plain_content) . "\n";
        $prompt .= "标题结构: " . implode(', ', $headings) . "\n\n";
        $prompt .= "**文章内容（前1000字）：**\n";
        $prompt .= mb_substr($plain_content, 0, 1000) . "\n\n";

        $prompt .= "**分析要求：**\n";
        $prompt .= "请从以下几个方面分析并给出评分（0-100分）和具体建议：\n\n";

        $prompt .= "1. **标题优化** - 分析标题长度、关键词位置、吸引力\n";
        $prompt .= "   - 评分标准：长度50-60字符为佳，关键词前置，包含数字或情感词\n";
        $prompt .= "   - 提供3-5个优化后的标题建议\n\n";

        $prompt .= "2. **Meta描述优化** - 分析描述长度、关键词、CTA\n";
        $prompt .= "   - 评分标准：长度150-160字符，包含关键词和行动号召\n";
        $prompt .= "   - 提供1-3个优化后的Meta描述建议\n\n";

        $prompt .= "3. **关键词使用** - 分析关键词密度和分布\n";
        $prompt .= "   - 评分标准：密度1-3%，分布自然不堆砌\n";
        $prompt .= "   - 指出关键词使用问题\n\n";

        $prompt .= "4. **内容质量** - 分析内容结构、段落、字数\n";
        $prompt .= "   - 评分标准：至少300字，段落简短，标题层次清晰\n";
        $prompt .= "   - 提供内容改进建议\n\n";

        $prompt .= "5. **可读性评分** - 分析句子长度、词汇难度\n";
        $prompt .= "   - 评分标准：句子简短，避免复杂词汇和被动语态\n";
        $prompt .= "   - 给出可读性等级（1-10分）\n\n";

        $prompt .= "6. **技术SEO** - 分析URL结构、图片优化\n";
        $prompt .= "   - 评分标准：URL简短包含关键词，图片有alt标签\n\n";

        $prompt .= "**输出格式（必须返回严格的JSON格式）：**\n";
        $prompt .= "```json\n";
        $prompt .= "{\n";
        $prompt .= '  "overall_score": 75,' . "\n";
        $prompt .= '  "title": {' . "\n";
        $prompt .= '    "score": 80,' . "\n";
        $prompt .= '    "current_length": 45,' . "\n";
        $prompt .= '    "issues": ["标题较短，建议增加到50-60字符"],' . "\n";
        $prompt .= '    "suggestions": ["建议标题1", "建议标题2", "建议标题3"]' . "\n";
        $prompt .= '  },' . "\n";
        $prompt .= '  "meta_description": {' . "\n";
        $prompt .= '    "score": 60,' . "\n";
        $prompt .= '    "current_length": 0,' . "\n";
        $prompt .= '    "issues": ["未设置Meta描述"],' . "\n";
        $prompt .= '    "suggestions": ["建议描述1", "建议描述2"]' . "\n";
        $prompt .= '  },' . "\n";
        $prompt .= '  "keyword": {' . "\n";
        $prompt .= '    "score": 70,' . "\n";
        $prompt .= '    "density": 2.5,' . "\n";
        $prompt .= '    "issues": ["关键词分布不均匀"],' . "\n";
        $prompt .= '    "suggestions": ["在第一段增加关键词", "在小标题中使用关键词"]' . "\n";
        $prompt .= '  },' . "\n";
        $prompt .= '  "content": {' . "\n";
        $prompt .= '    "score": 85,' . "\n";
        $prompt .= '    "word_count": 800,' . "\n";
        $prompt .= '    "issues": ["部分段落过长"],' . "\n";
        $prompt .= '    "suggestions": ["将长段落拆分为多个短段落", "增加小标题"]' . "\n";
        $prompt .= '  },' . "\n";
        $prompt .= '  "readability": {' . "\n";
        $prompt .= '    "score": 90,' . "\n";
        $prompt .= '    "level": 8,' . "\n";
        $prompt .= '    "issues": [],' . "\n";
        $prompt .= '    "suggestions": ["保持当前的可读性水平"]' . "\n";
        $prompt .= '  },' . "\n";
        $prompt .= '  "technical": {' . "\n";
        $prompt .= '    "score": 75,' . "\n";
        $prompt .= '    "url_length": 65,' . "\n";
        $prompt .= '    "issues": ["URL较长"],' . "\n";
        $prompt .= '    "suggestions": ["缩短URL长度", "确保图片有alt属性"]' . "\n";
        $prompt .= '  }' . "\n";
        $prompt .= "}\n";
        $prompt .= "```\n\n";
        $prompt .= "请只返回JSON数据，不要包含其他说明文字。";

        return $prompt;
    }

    /**
     * 提取标题标签
     *
     * @param string $content HTML内容
     * @return array 标题列表
     */
    private function extract_headings($content) {
        $headings = array();

        // 匹配H1-H6标签
        preg_match_all('/<h([1-6])[^>]*>(.*?)<\/h\1>/i', $content, $matches);

        if (!empty($matches[1])) {
            foreach ($matches[1] as $index => $level) {
                $text = wp_strip_all_tags($matches[2][$index]);
                $headings[] = "H{$level}: {$text}";
            }
        }

        return $headings;
    }

    /**
     * 调用Claude API
     *
     * @param string $prompt 提示词
     * @return array|WP_Error API响应或错误
     */
    private function call_api($prompt, $retry_count = 0) {
        $headers = array(
            'Content-Type' => 'application/json',
            'x-api-key' => $this->api_key,
            'anthropic-version' => $this->api_version
        );

        $body = array(
            'model' => $this->model,
            'max_tokens' => 4096,
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            )
        );

        $args = array(
            'headers' => $headers,
            'body' => wp_json_encode($body),
            'timeout' => $this->timeout,
            'method' => 'POST'
        );

        $response = wp_remote_post($this->api_url, $args);

        // 检查HTTP错误
        if (is_wp_error($response)) {
            // 如果是网络错误且未达到最大重试次数，则重试
            if ($retry_count < $this->max_retries) {
                sleep(pow(2, $retry_count)); // 指数退避
                return $this->call_api($prompt, $retry_count + 1);
            }
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);

        // 处理不同的响应码
        if ($response_code !== 200) {
            $error_body = wp_remote_retrieve_body($response);
            $error_data = json_decode($error_body, true);

            $error_message = isset($error_data['error']['message'])
                ? $error_data['error']['message']
                : __('API调用失败', 'ai-seo-auditor');

            // 对于429（限流）或500系列错误，可以重试
            if (($response_code === 429 || $response_code >= 500) && $retry_count < $this->max_retries) {
                sleep(pow(2, $retry_count));
                return $this->call_api($prompt, $retry_count + 1);
            }

            return new WP_Error(
                'api_error',
                sprintf(__('API错误 (%d): %s', 'ai-seo-auditor'), $response_code, $error_message)
            );
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_error', __('API响应解析失败', 'ai-seo-auditor'));
        }

        return $data;
    }

    /**
     * 解析API响应
     *
     * @param array $response API响应
     * @return array|WP_Error 解析后的结果或错误
     */
    private function parse_response($response) {
        if (!isset($response['content'][0]['text'])) {
            return new WP_Error('invalid_response', __('API响应格式无效', 'ai-seo-auditor'));
        }

        $text = $response['content'][0]['text'];

        // 提取JSON部分
        if (preg_match('/```json\s*(.*?)\s*```/s', $text, $matches)) {
            $json_str = $matches[1];
        } else {
            // 如果没有代码块，尝试直接解析
            $json_str = $text;
        }

        $result = json_decode($json_str, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // 记录原始响应用于调试
            error_log('AI SEO Auditor - JSON解析失败: ' . $text);
            return new WP_Error(
                'parse_error',
                __('无法解析分析结果，请重试', 'ai-seo-auditor'),
                array('raw_response' => $text)
            );
        }

        // 验证必要的字段
        if (!isset($result['overall_score'])) {
            return new WP_Error('invalid_result', __('分析结果缺少必要字段', 'ai-seo-auditor'));
        }

        return $result;
    }

    /**
     * 测试API连接
     *
     * @return array|WP_Error 测试结果
     */
    public function test_connection() {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('请先配置API Key', 'ai-seo-auditor'));
        }

        $test_prompt = "请回复：API连接成功";

        $response = $this->call_api($test_prompt);

        if (is_wp_error($response)) {
            return $response;
        }

        return array(
            'success' => true,
            'message' => __('API连接测试成功', 'ai-seo-auditor')
        );
    }
}
