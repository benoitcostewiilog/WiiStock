let datatableColis;
let datatableLoading = false;

let chartArrivalUm;
let chartAssoRecep;
let chartDailyArrival;
let chartWeeklyArrival;
let chartColis;
let chartMonetaryFiability;
let chartFirstForAdmin;
let chartSecondForAdmin;

$(function () {
    // config chart js
    Chart.defaults.global.defaultFontFamily = 'Myriad';
    Chart.defaults.global.responsive = true;
    Chart.defaults.global.maintainAspectRatio = false;
    //// charts monitoring réception arrivage
    drawChartWithHisto($('#chartArrivalUm'), 'get_arrival_um_statistics').then((chart) => {
        chartArrivalUm = chart;
    });
    drawChartWithHisto($('#chartAssocRecep'), 'get_asso_recep_statistics').then((chart) => {
        chartAssoRecep = chart;
    });
    //// charts monitoring réception quai
    drawSimpleChart($('#chartDailyArrival'), 'get_daily_arrivals_statistics').then((chart) => {
        chartDailyArrival = chart;
    });
    drawSimpleChart($('#chartWeeklyArrival'), 'get_weekly_arrivals_statistics').then((chart) => {
        chartWeeklyArrival = chart;
    });
    drawSimpleChart($('#chartColis'), 'get_daily_packs_statistics').then((chart) => {
        chartColis = chart;
    });
    drawSimpleChart($('#chartMonetaryFiability'), 'get_monetary_fiability_statistics').then((chart) => {
        chartMonetaryFiability = chart;
    });
    drawMultipleBarChart($('#chartFirstForAdmin'), 'get_encours_count_by_nature_and_timespan', {graph: 1}, 1).then((chart) => {
        chartFirstForAdmin = chart;
    });
    drawMultipleBarChart($('#chartSecondForAdmin'), 'get_encours_count_by_nature_and_timespan', {graph: 2}, 2).then((chart) => {
        chartSecondForAdmin = chart;
    });

    loadRetards();
    refreshIndicatorsReceptionDock();
    refreshIndicatorsReceptionAdmin();
    updateCarriers();
    initTooltips($('.has-tooltip'));

    let reloadFrequency = 1000 * 60 * 15;
    setInterval(reloadDashboards, reloadFrequency);

    let $indicators = $('#indicators');
    $('#btnIndicators').mouseenter(function () {
        $indicators.fadeIn();
    });
    $('#blocIndicators').mouseleave(function () {
        $indicators.fadeOut();
    });

    $(document).on('keydown', function(e) {
        let activeBtn = $('#carouselIndicators').find('[data-slide-to].active');
        if (e.which === 37) {
            activeBtn.prev('li').click()
        } else if (e.which === 39) {
            activeBtn.next('li').click()
        }
    })
});

function reloadDashboards() {
    if (datatableColis) {
        datatableColis.ajax.reload();
    }
    updateCharts();
    updateCarriers();
    refreshIndicatorsReceptionDock();
    refreshIndicatorsReceptionAdmin();

    let now = new Date();
    $('.refreshDate').text(('0' + (now.getDate() + 1)).slice(-2) + '/' + ('0' + (now.getMonth() + 1)).slice(-2) + '/' + now.getFullYear() + ' à ' + now.getHours() + ':' + now.getMinutes());
}

function updateCharts() {
    drawChartWithHisto($('#chartArrivalUm'), 'get_arrival_um_statistics', 'now', chartArrivalUm);
    drawChartWithHisto($('#chartAssocRecep'), 'get_asso_recep_statistics', 'now', chartAssoRecep);
    drawSimpleChart($('#chartDailyArrival'), 'get_daily_arrivals_statistics', chartDailyArrival);
    drawSimpleChart($('#chartWeeklyArrival'), 'get_weekly_arrivals_statistics', chartWeeklyArrival);
    drawSimpleChart($('#chartColis'), 'get_daily_packs_statistics', chartColis);
    drawSimpleChart($('#chartMonetaryFiability'), 'get_monetary_fiability_statistics', chartMonetaryFiability);
    drawMultipleBarChart($('#chartFirstForAdmin'), 'get_encours_count_by_nature_and_timespan', {graph: 1}, 1, chartFirstForAdmin);
    drawMultipleBarChart($('#chartSecondForAdmin'), 'get_encours_count_by_nature_and_timespan', {graph: 2}, 2, chartSecondForAdmin);
}

function updateSimpleChartData(
    chart,
    data,
    {lastColor, lastLabel,  label } = {lastColor: undefined, lastLabel: undefined, label: undefined},
    {data: subData, label: lineChartLabel} = {data: undefined, label: undefined}) {
    chart.data.datasets = [{data: [], label}];
    chart.data.labels = [];
    const dataKeys = Object.keys(data);
    for (const key of dataKeys) {
        chart.data.labels.push(key);
        chart.data.datasets[0].data.push(data[key]);
    }


    const dataLength = chart.data.datasets[0].data.length;
    if (dataLength > 0) {
        chart.data.datasets[0].backgroundColor = new Array(dataLength);
        chart.data.datasets[0].backgroundColor.fill('#A3D1FF');
        chart.data.datasets[0].backgroundColor[dataLength - 1] = lastColor;
    }

    if (subData) {
        const subColor = '#e0e0e0';
        chart.data.datasets.push({
            label: lineChartLabel,
            backgroundColor: (new Array(dataLength)).fill(subColor),
            data: Object.values(subData)
        });

        chart.legend.display = true;

        const legendConfig = [
            {
                label,
                color: '#A3D1FF'
            },
            ...((lastLabel && lastColor)
                ? [{
                    label: lastLabel,
                    color: lastColor
                }]
                : []),
            {
                label: lineChartLabel,
                color: subColor
            }
        ];


        const $legendContainer = $(chart.canvas).parent().siblings('.custom-chart-legend');
        $legendContainer.html($('<ul/>', {
            class: 'd-flex justify-content-center align-items-center',
            html: legendConfig.map(({label, color}) => (
                $('<li/>', {
                    class: 'd-flex justify-content-center align-items-center',
                    html: [
                        $('<span/>', {class: 'chart-legend-color', style: `background-color: ${color}`}),
                        $('<span/>', {class: 'chart-legend-label', text: label})
                    ]
                })
            ))
        }));

    }



    chart.update();
}

/**
 * @param chart
 * @param chartData
 * @param chartColors boolean or Object.<Nature, Color>
 */
function updateMultipleChartData(chart, chartData, chartColors) {
    chart.data.labels = [];
    chart.data.datasets = [];

    const dataKeys = Object.keys(chartData);
    for (const key of dataKeys) {
        const dataSubKeys = Object.keys(chartData[key]);
        chart.data.labels.push(key);
        for (const subKey of dataSubKeys) {
            let dataset = chart.data.datasets.find(({label}) => (label === subKey));
            if (!dataset) {
                dataset = {
                    label: subKey,
                    backgroundColor: (chartColors
                        ? (
                            (chartColors && chartColors[subKey])
                            || (`#${((1 << 24) * Math.random() | 0).toString(16)}`)
                        )
                        : '#a3d1ff'
                    ),
                    data: []
                };
                chart.data.datasets.push(dataset);
            }
            dataset.data.push(chartData[key][subKey]);
        }
    }
    chart.update();
}

function drawSimpleChart($canvas, path, chart = null) {
    return new Promise(function (resolve) {
        if ($canvas.length == 0) {
            resolve();
        } else {
            $.get(Routing.generate(path), function (data) {
                if (!chart) {
                    chart = newChart($canvas);
                }

                updateSimpleChartData(
                    chart,
                    data.data || data,
                    {
                        lastColor: '#39B54A',
                        lastLabel: data.data && data.lastLabel,
                        label:  data.data && data.label
                    },
                    {
                        data: data.subCounters,
                        label: data.subLabel
                    });
                resolve(chart);
            });
        }
    });
}

function drawChartWithHisto($button, path, beforeAfter = 'now', chart = null) {
    return new Promise(function (resolve) {
        if ($button.length == 0) {
            resolve();
        } else {
            let $dashboardBox = $button.closest('.dashboard-box');
            let $rangeBtns = $dashboardBox.find('.range-buttons');
            let $firstDay = $rangeBtns.find('.firstDay');
            let $lastDay = $rangeBtns.find('.lastDay');

            let $canvas = $dashboardBox.find('canvas');

            let params = {
                'firstDay': $firstDay.data('day'),
                'lastDay': $lastDay.data('day'),
                'beforeAfter': beforeAfter
            };
            $.get(Routing.generate(path), params, function (data) {
                $firstDay.text(data.firstDay);
                $firstDay.data('day', data.firstDayData);
                $lastDay.text(data.lastDay);
                $lastDay.data('day', data.lastDayData);

                $rangeBtns.removeClass('d-none');

                if (!chart) {
                    chart = newChart($canvas);
                }

                const chartData = Object.keys(data.data).reduce((previous, currentKeys) => {
                    previous[currentKeys] = (data.data[currentKeys].count || data.data[currentKeys] || 0);
                    return previous;
                }, {});
                updateSimpleChartData(chart, chartData);
                resolve(chart);
            });
        }
    });
}

function drawMultipleBarChart($canvas, path, params, chartNumber, chart = null) {
    return new Promise(function (resolve) {
        if ($canvas.length == 0) {
            resolve();
        } else {
            $.get(Routing.generate(path, params), function (data) {
                $('#empForChart' + chartNumber).text(data.location);
                $('#totalForChart' + chartNumber).text(data.total);

                if (!chart) {
                    chart = newChart($canvas, true, true);
                }

                updateMultipleChartData(chart, data.data, (data.chartColors || {}));
                resolve(chart);
            });
        }
    });
}

function goToFilteredDemande(type, filter) {
    let path = '';
    if (type === 'livraison') {
        path = 'demande_index';
    } else if (type === 'collecte') {
        path = 'collecte_index';
    } else if (type === 'manutention') {
        path = 'manutention_index';
    }

    let params = {
        reception: 0,
        filter: filter
    };
    let route = Routing.generate(path, params);
    window.location.href = route;
}

function newChart($canvasId, showLegend = false, redForLastData = false) {
    if ($canvasId.length) {
        const chart = new Chart($canvasId, {
            type: 'bar',
            data: {},
            options: {
                layout: {
                    padding: {
                        top: 30
                    }
                },
                tooltips: false,
                responsive: true,
                legend: {
                    display: showLegend,
                    position: 'bottom',
                    labels: {
                        filter: function(item) {
                            return Boolean(item && item.text);
                        }
                    }
                },
                scales: {
                    yAxes: [{
                        ticks: {
                            beginAtZero: true,
                            callback: (value) => {
                                if (Math.floor(value) === value) {
                                    return value;
                                }
                            }
                        }
                    }]
                },
                hover: {mode: null},
                animation: {
                    onComplete() {
                        buildLabelOnBarChart(this, redForLastData);
                    }
                }
            }
        });
        return chart;
    } else {
        return null;
    }
}

function loadRetards() {
    const $retardsTable = $('.retards-table');

    if (!datatableLoading) {
        datatableLoading = true;
        if (datatableColis) {
            datatableColis.destroy();
        }
        datatableColis = $retardsTable.DataTable({
            responsive: true,
            dom: 'tr',
            paging: false,
            scrollCollapse: true,
            scrollY: '18vh',
            processing: true,
            "language": {
                url: "/js/i18n/dataTableLanguage.json",
            },
            ajax: {
                "url": Routing.generate('api_retard', true),
                "type": "GET",
            },
            initComplete: () => {
                datatableLoading = false;
            },
            order: [[2, 'desc']],
            columns: [
                {"data": 'colis', 'name': 'colis', 'title': 'Colis'},
                {"data": 'date', 'name': 'date', 'title': 'Dépose'},
                {"data": 'delay', 'name': 'delay', 'title': 'Délai', render: (milliseconds, type) => renderMillisecondsToDelayDatatable(milliseconds, type)},
                {"data": 'emp', 'name': 'emp', 'title': 'Emplacement'},
            ]
        });
    }
}

function refreshIndicatorsReceptionDock() {
    $.get(Routing.generate('get_indicators_reception_dock'), function(data) {
        refreshCounter($('#remaining-urgences-box-dock'), data.urgenceCount);
        refreshCounter($('#encours-dock-box'), data.enCoursDock);
        refreshCounter($('#encours-clearance-box-dock'), data.enCoursClearance);
        refreshCounter($('#encours-cleared-box'), data.enCoursCleared);
        refreshCounter($('#encours-dropzone-box'), data.enCoursDropzone);
    });
}

function refreshIndicatorsReceptionAdmin() {
    $.get(Routing.generate('get_indicators_reception_admin', true), function(data) {
        refreshCounter($('#encours-clearance-box-admin'), data.enCoursClearance);
        refreshCounter($('#encours-litige-box'), data.enCoursLitige);
        refreshCounter($('#encours-urgence-box'), data.enCoursUrgence);
        refreshCounter($('#remaining-urgences-box-admin'), data.urgenceCount);
    });
}

function refreshCounter($counterCountainer, data) {
    let counter;

    if (typeof data === 'object') {
        const label = data ? data.label : '-';
        counter = data ? data.count : '-';
        $counterCountainer.find('.location-label').text('(' + label + ')');
    }
    else {
        counter = data;
    }

    $counterCountainer.find('.counter').text(counter);
}

function updateCarriers() {
    $.get(Routing.generate('get_daily_carriers_statistics'), function(data) {
        const $container = $('#statistics-arrival-carriers');
        $container.empty();
        $container.append(
            ...((data || []).map((carrier) => ($('<p/>', {text: carrier}))))
        );
    });
}

function buildLabelOnBarChart(chartInstance, redForFirstData) {
    let ctx = (chartInstance.chart.ctx);
    ctx.font = Chart.helpers.fontString(Chart.defaults.global.defaultFontFamily, 'bold', Chart.defaults.global.defaultFontFamily);

    ctx.textAlign = 'center';
    ctx.textBaseline = 'top';
    ctx.strokeStyle = 'black';
    ctx.shadowColor = '#999';

    // on récupère la fontSize de font (format [X]px Arial)
    const fontArray = (ctx.font || '').split(' ');

    const fontSize = Number(fontArray[0].substr(0, fontArray[0].length > 2 ? (fontArray[0].length - 2) : 0) || 12);
    const figurePaddingHorizontal = 8;
    const figurePaddingVertical = 4;
    const figureColor = '#666666';
    const rectColor = '#FFFFFF';

    chartInstance.data.datasets.forEach(function (dataset, index) {
        if (chartInstance.isDatasetVisible(index)) {
            for (let i = 0; i < dataset.data.length; i++) {
                for (let key in dataset._meta) {
                    if (parseInt(dataset.data[i]) > 0) {
                        let {x, y} = dataset._meta[key].data[i]._model;
                        y -= 23;
                        const figure = dataset.data[i];
                        const {width} = ctx.measureText(figure);
                        const rectX = x - (width / 2) - figurePaddingHorizontal;
                        const rectY = y - figurePaddingVertical;
                        const rectWidth = width + (figurePaddingHorizontal * 2);
                        const rectHeight = fontSize + (figurePaddingVertical * 2);

                        // context only for rect
                        ctx.shadowBlur = 2;
                        ctx.shadowOffsetX = 1;
                        ctx.shadowOffsetY = 1;
                        ctx.fillStyle = rectColor;
                        ctx.fillRect(rectX, rectY, rectWidth, rectHeight);

                        // context only for text
                        ctx.shadowBlur = 0;
                        ctx.shadowOffsetX = 0;
                        ctx.shadowOffsetY = 0;
                        const applyRedFont = (redForFirstData && (i === 0));
                        ctx.fillStyle = applyRedFont ? 'red' : figureColor;
                        ctx.fillText(figure, x, y);
                    }
                }
            }
        }
    });
}
