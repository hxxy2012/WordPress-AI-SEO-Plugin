/**
 * AI SEO Auditor - Meta Box脚本
 */

(function($) {
    'use strict';

    const AiSeoMetaBox = {
        isAnalyzing: false,

        init: function() {
            this.bindEvents();
            this.checkApiKey();
        },

        bindEvents: function() {
            // 开始分析按钮
            $('#ai-seo-analyze-btn').on('click', this.startAnalysis.bind(this));

            // 刷新按钮
            $('#ai-seo-refresh-btn').on('click', this.refreshAnalysis.bind(this));

            // 应用标题建议
            $(document).on('click', '.apply-title-btn', this.applyTitleSuggestion);

            // 应用Meta描述建议
            $(document).on('click', '.apply-meta-btn', this.applyMetaSuggestion);
        },

        checkApiKey: function() {
            // 检查是否配置了API Key（可以通过AJAX检查，这里简化处理）
        },

        startAnalysis: function(e) {
            e.preventDefault();

            if (this.isAnalyzing) {
                return;
            }

            const postId = $('#ai-seo-analyze-btn').data('post-id');

            if (!postId) {
                this.showError('无效的文章ID');
                return;
            }

            // 检查文章是否有标题和内容
            const title = $('#title').val();
            const content = this.getEditorContent();

            if (!title) {
                this.showError('请先输入文章标题');
                return;
            }

            if (!content || content.length < 10) {
                this.showError('请先添加文章内容');
                return;
            }

            this.isAnalyzing = true;
            this.showLoading();
            this.hideError();

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ai_seo_analyze',
                    post_id: postId,
                    nonce: aiSeoData.nonce
                },
                success: this.handleAnalysisSuccess.bind(this),
                error: this.handleAnalysisError.bind(this),
                complete: this.handleAnalysisComplete.bind(this)
            });
        },

        refreshAnalysis: function(e) {
            e.preventDefault();
            this.startAnalysis(e);
        },

        getEditorContent: function() {
            // 获取编辑器内容
            if (typeof tinymce !== 'undefined' && tinymce.get('content')) {
                return tinymce.get('content').getContent();
            } else if ($('#content').length) {
                return $('#content').val();
            }
            return '';
        },

        showLoading: function() {
            $('.ai-seo-loading').show();
            $('.ai-seo-results').hide();
            $('#ai-seo-analyze-btn').prop('disabled', true).text('分析中...');
        },

        hideLoading: function() {
            $('.ai-seo-loading').hide();
            $('#ai-seo-analyze-btn').prop('disabled', false).text('开始 SEO 审计');
        },

        showError: function(message) {
            $('.ai-seo-error .error-message').text(message);
            $('.ai-seo-error').show();
        },

        hideError: function() {
            $('.ai-seo-error').hide();
        },

        handleAnalysisSuccess: function(response) {
            if (response.success) {
                this.renderResults(response.data.results);
                this.showSuccessNotice(response.data.message);
            } else {
                this.showError(response.data.message || '分析失败，请重试');
            }
        },

        handleAnalysisError: function(xhr, status, error) {
            let errorMessage = '分析失败，请重试';

            if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                errorMessage = xhr.responseJSON.data.message;
            } else if (status === 'timeout') {
                errorMessage = '请求超时，请检查网络连接';
            }

            this.showError(errorMessage);
        },

        handleAnalysisComplete: function() {
            this.isAnalyzing = false;
            this.hideLoading();
        },

        renderResults: function(results) {
            const html = this.buildResultsHtml(results);
            $('.ai-seo-results').html(html).show();
            $('#ai-seo-refresh-btn').show();
        },

        buildResultsHtml: function(results) {
            const overallScore = results.overall_score || 0;
            const scoreClass = this.getScoreClass(overallScore);
            const scoreLabel = this.getScoreLabel(overallScore);

            let html = `
                <div class="ai-seo-overall-score">
                    <div class="score-circle ${scoreClass}">
                        <span class="score-number">${overallScore}</span>
                        <span class="score-total">/100</span>
                    </div>
                    <div class="score-info">
                        <h3>SEO 总评分</h3>
                        <p class="score-label">${scoreLabel}</p>
                        <p class="score-time">刚刚更新</p>
                    </div>
                </div>
                <div class="ai-seo-details">
            `;

            const sections = {
                'title': '标题优化',
                'meta_description': 'Meta 描述',
                'keyword': '关键词使用',
                'content': '内容质量',
                'readability': '可读性',
                'technical': '技术 SEO'
            };

            for (const key in sections) {
                if (results[key]) {
                    html += this.buildSectionCard(key, sections[key], results[key]);
                }
            }

            html += '</div>';

            return html;
        },

        buildSectionCard: function(key, label, data) {
            const score = data.score || 0;
            const scoreClass = this.getScoreClass(score);
            const icon = this.getScoreIcon(score);
            const issues = data.issues || [];
            const suggestions = data.suggestions || [];

            let html = `
                <div class="ai-seo-card">
                    <div class="card-header">
                        <h4>
                            <span class="dashicons ${icon}"></span>
                            ${label}
                        </h4>
                        <span class="card-score ${scoreClass}">${score}/100</span>
                    </div>
                    <div class="card-body">
            `;

            // 问题列表
            if (issues.length > 0) {
                html += '<div class="card-issues"><strong>发现的问题:</strong><ul>';
                issues.forEach(issue => {
                    html += `<li>${this.escapeHtml(issue)}</li>`;
                });
                html += '</ul></div>';
            }

            // 建议列表
            if (suggestions.length > 0) {
                html += '<div class="card-suggestions"><strong>优化建议:</strong>';

                if (key === 'title') {
                    html += '<div class="title-suggestions">';
                    suggestions.forEach((suggestion, index) => {
                        html += `
                            <div class="suggestion-item">
                                <label>
                                    <input type="radio" name="suggested_title" value="${this.escapeHtml(suggestion)}">
                                    <span>${this.escapeHtml(suggestion)}</span>
                                </label>
                            </div>
                        `;
                    });
                    html += '<button type="button" class="button button-small apply-title-btn">应用选中的标题</button>';
                    html += '</div>';
                } else if (key === 'meta_description') {
                    html += '<div class="meta-suggestions">';
                    suggestions.forEach((suggestion, index) => {
                        html += `
                            <div class="suggestion-item">
                                <label>
                                    <input type="radio" name="suggested_meta" value="${this.escapeHtml(suggestion)}">
                                    <span>${this.escapeHtml(suggestion)}</span>
                                </label>
                            </div>
                        `;
                    });
                    html += '<button type="button" class="button button-small apply-meta-btn">应用选中的描述</button>';
                    html += '</div>';
                } else {
                    html += '<ul>';
                    suggestions.forEach(suggestion => {
                        html += `<li>${this.escapeHtml(suggestion)}</li>`;
                    });
                    html += '</ul>';
                }

                html += '</div>';
            }

            // 额外信息
            if (key === 'title' && data.current_length) {
                html += `<p class="meta-info">当前长度: ${data.current_length} 字符</p>`;
            }
            if (key === 'meta_description' && data.current_length !== undefined) {
                html += `<p class="meta-info">当前长度: ${data.current_length} 字符</p>`;
            }
            if (key === 'keyword' && data.density) {
                html += `<p class="meta-info">关键词密度: ${data.density}%</p>`;
            }
            if (key === 'content' && data.word_count) {
                html += `<p class="meta-info">文章字数: ${data.word_count}</p>`;
            }
            if (key === 'readability' && data.level) {
                html += `<p class="meta-info">可读性等级: ${data.level}/10</p>`;
            }

            html += '</div></div>';

            return html;
        },

        applyTitleSuggestion: function(e) {
            e.preventDefault();

            const selectedTitle = $('input[name="suggested_title"]:checked').val();

            if (!selectedTitle) {
                alert('请先选择一个标题建议');
                return;
            }

            if (confirm('确定要应用这个标题吗？\n\n' + selectedTitle)) {
                $('#title').val(selectedTitle);
                alert('标题已更新，请记得保存文章');
            }
        },

        applyMetaSuggestion: function(e) {
            e.preventDefault();

            const selectedMeta = $('input[name="suggested_meta"]:checked').val();

            if (!selectedMeta) {
                alert('请先选择一个Meta描述建议');
                return;
            }

            // 尝试更新Yoast SEO或All in One SEO的Meta描述字段
            let updated = false;

            // Yoast SEO
            if ($('#yoast_wpseo_metadesc').length) {
                $('#yoast_wpseo_metadesc').val(selectedMeta);
                updated = true;
            }

            // All in One SEO
            if ($('#aioseo-post-settings-metadesc').length) {
                $('#aioseo-post-settings-metadesc').val(selectedMeta);
                updated = true;
            }

            if (updated) {
                alert('Meta描述已更新，请记得保存文章');
            } else {
                alert('未找到Meta描述字段。\n\n建议的Meta描述：\n' + selectedMeta + '\n\n请手动复制到您的SEO插件中');
            }
        },

        getScoreClass: function(score) {
            if (score >= 80) return 'score-excellent';
            if (score >= 60) return 'score-good';
            if (score >= 40) return 'score-fair';
            return 'score-poor';
        },

        getScoreLabel: function(score) {
            if (score >= 80) return '优秀';
            if (score >= 60) return '良好';
            if (score >= 40) return '需要改进';
            return '急需优化';
        },

        getScoreIcon: function(score) {
            if (score >= 80) return 'dashicons-yes-alt';
            if (score >= 60) return 'dashicons-warning';
            return 'dashicons-dismiss';
        },

        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        showSuccessNotice: function(message) {
            // 可以添加WordPress通知
            console.log('Success:', message);
        }
    };

    // 页面加载完成后初始化
    $(document).ready(function() {
        if ($('#ai-seo-analyze-btn').length) {
            AiSeoMetaBox.init();
        }
    });

})(jQuery);
