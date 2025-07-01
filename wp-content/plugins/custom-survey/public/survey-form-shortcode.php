<?php
function custom_survey_enqueue_styles(): void
{
    // CSS
    wp_enqueue_style('custom-survey-style', plugin_dir_url(__FILE__) . 'css/custom-survey-style.css', [], '1.0', 'all');
    wp_enqueue_style('custom-survey-form-style', plugin_dir_url(__FILE__) . 'css/custom-survey-form.css', [], '1.0', 'all');
    wp_enqueue_style('intl-tel-input-css', 'https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.19/css/intlTelInput.css');

    // JS
    wp_enqueue_script('intl-tel-input-js', 'https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.19/js/intlTelInput.min.js', ['jquery'], null, true);
    wp_enqueue_script('intl-tel-utils', 'https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.19/js/utils.js', [], null, true);
    // JS-i enqueue et
    wp_enqueue_script(
        'custom-survey-public-js',
        plugins_url('includes/js/public.js', dirname(__FILE__)),
        ['jquery'],
        '1.0',
        true
    );

    // AJAX üçün customSurveyAjax obyektini əlavə et
    wp_localize_script('custom-survey-public-js', 'customSurveyAjax', [
        'ajax_url' => admin_url('admin-ajax.php')
    ]);
}

add_action('wp_enqueue_scripts', 'custom_survey_enqueue_styles');

function custom_phone_input_shortcode()
{
    ob_start();
    ?>
    <form method="POST" class="custom-survey-form">
        <input type="tel" id="phone" name="phone" class="custom-phone-input" placeholder="Telefon Numaranız"/>

        <button type="submit">Gönder</button>
    </form>
    <?php
    return ob_get_clean();
}

add_shortcode('phone_input', 'custom_phone_input_shortcode');
add_action('wp_footer', function () {
    ?>
    <script>
        jQuery(document).ready(function ($) {
            const $phoneInput = $("#phone");

            if ($phoneInput.length > 0 && window.intlTelInput) {
                const iti = window.intlTelInput($phoneInput[0], {
                    initialCountry: "auto",
                    geoIpLookup: function (callback) {
                        $.get("https://ipinfo.io", function () {
                        }, "jsonp").always(function (resp) {
                            const countryCode = (resp && resp.country) ? resp.country : "us";
                            callback(countryCode);
                        });
                    },
                    utilsScript: "https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.19/js/utils.js"
                });

                $(".custom-survey-form").on("submit", function (e) {
                    const isValid = iti.isValidNumber();
                    if (!isValid) {
                        alert("Lütfen geçerli bir telefon numarası girin!");
                        e.preventDefault();
                        return false;
                    }

                    const formattedNumber = iti.getNumber(); // + ile başlayan
                    console.log("Gönderilecek numara:", formattedNumber);

                    // Gizli input olarak ekle
                    $('<input>').attr({
                        type: 'hidden',
                        name: 'formatted_phone',
                        value: formattedNumber
                    }).appendTo(this);
                });
            }
        });
    </script>
    <?php
});
// Shortcode tanımı
// [custom_survey id="1"]
function custom_survey_form_shortcode($atts)
{
    remove_filter('the_content', 'wpautop');
    remove_filter('the_excerpt', 'wpautop');
    ob_start();

    $atts = shortcode_atts(['id' => 0], $atts);
    $form_id = intval($atts['id']);
    $message = null;

    if (!$form_id) return 'Form ID not specified.';

    global $wpdb;

    $form = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}survey_forms WHERE id = %d", $form_id));
    if (!$form) $message = 'Form not found.';

    $fields = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}survey_fields WHERE form_id = %d AND is_active = 1",
        $form_id
    ));

    if (empty($fields)) $message = '<div class="notice notice-error custom-error-message"><p>Form not found.</p></div>';

    if (isset($_GET['submitted'])) {
        $message = '<div class="notice notice-success custom-success-message"><p>The form was sent successfully.</p></div>';
    }
    ?>
    <h3 style="text-align: center"><?php echo $form->title; ?></h3>
    <?php
    // Auto-save trigger index tap
    $auto_save_trigger_index = null;
    foreach ($fields as $i => $field) {
        if (!empty($field->auto_save_trigger)) {
            $auto_save_trigger_index = $i;
            break;
        }
    }
    ?>
    <?php
    // Auto-save trigger field_id tap
    $auto_save_trigger_field_id = null;
    foreach ($fields as $field) {
        if (!empty($field->auto_save_trigger)) {
            $auto_save_trigger_field_id = $field->id;
            break;
        }
    }
    ?>
    <form method="POST" enctype="multipart/form-data" class="custom-survey-form"
        data-auto-save-trigger-field-id="<?php echo $auto_save_trigger_field_id !== null ? $auto_save_trigger_field_id : ''; ?>">
        <?php
        if (isset($message)) {
            echo $message;
        }
        ?>
        <input type="hidden" name="form_id" value="<?php echo esc_attr($form_id); ?>">
        <div class="custom-survey-fields">
        <?php foreach ($fields as $field): ?>
            <div class="form-group survey-animate-pop" style="margin-bottom: 1rem;">
                <label for="field_<?php echo esc_attr($field->id); ?>" style="font-weight: bold;">
                    <?php echo esc_html($field->label); ?>
                    <?php if ($field->required): ?>
                        <span style="color:red;">*</span>
                    <?php endif; ?>
                    <?php if (!empty($field->placeholder)): ?>
                        <small style="color:#666;"><?php echo esc_html($field->placeholder); ?></small>
                    <?php endif; ?>
                </label>

                <?php
                // Yalnız bu typeları dəstəklə: text, email, tel, file, number
                $input_type = 'text';
                switch ($field->type) {
                    case 'email':
                        $input_type = 'email';
                        break;
                    case 'phone':
                        $input_type = 'tel';
                        break;
                    case 'file':
                        $input_type = 'file';
                        break;
                    case 'number':
                        $input_type = 'number';
                        break;
                    default:
                        $input_type = 'text';
                }
                ?>

                <?php if ($input_type === 'file'): ?>
                    <input type="file"
                           id="field_<?php echo esc_attr($field->id); ?>"
                           name="field_<?php echo esc_attr($field->id); ?>"
                           class="form-control"
                           data-field-id="<?php echo esc_attr($field->id); ?>"
                        <?php if ($field->required) echo 'required'; ?> />
                <?php else: ?>
                    <input type="<?php echo esc_attr($input_type); ?>"
                           id="field_<?php echo esc_attr($field->id); ?>"
                           name="field_<?php echo esc_attr($field->id); ?>"
                           class="form-control<?php echo $input_type === 'tel' ? ' custom-phone-input' : ''; ?>"
                           data-field-id="<?php echo esc_attr($field->id); ?>"
                        <?php if ($field->required) echo 'required'; ?>
                           placeholder="<?php echo esc_attr($field->placeholder ?? ''); ?>"/>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        </div>
        <button type="submit"
                name="custom_survey_submit">
            Send
        </button>
    </form>
    <?php // echo do_shortcode('[phone_input]'); ?>

    <?php
    $output = ob_get_clean();
    return shortcode_unautop($output); // <- Bu satır önemli
}

add_shortcode('custom_survey', 'custom_survey_form_shortcode');
