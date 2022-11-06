window.addEventListener('load', function () {
    window.objectcache.options.init();
});

jQuery.extend(window.objectcache, {
    options: {
        init: function () {
            document.querySelector('#objectcache\\:options input[name="submit"]')
                .addEventListener('click', window.objectcache.options.submit);
        },

        submit: function (event) {
            event.preventDefault();
            event.target.disabled = true;

            window.objectcache.options.dismissAdminNotice();

            var form = document.querySelector('#objectcache\\:options');

            jQuery
                .ajax({
                    type: 'POST',
                    url: objectcache.rest.url + 'objectcache/v1/options',
                    data: new FormData(form),
                    processData: false,
                    contentType: false,
                    beforeSend: function (xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', objectcache.rest.nonce);
                    },
                })
                .done(function () {
                    window.objectcache.options.addAdminNotice({
                        message: 'Settings saved.',
                        class: 'notice-success',
                    });
                })
                .error(function (error) {
                    var message = 'Request failed (' + error.status + ').';

                    if (error.responseJSON && error.responseJSON.message) {
                        message = error.responseJSON.message; // Use `error.responseJSON.additional_errors` as well?
                    }

                    window.objectcache.options.addAdminNotice({
                        message: message,
                        class: 'notice-error',
                    });
                })
                .always(function () {
                    event.target.disabled = false;
                });
        },

        addAdminNotice: function (data) {
            data.id = 'objectcache:options-notice';
            data.className = data.class + ' settings-error is-dismissible';

            var noticeHtml = wp.template('objectcache-options-notice')(data);
            var $notice = jQuery('#objectcache\\:options-notice');

            if ($notice.length) {
                $notice.replaceWith(noticeHtml);
            } else {
                jQuery('.wrap > h1').after(noticeHtml);
            }

            jQuery(document).trigger('wp-updates-notice-added');
        },

        dismissAdminNotice: function () {
            jQuery('#objectcache\\:options-notice .notice-dismiss').click();
        },
    },
});
