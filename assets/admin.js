(function ($) {
    $(document).ready(function () {
        $('#idrivee2-test-button').on('click', function (e) {
            e.preventDefault();
            const $result = $('#idrivee2-test-result');
            $result
                .removeClass('notice-error notice-success')
                .text('<?php // Placeholder, will be overridden ?>');

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
                });
        });
    });
})(jQuery);
