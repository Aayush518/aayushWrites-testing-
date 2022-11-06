
jQuery(function () {
    jQuery('#menu-settings').pointer({
        content: '<h3>' + objectcache_pointer.heading + '</h3><p>' + objectcache_pointer.content + '</p>',
        position: {
            edge: 'left',
            align: 'right',
        },
        pointerClass: 'wp-pointer arrow-top',
        pointerWidth: 420,
        close: function () {
            jQuery.post(window.ajaxurl, {
                action: 'dismiss-wp-pointer',
                pointer: 'objectcache-setting-pointer',
            });
        },
    }).pointer('open');
});
