<?php
/**
 * Plugin Name: AI SEO Auditor
 * Plugin URI: https://github.com/hxxy2012/WordPress-AI-SEO-Plugin
 * Description: 使用Claude AI API自动分析文章的SEO质量，并提供智能优化建议
 * Version: 1.0.0
 * Author: AI SEO Team
 * Author URI: https://github.com/hxxy2012
 * Text Domain: ai-seo-auditor
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

// 定义插件常量
define('AI_SEO_VERSION', '1.0.0');
define('AI_SEO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AI_SEO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AI_SEO_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * 主插件类
 */
class AI_SEO_Auditor {

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
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * 加载依赖文件
     */
    private function load_dependencies() {
        // 加载核心类
        require_once AI_SEO_PLUGIN_DIR . 'includes/class-api-handler.php';
        require_once AI_SEO_PLUGIN_DIR . 'includes/class-seo-analyzer.php';
        require_once AI_SEO_PLUGIN_DIR . 'includes/class-meta-box.php';
        require_once AI_SEO_PLUGIN_DIR . 'includes/class-settings.php';
        require_once AI_SEO_PLUGIN_DIR . 'includes/class-admin-ui.php';
        require_once AI_SEO_PLUGIN_DIR . 'includes/class-competitor-analysis.php';
        require_once AI_SEO_PLUGIN_DIR . 'includes/class-scheduler.php';
        require_once AI_SEO_PLUGIN_DIR . 'includes/class-image-seo.php';
        require_once AI_SEO_PLUGIN_DIR . 'includes/class-social-media.php';
        require_once AI_SEO_PLUGIN_DIR . 'includes/class-quick-actions.php';
        require_once AI_SEO_PLUGIN_DIR . 'includes/class-trend-chart.php';
        require_once AI_SEO_PLUGIN_DIR . 'includes/class-report-generator.php';
    }

    /**
     * 初始化WordPress钩子
     */
    private function init_hooks() {
        // 插件激活和停用钩子
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // 初始化各个组件
        add_action('plugins_loaded', array($this, 'init_components'));

        // 加载文本域
        add_action('plugins_loaded', array($this, 'load_textdomain'));

        // 加载后台资源
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    /**
     * 插件激活
     */
    public function activate() {
        // 设置默认选项
        if (!get_option('ai_seo_api_key')) {
            add_option('ai_seo_api_key', '');
        }

        if (!get_option('ai_seo_settings')) {
            add_option('ai_seo_settings', array(
                'keyword_density_min' => 1,
                'keyword_density_max' => 3,
                'title_length_min' => 50,
                'title_length_max' => 60,
                'meta_length_min' => 150,
                'meta_length_max' => 160,
                'min_word_count' => 300,
                'language' => 'zh',
                'model' => 'claude-sonnet-4-5-20250929'
            ));
        }

        // 创建必要的数据库表（如需要）
        $this->create_tables();

        // 设置版本号
        update_option('ai_seo_version', AI_SEO_VERSION);

        // 刷新重写规则
        flush_rewrite_rules();
    }

    /**
     * 插件停用
     */
    public function deactivate() {
        // 清理临时数据
        flush_rewrite_rules();
    }

    /**
     * 创建数据库表
     */
    private function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'ai_seo_audit_history';

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            audit_date datetime DEFAULT CURRENT_TIMESTAMP,
            overall_score int(3) NOT NULL,
            results longtext NOT NULL,
            PRIMARY KEY  (id),
            KEY post_id (post_id),
            KEY audit_date (audit_date)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * 初始化组件
     */
    public function init_components() {
        // 仅在后台初始化
        if (is_admin()) {
            AI_SEO_Settings::get_instance();
            AI_SEO_Admin_UI::get_instance();
            AI_SEO_Meta_Box::get_instance();
            AI_SEO_Competitor_Analysis::get_instance();
            AI_SEO_Image_SEO::get_instance();
            AI_SEO_Social_Media::get_instance();
            AI_SEO_Quick_Actions::get_instance();
            AI_SEO_Trend_Chart::get_instance();
            AI_SEO_Report_Generator::get_instance();
        }

        // 初始化定时任务（前后台都需要）
        AI_SEO_Scheduler::get_instance();

        // 前台也初始化快捷代码
        if (!is_admin()) {
            AI_SEO_Quick_Actions::get_instance();
        }
    }

    /**
     * 加载文本域
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'ai-seo-auditor',
            false,
            dirname(AI_SEO_PLUGIN_BASENAME) . '/languages'
        );
    }

    /**
     * 加载后台资源
     */
    public function enqueue_admin_assets($hook) {
        // 仅在需要的页面加载
        $allowed_pages = array(
            'post.php',
            'post-new.php',
            'toplevel_page_ai-seo-auditor',
            'ai-seo-auditor_page_ai-seo-batch-audit',
            'ai-seo-auditor_page_ai-seo-competitor',
            'ai-seo-auditor_page_ai-seo-scheduler',
            'ai-seo-auditor_page_ai-seo-trends'
        );

        if (!in_array($hook, $allowed_pages)) {
            return;
        }

        // 加载CSS
        wp_enqueue_style(
            'ai-seo-admin',
            AI_SEO_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            AI_SEO_VERSION
        );

        wp_enqueue_style(
            'ai-seo-meta-box',
            AI_SEO_PLUGIN_URL . 'assets/css/meta-box.css',
            array(),
            AI_SEO_VERSION
        );

        // 竞品分析页面加载专用样式
        if ($hook === 'ai-seo-auditor_page_ai-seo-competitor') {
            wp_enqueue_style(
                'ai-seo-competitor',
                AI_SEO_PLUGIN_URL . 'assets/css/competitor.css',
                array(),
                AI_SEO_VERSION
            );
        }

        // 加载JS
        wp_enqueue_script(
            'ai-seo-admin',
            AI_SEO_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            AI_SEO_VERSION,
            true
        );

        wp_enqueue_script(
            'ai-seo-meta-box',
            AI_SEO_PLUGIN_URL . 'assets/js/meta-box.js',
            array('jquery'),
            AI_SEO_VERSION,
            true
        );

        // 竞品分析页面加载专用脚本
        if ($hook === 'ai-seo-auditor_page_ai-seo-competitor') {
            wp_enqueue_script(
                'ai-seo-competitor',
                AI_SEO_PLUGIN_URL . 'assets/js/competitor.js',
                array('jquery'),
                AI_SEO_VERSION,
                true
            );
        }

        // 传递数据给JS
        wp_localize_script('ai-seo-meta-box', 'aiSeoData', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ai_seo_nonce'),
            'strings' => array(
                'analyzing' => __('正在分析...', 'ai-seo-auditor'),
                'error' => __('分析失败，请重试', 'ai-seo-auditor'),
                'success' => __('分析完成', 'ai-seo-auditor'),
                'api_key_missing' => __('请先在设置页面配置API Key', 'ai-seo-auditor')
            )
        ));
    }
}

/**
 * 获取插件主实例
 */
function ai_seo_auditor() {
    return AI_SEO_Auditor::get_instance();
}

// 启动插件
ai_seo_auditor();
