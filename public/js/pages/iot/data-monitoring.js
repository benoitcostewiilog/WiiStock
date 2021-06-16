$(document).ready(() => {
    $(`[data-map]`).each((i, elem) => initMap(elem));
    $(`[data-chart]`).each((i, elem) => initLineChart(elem));

    $(document).arrive(`[data-map]`, elem => initMap(elem));
    $(document).arrive(`[data-chart]`, elem => initLineChart(elem));

    const $timelineContainer = $('.timeline-container');
    if ($timelineContainer.exists()) {
        $timelineContainer.each(function() {
            initTimeline($(this));
        });
    }

    const $editEndButton = $(`button[data-target="#modalEditPairingEnd"]`);
    if ($editEndButton.exists()) {
        $editEndButton.click(function () {
            modalEditPairingEnd.find(`input[name="id"]`).val($(this).data(`id`));
        });

        const modalEditPairingEnd = $("#modalEditPairingEnd");
        const submitEditPairingEnd = $("#submitEditPairingEnd");
        const urlEditPairingEnd = Routing.generate('pairing_edit_end', {});
        InitModal(modalEditPairingEnd, submitEditPairingEnd, urlEditPairingEnd, {
            success: response => {
                $(response.selector).text(response.date);
            }
        });
    }
});

function filter() {
    $(`[data-map]`).each((i, elem) => initMap(elem));
    $(`[data-chart]`).each((i, elem) => initLineChart(elem));
}

function unpair(pairing) {
    $.post(Routing.generate(`unpair`, {pairing}), function (response) {
        if (response.success) {
            window.location.href = Routing.generate(`pairing_index`);
        }
    })
}

function getFiltersValue() {
    return JSON.stringify({
        start: $(`input[name="start"]`).val(),
        end: $(`input[name="end"]`).val(),
    });
}

let previousMap = null;
function initMap(element) {
    const $element = $(element);

    $.post($element.data(`fetch-url`), getFiltersValue(), function (response) {
        if(previousMap) {
            previousMap.off();
            previousMap.remove();
        }

        let map = Leaflet.map(element).setView([44.831598, -0.577096], 13);
        previousMap = map;

        Leaflet
            .tileLayer(
                'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
                { attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'}
            )
            .addTo(map);

        let sensors = Object.keys(response);
        let index = 0;

        let globalBounds = Leaflet.latLngBounds();

        const responseValues = Object.values(response);
        if(responseValues.length > 0) {
            responseValues.forEach(((date) => {
                Object.values(date).forEach((coordinates) => {
                    globalBounds.extend(coordinates);      // Extend LatLngBounds with coordinates
                });
            }));

            map.fitBounds(globalBounds);

            sensors.forEach((sensor) => {
                const dates = Object.keys(response[sensor]);
                let polyline = [];
                dates.forEach((label, iteration) => {
                    const coordinates = response[sensor][label];
                    polyline.push(coordinates);
                    index++;
                    setTimeout(() => {
                        Leaflet
                            .marker(coordinates)
                            .addTo(map)
                            .bounce(1)
                            .on('click', function () {
                                this.bounce(1);
                            })
                            .bindPopup(`Capteur : ${sensor} <br> Date et heure : ${label}`);
                        if (iteration === dates.length - 1 && dates.length > 1) {
                            Leaflet
                                .polyline(polyline, {color: 'blue', snakingSpeed: 500})
                                .addTo(map)
                                .snakeIn()
                                .on('snakeend', function () {
                                    let antPolyline = new Leaflet.Polyline.AntPath(polyline, {
                                        color: 'blue',
                                        delay: 400,
                                        dashArray: [
                                            100,
                                            100
                                        ]
                                    });
                                    antPolyline.addTo(map);
                                });
                        }
                    }, 200 * index);
                });
            });
        }
    });
}

function initLineChart(element) {
    const $element = $(element);

    $.post($element.data(`fetch-url`), getFiltersValue(), function (response) {
        let data = {
            datasets: [],
            labels: []
        };
        let sensorDates = Object.keys(response).filter((key) => key !== 'colors');
        const sensors = Object.keys(response['colors']);
        let datasets = {};
        sensorDates.forEach((date) => {
            data.labels.push(date);
            sensors.forEach((sensor) => {
                const value = response[date][sensor] || null;
                let dataset = datasets[sensor] || {
                    label: sensor,
                    fill: false,
                    data: [],
                    borderColor: response.colors[sensor],
                    tension: 0.1
                };
                dataset.data.push(value);
                datasets[sensor] = dataset;
            });
        });
        data.datasets = Object.values(datasets);
        let chart = new Chart($element, {
            type: 'line',
            data,
            options: {
                maintainAspectRatio: false,
                spanGaps: true,
                scales: {
                    xAxes: [{
                        ticks: {
                            callback: (label) => {
                                if (/\s/.test(label)) {
                                    return label.split(` `);
                                } else{
                                    return label;
                                }
                            }
                        }
                    }]
                }
            }
        });
    });
}

function initTimeline($timelineContainer, showMore = false) {
    $timelineContainer.pushLoader('black', 'normal');

    const timelineDataPath = $timelineContainer.data('timeline-data-path');
    const ended = $timelineContainer.data('timeline-end');
    const $oldShowMoreButton = $timelineContainer.find('.timeline-show-more-button');

    if (!showMore) {
        $timelineContainer.find('.timeline-row').remove();
    }

    if (!ended) {
        $
            .get(timelineDataPath)
            .then(({data, isEnd}) => {
                $timelineContainer.data('timeline-end', Boolean(isEnd));
                if ($oldShowMoreButton.exists()) {
                    $oldShowMoreButton.parent().remove();
                }

                const timeline = data || [];
                let lastGroupTitle;
                const $timeline = timeline.map(({title, subtitle, active, group}) => {
                    const groupTitle = group ? group.title : null;
                    const groupColor = group ? group.color : null;
                    const displayGroup = lastGroupTitle !== groupTitle;
                    lastGroupTitle = groupTitle;
                    return $('<div/>', {
                        class: 'timeline-row',
                        html: [
                            $('<div/>', {
                                class: `timeline-cell timeline-cell-left`,
                                ...(displayGroup
                                    ? {
                                        style: groupColor ? `color: ${groupColor};` : null,
                                        text: groupTitle ? groupTitle : null
                                    }
                                    : {})
                            }),
                            $('<div/>', {
                                class: 'timeline-cell timeline-cell-right',
                                html: [
                                    `<span style="${active ? `color: green;` : ''}">${title}</span>`,
                                    `<br/>`,
                                    `<span>${subtitle}</span>`
                                ]
                            })
                        ]
                    })
                });
                $timelineContainer.append($timeline);

                if (!isEnd) {
                    $timelineContainer.append(
                        $('<div/>', {
                            class: 'timeline-row justify-content-center pt-4',
                            html: $('<button/>', {
                                class: 'btn btn-outline-primary timeline-show-more-button',
                                text: 'Voir plus',
                                click: () => {
                                    initTimeline($timelineContainer, true);
                                }
                            })
                        })
                    );
                }

                $timelineContainer.popLoader();
            });
    }
}
