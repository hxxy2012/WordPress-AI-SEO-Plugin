/**
 * AI SEO Auditor - 竞品分析脚本
 */

(function($) {
    'use strict';

    const CompetitorAnalysis = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            $('#competitor-analysis-form').on('submit', this.handleSubmit.bind(this));
        },

        handleSubmit: function(e) {
            e.preventDefault();

            const url = $('#competitor_url').val();
            const comparePostId = $('#compare_post_id').val();

            if (!url) {
                alert('请输入竞品URL');
                return;
            }

            this.showLoading();
            this.hideError();
            this.hideResults();

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ai_seo_analyze_competitor',
                    nonce: aiSeoData.nonce,
                    competitor_url: url,
                    compare_post_id: comparePostId
                },
                success: this.handleSuccess.bind(this),
                error: this.handleError.bind(this),
                complete: this.hideLoading.bind(this)
            });
        },

        showLoading: function() {
            $('.competitor-loading').show();
        },

        hideLoading: function() {
            $('.competitor-loading').hide();
        },

        showError: function(message) {
            $('.competitor-error .error-message').text(message);
            $('.competitor-error').show();
        },

        hideError: function() {
            $('.competitor-error').hide();
        },

        hideResults: function() {
            $('.competitor-results').hide();
        },

        handleSuccess: function(response) {
            if (response.success) {
                this.renderResults(response.data);
            } else {
                this.showError(response.data.message || '分析失败，请重试');
            }
        },

        handleError: function(xhr, status, error) {
            let errorMessage = '分析失败，请重试';

            if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                errorMessage = xhr.responseJSON.data.message;
            }

            this.showError(errorMessage);
        },

        renderResults: function(data) {
            let html = '';

            // 竞品分析结果
            html += '<div class="competitor-analysis-section">';
            html += '<h3>竞品分析结果</h3>';
            html += this.renderCompetitorCard(data.competitor);
            html += '</div>';

            // 如果有对比数据
            if (data.own && data.comparison) {
                html += '<div class="comparison-section">';
                html += '<h3>对比分析</h3>';
                html += this.renderComparison(data.own, data.competitor, data.comparison);
                html += '</div>';
            }

            $('.competitor-results .results-content').html(html);
            $('.competitor-results').show();
        },

        renderCompetitorCard: function(competitor) {
            const score = competitor.overall_score || 0;
            const scoreClass = this.getScoreClass(score);

            let html = '<div class="competitor-card">';

            // 总评分
            html += '<div class="competitor-score">';
            html += '<div class="score-circle ' + scoreClass + '">';
            html += '<span class="score-number">' + score + '</span>';
            html += '<span class="score-total">/100</span>';
            html += '</div>';
            html += '<div class="score-info">';
            html += '<h4>竞品SEO评分</h4>';
            html += '<p>' + this.escapeHtml(competitor.url) + '</p>';
            html += '</div>';
            html += '</div>';

            // 优势
            if (competitor.strengths && competitor.strengths.length > 0) {
                html += '<div class="competitor-strengths">';
                html += '<h5>优势</h5>';
                html += '<ul>';
                competitor.strengths.forEach(function(strength) {
                    html += '<li>' + this.escapeHtml(strength) + '</li>';
                }.bind(this));
                html += '</ul>';
                html += '</div>';
            }

            // 弱点
            if (competitor.weaknesses && competitor.weaknesses.length > 0) {
                html += '<div class="competitor-weaknesses">';
                html += '<h5>弱点</h5>';
                html += '<ul>';
                competitor.weaknesses.forEach(function(weakness) {
                    html += '<li>' + this.escapeHtml(weakness) + '</li>';
                }.bind(this));
                html += '</ul>';
                html += '</div>';
            }

            // 可学习之处
            if (competitor.opportunities && competitor.opportunities.length > 0) {
                html += '<div class="competitor-opportunities">';
                html += '<h5>可学习之处</h5>';
                html += '<ul>';
                competitor.opportunities.forEach(function(opp) {
                    html += '<li>' + this.escapeHtml(opp) + '</li>';
                }.bind(this));
                html += '</ul>';
                html += '</div>';
            }

            // 推荐
            if (competitor.recommendations && competitor.recommendations.length > 0) {
                html += '<div class="competitor-recommendations">';
                html += '<h5>优化建议</h5>';
                html += '<ul>';
                competitor.recommendations.forEach(function(rec) {
                    html += '<li>' + this.escapeHtml(rec) + '</li>';
                }.bind(this));
                html += '</ul>';
                html += '</div>';
            }

            html += '</div>';

            return html;
        },

        renderComparison: function(own, competitor, comparison) {
            let html = '<div class="comparison-grid">';

            // 总分对比
            html += '<div class="comparison-item">';
            html += '<h5>总体评分</h5>';
            html += this.renderScoreBar(own.overall_score, competitor.overall_score);
            html += '</div>';

            // 各维度对比
            const dimensions = {
                'title': '标题优化',
                'meta_description': 'Meta描述',
                'keyword': '关键词使用',
                'content': '内容质量',
                'readability': '可读性',
                'technical': '技术SEO'
            };

            for (const dim in dimensions) {
                if (comparison.details && comparison.details[dim]) {
                    html += '<div class="comparison-item">';
                    html += '<h5>' + dimensions[dim] + '</h5>';
                    html += this.renderScoreBar(
                        comparison.details[dim].own_score,
                        comparison.details[dim].competitor_score
                    );
                    html += '</div>';
                }
            }

            html += '</div>';

            // 优劣势总结
            html += '<div class="comparison-summary">';

            if (comparison.advantages && comparison.advantages.length > 0) {
                html += '<div class="advantages">';
                html += '<h5>您的优势</h5>';
                html += '<ul>';
                comparison.advantages.forEach(function(adv) {
                    html += '<li>' + dimensions[adv] + '</li>';
                });
                html += '</ul>';
                html += '</div>';
            }

            if (comparison.disadvantages && comparison.disadvantages.length > 0) {
                html += '<div class="disadvantages">';
                html += '<h5>需要改进的地方</h5>';
                html += '<ul>';
                comparison.disadvantages.forEach(function(dis) {
                    html += '<li>' + dimensions[dis] + '</li>';
                });
                html += '</ul>';
                html += '</div>';
            }

            html += '</div>';

            return html;
        },

        renderScoreBar: function(ownScore, competitorScore) {
            let html = '<div class="score-bar-container">';

            html += '<div class="score-bar-row">';
            html += '<span class="score-label">您的文章</span>';
            html += '<div class="score-bar">';
            html += '<div class="score-bar-fill ' + this.getScoreClass(ownScore) + '" style="width: ' + ownScore + '%"></div>';
            html += '</div>';
            html += '<span class="score-value">' + ownScore + '</span>';
            html += '</div>';

            html += '<div class="score-bar-row">';
            html += '<span class="score-label">竞品文章</span>';
            html += '<div class="score-bar">';
            html += '<div class="score-bar-fill ' + this.getScoreClass(competitorScore) + '" style="width: ' + competitorScore + '%"></div>';
            html += '</div>';
            html += '<span class="score-value">' + competitorScore + '</span>';
            html += '</div>';

            html += '</div>';

            return html;
        },

        getScoreClass: function(score) {
            if (score >= 80) return 'score-excellent';
            if (score >= 60) return 'score-good';
            if (score >= 40) return 'score-fair';
            return 'score-poor';
        },

        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    // 页面加载完成后初始化
    $(document).ready(function() {
        if ($('#competitor-analysis-form').length) {
            CompetitorAnalysis.init();
        }
    });

})(jQuery);
