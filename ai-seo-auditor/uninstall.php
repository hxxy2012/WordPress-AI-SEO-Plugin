<?php
/**
 * 插件卸载清理脚本
 *
 * @package AI_SEO_Auditor
 */

// 如果不是从WordPress调用的卸载，退出
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * 清理插件数据
 */
function ai_seo_uninstall_cleanup() {
    global $wpdb;

    // 1. 删除选项
    delete_option('ai_seo_api_key');
    delete_option('ai_seo_settings');
    delete_option('ai_seo_version');
    delete_option('ai_seo_scheduler_options');
    delete_option('ai_seo_scheduler_log');

    // 2. 删除所有文章的meta数据
    $meta_keys = array(
        '_seo_audit_score',
        '_seo_audit_results',
        '_seo_audit_timestamp',
        '_ai_seo_target_keyword',
        '_seo_suggestions_applied'
    );

    foreach ($meta_keys as $meta_key) {
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s",
            $meta_key
        ));
    }

    // 3. 删除自定义数据表
    $table_name = $wpdb->prefix . 'ai_seo_audit_history';
    $wpdb->query("DROP TABLE IF EXISTS {$table_name}");

    // 4. 清除定时任务
    $cron_hooks = array(
        'ai_seo_scheduled_audit',
        'ai_seo_weekly_report'
    );

    foreach ($cron_hooks as $hook) {
        $timestamp = wp_next_scheduled($hook);
        if ($timestamp) {
            wp_unschedule_event($timestamp, $hook);
        }
    }

    // 5. 清除所有定时任务（防止有多个）
    wp_clear_scheduled_hook('ai_seo_scheduled_audit');
    wp_clear_scheduled_hook('ai_seo_weekly_report');

    // 6. 清除transients
    $wpdb->query(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_ai_seo_%'"
    );

    $wpdb->query(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_ai_seo_%'"
    );

    // 7. 清除网络站点选项（如果是多站点）
    if (is_multisite()) {
        delete_site_option('ai_seo_api_key');
        delete_site_option('ai_seo_settings');
        delete_site_option('ai_seo_version');

        // 清除所有站点的数据
        $sites = get_sites(array('number' => 1000));
        foreach ($sites as $site) {
            switch_to_blog($site->blog_id);

            // 删除选项
            delete_option('ai_seo_api_key');
            delete_option('ai_seo_settings');
            delete_option('ai_seo_version');
            delete_option('ai_seo_scheduler_options');
            delete_option('ai_seo_scheduler_log');

            // 删除文章meta
            foreach ($meta_keys as $meta_key) {
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s",
                    $meta_key
                ));
            }

            // 删除表
            $table_name = $wpdb->prefix . 'ai_seo_audit_history';
            $wpdb->query("DROP TABLE IF EXISTS {$table_name}");

            restore_current_blog();
        }
    }
}

// 执行清理
ai_seo_uninstall_cleanup();
