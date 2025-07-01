<?php
if (!session_id()) {
    session_start();
}

add_action('init', function () {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['custom_survey_submit'])) {
        global $wpdb;

        $form_id = intval($_POST['form_id']);
        $fields = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}survey_fields WHERE form_id = %d",
            $form_id
        ));

        $answers = [];

        foreach ($fields as $field) {
            $field_key = 'field_' . $field->id;
            $value = '';

            // File upload
            if ($field->type === 'file') {
                if (!empty($_FILES[$field_key]['name'])) {
                    $uploaded_file = $_FILES[$field_key];

                    if (!function_exists('wp_handle_upload')) {
                        require_once(ABSPATH . 'wp-admin/includes/file.php');
                        require_once(ABSPATH . 'wp-admin/includes/media.php');
                        require_once(ABSPATH . 'wp-admin/includes/image.php');
                    }

                    $upload = wp_handle_upload($uploaded_file, ['test_form' => false]);

                    if (isset($upload['error'])) {
                        wp_die('Dosya yüklenemedi: ' . esc_html($upload['error']));
                    }

                    $value = $upload['url'];
                } elseif ($field->required) {
                    wp_die('Zorunlu dosya yüklenmedi.');
                }
            } else {
                $value = isset($_POST[$field_key]) ? sanitize_text_field($_POST[$field_key]) : '';

                if ($field->required && empty($value)) {
                    wp_die('You must fill in the required fields.');
                }
            }

            $answers[$field->label] = $value;
        }

        $response_id = $_SESSION['custom_survey_response_id'] ?? null;
        $finalized = $_SESSION['custom_survey_response_finalized'] ?? false;

        if ($response_id && !$finalized) {
            // 🔁 İlk dəfə insert olunmuşdu, indi UPDATE edirik
            $wpdb->update(
                $wpdb->prefix . 'survey_responses',
                [
                    'data' => wp_json_encode($answers, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                    'submitted_at' => current_time('mysql')
                ],
                ['id' => $response_id]
            );

            // Artıq bu cavab tamamlandı
            $_SESSION['custom_survey_response_finalized'] = true;
        } else {
            // 🔄 Ya ilk dəfədir, ya da əvvəlki tamamlanıb – yeni INSERT
            $wpdb->insert($wpdb->prefix . 'survey_responses', [
                'form_id' => $form_id,
                'data' => wp_json_encode($answers, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                'submitted_at' => current_time('mysql')
            ]);

            $_SESSION['custom_survey_response_id'] = $wpdb->insert_id;
            $_SESSION['custom_survey_response_finalized'] = true;
        }

        wp_redirect(add_query_arg('submitted', 'true', wp_get_referer()));
        exit;
    }
});

add_action('wp_ajax_custom_survey_auto_submit', 'handle_custom_survey_auto_submit');
add_action('wp_ajax_nopriv_custom_survey_auto_submit', 'handle_custom_survey_auto_submit');

function handle_custom_survey_auto_submit(): void
{
    global $wpdb;

    // DEBUG: POST datanı logla
    error_log('CUSTOM_SURVEY_AUTO_SAVE POST: ' . print_r($_POST, true));

    $form_id = intval($_POST['form_id'] ?? 0);
    if (!$form_id) {
        wp_send_json_error('Form ID eksik.');
    }

    $fields = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}survey_fields WHERE form_id = %d ORDER BY id ASC",
        $form_id
    ));

    $answers = [];
    $count = 0;

    $trigger_field_id = $_POST['trigger_field_id'] ?? null;
    foreach ($fields as $field) {
        $key = 'field_' . $field->id;
        $value = sanitize_text_field($_POST[$key] ?? '');
        $count++;

        if ($trigger_field_id && $field->id == $trigger_field_id) {
            // Trigger field-a çatanda onu da əlavə edib break et
            $answers[$field->label] = $value;
            break;
        }

        if ($field->required && empty($value)) {
            wp_send_json_error('Zorunlu alanlar boş bırakılamaz.');
        }

        $answers[$field->label] = $value;
    }

    $response_id = $_SESSION['custom_survey_response_id'] ?? null;
    $finalized = $_SESSION['custom_survey_response_finalized'] ?? false;

    if ($response_id && !$finalized) {
        // UPDATE
        $wpdb->update(
            $wpdb->prefix . 'survey_responses',
            [
                'data' => wp_json_encode($answers, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                'submitted_at' => current_time('mysql')
            ],
            ['id' => $response_id]
        );
    } else {
        // INSERT
        $wpdb->insert($wpdb->prefix . 'survey_responses', [
            'form_id' => $form_id,
            'data' => wp_json_encode($answers, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            'submitted_at' => current_time('mysql')
        ]);

        $_SESSION['custom_survey_response_id'] = $wpdb->insert_id;
        $_SESSION['custom_survey_response_finalized'] = false;
        $response_id = $wpdb->insert_id;
    }

    wp_send_json_success(['message' => 'Kaydedildi', 'response_id' => $response_id]);
}
