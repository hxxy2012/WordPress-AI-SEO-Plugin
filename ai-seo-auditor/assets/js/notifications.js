/**
 * AI SEO Auditor - 通知中心脚本
 */

(function($) {
    'use strict';

    const Notifications = {
        init: function() {
            this.bindEvents();
            this.checkForUpdates();
        },

        bindEvents: function() {
            // 全部标记为已读
            $(document).on('click', '#mark-all-read-btn', this.markAllRead.bind(this));

            // 忽略单个通知
            $(document).on('click', '.dismiss-notification-btn', this.dismissNotification.bind(this));
        },

        markAllRead: function(e) {
            e.preventDefault();

            if (!confirm('确定要将所有通知标记为已读吗？')) {
                return;
            }

            const $button = $(e.currentTarget);
            const originalText = $button.html();
            $button.html('<span class="dashicons dashicons-update spin"></span> 处理中...').prop('disabled', true);

            $.ajax({
                url: aiSeoNotifications.ajaxurl,
                type: 'POST',
                data: {
                    action: 'ai_seo_mark_all_read',
                    nonce: aiSeoNotifications.nonce
                },
                success: (response) => {
                    if (response.success) {
                        // 刷新页面
                        location.reload();
                    } else {
                        alert(response.data.message || '操作失败');
                        $button.html(originalText).prop('disabled', false);
                    }
                },
                error: (xhr, status, error) => {
                    alert('操作失败: ' + error);
                    $button.html(originalText).prop('disabled', false);
                }
            });
        },

        dismissNotification: function(e) {
            e.preventDefault();

            const $button = $(e.currentTarget);
            const notificationId = $button.data('notification-id');
            const $item = $button.closest('.notification-item');

            $item.addClass('dismissing');

            $.ajax({
                url: aiSeoNotifications.ajaxurl,
                type: 'POST',
                data: {
                    action: 'ai_seo_dismiss_notification',
                    nonce: aiSeoNotifications.nonce,
                    notification_id: notificationId
                },
                success: (response) => {
                    if (response.success) {
                        setTimeout(() => {
                            $item.slideUp(300, function() {
                                $(this).remove();

                                // 检查是否还有通知
                                if ($('.notification-item').length === 0) {
                                    location.reload();
                                } else {
                                    this.updateCounts();
                                }
                            }.bind(this));
                        }, 300);
                    } else {
                        $item.removeClass('dismissing');
                        alert(response.data.message || '操作失败');
                    }
                },
                error: (xhr, status, error) => {
                    $item.removeClass('dismissing');
                    alert('操作失败: ' + error);
                }
            });
        },

        updateCounts: function() {
            // 更新统计数字
            const total = $('.notification-item').length;
            const unread = $('.notification-unread').length;

            $('.notification-stats .stat-card:first-child .stat-value').text(total);
            $('.notification-stats .stat-card.unread .stat-value').text(unread);

            // 更新管理栏计数
            if (unread === 0) {
                $('#wpadminbar .ai-seo-notification-count').remove();
            } else {
                $('#wpadminbar .ai-seo-notification-count').text(unread);
            }
        },

        checkForUpdates: function() {
            // 每30秒检查一次新通知
            setInterval(() => {
                this.fetchNewNotifications();
            }, 30000);
        },

        fetchNewNotifications: function() {
            $.ajax({
                url: aiSeoNotifications.ajaxurl,
                type: 'POST',
                data: {
                    action: 'ai_seo_get_notifications',
                    nonce: aiSeoNotifications.nonce
                },
                success: (response) => {
                    if (response.success) {
                        const currentCount = $('.notification-item').length;
                        const newCount = response.data.length;

                        if (newCount > currentCount) {
                            // 有新通知，显示提示
                            this.showNewNotificationBanner(newCount - currentCount);
                        }
                    }
                }
            });
        },

        showNewNotificationBanner: function(count) {
            if ($('.new-notifications-banner').length > 0) {
                return;
            }

            const banner = $('<div class="notice notice-info is-dismissible new-notifications-banner">')
                .html('<p><strong>有 ' + count + ' 条新通知</strong> <a href="#" class="reload-page">刷新页面查看</a></p>')
                .prependTo('.ai-seo-notifications-page');

            $(document).on('click', '.reload-page', function(e) {
                e.preventDefault();
                location.reload();
            });
        }
    };

    // 页面加载完成后初始化
    $(document).ready(function() {
        if ($('.ai-seo-notifications-page').length) {
            Notifications.init();
        }
    });

})(jQuery);
