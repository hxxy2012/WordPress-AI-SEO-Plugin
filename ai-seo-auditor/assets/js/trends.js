/**
 * AI SEO Auditor - 趋势分析脚本
 */

(function($) {
    'use strict';

    const TrendChart = {
        chart: null,

        init: function() {
            this.bindEvents();
            this.loadInitialData();
        },

        bindEvents: function() {
            $('#update-trend-chart').on('click', this.updateChart.bind(this));
        },

        loadInitialData: function() {
            this.updateChart();
        },

        updateChart: function() {
            const postId = $('#trend-post-select').val();
            const period = $('#trend-period-select').val();
            const metric = $('#trend-metric-select').val();

            this.showLoading();
            this.hideError();

            $.ajax({
                url: aiSeoTrends.ajaxurl,
                type: 'POST',
                data: {
                    action: 'ai_seo_get_trend_data',
                    nonce: aiSeoTrends.nonce,
                    post_id: postId,
                    period: period,
                    metric: metric
                },
                success: this.handleSuccess.bind(this),
                error: this.handleError.bind(this),
                complete: this.hideLoading.bind(this)
            });
        },

        showLoading: function() {
            $('.trends-loading').show();
        },

        hideLoading: function() {
            $('.trends-loading').hide();
        },

        showError: function(message) {
            $('.trends-error .error-message').text(message);
            $('.trends-error').show();
        },

        hideError: function() {
            $('.trends-error').hide();
        },

        handleSuccess: function(response) {
            if (response.success) {
                this.renderChart(response.data);
                this.updateStats(response.data.stats);
                this.renderInsights(response.data.insights);
            } else {
                this.showError(response.data.message || '加载趋势数据失败');
            }
        },

        handleError: function(xhr, status, error) {
            let errorMessage = '加载趋势数据失败，请重试';

            if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                errorMessage = xhr.responseJSON.data.message;
            }

            this.showError(errorMessage);
        },

        renderChart: function(data) {
            const ctx = document.getElementById('seo-trend-chart').getContext('2d');

            // 销毁旧图表
            if (this.chart) {
                this.chart.destroy();
            }

            // 创建新图表
            this.chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'SEO评分',
                        data: data.scores,
                        borderColor: '#0073aa',
                        backgroundColor: this.createGradient(ctx),
                        borderWidth: 3,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        pointBackgroundColor: '#0073aa',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    aspectRatio: 2.5,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            labels: {
                                font: {
                                    size: 14,
                                    weight: 'bold'
                                },
                                padding: 20
                            }
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            padding: 12,
                            titleFont: {
                                size: 14
                            },
                            bodyFont: {
                                size: 13
                            },
                            callbacks: {
                                label: function(context) {
                                    return 'SEO评分: ' + context.parsed.y + '/100';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            ticks: {
                                stepSize: 20,
                                font: {
                                    size: 12
                                }
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        x: {
                            ticks: {
                                font: {
                                    size: 12
                                },
                                maxRotation: 45,
                                minRotation: 0
                            },
                            grid: {
                                display: false
                            }
                        }
                    },
                    interaction: {
                        mode: 'nearest',
                        axis: 'x',
                        intersect: false
                    }
                }
            });
        },

        createGradient: function(ctx) {
            const gradient = ctx.createLinearGradient(0, 0, 0, 400);
            gradient.addColorStop(0, 'rgba(0, 115, 170, 0.3)');
            gradient.addColorStop(1, 'rgba(0, 115, 170, 0.01)');
            return gradient;
        },

        updateStats: function(stats) {
            if (!stats) {
                return;
            }

            // 当前评分
            $('#current-score').text(stats.current);
            this.applyScoreColor($('#current-score'), stats.current);

            // 平均评分
            $('#average-score').text(stats.average);
            this.applyScoreColor($('#average-score'), stats.average);

            // 最高评分
            $('#highest-score').text(stats.highest);
            this.applyScoreColor($('#highest-score'), stats.highest);

            // 改进幅度
            const improvement = stats.improvement;
            const improvementText = improvement >= 0 ? '+' + improvement : improvement;
            $('#improvement').text(improvementText);

            if (improvement > 0) {
                $('#improvement').css('color', '#46b450');
            } else if (improvement < 0) {
                $('#improvement').css('color', '#dc3232');
            } else {
                $('#improvement').css('color', '#666');
            }
        },

        applyScoreColor: function($element, score) {
            if (score >= 80) {
                $element.css('color', '#46b450');
            } else if (score >= 60) {
                $element.css('color', '#00a0d2');
            } else if (score >= 40) {
                $element.css('color', '#ffb900');
            } else {
                $element.css('color', '#dc3232');
            }
        },

        renderInsights: function(insights) {
            const $container = $('#insights-content');
            $container.empty();

            if (!insights || insights.length === 0) {
                $container.html('<p>暂无洞察信息</p>');
                return;
            }

            let html = '<ul class="insights-list">';

            insights.forEach(function(insight) {
                const iconClass = this.getInsightIcon(insight.type);
                html += '<li class="insight-item insight-' + insight.type + '">';
                html += '<span class="insight-icon dashicons ' + iconClass + '"></span>';
                html += '<span class="insight-message">' + this.escapeHtml(insight.message) + '</span>';
                html += '</li>';
            }.bind(this));

            html += '</ul>';

            $container.html(html);
        },

        getInsightIcon: function(type) {
            const icons = {
                'success': 'dashicons-yes-alt',
                'warning': 'dashicons-warning',
                'error': 'dashicons-dismiss',
                'info': 'dashicons-info'
            };

            return icons[type] || 'dashicons-info';
        },

        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    // 页面加载完成后初始化
    $(document).ready(function() {
        if ($('.ai-seo-trends-page').length) {
            TrendChart.init();
        }
    });

})(jQuery);
