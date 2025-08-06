(function ($) {
    $(document).ready(function () {
        // Test connection handler
        $('#idrivee2-test-button').on('click', function (e) {
            e.preventDefault();

            var $button = $(this);
            var $result = $('#idrivee2-test-result');

            $button.prop('disabled', true).text(iDrivee2Media.testingLabel);
            $result.removeClass('notice notice-success notice-error').text('');

            $.ajax({
                url: iDrivee2Media.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'idrivee2_test_connection',
                    nonce: iDrivee2Media.nonce
                }
            })
            .done(function (response) {
                if (response.success) {
                    $result
                        .addClass('notice notice-success')
                        .text(response.data);
                } else {
                    $result
                        .addClass('notice notice-error')
                        .text(response.data);
                }
            })
            .fail(function (jqXHR, textStatus) {
                $result
                    .addClass('notice notice-error')
                    .text(textStatus || 'AJAX error');
            })
            .always(function () {
                $button.prop('disabled', false).text(iDrivee2Media.buttonLabel);
            });
        });

        // Upload test file handler
        $('#idrivee2-upload-button').on('click', function (e) {
            e.preventDefault();

            var $button = $(this);
            var $result = $('#idrivee2-test-result');

            $button.prop('disabled', true).text(iDrivee2Media.uploadingLabel);
            $result.removeClass('notice notice-success notice-error').text('');

            // Create file with content "test"
            var content = 'test';
            var blob = new Blob([content], { type: 'text/plain' });
            var file = new File([blob], 'test.txt', { type: 'text/plain' });

            var formData = new FormData();
            formData.append('action', 'idrivee2_upload_test_file');
            formData.append('nonce', iDrivee2Media.nonce);
            formData.append('file', file);

            $.ajax({
                url: iDrivee2Media.ajaxUrl,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false
            })
            .done(function (response) {
                var text = JSON.stringify(response, null, 2);
                $result
                    .addClass(response.success ? 'notice notice-success' : 'notice notice-error')
                    .text(text);
            })
            .fail(function (jqXHR, textStatus) {
                $result
                    .addClass('notice notice-error')
                    .text(textStatus || 'AJAX error');
            })
            .always(function () {
                $button.prop('disabled', false).text(iDrivee2Media.uploadButtonLabel);
            });
        });
    });
})(jQuery);