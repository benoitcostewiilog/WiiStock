let datatableColis;
let currentDashboard;

let chartArrivalUm;
let chartAssoRecep;
let chartDailyArrival;
let chartWeeklyArrival;
let chartColis;
let chartMonetaryFiability;
let chartFirstForAdmin;
let chartSecondForAdmin;
let currentChartsFontSize;
const dashboardChartsData = {};

$(function () {
    // config chart js
    Chart.defaults.global.defaultFontFamily = 'Myriad';
    Chart.defaults.global.responsive = true;
    Chart.defaults.global.maintainAspectRatio = false;
    currentChartsFontSize = calculateChartsFontSize();
    currentDashboard = window.location.href.includes('admin')
        ? 'adminDashboard'
        : window.location.href.includes('quai')
            ? 'dockDashboard'
            : window.location.href.includes('emballage')
                ? 'packagingDashboard'
                : 'arrivalDashboard';
    hideCarouselTargetOverlay();
    loadProperData();

    initTooltips($('.has-tooltip'));

    let reloadFrequency = 1000 * 60 * 15; // 15min
    setInterval(reloadData, reloadFrequency);

    let $indicators = $('#indicators');
    $('#btnIndicators').mouseenter(function () {
        $indicators.fadeIn();
    });
    $('#blocIndicators').mouseleave(function () {
        $indicators.fadeOut();
    });

    $(document).on('keydown', function (e) {
        if (!$('.carousel-indicators').hasClass('d-none')) {
            let activeBtn = $('#carouselIndicators').find('[data-slide-to].active');
            if (e.which === 37) {
                activeBtn.prev('li').click()
            } else if (e.which === 39) {
                activeBtn.next('li').click()
            }
        }
    });

    $(window).resize(function () {
        let newFontSize = calculateChartsFontSize();
        if (newFontSize !== currentChartsFontSize) {
            currentChartsFontSize = newFontSize;
            resizeCharts();
        }
    });

    refreshPageTitle();
    const $carouselIndicators = $('#carouselIndicators');
    $carouselIndicators.on('slide.bs.carousel', (event) => {
        let $newlyActivScreen = $(event.relatedTarget);
        currentDashboard = $newlyActivScreen.attr('id');
        hideCarouselTargetOverlay();
        showSpinner();
    });
    $carouselIndicators.on('slid.bs.carousel', () => {
        loadProperData();
        refreshPageTitle();
    });
});

function hideCarouselTargetOverlay() {
    let $carouselIndicators = $('.carousel-indicators');
    let $carouselContainer = $('#' + currentDashboard);
    $carouselIndicators.addClass('d-none');
    $carouselContainer.addClass('d-none');
}

function showSpinner() {
    let $spinner = $('.spinner-grow');
    $spinner.removeClass('d-none');
}

function showCarouselOverlayAndHideSpinner() {
    let $spinner = $('.spinner-grow');
    let $carouselIndicators = $('.carousel-indicators');
    let $carouselContainer = $('#' + currentDashboard);
    $spinner.addClass('d-none');
    $carouselIndicators.removeClass('d-none');
    $carouselContainer.removeClass('d-none');
}

function loadProperData(preferCache = false) {
    switch (currentDashboard) {
        case 'arrivalDashboard':
            loadArrivalDashboard(preferCache).then(() => {
                showCarouselOverlayAndHideSpinner();
                resizeDatatable();
            });
            break;
        case 'dockDashboard':
            loadDockDashboard(preferCache).then(() => {
                showCarouselOverlayAndHideSpinner();
            });
            break;
        case 'adminDashboard':
            loadAdminDashboard(preferCache).then(() => {
                showCarouselOverlayAndHideSpinner();
            });
            break;
        case 'packagingDashboard':
            loadPackagingData().then(() => {
                showCarouselOverlayAndHideSpinner();
            });
            break;
        default:
            break;
    }
}

function resizeDatatable() {
    datatableColis.columns.adjust().draw();
}

function loadPackagingData() {
    return new Promise(function (resolve) {
        let total = 0;
        let pathForPackagingData = Routing.generate('get_indicators_monitoring_packaging', true);
        $.get(pathForPackagingData, function (response) {
            Object.keys(response).forEach((key) => {
                total += fillPackagingCard(key, response[key]);
            });
            $('#packagingTotal').find('.dashboard-stats-counter').html(total);
            resolve();
        });
    });
}

function fillPackagingCard(cardId, data) {
    let $container = $('#' + cardId);
    $container.find('.location-label').html(data ? data.label : '-');
    $container.find('.dashboard-stats-counter').html(data && data.count ? data.count : '-');
    let $titleDelayContainer = $container.find('.dashboard-stats-delay-title');
    let $titleDelayValue = $container.find('.dashboard-stats-delay');
    if (data && data.delay < 0) {
        $titleDelayContainer.html('Retard : ');
        $titleDelayContainer.addClass('red');
        $titleDelayValue.html(renderMillisecondsToDelayDatatable(Math.abs(data.delay), 'display'));
        $titleDelayValue.addClass('red');
    } else if (data && data.delay > 0) {
        $titleDelayContainer.html('A traiter sous : ');
        $titleDelayContainer.removeClass('red');
        $titleDelayValue.html(renderMillisecondsToDelayDatatable(data.delay, 'display'));
        $titleDelayValue.removeClass('red');
    } else {
        $titleDelayValue.html('-');
    }
    return data && $container.hasClass('contribute-to-total') ? data.count : 0;
}

function loadArrivalDashboard(preferCache) {
    return Promise
        .all([
            drawChartWithHisto($('#chartArrivalUm'), 'get_arrival_um_statistics', 'now', chartArrivalUm, preferCache),
            drawChartWithHisto($('#chartAssocRecep'), 'get_asso_recep_statistics', 'now', chartAssoRecep, preferCache),
            drawSimpleChart($('#chartMonetaryFiability'), 'get_monetary_fiability_statistics', chartMonetaryFiability, preferCache),
            ...(!preferCache ? [loadRetards()]: [])
        ])
        .then(([chartArrivalUmLocal, chartAssoRecepLocal, chartMonetaryFiabilityLocal]) => {
            chartArrivalUm = chartArrivalUmLocal;
            chartAssoRecep = chartAssoRecepLocal;
            chartMonetaryFiability = chartMonetaryFiabilityLocal;
        });
}

function loadDockDashboard(preferCache) {
    return Promise
        .all([
            drawSimpleChart($('#chartDailyArrival'), 'get_daily_arrivals_statistics', chartDailyArrival, preferCache),
            drawSimpleChart($('#chartWeeklyArrival'), 'get_weekly_arrivals_statistics', chartWeeklyArrival, preferCache),
            drawSimpleChart($('#chartColis'), 'get_daily_packs_statistics', chartColis, preferCache),
            ...(
                !preferCache
                    ? [
                        refreshIndicatorsReceptionDock(),
                        updateCarriers()
                    ]
                    : []
            )
        ])
        .then(([chartDailyArrivalLocal, chartWeeklyArrivalLocal, chartColisLocal]) => {
            chartDailyArrival = chartDailyArrivalLocal;
            chartWeeklyArrival = chartWeeklyArrivalLocal;
            chartColis = chartColisLocal;
        });
}

function loadAdminDashboard(preferCache) {
    return Promise
        .all([
            drawMultipleBarChart($('#chartFirstForAdmin'), 'get_encours_count_by_nature_and_timespan', {graph: 1}, 1, chartFirstForAdmin, preferCache),
            drawMultipleBarChart($('#chartSecondForAdmin'), 'get_encours_count_by_nature_and_timespan', {graph: 2}, 2, chartSecondForAdmin, preferCache),
            ...(!preferCache ? [refreshIndicatorsReceptionAdmin()]: [])
        ])
        .then(([chartFirstForAdminLocal, chartSecondForAdminLocal]) => {
            chartFirstForAdmin = chartFirstForAdminLocal;
            chartSecondForAdmin = chartSecondForAdminLocal;
        });
}


function reloadData() {
    loadProperData();
    let now = new Date();
    const date = ('0' + (now.getDate() + 1)).slice(-2) + '/' + ('0' + (now.getMonth() + 1)).slice(-2) + '/' + now.getFullYear();
    const hour =  now.getHours() + ':' + now.getMinutes();
    $('.refreshDate').text(`${date} à ${hour}`);
}

function resizeCharts() {
    loadProperData(true);
}

function updateSimpleChartData(
    chart,
    data,
    label,
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
    }

    if (subData) {
        const subColor = '#999';
        chart.data.datasets.push({
            label: lineChartLabel,
            backgroundColor: (new Array(dataLength)).fill(subColor),
            data: Object.values(subData)
        });

        chart.legend.display = true;
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

function drawSimpleChart($canvas, path, chart, preferCacheData = false) {
    return new Promise(function (resolve) {
        if ($canvas.length == 0) {
            resolve();
        } else {
            if (!preferCacheData) {
                $.get(Routing.generate(path), function (data) {
                    dashboardChartsData[$canvas.attr('id')] = data;
                    chart = createAndUpdateSimpleChart($canvas, chart, data);
                    resolve(chart);
                });
            } else {
                const data = dashboardChartsData[$canvas.attr('id')];
                chart = createAndUpdateSimpleChart($canvas, chart, data, true);
                resolve(chart);
            }
        }
    });
}

function createAndUpdateSimpleChart($canvas, chart, data, forceCreation = false) {
    if (forceCreation || !chart) {
        chart = newChart($canvas, false);
    }

    if (data) {
        updateSimpleChartData(
            chart,
            data.data || data,
            data.data && data.label,
            {
                data: data.subCounters,
                label: data.subLabel
            }
        );
    }

    return chart;
}

function createAndUpdateMultipleCharts($canvas, chart, data, forceCreation = false) {
    if (forceCreation || !chart) {
        chart = newChart($canvas, true);
    }

    if (data) {
        updateMultipleChartData(chart, data.data, (data.chartColors || {}));
    }
    return chart;
}


function drawChartWithHisto($button, path, beforeAfter = 'now', chart = null, preferCacheData = false) {
    return new Promise(function (resolve) {
        if ($button.length == 0) {
            resolve();
        } else {
            let $dashboardBox = $button.closest('.dashboard-box');
            let $rangeBtns = $dashboardBox.find('.range-buttons');
            let $firstDay = $rangeBtns.find('.firstDay');
            let $lastDay = $rangeBtns.find('.lastDay');
            let $canvas = $dashboardBox.find('canvas');
            if (!preferCacheData) {
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

                    const chartData = Object.keys(data.data).reduce((previous, currentKeys) => {
                        previous[currentKeys] = (data.data[currentKeys].count || data.data[currentKeys] || 0);
                        return previous;
                    }, {});

                    dashboardChartsData[$canvas.attr('id')] = chartData;
                    chart = createAndUpdateSimpleChart($canvas, chart, chartData);
                    resolve(chart);
                });
            } else {
                const chartData = dashboardChartsData[$canvas.attr('id')];

                chart = createAndUpdateSimpleChart($canvas, chart, chartData, true);
                resolve(chart);
            }
        }
    });
}

function drawMultipleBarChart($canvas, path, params, chartNumber, chart, preferCacheData = false) {
    return new Promise(function (resolve) {
        if ($canvas.length == 0) {
            resolve();
        } else {
            if (!preferCacheData) {
                $.get(Routing.generate(path, params), function (data) {
                    $('#empForChart' + chartNumber).text(data.location);
                    $('#totalForChart' + chartNumber).text(data.total);
                    dashboardChartsData[$canvas.attr('id')] = data;

                    chart = createAndUpdateMultipleCharts($canvas, chart, data);
                    resolve(chart);
                });
            } else {
                data = dashboardChartsData[$canvas.attr('id')];
                chart = createAndUpdateMultipleCharts($canvas, chart, data, true);
                resolve(chart);
            }
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

function newChart($canvasId, redForLastData = false) {
    if ($canvasId.length) {
        const fontSize = currentChartsFontSize;
        const fontStyle = isDashboardExt()
            ? 'bold'
            : undefined;

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
                    position: 'bottom',
                    labels: {
                        fontSize,
                        fontStyle,
                        filter: function (item) {
                            return Boolean(item && item.text);
                        }
                    }
                },
                scales: {
                    yAxes: [{
                        ticks: {
                            fontSize,
                            fontStyle,
                            beginAtZero: true,
                            callback: (value) => {
                                if (Math.floor(value) === value) {
                                    return value;
                                }
                            }
                        }
                    }],
                    xAxes: [{
                        ticks: {
                            fontSize,
                            fontStyle
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
    return new Promise(function (resolve) {
        const $retardsTable = $('.retards-table');
        if (datatableColis) {
            datatableColis.destroy();
        }
        let datatableColisConfig = {
            responsive: true,
            domConfig: {
                needsMinimalDomOverride: true
            },
            paging: false,
            scrollCollapse: true,
            scrollY: '22vh',
            processing: true,
            ajax: {
                "url": Routing.generate('api_retard', true),
                "type": "GET",
            },
            initCompleteCallback: () => {
                resolve();
            },
            order: [[2, 'desc']],
            columns: [
                {"data": 'colis', 'name': 'colis', 'title': 'Colis'},
                {"data": 'date', 'name': 'date', 'title': 'Dépose'},
                {"data": 'delay', 'name': 'delay', 'title': 'Délai', render: (milliseconds, type) => renderMillisecondsToDelayDatatable(milliseconds, type)},
                {"data": 'emp', 'name': 'emp', 'title': 'Emplacement'},
            ]
        };
        datatableColis = initDataTable($retardsTable.attr('id'), datatableColisConfig);
    });
}

function refreshIndicatorsReceptionDock() {
    return new Promise(function (resolve) {
        $.get(Routing.generate('get_indicators_reception_dock'), function (data) {
            refreshCounter($('#remaining-urgences-box-dock'), data.urgenceCount);
            refreshCounter($('#encours-dock-box'), data.enCoursDock);
            refreshCounter($('#encours-clearance-box-dock'), data.enCoursClearance);
            refreshCounter($('#encours-cleared-box'), data.enCoursCleared);
            refreshCounter($('#encours-dropzone-box'), data.enCoursDropzone);
            resolve();
        });
    });
}

function refreshIndicatorsReceptionAdmin() {
    return new Promise(function (resolve) {
        $.get(Routing.generate('get_indicators_reception_admin', true), function (data) {
            refreshCounter($('#encours-clearance-box-admin'), data.enCoursClearance);
            refreshCounter($('#encours-litige-box'), data.enCoursLitige);
            refreshCounter($('#encours-urgence-box'), data.enCoursUrgence, true);
            refreshCounter($('#remaining-urgences-box-admin'), data.urgenceCount);
            resolve();
        });
    });
}

function refreshCounter($counterCountainer, data, needsRedColorIfPositiv = false) {
    let counter;

    if (typeof data === 'object') {
        const label = data ? data.label : '-';
        counter = data ? data.count : '-';
        $counterCountainer.find('.location-label').text('(' + label + ')');
    }
    else {
        counter = data;
    }
    if (counter > 0 && needsRedColorIfPositiv) {
        $counterCountainer.find('.dashboard-stats').addClass('red');
        $counterCountainer.find('.fas').addClass('red fa-exclamation-triangle');
    } else {
        $counterCountainer.find('.dashboard-stats').removeClass('red');
        $counterCountainer.find('.fas').removeClass('red fa-exclamation-triangle');
    }
    $counterCountainer.find('.dashboard-stats').text(counter);
}

function updateCarriers() {
    return new Promise(function (resolve) {
        $.get(Routing.generate('get_daily_carriers_statistics'), function (data) {
            const $container = $('#statistics-arrival-carriers');
            $container.empty();
            const cssClass = `${isDashboardExt() ? 'medium-font' : ''} m-0`;
            $container.append(
                ...((data || []).map((carrier) => ($('<li/>', {text: carrier, class: cssClass}))))
            );
            resolve();
        });
    });
}

function buildLabelOnBarChart(chartInstance, redForFirstData) {
    let ctx = (chartInstance.chart.ctx);
    ctx.font = Chart.helpers.fontString(Chart.defaults.global.defaultFontFamily, 'bold', Chart.defaults.global.defaultFontFamily);

    ctx.textAlign = 'center';
    ctx.textBaseline = 'top';
    ctx.strokeStyle = 'black';
    ctx.shadowColor = '#999';


    const fontSize = currentChartsFontSize;

    const figurePaddingHorizontal = 8;
    const figurePaddingVertical = 4;
    const figureColor = '#666666';
    const rectColor = '#FFFFFF';

    const yAdjust = 23;

    chartInstance.data.datasets.forEach(function (dataset, index) {
        if (chartInstance.isDatasetVisible(index)) {
            let containsNegativValues = dataset.data.some((current) => (current < 0));
            for (let i = 0; i < dataset.data.length; i++) {
                for (let key in dataset._meta) {
                    const value = parseInt(dataset.data[i]);
                    const isNegativ = (value < 0);
                    if (value !== 0) {
                        let {x, y, base} = dataset._meta[key].data[i]._model;
                        const figure = dataset.data[i];
                        const {width} = ctx.measureText(figure);
                        const rectWidth = width + (figurePaddingHorizontal * 2);
                        const rectHeight = fontSize + (figurePaddingVertical * 2);

                        y = isNegativ
                            ? (base - rectHeight)
                            : (containsNegativValues
                                ? (base + (rectHeight / 2))
                                : (y - yAdjust));

                        const rectX = x - (width / 2) - figurePaddingHorizontal;
                        const rectY = y - figurePaddingVertical;

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

function isDashboardExt() {
    const $isDashboardExt = $('#isDashboardExt');
    return ($isDashboardExt.length > 0 ? ($isDashboardExt.val() === "1") : false);
}

function refreshPageTitle() {
    const $carouselIndicators = $('#carouselIndicators');
    const $activeCarousel = $carouselIndicators.find('.carousel-item.active').first();
    const $pageTitle = $activeCarousel.length > 0
        ? $activeCarousel.find('input.page-title')
        : $('input.page-title');
    const pageTitle = $pageTitle.val();

    document.title = `FollowGT${(pageTitle ? ' | ' : '') + pageTitle}`;

    const words = pageTitle.split('|');

    if (words && words.length > 0) {
        const $titleContainer = $('<span/>');
        for (let wordIndex = 0; wordIndex < words.length; wordIndex++) {
            if ($titleContainer.children().length > 0) {
                $titleContainer.append(' | ')
            }
            const className = (wordIndex === (words.length - 1)) ? 'bold' : undefined;
            $titleContainer.append($('<span/>', {class: className, text: words[wordIndex]}));
        }
        $('.main-header .header-title').html($titleContainer);
    }
}

function calculateChartsFontSize() {
    let width = document.body.clientWidth;
    return Math.floor(width / 120);
}
