<?php
/**
 * 设置导入导出类
 *
 * @package AI_SEO_Auditor
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AI_SEO_Settings_Import_Export类
 *
 * 负责插件设置的导入和导出
 */
class AI_SEO_Settings_Import_Export {

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
        // 添加到设置页面
        add_action('ai_seo_settings_page_after', array($this, 'render_import_export_section'));

        // AJAX处理
        add_action('wp_ajax_ai_seo_export_settings', array($this, 'ajax_export_settings'));
        add_action('wp_ajax_ai_seo_import_settings', array($this, 'ajax_import_settings'));
    }

    /**
     * 渲染导入导出区域
     */
    public function render_import_export_section() {
        ?>
        <div class="wrap">
            <h2><?php _e('设置导入/导出', 'ai-seo-auditor'); ?></h2>

            <div class="ai-seo-import-export-container">
                <div class="import-export-grid">
                    <!-- 导出设置 -->
                    <div class="export-section">
                        <h3>
                            <span class="dashicons dashicons-download"></span>
                            <?php _e('导出设置', 'ai-seo-auditor'); ?>
                        </h3>
                        <p><?php _e('导出当前插件配置为JSON文件，可在其他WordPress站点导入。', 'ai-seo-auditor'); ?></p>

                        <div class="export-options">
                            <label>
                                <input type="checkbox" id="export-api-key" checked>
                                <?php _e('包含API密钥（敏感）', 'ai-seo-auditor'); ?>
                            </label>
                            <label>
                                <input type="checkbox" id="export-settings" checked>
                                <?php _e('包含插件设置', 'ai-seo-auditor'); ?>
                            </label>
                            <label>
                                <input type="checkbox" id="export-scheduler" checked>
                                <?php _e('包含定时任务配置', 'ai-seo-auditor'); ?>
                            </label>
                        </div>

                        <button type="button" id="export-settings-btn" class="button button-primary button-large">
                            <span class="dashicons dashicons-download"></span>
                            <?php _e('导出设置', 'ai-seo-auditor'); ?>
                        </button>
                    </div>

                    <!-- 导入设置 -->
                    <div class="import-section">
                        <h3>
                            <span class="dashicons dashicons-upload"></span>
                            <?php _e('导入设置', 'ai-seo-auditor'); ?>
                        </h3>
                        <p><?php _e('从JSON文件导入插件配置。将覆盖当前设置。', 'ai-seo-auditor'); ?></p>

                        <div class="import-file-area">
                            <input type="file" id="import-file" accept=".json" style="display: none;">
                            <div class="file-drop-zone" id="file-drop-zone">
                                <span class="dashicons dashicons-cloud-upload"></span>
                                <p><?php _e('点击选择文件或拖拽到此处', 'ai-seo-auditor'); ?></p>
                                <p class="file-name"></p>
                            </div>
                        </div>

                        <div class="import-preview" id="import-preview" style="display: none;">
                            <h4><?php _e('导入预览', 'ai-seo-auditor'); ?></h4>
                            <div class="preview-content"></div>
                        </div>

                        <button type="button" id="import-settings-btn" class="button button-primary button-large" disabled>
                            <span class="dashicons dashicons-upload"></span>
                            <?php _e('导入设置', 'ai-seo-auditor'); ?>
                        </button>
                    </div>
                </div>

                <!-- 提示信息 -->
                <div class="import-export-notice">
                    <h4><?php _e('注意事项', 'ai-seo-auditor'); ?></h4>
                    <ul>
                        <li><?php _e('导出的JSON文件包含所有插件配置，请妥善保管', 'ai-seo-auditor'); ?></li>
                        <li><?php _e('如果包含API密钥，请勿分享给他人', 'ai-seo-auditor'); ?></li>
                        <li><?php _e('导入设置将完全覆盖当前配置，建议先导出备份', 'ai-seo-auditor'); ?></li>
                        <li><?php _e('导入后建议检查所有设置是否正确', 'ai-seo-auditor'); ?></li>
                    </ul>
                </div>
            </div>
        </div>

        <style>
        .ai-seo-import-export-container {
            background: #fff;
            padding: 20px;
            margin: 20px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .import-export-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .export-section,
        .import-section {
            padding: 20px;
            background: #f9f9f9;
            border-radius: 4px;
        }

        .export-section h3,
        .import-section h3 {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 0;
            font-size: 18px;
            color: #333;
        }

        .export-section h3 .dashicons,
        .import-section h3 .dashicons {
            color: #0073aa;
        }

        .export-options {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin: 20px 0;
        }

        .export-options label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

        .file-drop-zone {
            border: 2px dashed #ddd;
            border-radius: 4px;
            padding: 40px 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }

        .file-drop-zone:hover {
            border-color: #0073aa;
            background: #f5f5f5;
        }

        .file-drop-zone.dragover {
            border-color: #0073aa;
            background: #e5f5fa;
        }

        .file-drop-zone .dashicons {
            font-size: 48px;
            width: 48px;
            height: 48px;
            color: #999;
        }

        .file-drop-zone p {
            margin: 10px 0 0;
            color: #666;
        }

        .file-drop-zone .file-name {
            font-weight: 600;
            color: #0073aa;
        }

        .import-preview {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            margin: 15px 0;
            max-height: 200px;
            overflow-y: auto;
        }

        .import-preview h4 {
            margin-top: 0;
        }

        .preview-content {
            font-size: 13px;
            line-height: 1.6;
        }

        .preview-item {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .preview-item:last-child {
            border-bottom: none;
        }

        .preview-label {
            font-weight: 600;
            color: #333;
        }

        .preview-value {
            color: #666;
        }

        .import-export-notice {
            background: #fff9e6;
            border-left: 4px solid #ffb900;
            padding: 15px 20px;
            border-radius: 4px;
        }

        .import-export-notice h4 {
            margin-top: 0;
            color: #333;
        }

        .import-export-notice ul {
            margin: 10px 0 0 20px;
        }

        .import-export-notice li {
            margin: 5px 0;
            color: #666;
        }

        @media screen and (max-width: 782px) {
            .import-export-grid {
                grid-template-columns: 1fr;
            }
        }
        </style>

        <script>
        jQuery(document).ready(function($) {
            // 导出设置
            $('#export-settings-btn').on('click', function(e) {
                e.preventDefault();

                const $button = $(this);
                const originalText = $button.html();
                $button.html('<span class="dashicons dashicons-update spin"></span> 导出中...').prop('disabled', true);

                const options = {
                    include_api_key: $('#export-api-key').is(':checked'),
                    include_settings: $('#export-settings').is(':checked'),
                    include_scheduler: $('#export-scheduler').is(':checked')
                };

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'ai_seo_export_settings',
                        nonce: '<?php echo wp_create_nonce('ai_seo_import_export'); ?>',
                        options: options
                    },
                    success: function(response) {
                        if (response.success) {
                            // 创建下载链接
                            const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(response.data, null, 2));
                            const downloadAnchorNode = document.createElement('a');
                            downloadAnchorNode.setAttribute("href", dataStr);
                            downloadAnchorNode.setAttribute("download", "ai-seo-settings-" + Date.now() + ".json");
                            document.body.appendChild(downloadAnchorNode);
                            downloadAnchorNode.click();
                            downloadAnchorNode.remove();
                        } else {
                            alert(response.data.message || '导出失败');
                        }
                    },
                    error: function(xhr, status, error) {
                        alert('导出失败: ' + error);
                    },
                    complete: function() {
                        $button.html(originalText).prop('disabled', false);
                    }
                });
            });

            // 文件选择
            $('#file-drop-zone').on('click', function() {
                $('#import-file').click();
            });

            // 文件拖拽
            $('#file-drop-zone')
                .on('dragover', function(e) {
                    e.preventDefault();
                    $(this).addClass('dragover');
                })
                .on('dragleave', function(e) {
                    e.preventDefault();
                    $(this).removeClass('dragover');
                })
                .on('drop', function(e) {
                    e.preventDefault();
                    $(this).removeClass('dragover');

                    const files = e.originalEvent.dataTransfer.files;
                    if (files.length > 0) {
                        handleFile(files[0]);
                    }
                });

            // 文件输入change事件
            $('#import-file').on('change', function(e) {
                const files = e.target.files;
                if (files.length > 0) {
                    handleFile(files[0]);
                }
            });

            // 处理文件
            function handleFile(file) {
                if (file.type !== 'application/json') {
                    alert('请选择JSON文件');
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(e) {
                    try {
                        const data = JSON.parse(e.target.result);
                        displayPreview(data);
                        $('#file-drop-zone .file-name').text(file.name);
                        $('#import-settings-btn').prop('disabled', false);

                        // 存储数据
                        $('#import-settings-btn').data('import-data', data);
                    } catch (error) {
                        alert('JSON文件格式错误: ' + error.message);
                    }
                };
                reader.readAsText(file);
            }

            // 显示预览
            function displayPreview(data) {
                let html = '';

                if (data.api_key) {
                    html += '<div class="preview-item"><span class="preview-label">API密钥:</span><span class="preview-value">***已包含***</span></div>';
                }

                if (data.settings) {
                    html += '<div class="preview-item"><span class="preview-label">插件设置:</span><span class="preview-value">' + Object.keys(data.settings).length + ' 项</span></div>';
                }

                if (data.scheduler_settings) {
                    html += '<div class="preview-item"><span class="preview-label">定时任务:</span><span class="preview-value">已包含</span></div>';
                }

                html += '<div class="preview-item"><span class="preview-label">导出时间:</span><span class="preview-value">' + (data.export_date || '未知') + '</span></div>';
                html += '<div class="preview-item"><span class="preview-label">版本:</span><span class="preview-value">' + (data.version || '未知') + '</span></div>';

                $('#import-preview .preview-content').html(html);
                $('#import-preview').slideDown();
            }

            // 导入设置
            $('#import-settings-btn').on('click', function(e) {
                e.preventDefault();

                if (!confirm('确定要导入设置吗？这将覆盖当前所有配置！')) {
                    return;
                }

                const $button = $(this);
                const originalText = $button.html();
                const importData = $button.data('import-data');

                if (!importData) {
                    alert('没有可导入的数据');
                    return;
                }

                $button.html('<span class="dashicons dashicons-update spin"></span> 导入中...').prop('disabled', true);

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'ai_seo_import_settings',
                        nonce: '<?php echo wp_create_nonce('ai_seo_import_export'); ?>',
                        data: JSON.stringify(importData)
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(response.data.message || '导入成功！页面将刷新。');
                            location.reload();
                        } else {
                            alert(response.data.message || '导入失败');
                            $button.html(originalText).prop('disabled', false);
                        }
                    },
                    error: function(xhr, status, error) {
                        alert('导入失败: ' + error);
                        $button.html(originalText).prop('disabled', false);
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * AJAX导出设置
     */
    public function ajax_export_settings() {
        check_ajax_referer('ai_seo_import_export', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('权限不足', 'ai-seo-auditor')));
        }

        $options = isset($_POST['options']) ? $_POST['options'] : array();

        $export_data = array(
            'version' => AI_SEO_VERSION,
            'export_date' => current_time('mysql'),
            'site_url' => get_site_url()
        );

        // 包含API密钥
        if (!empty($options['include_api_key'])) {
            $export_data['api_key'] = get_option('ai_seo_api_key', '');
        }

        // 包含插件设置
        if (!empty($options['include_settings'])) {
            $export_data['settings'] = get_option('ai_seo_settings', array());
        }

        // 包含定时任务配置
        if (!empty($options['include_scheduler'])) {
            $export_data['scheduler_settings'] = array(
                'enabled' => get_option('ai_seo_scheduler_enabled', false),
                'frequency' => get_option('ai_seo_scheduler_frequency', 'daily'),
                'email_reports' => get_option('ai_seo_email_reports', false),
                'report_email' => get_option('ai_seo_report_email', '')
            );
        }

        wp_send_json_success($export_data);
    }

    /**
     * AJAX导入设置
     */
    public function ajax_import_settings() {
        check_ajax_referer('ai_seo_import_export', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('权限不足', 'ai-seo-auditor')));
        }

        $data_json = isset($_POST['data']) ? stripslashes($_POST['data']) : '';

        if (empty($data_json)) {
            wp_send_json_error(array('message' => __('没有数据', 'ai-seo-auditor')));
        }

        $data = json_decode($data_json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(array('message' => __('JSON格式错误', 'ai-seo-auditor')));
        }

        // 导入API密钥
        if (isset($data['api_key'])) {
            update_option('ai_seo_api_key', sanitize_text_field($data['api_key']));
        }

        // 导入插件设置
        if (isset($data['settings']) && is_array($data['settings'])) {
            update_option('ai_seo_settings', $data['settings']);
        }

        // 导入定时任务配置
        if (isset($data['scheduler_settings']) && is_array($data['scheduler_settings'])) {
            $scheduler = $data['scheduler_settings'];

            if (isset($scheduler['enabled'])) {
                update_option('ai_seo_scheduler_enabled', (bool) $scheduler['enabled']);
            }

            if (isset($scheduler['frequency'])) {
                update_option('ai_seo_scheduler_frequency', sanitize_text_field($scheduler['frequency']));
            }

            if (isset($scheduler['email_reports'])) {
                update_option('ai_seo_email_reports', (bool) $scheduler['email_reports']);
            }

            if (isset($scheduler['report_email'])) {
                update_option('ai_seo_report_email', sanitize_email($scheduler['report_email']));
            }
        }

        wp_send_json_success(array(
            'message' => __('设置导入成功', 'ai-seo-auditor')
        ));
    }
}
