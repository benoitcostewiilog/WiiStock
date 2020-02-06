google.charts.load('current', {'packages':['corechart']});
google.charts.setOnLoadCallback(drawAllCharts);

function drawAllCharts() {
    drawChart('dashboard-assoc');
    drawChart('dashboard-arrival');
    drawChartMonetary();
    reloadDashboardLinks();
}

let reloadFrequency = 1000 * 60 * 15;
setInterval(reloadPage, reloadFrequency);

function reloadPage() {
    drawAllCharts();
    datatableColis.ajax.reload();
    reloadDashboardLinks();
}

function reloadDashboardLinks() {
    $.post(Routing.generate('get_dashboard', true), function(resp) {
        $('#dashboardLinks').html(resp);
    });
}

function drawChart(parent, after = true, fromStart = true) {
    $('#' + parent + ' > .range-buttons').hide();
    $('#' + parent + ' .spinner-border').show();
    let data = new google.visualization.DataTable();
    let currentWeekRoute = Routing.generate(parent, true);
    let params = {
        'firstDay': $('#' + parent + ' > .range-buttons > .firstDay').data('day'),
        'lastDay': $('#' + parent + ' > .range-buttons > .lastDay').data('day'),
        'after': (fromStart ? 'now' : after)
    };
    $.post(currentWeekRoute, JSON.stringify(params), function (chartData) {
        chartData.columns.forEach(column => {
            if (column.annotation) {
                data.addColumn({type: column.type, role: column.role});
            } else {
                data.addColumn(column.type, column.value);
            }
        });
        for (const [key, value] of Object.entries(chartData.rows)) {
            if (value.conform !== undefined) data.addRow(
                [
                    key,
                    Number(value.count) !== 0 ? Number(value.count) : null,
                    value.conform,
                    key + ' : ' + String(value.conform) + '%']);
            else data.addRow([key, Number(value.count) !== 0 ? Number(value.count) : null]);
        }
        let options = {
            vAxes: {
                0: {
                    minValue: 1,
                    format: '#',
                    textStyle: {
                        color: 'black',
                        fontName: 'Montserrat',
                    }
                },
                1: {
                    maxValue: 100,
                    minValue: 0,
                    format: '#',
                    gridlines: {color: 'transparent'},
                    textStyle: {
                        color: 'black',
                        fontName: 'Montserrat',
                    }
                },
            },
            hAxis: {
                textStyle: {
                    color: 'black',
                    fontName: 'Montserrat',
                }
            },
            interpolateNulls: true,
            seriesType: 'bars',
            pointSize: 5,
            series: {
                1: {
                    pointShape: 'circle',
                    type: 'line',
                    targetAxisIndex: 1,
                }
            },
            legend: {
                position: 'top',
                textStyle: {
                    color: 'black',
                    fontName: 'Montserrat',
                }
            },
            colors: ['#130078', 'Red'],
            backgroundColor: {
                fill: 'transparent'
            }
        };
        let chart = new google.visualization.ColumnChart($('#' + parent + ' > .chart')[0]);
        chart.draw(data, options);
        $('#' + parent + ' > .range-buttons > .firstDay').data('day', chartData.firstDay);
        $('#' + parent + ' > .range-buttons > .firstDay').text(chartData.firstDay + ' - ');
        $('#' + parent + ' > .range-buttons > .lastDay').data('day', chartData.lastDay);
        $('#' + parent + ' > .range-buttons > .lastDay').text(chartData.lastDay);
        $('#' + parent + ' > .range-buttons').show();
        $('#' + parent + ' .spinner-border').hide();
    }, 'json');
}

function drawChartMonetary() {
    let path = Routing.generate('graph_monetaire', true);
    $.ajax({
        url: path,
        dataType: "json",
        type: "GET",
        contentType: "application/json; charset=utf-8",
        success: function (data) {
            let tdata = new google.visualization.DataTable();

            tdata.addColumn('string', 'Month');
            tdata.addColumn('number', 'Fiabilité monétaire');

            $.each(data, function (index, value) {
                tdata.addRow([value.mois, value.nbr]);
            });

            let options = {
                curveType: 'function',
                backdropColor: 'transparent',
                legend: 'none',
                backgroundColor: 'transparent',
            };

            let chart = new google.visualization.LineChart(document.getElementById('curve_chart'));
            chart.draw(tdata, options);
            $('#dashboard-monetary > .spinner-border').hide();
        }
    });
}

let routeForLate = Routing.generate('api_retard', true);

let datatableColis = $('.retards-table').DataTable({
    responsive: true,
    dom: 'tipr',
    pageLength: 5,
    processing: true,
    "language": {
        url: "/js/i18n/dataTableLanguage.json",
    },
    ajax: {
        "url": routeForLate,
        "type": "POST",
    },
    columns: [
        {"data": 'colis', 'name': 'colis', 'title': 'Colis'},
        {"data": 'date', 'name': 'date', 'title': 'Dépose'},
        {"data": 'time', 'name': 'delai', 'title': 'Délai'},
        {"data": 'emp', 'name': 'emp', 'title': 'Emplacement'},
    ]
});

function goToFilteredDemande(type, filter){
    let path = '';
    if (type === 'livraison'){
        path = 'demande_index';
    } else if (type === 'collecte') {
        path = 'collecte_index';
    } else if (type === 'manutention'){
        path = 'manutention_index';
    }

    let params = {
        reception: 0,
        filter: filter
    };
    let route = Routing.generate(path, params);
    window.location.href = route;
}