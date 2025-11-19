/**
 * AI SEO Auditor - 检查清单脚本
 */

(function($) {
    'use strict';

    const SeoChecklist = {
        isRunning: false,

        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            // 运行检查按钮
            $(document).on('click', '#run-checklist-btn', this.runChecklist.bind(this));

            // 快速修复按钮
            $(document).on('click', '.quick-fix-btn', this.applyQuickFix.bind(this));

            // 显示检查清单
            $(document).on('click', '#show-checklist-btn', this.showChecklist.bind(this));
        },

        showChecklist: function(e) {
            e.preventDefault();
            $('.ai-seo-checklist-container').slideDown();
            $('#show-checklist-btn').hide();
        },

        runChecklist: function(e) {
            e.preventDefault();

            if (this.isRunning) {
                return;
            }

            const postId = $(e.currentTarget).data('post-id');

            if (!postId) {
                alert('无效的文章ID');
                return;
            }

            this.isRunning = true;
            this.showLoading();
            this.clearResults();

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ai_seo_run_checklist',
                    nonce: aiSeoData.nonce,
                    post_id: postId
                },
                success: this.handleChecklistSuccess.bind(this),
                error: this.handleChecklistError.bind(this),
                complete: this.hideLoading.bind(this)
            });
        },

        handleChecklistSuccess: function(response) {
            if (response.success) {
                this.renderChecklist(response.data);
            } else {
                alert(response.data.message || '检查失败');
            }
        },

        handleChecklistError: function(xhr, status, error) {
            alert('检查失败: ' + error);
        },

        showLoading: function() {
            $('.checklist-loading').show();
        },

        hideLoading: function() {
            $('.checklist-loading').hide();
            this.isRunning = false;
        },

        clearResults: function() {
            $('.checklist-results').empty();
        },

        renderChecklist: function(data) {
            const $container = $('.checklist-results');

            // 渲染摘要
            const summaryHtml = this.renderSummary(data.summary);
            $container.append(summaryHtml);

            // 渲染检查项
            const itemsHtml = this.renderItems(data.items);
            $container.append(itemsHtml);
        },

        renderSummary: function(summary) {
            const percentage = summary.percentage;
            const scoreClass = this.getScoreClass(percentage);

            return `
                <div class="checklist-summary">
                    <div class="summary-score ${scoreClass}">
                        <div class="score-circle">
                            <div class="score-value">${percentage}%</div>
                        </div>
                        <div class="score-label">通过率</div>
                    </div>
                    <div class="summary-stats">
                        <div class="stat-item">
                            <span class="stat-label">总计:</span>
                            <span class="stat-value">${summary.total}</span>
                        </div>
                        <div class="stat-item passed">
                            <span class="stat-label">通过:</span>
                            <span class="stat-value">${summary.passed}</span>
                        </div>
                        <div class="stat-item failed">
                            <span class="stat-label">失败:</span>
                            <span class="stat-value">${summary.failed}</span>
                        </div>
                    </div>
                </div>
            `;
        },

        renderItems: function(items) {
            let html = '<div class="checklist-items">';

            items.forEach((item, index) => {
                html += this.renderItem(item, index);
            });

            html += '</div>';
            return html;
        },

        renderItem: function(item, index) {
            const statusIcon = this.getStatusIcon(item.status);
            const statusClass = item.status;
            const fixButton = item.fixable ? this.renderFixButton(item) : '';

            return `
                <div class="checklist-item checklist-item-${statusClass}" data-index="${index}">
                    <div class="item-icon">
                        <span class="dashicons ${statusIcon}"></span>
                    </div>
                    <div class="item-content">
                        <div class="item-name">${this.escapeHtml(item.name)}</div>
                        <div class="item-message">${this.escapeHtml(item.message)}</div>
                    </div>
                    <div class="item-actions">
                        ${fixButton}
                    </div>
                </div>
            `;
        },

        renderFixButton: function(item) {
            return `
                <button type="button"
                        class="button button-small quick-fix-btn"
                        data-fix-type="${item.fix_type}"
                        data-item-id="${item.id}">
                    <span class="dashicons dashicons-admin-tools"></span>
                    快速修复
                </button>
            `;
        },

        applyQuickFix: function(e) {
            e.preventDefault();

            const $button = $(e.currentTarget);
            const fixType = $button.data('fix-type');
            const postId = $('#run-checklist-btn').data('post-id');

            if (!confirm('确定要应用此修复吗？此操作将修改文章内容。')) {
                return;
            }

            const originalText = $button.html();
            $button.html('<span class="dashicons dashicons-update spin"></span> 修复中...').prop('disabled', true);

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ai_seo_quick_fix',
                    nonce: aiSeoData.nonce,
                    post_id: postId,
                    fix_type: fixType
                },
                success: (response) => {
                    if (response.success) {
                        alert(response.data.message || '修复成功');
                        // 重新运行检查
                        $('#run-checklist-btn').trigger('click');
                    } else {
                        alert(response.data.message || '修复失败');
                        $button.html(originalText).prop('disabled', false);
                    }
                },
                error: (xhr, status, error) => {
                    alert('修复失败: ' + error);
                    $button.html(originalText).prop('disabled', false);
                }
            });
        },

        getScoreClass: function(score) {
            if (score >= 80) return 'score-excellent';
            if (score >= 60) return 'score-good';
            if (score >= 40) return 'score-fair';
            return 'score-poor';
        },

        getStatusIcon: function(status) {
            const icons = {
                'passed': 'dashicons-yes-alt',
                'failed': 'dashicons-dismiss',
                'warning': 'dashicons-warning',
                'info': 'dashicons-info'
            };

            return icons[status] || 'dashicons-minus';
        },

        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    // 页面加载完成后初始化
    $(document).ready(function() {
        if ($('#run-checklist-btn').length || $('.ai-seo-checklist-container').length) {
            SeoChecklist.init();
        }
    });

})(jQuery);
