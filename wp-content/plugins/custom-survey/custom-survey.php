<?php
/*
Plugin Name: Survey Forms
Description: Management of survey forms with custom fields
Version: 1.0
Author: Elvin Muradov
*/

defined('ABSPATH') || exit;

// Tanımlar
define('CUSTOM_SURVEY_PATH', plugin_dir_path(__FILE__));
define('CUSTOM_SURVEY_URL', plugin_dir_url(__FILE__));

// Dosyaları dahil et
require_once CUSTOM_SURVEY_PATH . 'includes/survey-functions.php';
require_once CUSTOM_SURVEY_PATH . 'admin/survey-admin-page.php';
require_once CUSTOM_SURVEY_PATH . 'public/survey-form-shortcode.php';
require_once CUSTOM_SURVEY_PATH . 'admin/survey-fields-page.php';
require_once CUSTOM_SURVEY_PATH . 'admin/survey-responses-page.php';

// Aktivasyon hook
register_activation_hook(__FILE__, 'custom_survey_install');

function custom_survey_install(): void
{
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $forms = $wpdb->prefix . 'survey_forms';
    $fields = $wpdb->prefix . 'survey_fields';
    $responses = $wpdb->prefix . 'survey_responses';

    $sql = "
    CREATE TABLE $forms (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL
    ) $charset_collate;

    CREATE TABLE $fields (
        id INT AUTO_INCREMENT PRIMARY KEY,
        form_id INT,
        label VARCHAR(255),
        type VARCHAR(50),
        required BOOLEAN DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        FOREIGN KEY (form_id) REFERENCES $forms(id) ON DELETE CASCADE
    ) $charset_collate;

    CREATE TABLE $responses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        form_id INT,
        data TEXT,
        submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (form_id) REFERENCES $forms(id) ON DELETE CASCADE
    ) $charset_collate;
    ";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

