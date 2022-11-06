window.addEventListener('load', function () {
    window.objectcache.groups.init();
    window.objectcache.latency.init();
    window.objectcache.flushlog.init();
});

jQuery.extend(window.objectcache, {
    latency: {
        init: function () {
            this.fetchData();
            setInterval(this.fetchData, 10000);
        },

        fetchData: function () {
            jQuery
                .ajax({
                    url: objectcache.rest.url + 'objectcache/v1/latency',
                    beforeSend: function (xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', objectcache.rest.nonce);
                    },
                })
                .done(function (data) {
                    var widget = document.querySelector('.objectcache\\:latency-widget');

                    var table = widget.querySelector('table');
                    table && widget.removeChild(table);

                    var error = widget.querySelector('.error');
                    error && widget.removeChild(error);

                    table = document.createElement('table');
                    widget.prepend(table);

                    var content = '';

                    var formatLatency = function (us) {
                        if (us < 500) return '<strong>' + us + '</strong> μs';
                        if (us < 1000) return '<strong class="warning">' + us + '</strong> μs';
                        return '<strong class="error">' + Math.round((us / 1000 + Number.EPSILON) * 100) / 100 + '</strong> ms';
                    };

                    data.forEach(function (item) {
                        content += '<tr>';
                        content += '  <td>' + item.url + '</td>';
                        content += '  <td>';
                        content += item.error ? '<span class="error">' + item.error + '</span>' : formatLatency(item.latency);
                        content += '  </td>';
                        content += '</tr>';
                    });

                    document.querySelector('.objectcache\\:latency-widget table').innerHTML = content;
                })
                .error(function (error) {
                    var widget = document.querySelector('.objectcache\\:latency-widget');

                    var table = widget.querySelector('table');
                    table && widget.removeChild(table);

                    var container = widget.querySelector('.error');

                    if (! container) {
                        container = document.createElement('p');
                        container.classList.add('error');

                        widget.append(container);
                    }

                    if (error.responseJSON && error.responseJSON.message) {
                        container.textContent = error.responseJSON.message;
                    } else {
                        container.textContent = 'Request failed (' + error.status + ').';
                    }
                });
        },
    },

    groups: {
        init: function () {
            document.querySelector('.objectcache\\:groups-widget button')
                .addEventListener('click', window.objectcache.groups.fetchData);

            if (! ClipboardJS.isSupported()) {
                return;
            }

            var widget = document.querySelector('.objectcache\\:groups-widget');
            var copyButton = widget.querySelector('.button[data-clipboard-target]');
            var clipboard = new ClipboardJS(copyButton);

            clipboard.on('success', function (event) {
                event.clearSelection();
                copyButton.textContent = copyButton.dataset.copied;

                setTimeout(function () {
                    copyButton.textContent = copyButton.dataset.text;
                }, 3000);
            });

            clipboard.on('error', function (event) {
                event.clearSelection();

                window.alert('Sorry, something went wrong.');
            });
        },

        fetchData: function () {
            var widget = document.querySelector('.objectcache\\:groups-widget');

            var button = widget.querySelector('.button');
            button.blur();
            button.classList.add('disabled');
            button.textContent = button.dataset.loading;

            var copy = widget.querySelector('.button[data-clipboard-target]');
            copy.classList.add('hidden');

            var container = widget.querySelector('.table-container');
            container && widget.removeChild(container);

            var error = widget.querySelector('.error');
            error && widget.removeChild(error);

            jQuery
                .ajax({
                    url: objectcache.rest.url + 'objectcache/v1/groups',
                    beforeSend: function (xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', objectcache.rest.nonce);
                    },
                })
                .done(function (data) {
                    var info = widget.querySelector('p:first-child');
                    info && widget.removeChild(info);

                    var container = document.createElement('div');
                    container.classList.add('table-container');
                    widget.prepend(container);

                    var table = document.createElement('table');
                    container.prepend(table);

                    var content = '';

                    if (data.length) {
                        data.forEach(function (item) {
                            content += '<tr title="' + item.count + ' objects found in `' + item.group + '` group">';
                            content += '  <td>' + item.group + '</td>';
                            content += '  <td>';
                            content += '    <strong>' + item.count + '</strong>';
                            content += '  </td>';
                            content += '</tr>';
                        });

                        ClipboardJS.isSupported() && copy.classList.remove('hidden');
                    } else {
                        content += '<tr>';
                        content += '  <td colspan="2">No cache groups found.</td>';
                        content += '</tr>';
                    }

                    table.innerHTML = content;
                })
                .error(function (error) {
                    var container = widget.querySelector('.error');

                    if (! container) {
                        container = document.createElement('p');
                        container.classList.add('error');

                        widget.append(container);
                    }

                    if (error.responseJSON && error.responseJSON.message) {
                        container.textContent = error.responseJSON.message;
                    } else {
                        container.textContent = 'Request failed (' + error.status + ').';
                    }
                })
                .always(function () {
                    var button = widget.querySelector('.objectcache\\:groups-widget .button');
                    button.textContent = button.dataset.text;
                    button.classList.remove('disabled');
                });
        },
    },

    flushlog: {
        init: function () {
            var input = document.querySelector('.objectcache\\:flushlog-widget input');

            if (input) {
                input.addEventListener('click', window.objectcache.flushlog.save);
            }
        },

        save: function (event) {
            event.target.disabled = true;

            jQuery
                .ajax({
                    type: 'POST',
                    url: objectcache.rest.url + 'objectcache/v1/options',
                    data: {
                        flushlog: event.target.checked ? 1 : 0,
                    },
                    beforeSend: function (xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', objectcache.rest.nonce);
                    },
                })
                .error(function (error) {
                    if (error.responseJSON && error.responseJSON.message) {
                        window.alert(error.responseJSON.message);
                    } else {
                        window.alert('Request failed (' + error.status + ').');
                    }
                })
                .always(function () {
                    event.target.disabled = false;
                });
        },
    },
});
