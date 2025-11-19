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

        $prompt = "你是一位资深的SEO专家和内容营销顾问，拥有超过10年的搜索引擎优化经验。请对以下WordPress文章进行全面、深入的SEO质量分析，并提供可执行的优化建议。\n\n";

        $prompt .= "=== 文章基本信息 ===\n";
        $prompt .= "标题: {$title}\n";
        $prompt .= "URL: {$url}\n";
        $prompt .= "Meta描述: " . ($meta_description ?: '[未设置]') . "\n";
        $prompt .= "目标关键词: " . ($target_keyword ?: '[未指定]') . "\n";
        $prompt .= "内容字数: " . mb_strlen($plain_content) . " 字\n";
        $prompt .= "标题层级: " . (empty($headings) ? '[无标题层级]' : implode(', ', $headings)) . "\n\n";

        $prompt .= "=== 文章内容预览（前1000字）===\n";
        $prompt .= mb_substr($plain_content, 0, 1000) . "\n\n";

        $prompt .= "=== SEO分析要求 ===\n";
        $prompt .= "请从以下6个核心维度进行专业分析，每个维度都要给出0-100分的评分，并提供具体可行的优化建议：\n\n";

        $prompt .= "1. 【标题优化 Title Optimization】\n";
        $prompt .= "   评分要素：\n";
        $prompt .= "   - 长度：50-60个字符为最佳（中文约25-30字）\n";
        $prompt .= "   - 关键词位置：主关键词应出现在标题前半部分\n";
        $prompt .= "   - 吸引力：使用数字、问句、权威词、情感词提升点击率\n";
        $prompt .= "   - 唯一性：避免与竞品标题雷同\n";
        $prompt .= "   输出要求：\n";
        $prompt .= "   - current_length: 当前标题字符数\n";
        $prompt .= "   - issues: 列出具体问题（如：标题过短、缺少关键词等）\n";
        $prompt .= "   - suggestions: 提供3-5个优化后的完整标题方案\n\n";

        $prompt .= "2. 【Meta描述优化 Meta Description】\n";
        $prompt .= "   评分要素：\n";
        $prompt .= "   - 长度：150-160个字符（中文约70-80字）\n";
        $prompt .= "   - 关键词：自然融入主关键词和相关词\n";
        $prompt .= "   - 行动号召：包含明确的CTA（如：立即了解、点击查看）\n";
        $prompt .= "   - 价值主张：清晰说明用户能获得什么\n";
        $prompt .= "   输出要求：\n";
        $prompt .= "   - current_length: 当前描述字符数（0表示未设置）\n";
        $prompt .= "   - issues: 列出问题（如：未设置、过短、无CTA）\n";
        $prompt .= "   - suggestions: 提供2-3个完整的Meta描述方案\n\n";

        $prompt .= "3. 【关键词策略 Keyword Strategy】\n";
        $prompt .= "   评分要素：\n";
        $prompt .= "   - 密度：1-3%为最佳（自然出现，不堆砌）\n";
        $prompt .= "   - 分布：在标题、首段、小标题、正文、结尾均衡出现\n";
        $prompt .= "   - 语义相关：使用LSI关键词和同义词\n";
        $prompt .= "   - 长尾词：包含2-3个长尾关键词变体\n";
        $prompt .= "   输出要求：\n";
        $prompt .= "   - density: 计算得出的关键词密度百分比\n";
        $prompt .= "   - issues: 指出问题（如：密度过低、分布不均、堆砌）\n";
        $prompt .= "   - suggestions: 如何优化关键词使用\n\n";

        $prompt .= "4. 【内容质量 Content Quality】\n";
        $prompt .= "   评分要素：\n";
        $prompt .= "   - 字数：至少300字，深度内容推荐1000-2000字\n";
        $prompt .= "   - 结构：使用H2-H6建立清晰层级，每200-300字一个小标题\n";
        $prompt .= "   - 段落：每段3-5句话，避免大段文字\n";
        $prompt .= "   - 价值：提供独特见解、数据、案例或实用建议\n";
        $prompt .= "   - 原创性：避免重复和陈词滥调\n";
        $prompt .= "   输出要求：\n";
        $prompt .= "   - word_count: 实际字数\n";
        $prompt .= "   - issues: 内容问题（如：字数不足、结构混乱、段落过长）\n";
        $prompt .= "   - suggestions: 内容改进方向\n\n";

        $prompt .= "5. 【可读性 Readability】\n";
        $prompt .= "   评分要素：\n";
        $prompt .= "   - 句子长度：平均15-20字，避免超过30字的长句\n";
        $prompt .= "   - 词汇难度：使用简单直接的表达，少用专业术语（或加以解释）\n";
        $prompt .= "   - 语态：主动语态优于被动语态\n";
        $prompt .= "   - 过渡：使用连接词使内容流畅\n";
        $prompt .= "   - 格式：使用列表、粗体、引用等提升可读性\n";
        $prompt .= "   输出要求：\n";
        $prompt .= "   - level: 可读性等级（1-10，10为最易读）\n";
        $prompt .= "   - issues: 可读性问题\n";
        $prompt .= "   - suggestions: 如何提升可读性\n\n";

        $prompt .= "6. 【技术SEO Technical SEO】\n";
        $prompt .= "   评分要素：\n";
        $prompt .= "   - URL结构：简短（<60字符）、包含关键词、使用连字符\n";
        $prompt .= "   - 标题层级：正确使用H1-H6（H1唯一，H2-H6有序）\n";
        $prompt .= "   - 内部链接：适当链接到相关内容（3-5个）\n";
        $prompt .= "   - 多媒体：图片、视频等丰富内容\n";
        $prompt .= "   输出要求：\n";
        $prompt .= "   - url_length: URL字符数\n";
        $prompt .= "   - issues: 技术问题\n";
        $prompt .= "   - suggestions: 技术优化建议\n\n";

        $prompt .= "=== 输出格式要求 ===\n";
        $prompt .= "必须严格按照以下JSON格式返回分析结果，不要添加任何额外的说明文字或markdown标记：\n\n";
        $prompt .= "```json\n";
        $prompt .= "{\n";
        $prompt .= '  "overall_score": 75,' . "\n";
        $prompt .= '  "title": {' . "\n";
        $prompt .= '    "score": 80,' . "\n";
        $prompt .= '    "current_length": 45,' . "\n";
        $prompt .= '    "issues": ["标题长度为45字符，建议扩展到50-60字符", "关键词未出现在标题前半部分", "缺少数字或情感词吸引点击"],' . "\n";
        $prompt .= '    "suggestions": ["【优化示例1】完整的标题文案", "【优化示例2】另一个标题方案", "【优化示例3】第三个标题建议"]' . "\n";
        $prompt .= '  },' . "\n";
        $prompt .= '  "meta_description": {' . "\n";
        $prompt .= '    "score": 60,' . "\n";
        $prompt .= '    "current_length": 0,' . "\n";
        $prompt .= '    "issues": ["未设置Meta描述，搜索结果将随机显示文章内容", "缺少明确的价值主张和行动号召"],' . "\n";
        $prompt .= '    "suggestions": ["【建议1】包含关键词、价值点和CTA的完整描述文案", "【建议2】另一个Meta描述方案"]' . "\n";
        $prompt .= '  },' . "\n";
        $prompt .= '  "keyword": {' . "\n";
        $prompt .= '    "score": 70,' . "\n";
        $prompt .= '    "density": 2.5,' . "\n";
        $prompt .= '    "issues": ["关键词密度2.5%略高，建议控制在1-3%", "关键词在文章后半部分出现较少，分布不够均衡", "缺少LSI相关关键词和同义词"],' . "\n";
        $prompt .= '    "suggestions": ["在第一段自然融入1-2次主关键词", "在H2/H3小标题中使用关键词变体", "增加2-3个语义相关的LSI关键词", "在结尾段落呼应关键词"]' . "\n";
        $prompt .= '  },' . "\n";
        $prompt .= '  "content": {' . "\n";
        $prompt .= '    "score": 85,' . "\n";
        $prompt .= '    "word_count": 800,' . "\n";
        $prompt .= '    "issues": ["第3段和第5段字数超过200字，建议拆分", "标题层级从H2直接跳到H4，缺少H3", "缺少具体数据和案例支撑观点"],' . "\n";
        $prompt .= '    "suggestions": ["将长段落拆分为多个短段落，每段3-5句话", "补充H3标题完善内容层级", "增加1-2个具体案例或数据佐证", "每200-300字添加一个小标题"]' . "\n";
        $prompt .= '  },' . "\n";
        $prompt .= '  "readability": {' . "\n";
        $prompt .= '    "score": 90,' . "\n";
        $prompt .= '    "level": 8,' . "\n";
        $prompt .= '    "issues": ["第2段有2个超过30字的长句", "专业术语\'XXX\'未做解释"],' . "\n";
        $prompt .= '    "suggestions": ["将长句拆分为短句，每句控制在20字以内", "对专业术语添加简单解释或举例", "增加过渡词使段落间衔接更流畅", "使用列表或表格呈现复杂信息"]' . "\n";
        $prompt .= '  },' . "\n";
        $prompt .= '  "technical": {' . "\n";
        $prompt .= '    "score": 75,' . "\n";
        $prompt .= '    "url_length": 65,' . "\n";
        $prompt .= '    "issues": ["URL长度65字符略长，建议控制在60字符以内", "H1标题出现2次，应该保持唯一", "缺少内部链接指向相关文章"],' . "\n";
        $prompt .= '    "suggestions": ["简化URL，移除不必要的词汇", "确保页面只有一个H1标题", "添加3-5个内部链接指向相关内容", "考虑添加目录导航提升用户体验"]' . "\n";
        $prompt .= '  }' . "\n";
        $prompt .= "}\n";
        $prompt .= "```\n\n";
        $prompt .= "=== 重要提醒 ===\n";
        $prompt .= "1. 必须返回完整的JSON格式数据\n";
        $prompt .= "2. 所有字段都必须填写，不要省略\n";
        $prompt .= "3. issues和suggestions数组至少包含1条内容\n";
        $prompt .= "4. 评分要客观公正，基于实际分析结果\n";
        $prompt .= "5. 建议要具体可执行，避免空泛的表述\n";
        $prompt .= "6. 不要在JSON外添加任何解释性文字\n\n";
        $prompt .= "现在开始分析，只返回JSON数据：";

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
