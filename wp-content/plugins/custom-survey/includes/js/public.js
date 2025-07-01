// Ard-arda animasiya ilə inputların gəlməsi
jQuery(function($){
    var $fields = $('.custom-survey-fields .form-group');
    $fields.css('opacity', 0);
    $fields.each(function(i){
        var $el = $(this);
        setTimeout(function(){
            $el.addClass('survey-animate-pop');
            $el.css('opacity', 1);
        }, 160 * i);
    });
});
jQuery(document).ready(function ($) {
    // Auto-save trigger logic (field-id əsaslı)
    var $form = $('.custom-survey-form');
    var triggerFieldId = $form.attr('data-auto-save-trigger-field-id');
    if (triggerFieldId) {
        var $allFields = $form.find('input:not([type="hidden"]):not([type="submit"]), textarea');
        var $triggerInput = null;
        var triggerIndex = -1;
        $allFields.each(function(i){
            if ($(this).attr('data-field-id') == triggerFieldId) {
                $triggerInput = $(this);
                triggerIndex = i;
            }
        });
        if ($triggerInput && triggerIndex !== -1) {
            var isAutoSaved = false;
            function autoSaveForm() {
                if (isAutoSaved) return;
                var formData = new FormData($form[0]);
                formData.append('action', 'custom_survey_auto_submit');
                // Əgər öncəki response_id varsa, əlavə et
                var existingResponseId = $form.find('input[name="response_id"]').val();
                if (existingResponseId) {
                    formData.append('response_id', existingResponseId);
                }
                formData.append('trigger_field_id', triggerFieldId);
                $.ajax({
                    url: customSurveyAjax.ajax_url,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function (response) {
                        if (response.success && response.data && response.data.response_id) {
                            // DB-dəki ID-ni forma əlavə et
                            if (!$form.find('input[name="response_id"]').length) {
                                $('<input>').attr({
                                    type: 'hidden',
                                    name: 'response_id',
                                    value: response.data.response_id
                                }).appendTo($form);
                            }
                            isAutoSaved = true;
                            console.log('✅ Auto-saved:', response.data);
                        } else {
                            console.warn('Auto-save failed:', response.data);
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error('AJAX error:', error);
                    }
                });
            }
            // Növbəti inputa focus, blur və ya input olduqda auto-save et
            if ($allFields.length > triggerIndex + 1) {
                var $nextInput = $($allFields[triggerIndex + 1]);
                $nextInput.on('focus blur input', function () {
                    autoSaveForm();
                });
            }
            // File input üçün xüsusi: change və blur eventində də auto-save et
            if ($triggerInput.attr('type') === 'file') {
                $triggerInput.on('change blur', function () {
                    autoSaveForm();
                });
            }
            // Formdan kənara kliklənəndə və ya səhifə bağlananda da auto-save et
            $(document).on('mousedown touchstart', function (event) {
                if (
                    !$(event.target).closest($form).length &&
                    $triggerInput.val().trim() !== '' &&
                    !isAutoSaved
                ) {
                    autoSaveForm();
                }
            });
            window.addEventListener('beforeunload', function (e) {
                if (!isAutoSaved && $triggerInput.val().trim() !== '') {
                    autoSaveForm();
                }
            });
        }
    }
});
