(function ($) {
    $(document).ready(function () {
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
                    nonce: iDrivee2Media.nonce,
                },
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
    });
})(jQuery);
