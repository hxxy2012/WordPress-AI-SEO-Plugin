/**
 * AI SEO Auditor - 后台管理脚本
 */

(function($) {
    'use strict';

    /**
     * 设置页面功能
     */
    const SettingsPage = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            // API测试按钮
            $('#ai-seo-test-api-btn').on('click', this.testApiConnection);
        },

        testApiConnection: function(e) {
            e.preventDefault();

            const $button = $(this);
            const $result = $('#ai-seo-test-result');
            const apiKey = $('#ai_seo_api_key').val();

            if (!apiKey) {
                $result.removeClass('success').addClass('error').text('请先输入API Key');
                return;
            }

            $button.prop('disabled', true).text('测试中...');
            $result.text('');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ai_seo_test_api',
                    nonce: aiSeoData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $result.removeClass('error').addClass('success')
                            .text('✓ ' + response.data.message);
                    } else {
                        $result.removeClass('success').addClass('error')
                            .text('✗ ' + response.data.message);
                    }
                },
                error: function() {
                    $result.removeClass('success').addClass('error')
                        .text('✗ 连接失败，请检查网络');
                },
                complete: function() {
                    $button.prop('disabled', false).text('测试API连接');
                }
            });
        }
    };

    /**
     * 批量审计页面功能
     */
    const BatchAuditPage = {
        currentIndex: 0,
        totalCount: 0,
        successCount: 0,
        failedCount: 0,
        postIds: [],

        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            // 筛选类型切换
            $('input[name="filter_type"]').on('change', this.toggleFilters);

            // 提交表单
            $('#ai-seo-batch-form').on('submit', this.startBatchAudit.bind(this));
        },

        toggleFilters: function() {
            const filterType = $(this).val();

            $('.category-filter, .tag-filter').hide();

            if (filterType === 'category') {
                $('.category-filter').show();
            } else if (filterType === 'tag') {
                $('.tag-filter').show();
            }
        },

        startBatchAudit: function(e) {
            e.preventDefault();

            const formData = $('#ai-seo-batch-form').serialize();

            // 重置计数
            this.currentIndex = 0;
            this.successCount = 0;
            this.failedCount = 0;

            // 隐藏表单，显示进度
            $('.ai-seo-batch-filters').hide();
            $('.ai-seo-batch-progress').show();
            $('.progress-log').empty();

            // 发送AJAX请求获取文章列表
            this.getPostsToAudit(formData);
        },

        getPostsToAudit: function(formData) {
            const self = this;

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData + '&action=ai_seo_batch_analyze',
                success: function(response) {
                    if (response.success) {
                        const results = response.data;
                        self.totalCount = results.total;
                        self.successCount = results.success;
                        self.failedCount = results.failed;

                        self.updateProgress(100);
                        self.showResults(results);
                    } else {
                        self.showError(response.data.message);
                    }
                },
                error: function() {
                    self.showError('批量审计失败，请重试');
                }
            });
        },

        updateProgress: function(percent) {
            $('.progress-bar-fill').css('width', percent + '%');
            $('.progress-text').text(Math.round(percent) + '%');
            $('.total-count').text(this.totalCount);
            $('.success-count').text(this.successCount);
            $('.failed-count').text(this.failedCount);
        },

        addLog: function(message, type) {
            const $log = $('<p></p>')
                .addClass(type)
                .text('[' + new Date().toLocaleTimeString() + '] ' + message);

            $('.progress-log').prepend($log);
        },

        showResults: function(results) {
            const summary = `
                <p><strong>审计完成！</strong></p>
                <p>总计: ${results.total} 篇文章</p>
                <p>成功: ${results.success} 篇</p>
                <p>失败: ${results.failed} 篇</p>
            `;

            $('.results-summary').html(summary);
            $('.ai-seo-batch-results').show();

            this.addLog('批量审计完成', 'success');

            // 显示错误详情
            if (results.errors && Object.keys(results.errors).length > 0) {
                for (const postId in results.errors) {
                    this.addLog(`文章 #${postId}: ${results.errors[postId]}`, 'error');
                }
            }
        },

        showError: function(message) {
            this.addLog(message, 'error');
            alert(message);
            $('.ai-seo-batch-filters').show();
            $('.ai-seo-batch-progress').hide();
        }
    };

    /**
     * 页面加载完成后初始化
     */
    $(document).ready(function() {
        // 设置页面
        if ($('#ai-seo-test-api-btn').length) {
            SettingsPage.init();
        }

        // 批量审计页面
        if ($('#ai-seo-batch-form').length) {
            BatchAuditPage.init();
        }
    });

})(jQuery);
