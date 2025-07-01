<?php
// Admin menüsüne Anketler ekle
add_action('admin_menu', function () {
    add_menu_page(
        'Survey Forms',                // Sayfa başlığı
        'Survey Forms',                // Menü adı
        'manage_options',          // Yetki
        'custom-survey',           // Slug
        'custom_survey_admin_page',// Sayfa render fonksiyonu
        'dashicons-feedback',      // Menü ikonu
        26                         // Menü sırası
    );
});

// Sayfa render fonksiyonu
function custom_survey_admin_page(): void
{
    global $wpdb;

    // Form gönderildiyse kayıt et
    if (isset($_POST['custom_survey_add_form'])) {
        $title = sanitize_text_field($_POST['form_title']);
        if (!empty($title)) {
            $wpdb->insert($wpdb->prefix . 'survey_forms', ['title' => $title]);
            echo '<div class="notice notice-success"><p>Form created successfully.</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Title is required!</p></div>';
        }
    }

    if (isset($_GET['delete_form']) && current_user_can('manage_options')) {
        $delete_id = intval($_GET['delete_form']);
        if (wp_verify_nonce($_GET['_wpnonce'], 'delete_form_' . $delete_id)) {
            $wpdb->delete($wpdb->prefix . 'survey_forms', ['id' => $delete_id]);

            wp_redirect(admin_url('admin.php?page=custom-survey'));
        } else {
            echo '<div class="notice notice-error"><p>Security error</p></div>';
        }
    }

    if (isset($_POST['update_form_title_button']) && current_user_can('manage_options')) {
        $form_id = intval($_POST['form_id']);
        $new_title = sanitize_text_field($_POST['new_title']);

        if (wp_verify_nonce($_POST['_wpnonce'], 'update_form_title_' . $form_id)) {
            $wpdb->update(
                $wpdb->prefix . 'survey_forms',
                ['title' => $new_title],
                ['id' => $form_id]
            );
            echo '<div class="notice notice-success"><p>Form title updated</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Security verification failed</p></div>';
        }
    }

    $per_page = 10;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $per_page;

    $total = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}survey_forms");
    $forms = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}survey_forms ORDER BY id DESC LIMIT %d OFFSET %d",
            $per_page,
            $offset
        )
    );

    // Var olan anketleri getir
    //$forms = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}survey_forms");

    ?>

    <div class="wrap">
        <h1>Surveys</h1>

        <h2>Add new survey</h2>
        <form method="POST">
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="form_title">Survey Title</label></th>
                    <td><input name="form_title" type="text" id="form_title" class="regular-text" required></td>
                </tr>
            </table>
            <p>
                <input type="submit" name="custom_survey_add_form" class="button button-primary" value="Create">
            </p>
        </form>

        <hr>

        <h2>Current Surveys</h2>
        <table class="widefat">
            <thead>
            <tr>
                <th>#</th>
                <th>Title</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($forms as $index => $form): ?>
                <tr>
                    <td><?php echo esc_attr($form->id); ?></td>
                    <td>
                        <form method="post" style="display: flex; gap: 8px; align-items: center;">
                            <?php wp_nonce_field('update_form_title_' . $form->id); ?>
                            <input type="hidden" name="form_id" value="<?php echo esc_attr($form->id); ?>">
                            <input type="text" name="new_title" value="<?php echo esc_attr($form->title); ?>"
                                   class="regular-text">
                            <input type="submit" name="update_form_title_button" class="button" value="Save">
                        </form>
                    </td>
                    <td>
                        <a href="<?php echo admin_url('admin.php?page=custom-survey-fields&form_id=' . $form->id); ?>"
                           class="button">Fields</a>
                        <a href="<?php echo admin_url('admin.php?page=custom-survey-responses&form_id=' . $form->id); ?>"
                           class="button">Answers</a>
                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=custom-survey&delete_form=' . $form->id), 'delete_form_' . $form->id); ?>"
                           class="button button-danger" onclick="return confirm('Bu anketi silmek istediğinize emin misiniz?')">Delete</a>
                    </td>

                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
        $total_pages = ceil($total / $per_page);
        if ($total_pages > 1):
            echo '<div class="tablenav"><div class="tablenav-pages">';
            for ($i = 1; $i <= $total_pages; $i++) {
                $url = add_query_arg('paged', $i, admin_url('admin.php?page=custom-survey'));
                echo '<a class="button' . ($i === $current_page ? ' current' : '') . '" href="' . esc_url($url) . '">' . $i . '</a> ';
            }
            echo '</div></div>';
        endif;
        ?>

    </div>

    <?php
}
