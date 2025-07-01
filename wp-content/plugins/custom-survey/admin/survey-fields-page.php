<?php
add_action('admin_menu', function () {
    add_submenu_page(
        null, // görünür olmasın
        'Manage fields',
        'Manage fields',
        'manage_options',
        'custom-survey-fields',
        'custom_survey_fields_page'
    );
});

function custom_survey_fields_page(): void
{
    global $wpdb;

    $form_id = intval($_GET['form_id'] ?? 0);
    $form = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}survey_forms WHERE id = %d", $form_id));

    if (!$form) {
        echo '<div class="notice notice-error"><p>No survey found.</p></div>';
        return;
    }

    // Yeni alan eklendiğinde
    if (isset($_POST['custom_survey_add_field'])) {
        $label = sanitize_text_field($_POST['field_label']);
        $type = sanitize_text_field($_POST['field_type']);
        $required = isset($_POST['field_required']) ? 1 : 0;

        if (!empty($label)) {
            $wpdb->insert($wpdb->prefix . 'survey_fields', [
                'form_id' => $form_id,
                'label' => $label,
                'type' => $type,
                'required' => $required
            ]);
            echo '<div class="notice notice-success"><p>Field added</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Field title is required</p></div>';
        }
    }

    // Alan redaktə (update)
    if (isset($_POST['custom_survey_update_field']) && current_user_can('manage_options')) {
        $field_id = intval($_POST['edit_field_id']);
        $label = sanitize_text_field($_POST['edit_field_label']);
        $type = sanitize_text_field($_POST['edit_field_type']);
        $required = isset($_POST['edit_field_required']) ? 1 : 0;

        if (!empty($label)) {
            $wpdb->update($wpdb->prefix . 'survey_fields', [
                'label' => $label,
                'type' => $type,
                'required' => $required
            ], ['id' => $field_id]);
            echo '<div class="notice notice-success"><p>Field updated</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Field title is required</p></div>';
        }
    }

    // Alan silme
    if (isset($_GET['delete_field']) && current_user_can('manage_options')) {
        $field_id = intval($_GET['delete_field']);
        if (wp_verify_nonce($_GET['_wpnonce'], 'delete_field_' . $field_id)) {
            $wpdb->delete($wpdb->prefix . 'survey_fields', ['id' => $field_id]);
            echo '<div class="notice notice-success"><p>Field deleted</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Security verification failed.</p></div>';
        }
    }

    // Pasifleştirme
    if (isset($_GET['deactivate_field']) && current_user_can('manage_options')) {
        $field_id = intval($_GET['deactivate_field']);
        if (wp_verify_nonce($_GET['_wpnonce'], 'deactivate_field_' . $field_id)) {
            $wpdb->update($wpdb->prefix . 'survey_fields', ['is_active' => 0], ['id' => $field_id]);
            echo '<div class="notice notice-success"><p>The field has been deactivated.</p></div>';
        }
    }

    // Aktifleştirme
    if (isset($_GET['activate_field']) && current_user_can('manage_options')) {
        $field_id = intval($_GET['activate_field']);
        if (wp_verify_nonce($_GET['_wpnonce'], 'activate_field_' . $field_id)) {
            $wpdb->update($wpdb->prefix . 'survey_fields', ['is_active' => 1], ['id' => $field_id]);
            echo '<div class="notice notice-success"><p>The field is activated.</p></div>';
        }
    }

    // Zorunlu Yap
    if (isset($_GET['set_required']) && current_user_can('manage_options')) {
        $field_id = intval($_GET['set_required']);
        if (wp_verify_nonce($_GET['_wpnonce'], 'set_required_' . $field_id)) {
            $wpdb->update($wpdb->prefix . 'survey_fields', ['required' => 1], ['id' => $field_id]);
            echo '<div class="notice notice-success"><p>The field was made required.</p></div>';
        }
    }

    // Zorunlu Kaldır
    if (isset($_GET['unset_required']) && current_user_can('manage_options')) {
        $field_id = intval($_GET['unset_required']);
        if (wp_verify_nonce($_GET['_wpnonce'], 'unset_required_' . $field_id)) {
            $wpdb->update($wpdb->prefix . 'survey_fields', ['required' => 0], ['id' => $field_id]);
            echo '<div class="notice notice-success"><p>The field requirement has been removed.</p></div>';
        }
    }

    $fields = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}survey_fields WHERE form_id = %d",
        $form_id
    ));

    ?>
    <div class="wrap">
        <h1><?php echo esc_html($form->title); ?> → Manage fields</h1>
        <form method="POST">
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="field_label">Field title</label></th>
                    <td><input name="field_label" type="text" id="field_label" class="regular-text" required></td>
                </tr>
                <tr>
                    <th scope="row"><label for="field_type">Type</label></th>
                    <td>
                        <select name="field_type" id="field_type">
                            <option value="text">Text</option>
                            <option value="email">Email</option>
                            <option value="tel">Telefon</option>
                            <option value="file">Fayl</option>
                            <option value="number">Number</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Is required?</th>
                    <td><input type="checkbox" name="field_required"/></td>
                </tr>
            </table>
            <p>
                <input type="submit" name="custom_survey_add_field" class="button button-primary" value="Add field">
            </p>
        </form>
        <hr>
        <h2>Current fields</h2>
        <table class="widefat fixed">
            <thead>
            <tr>
                <th>Field title</th>
                <th>Type</th>
                <th>Is required</th>
                <th>Auto-save trigger</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($fields as $field): ?>
                <tr>
                    <td><?php echo esc_html($field->label); ?></td>
                    <td><?php echo esc_html($field->type); ?></td>
                    <td><?php echo $field->required ? '✔' : '✖'; ?></td>
                    <td>
                        <input type="radio" name="auto_save_trigger" class="auto-save-trigger-radio"
                            value="<?php echo $field->id; ?>"
                            <?php if ($field->auto_save_trigger) echo 'checked'; ?>
                            data-field-id="<?php echo $field->id; ?>"
                            style="width:18px;height:18px;vertical-align:middle;cursor:pointer;"
                        />
                    </td>
                    <td>
                        <!-- Redaktə (Edit) Butonu -->
                        <button type="button" class="button edit-field-btn" title="Edit" data-id="<?php echo $field->id; ?>"
                                data-label="<?php echo esc_attr($field->label); ?>"
                                data-type="<?php echo esc_attr($field->type); ?>"
                                data-required="<?php echo $field->required ? '1' : '0'; ?>">
                            ✏️
                        </button>
                        <!-- Zorunlu/Aktif Butonu -->
                        <?php if ($field->required): ?>
                            <a href="<?php echo wp_nonce_url(admin_url("admin.php?page=custom-survey-fields&form_id={$form_id}&unset_required={$field->id}"), 'unset_required_' . $field->id); ?>"
                               class="button" title="Optional">Opt</a>
                        <?php else: ?>
                            <a href="<?php echo wp_nonce_url(admin_url("admin.php?page=custom-survey-fields&form_id={$form_id}&set_required={$field->id}"), 'set_required_' . $field->id); ?>"
                               class="button" title="Required">Req</a>
                        <?php endif; ?>
                        <!-- Aktif/Pasif Butonu -->
                        <?php if ($field->is_active): ?>
                            <a href="<?php echo wp_nonce_url(admin_url("admin.php?page=custom-survey-fields&form_id={$form_id}&deactivate_field={$field->id}"), 'deactivate_field_' . $field->id); ?>"
                               class="button" title="Deactivate">Off</a>
                        <?php else: ?>
                            <a href="<?php echo wp_nonce_url(admin_url("admin.php?page=custom-survey-fields&form_id={$form_id}&activate_field={$field->id}"), 'activate_field_' . $field->id); ?>"
                               class="button" title="Activate">On</a>
                        <?php endif; ?>
                        <!-- Sil Butonu -->
                        <a href="<?php echo wp_nonce_url(admin_url("admin.php?page=custom-survey-fields&form_id={$form_id}&delete_field={$field->id}"), 'delete_field_' . $field->id); ?>"
                           class="button button-danger" title="Delete"
                           onclick="return confirm('Are you sure you want to delete this field?')">
                            <span style="font-size:16px;vertical-align:middle;display:inline-block;line-height:1;">Delete</span>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($fields)): ?>
                <tr>
                    <td colspan="5">No fields added yet.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
        <script>
        // AJAX ilə auto_save_trigger radio dəyişəndə DB-də yenilə
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.auto-save-trigger-radio').forEach(function(radio) {
                radio.addEventListener('change', function() {
                    var fieldId = this.getAttribute('data-field-id');
                    var formId = <?php echo intval($form_id); ?>;
                    var nonce = '<?php echo wp_create_nonce('custom_survey_auto_save_trigger'); ?>';
                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', ajaxurl, true);
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        location.reload();
                    xhr.onload = function() {
                        if (xhr.status === 200) {
                            // Success
                        }
                    };
                    xhr.send('action=custom_survey_set_auto_save_trigger&form_id=' + formId + '&field_id=' + fieldId + '&_wpnonce=' + nonce);
                });
            });
        });
        </script>
        <style>
        /* Modern table design for Current fields */
        #custom-fields-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background: #fff;
            box-shadow: 0 2px 12px rgba(0,0,0,0.07);
            border-radius: 12px;
            overflow: hidden;
            margin-top: 20px;
            font-size: 16px;
        }
        #custom-fields-table th, #custom-fields-table td {
            padding: 14px 18px;
            text-align: left;
        }
        #custom-fields-table th {
            background: #f5f7fa;
            color: #222;
            font-weight: 700;
            border-bottom: 2px solid #e3e8ee;
        }
        #custom-fields-table tr {
            transition: background 0.2s;
        }
        #custom-fields-table tr:nth-child(even) {
            background: #f9fbfd;
        }
        #custom-fields-table tr:hover {
            background: #e6f0fa;
        }
        #custom-fields-table td {
            border-bottom: 1px solid #f0f0f0;
            vertical-align: middle;
        }
        #custom-fields-table td:last-child {
            white-space: nowrap;
        }
        .button, .button.button-primary, .button.button-danger {
            border-radius: 4px !important;
            padding: 2px 10px !important;
            font-size: 13px !important;
            margin-right: 8px;
            min-width: 0;
            box-shadow: 0 1px 2px rgba(0,0,0,0.04);
            transition: background 0.2s, color 0.2s;
            line-height: 1.2;
        }
        .button.button-primary {
            background: #2563eb !important;
            color: #fff !important;
            border: none !important;
        }
        .button.button-danger {
            background: #ef4444 !important;
            color: #fff !important;
            border: none !important;
            font-weight: bold !important;
            box-shadow: 0 2px 8px rgba(239,68,68,0.10);
            border-radius: 6px !important;
            padding: 2px 12px !important;
            font-size: 15px !important;
            min-width: 0;
            display: inline-block;
            vertical-align: middle;
            transition: background 0.2s, color 0.2s, box-shadow 0.2s;
        }
        .button.button-danger:hover {
            background: #b91c1c !important;
            color: #fff !important;
            box-shadow: 0 4px 12px rgba(239,68,68,0.18);
        }
        .button:hover, .button.button-primary:hover, .button.button-danger:hover {
            filter: brightness(0.95);
            color: #111 !important;
        }
        .edit-field-btn {
            background: #fbbf24 !important;
            color: #222 !important;
            border: none !important;
        }
        .edit-field-btn:hover {
            background: #f59e42 !important;
            color: #111 !important;
        }
        .field-required-badge {
            display: inline-block;
            background: #ef4444;
            color: #fff;
            font-size: 12px;
            padding: 2px 8px;
            border-radius: 8px;
            margin-left: 6px;
            vertical-align: middle;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        .field-type-badge {
            display: inline-block;
            background: #2563eb;
            color: #fff;
            font-size: 12px;
            padding: 2px 8px;
            border-radius: 8px;
            margin-left: 6px;
            vertical-align: middle;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        </style>
    </div>
    <!-- Edit Modal və JS burada qalır -->
    <?php
}
?>

<?php
/**
 * AJAX: Set auto_save_trigger for a field (only one per form)
 */
add_action('wp_ajax_custom_survey_set_auto_save_trigger', function () {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('İcazə yoxdur');
    }
    check_ajax_referer('custom_survey_auto_save_trigger');

    global $wpdb;
    $form_id = intval($_POST['form_id'] ?? 0);
    $field_id = intval($_POST['field_id'] ?? 0);

    if (!$form_id || !$field_id) {
        wp_send_json_error('Parametrlər səhvdir');
    }

    // Bütün field-lar üçün sıfırla
    $wpdb->query($wpdb->prepare(
        "UPDATE {$wpdb->prefix}survey_fields SET auto_save_trigger = 0 WHERE form_id = %d",
        $form_id
    ));
    // Seçilən field üçün 1 et
    $wpdb->update(
        $wpdb->prefix . 'survey_fields',
        ['auto_save_trigger' => 1],
        ['id' => $field_id, 'form_id' => $form_id]
    );

    wp_send_json_success('Auto-save trigger yeniləndi');
});
