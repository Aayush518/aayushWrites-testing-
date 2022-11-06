
window.addEventListener('load', function () {
    objectcache.analytics.init();
});

jQuery.extend(window.objectcache, {
    analytics: {
        charts: {},
        comboCharts: window.objectcache.comboCharts,
        options: {
            series: [],
            annotations: {
                position: 'back',
            },
            stroke: {
                width: 1,
                curve: 'straight',
            },
            dataLabels: {
                enabled: false,
            },
            legend: {
                show: false,
            },
            grid: {
                padding: {
                    top: -8,
                    right: 0,
                    left: 0,
                    bottom: -26,
                }
            },
            fill: {
                type: 'solid',
                opacity: 0.1,
            },
            noData: {
                text: 'Loading cache analytics...',
                align: 'center',
                verticalAlign: 'middle',
            },
            xaxis: {
                type: 'datetime',
                position: 'top',
                tickAmount: 3,
                tickPlacement: 'on',
                labels: {
                    offsetY: -3,
                    formatter: this.formatTimeTime,
                },
                axisBorder: {
                    show: false,
                },
                crosshairs: {
                    position: 'front',
                },
                tooltip: {
                    enabled: false,
                },
            },
        },

        init: function () {
            this.moveMetrics();
            this.renderCharts();
            this.fetchData();

            jQuery(document).on('postbox-toggled', function (event, postbox) {
                var $postbox = jQuery(postbox);
                var name = $postbox.find('.objectcache\\:chart').first().data('chart');

                if ($postbox.is(':visible')) {
                    this.charts[name].render();
                }
            }.bind(this));

            if (objectcache.refresh) {
                setInterval(
                    this.fetchData.bind(this),
                    objectcache.refresh * 1000
                );
            }
        },

        moveMetrics: function () {
            var screenOptions = document.getElementById('screen-options-wrap');

            if (! screenOptions) {
                return;
            }

            var metricsGroup = screenOptions.querySelector('.metrics-prefs');
            metricsGroup.classList.remove('hidden');

            screenOptions.querySelectorAll(
                '.metabox-prefs label[for*="objectcache_metric_"]'
            ).forEach(function (el) {
                metricsGroup.append(el);
            });
        },

        renderCharts: function () {
            document.querySelectorAll(
                '#dashboard-widgets .objectcache\\:chart'
            ).forEach(function (el) {
                var name = el.dataset.chart;
                var type = el.dataset.type;

                if (! this.comboCharts.hasOwnProperty(name)) {
                    this.charts[name] = this.setUpChart(el, name, type);
                } else {
                    this.charts[name] = this.setUpComboChart(el, name, JSON.parse(type));
                }

                this.charts[name].render();
            }.bind(this));
        },

        updateChartsMessage: function (message) {
            for (var chart in this.charts) {
                this.charts[chart].updateOptions({ noData: { text: message } }, false, false, false);
            }
        },

        setUpChart: function (el, id, type) {
            var color = (function (id) {
                switch (id.split('-')[0]) {
                    case 'redis':
                        return '#72d1a7';
                    case 'relay':
                        return '#7b90ff';
                    default:
                        return '#0096dd';
                }
            })(id);

            return new ApexCharts(el, Object.assign(this.options, {
                chart: {
                    id: id,
                    type: 'area',
                    group: 'analytics',
                    parentHeightOffset: 0,
                    toolbar: {
                        show: false,
                    },
                    animations: {
                        enabled: false,
                    },
                    zoom: {
                        enabled: false,
                    },
                },
                colors: [color],
                markers: {
                    strokeColors: color,
                    colors: [color],
                    showNullDataPoints: false,
                    hover: {
                        sizeOffset: 3,
                    },
                },
                tooltip: {
                    marker: {
                        show: false,
                    },
                    custom: this.renderTooltip.bind({ id: id, type: type }),
                },
                yaxis: {
                    forceNiceScale: true,
                    tickAmount: 3,
                    tooltip: {
                        enabled: false,
                    },
                    floating: true,
                    opposite: true,
                    labels: {
                        offsetX: -5,
                        offsetY: -5,
                        align: 'left',
                        formatter: this.formatLabel.bind({ id: id, type: type, compact: true }),
                    },
                    min: 0,
                    max: function (max) {
                        return this.type === 'ratio' ? (max > 100 ? 100 : max) : max;
                    }.bind({ id: id, type: type }),
                }
            }));
        },

        setUpComboChart: function (el, id, types) {
            var colors = (function (id) {
                switch (id.split('-')[0]) {
                    case 'redis':
                        return ['#72d1a7', '#98debf', '#4cc48f'];
                    case 'relay':
                        return ['#7b90ff', '#95a5ff', '#aebbff'];
                    default:
                        return ['#0096dd', '#11b3ff', '#0073aa'];
                }
            })(id);

            var type = types[function() { for (var key in types) return key; }()];

            return new ApexCharts(el, Object.assign(this.options, {
                chart: {
                    id: id,
                    type: 'line',
                    group: 'analytics',
                    parentHeightOffset: 0,
                    toolbar: {
                        show: false,
                    },
                    animations: {
                        enabled: false,
                    },
                    zoom: {
                        enabled: false,
                    },
                },
                colors: colors,
                fill: {
                    type: 'solid',
                    opacity: [0.1, 1, 1],
                },
                markers: {
                    showNullDataPoints: false,
                    hover: {
                        sizeOffset: 4,
                    },
                    colors: colors,
                },
                tooltip: {
                    marker: {
                        show: true,
                    },
                    custom: this.renderTooltip.bind({ id: id, type: types }),
                },
                yaxis: {
                    forceNiceScale: true,
                    tickAmount: 3,
                    tooltip: {
                        enabled: false,
                    },
                    floating: true,
                    opposite: true,
                    labels: {
                        offsetX: -5,
                        offsetY: -5,
                        align: 'left',
                        formatter: this.formatLabel.bind({ id: id, type: type, compact: true }),
                    },
                    min: 0,
                    max: function (max) {
                        return this.type === 'ratio' ? (max > 100 ? 100 : max) : max;
                    }.bind({ id: id, type: type }),
                }
            }));
        },

        updateCharts: function (response) {
            for (var chart in this.charts) {
                if (! response[0].hasOwnProperty(chart)) {
                    if (this.comboCharts.hasOwnProperty(chart)) {
                        this.comboCharts[chart].chart = this.charts[chart];
                    }

                    continue;
                }

                var series = [];

                objectcache.series.forEach(function (item) {
                    series.push({
                        name: item.name,
                        type: 'area',
                        data: response.map(function (interval) {
                            return {
                                x: interval.timestamp,
                                y: interval[chart][item.field],
                                date_display: interval.date_display,
                            };
                        }),
                    });
                });

                var measurements = series[0].data.filter(function (measurement) {
                    return measurement.y !== null;
                }).length;

                if (measurements < 2) {
                    this.charts[chart].updateOptions({ noData: { text: 'Waiting for more data...' } }, false, false, false);

                    continue;
                }

                this.charts[chart]
                    .updateSeries(series)
                    .then(function (chart) {
                        if (chart.annotations) {
                            chart.removeAnnotation('now');

                            chart.addXaxisAnnotation({
                                id: 'now',
                                x: response[0].timestamp,
                                x2: response[1].timestamp,
                                fillColor: '#e0e0e0',
                                borderColor: null,
                            });
                        }
                    });
            }

            this.updateComboCharts(response);
        },

        updateComboCharts: function (response) {
            for (var chart in this.comboCharts) {
                if (! this.charts.hasOwnProperty(chart)) {
                    continue;
                }

                var series = [];

                this.comboCharts[chart].containers.forEach(function (comboChart, index) {
                    objectcache.series.forEach(function (item) {
                        series.push({
                            name: comboChart + ' - ' + item.name,
                            type: parseInt(index) === 0 ? 'area' : 'line',
                            data: response.map(function (interval) {
                                return {
                                    x: interval.timestamp,
                                    y: interval[comboChart][item.field],
                                    date_display: interval.date_display,
                                };
                            }),
                        });
                    });
                });

                var measurements = series[0].data.filter(function (measurement) {
                    return measurement.y !== null;
                }).length;

                if (measurements < 2) {
                    this.comboCharts[chart].chart.updateOptions({ noData: { text: 'Waiting for more data...' } }, false, false, false);

                    continue;
                }

                this.charts[chart]
                    .updateSeries(series)
                    .then(function (chart) {
                        if (chart.annotations) {
                            chart.removeAnnotation('now');

                            chart.addXaxisAnnotation({
                                id: 'now',
                                x: response[0].timestamp,
                                x2: response[1].timestamp,
                                fillColor: '#e0e0e0',
                                borderColor: null,
                            });
                        }
                    });
            }
        },

        fetchData: function () {
            var fields = [
                'timestamp',
                'date_display',
            ];

            for (var chart in this.charts) {
                if (this.comboCharts.hasOwnProperty(chart)) {
                    this.comboCharts[chart].containers.forEach(function (container) {
                        objectcache.series.forEach(function (series) {
                            fields.push(container + '.' + series.field);
                        });
                    });

                    continue;
                }

                objectcache.series.forEach(function (series) {
                    fields.push(chart + '.' + series.field);
                });
            }

            jQuery
                .ajax({
                    url: objectcache.rest.url + 'objectcache/v1/analytics',
                    data: {
                        context: 'compute',
                        interval: objectcache.interval,
                        per_page: objectcache.per_page,
                        _fields: fields.filter(function (field, i) {
                            return fields.indexOf(field) == i;
                        }).join(','),
                    },
                    beforeSend: function (xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', objectcache.rest.nonce);
                    },
                })
                .done(
                    this.updateCharts.bind(this)
                )
                .error(function (error) {
                    console.log(error);

                    objectcache.analytics.updateChartsMessage('Unable to load cache analytics');
                });
        },

        formatTimeTime: function (value, timestamp, opts) {
            if (value < 990748800) {
                return;
            }

            var date = new Date(
                (timestamp + (objectcache.gmt_offset * 3600)) * 1000
            );

            var dateOffset = new Date(
                date.toISOString().substring(0, 19).replace('T', ' ') + ' GMT'
            );

            return opts.dateFormatter(dateOffset, 'HH:mm');
        },

        formatLabel: function (value) {
            if (value === null || value === Number.MIN_VALUE) {
                return 0;
            }

            if (this.id === 'ms-total' || this.id === 'response-times') {
                return Math.round(value) + ' ms';
            }

            if (this.id === 'ms-cache-median') {
                return value.toFixed(2) + ' ms';
            }

            if (this.type === 'integer') {
                if (value === 0) return value.toFixed(0);
                if (value < 1) return value.toFixed(2);
                if (value < 1e3) return value.toFixed(0);
                if (value >= 1e3 && value < 1e6) return +(value / 1e3).toFixed(1) + 'k';
                if (value >= 1e6 && value < 1e9) return +(value / 1e6).toFixed(1) + 'm';
                if (value >= 1e9 && value < 1e12) return +(value / 1e9).toFixed(1) + 'b';
                if (value >= 1e12) return +(value / 1e12).toFixed(1) + 't';
            }

            if (this.type === 'ratio') {
                return Math.round((value + Number.EPSILON) * 100) / 100 + '%';
            }

            if (this.type === 'time') {
                return Math.round((value + Number.EPSILON) * 100) / 100 + ' ms';
            }

            if (this.type === 'bytes') {
                var i = value === 0 ? 0 : Math.floor(Math.log(value) / Math.log(1024));

                return parseFloat((value / Math.pow(1024, i)).toFixed(this.compact ? 0 : 2)) + ' ' + ['B', 'KB', 'MB', 'GB', 'TB', 'PB'][i];
            }

            return value;
        },

        renderTooltip: function (opts) {
            var w = opts.w;
            var dataPointIndex = opts.dataPointIndex;

            var context = this;
            var analytics = objectcache.analytics;

            var values = w.config.series.map(function (series, index) {
                if (series.data[dataPointIndex].y === null) {
                    return '';
                }

                var marker = '';
                var seriesName = series.name;
                var label = analytics.formatLabel.call(context, series.data[dataPointIndex].y);

                if (analytics.comboCharts.hasOwnProperty(context.id)) {
                    var comboContext = analytics.comboCharts[context.id].containers.map(function (chart) {
                        return { id: chart, type: context.type[chart] };
                    }).filter(function (chart) {
                        return series.name.indexOf(chart.id + ' - ') === 0;
                    })[0];

                    marker = '<span class="apexcharts-tooltip-marker" style="background-color: ' + w.config.colors[index] + ';"></span>';
                    label = analytics.formatLabel.call(comboContext, series.data[dataPointIndex].y);
                    seriesName = analytics.comboCharts[context.id].labels[comboContext.id] + ' ' + series.name.substr(series.name.lastIndexOf('-'));
                }

                return '<div>' + marker + '<em>' + seriesName + ': ' + label + '</em></div>';
            });

            var date = w.config.series[0].data[dataPointIndex].date_display.date;
            var time = w.config.series[0].data[dataPointIndex].date_display.time;

            return '<div class="objectcache:chart-tooltip">' +
                '<div><small>' + date + '</small></div>' +
                '<div><strong>' + time + '</strong></div>' +
                values.join('') +
            '</div>';
        },
    },
});
