<?php
add_action('admin_menu', function () {
    add_submenu_page(
        'custom-survey',
        'Answers',
        'Answers',
        'manage_options',
        'custom-survey-responses',
        'custom_survey_responses_page'
    );
});

add_action('admin_init', 'custom_survey_export_csv');

function old_custom_survey_export_csv(): void
{
    if (isset($_POST['export_csv_button']) && current_user_can('manage_options')) {
        $export_form_id = intval($_POST['export_csv_form_id']);
        if (wp_verify_nonce($_POST['_wpnonce'], 'export_csv_' . $export_form_id)) {
            global $wpdb;

            $responses = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}survey_responses WHERE form_id = %d",
                $export_form_id
            ));

            if (empty($responses)) {
                wp_die('No response yet.');
            }

            // --- 1. Tüm yanıtları gezerek tüm alanları (label) topla
            $all_labels = [];

            foreach ($responses as $response) {
                $data = json_decode($response->data, true);
                foreach ($data as $label => $value) {
                    $all_labels[$label] = true;
                }
            }

            $columns = array_keys($all_labels);
            $columns[] = 'Tarih'; // En sona tarih sütunu

            // --- 2. Header ayarları
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=anket_cevaplari_form_' . $export_form_id . '.csv');

            $output = fopen('php://output', 'w');

            // --- 3. Başlık satırı yaz
            fputcsv($output, $columns);

            // --- 4. Her satırda eksik alanları boş string olarak tamamla
            foreach ($responses as $response) {
                $data = json_decode($response->data, true);

                $row = [];
                foreach ($columns as $col) {
                    if ($col === 'Tarih') {
                        $row[] = $response->submitted_at;
                    } else {
                        $row[] = $data[$col] ?? '';
                    }
                }

                fputcsv($output, $row);
            }

            fclose($output);
            exit;
        } else {
            wp_die('Invalid security verification.');
        }
    }

}

function custom_survey_export_csv(): void
{
    if (isset($_POST['export_csv_button']) && current_user_can('manage_options')) {
        $export_form_id = intval($_POST['export_csv_form_id']);
        if (wp_verify_nonce($_POST['_wpnonce'], 'export_csv_' . $export_form_id)) {
            global $wpdb;

            // ⛳️ Yalnızca görünür yanıt ID'lerini al
            $visible_ids = isset($_POST['visible_response_ids']) ? array_map('intval', $_POST['visible_response_ids']) : [];

            if (empty($visible_ids)) {
                wp_die('Hiçbir yanıt seçilmedi.');
            }

            $placeholders = implode(',', array_fill(0, count($visible_ids), '%d'));
            $query = $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}survey_responses WHERE form_id = %d AND id IN ($placeholders)",
                array_merge([$export_form_id], $visible_ids)
            );

            $responses = $wpdb->get_results($query);

            if (empty($responses)) {
                wp_die('Yanıt bulunamadı.');
            }

            // Tüm kolonları topla
            $all_labels = [];

            foreach ($responses as $response) {
                $data = json_decode($response->data, true);
                foreach ($data as $label => $value) {
                    $all_labels[$label] = true;
                }
            }

            $columns = array_keys($all_labels);
            $columns[] = 'Tarih';

            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=gorunur_yanitlar_form_' . $export_form_id . '.csv');

            $output = fopen('php://output', 'w');

            fputcsv($output, $columns);

            foreach ($responses as $response) {
                $data = json_decode($response->data, true);

                $row = [];
                foreach ($columns as $col) {
                    $row[] = $col === 'Tarix' ? $response->submitted_at : ($data[$col] ?? '');
                }

                fputcsv($output, $row);
            }

            fclose($output);
            exit;
        } else {
            wp_die('Güvenlik doğrulaması geçersiz.');
        }
    }
}

function custom_survey_responses_page(): void
{
    global $wpdb;

    $forms = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}survey_forms");

    $selected_form_id = isset($_GET['form_id']) ? intval($_GET['form_id']) : 0;
    $per_page = 10;
    $current_page = isset($_GET['response_page']) ? max(1, intval($_GET['response_page'])) : 1;
    $offset = ($current_page - 1) * $per_page;

    $total_responses = $wpdb->get_var(
        $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}survey_responses WHERE form_id = %d", $selected_form_id)
    );

    $responses = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}survey_responses WHERE form_id = %d ORDER BY submitted_at DESC LIMIT %d OFFSET %d",
            $selected_form_id, $per_page, $offset
        )
    );
    if (isset($_GET['delete_response']) && current_user_can('manage_options')) {
        $response_id = intval($_GET['delete_response']);
        if (wp_verify_nonce($_GET['_wpnonce'], 'delete_response_' . $response_id)) {
            $wpdb->delete($wpdb->prefix . 'survey_responses', ['id' => $response_id]);
            echo '<div class="notice notice-success"><p>Answer deleted.</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Security verification failed.</p></div>';
        }
    }
    ?>

    <div class="wrap">
        <h1>Survey answers</h1>

        <form method="get">
            <input type="hidden" name="page" value="custom-survey-responses">
            <label for="form_id">Select survey:</label>
            <select name="form_id" onchange="this.form.submit()">
                <option value="">-- Select --</option>
                <?php foreach ($forms as $form): ?>
                    <option value="<?php echo $form->id; ?>" <?php selected($form->id, $selected_form_id); ?>>
                        <?php echo esc_html($form->title); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>

        <!--        --><?php //if ($selected_form_id):
        ?>
        <!--            <form method="post" style="margin-top: 10px;">-->
        <!--                --><?php //wp_nonce_field('export_csv_' . $selected_form_id);
        ?>
        <!--                <input type="hidden" name="export_csv_form_id" value="-->
        <?php //echo esc_attr($selected_form_id);
        ?><!--">-->
        <!--                <p>-->
        <!--                    <input type="submit" name="export_csv_button" class="button button-secondary" value="CSV Export">-->
        <!--                </p>-->
        <!--            </form>-->
        <!--        --><?php //endif;
        ?>

        <?php if ($selected_form_id):
            ?>
            <form method="post" action="">
                <?php wp_nonce_field('export_csv_' . $selected_form_id); ?>
                <input type="hidden" name="export_csv_form_id" value="<?php echo esc_attr($selected_form_id); ?>">

                <?php foreach ($responses as $response): ?>
                    <input type="hidden" name="visible_response_ids[]" value="<?php echo esc_attr($response->id); ?>">
                <?php endforeach; ?>

                <p>
                    <input type="submit" name="export_csv_button" class="button button-secondary" value="CSV Export">
                </p>
            </form>
        <?php endif; ?>
        
        <?php if ($selected_form_id): ?>
            <h2>Answers (Form ID: <?php echo $selected_form_id; ?>)</h2>
            <table class="widefat fixed">
                <thead>
                <tr>
                    <th>#</th>
                    <th>Answers</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php

                if ($responses):
                    foreach ($responses as $i => $response):
                        $data = json_decode($response->data, true);
                        ?>
                        <tr>
                            <td><?php echo $i + 1; ?></td>
                            <td>
                                <ul>
                                    <?php foreach ($data as $label => $value): ?>
                                        <li>
                                            <strong><?php echo esc_html($label); ?>:</strong>
                                            <?php
                                            // Eğer değer bir dosya URL'siyse link olarak göster
                                            if (filter_var($value, FILTER_VALIDATE_URL) && strpos($value, '/wp-content/uploads/') !== false) {
                                                echo '<a href="' . esc_url($value) . '" target="_blank" download>Download file</a>';
                                            } else {
                                                echo esc_html($value);
                                            }
                                            ?>
                                        </li>
                                    <?php endforeach; ?>

                                </ul>
                            </td>
                            <td><?php echo esc_html($response->submitted_at); ?></td>
                            <td>
                                <a href="<?php echo wp_nonce_url(admin_url("admin.php?page=custom-survey-responses&form_id={$selected_form_id}&delete_response={$response->id}"), 'delete_response_' . $response->id); ?>"
                                   class="button button-small"
                                   onclick="return confirm('Are you sure you want to delete this answer?')">Delete</a>
                            </td>

                        </tr>
                    <?php endforeach;
                else: ?>
                    <tr>
                        <td colspan="3">No response yet.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <?php
        $total_response_pages = ceil($total_responses / $per_page);
        if ($total_response_pages > 1):
            echo '<div class="tablenav"><div class="tablenav-pages">';
            for ($i = 1; $i <= $total_response_pages; $i++) {
                $url = add_query_arg([
                    'page' => 'custom-survey-responses',
                    'form_id' => $selected_form_id,
                    'response_page' => $i
                ], admin_url('admin.php'));
                echo '<a class="button' . ($i === $current_page ? ' current' : '') . '" href="' . esc_url($url) . '">' . $i . '</a> ';
            }
            echo '</div></div>';
        endif;
        ?>

    </div>
    <?php
}
