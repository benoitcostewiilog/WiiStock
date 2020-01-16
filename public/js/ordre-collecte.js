$('.select2').select2();

let $submitSearchOrdreCollecte = $('#submitSearchOrdreCollecte');

let pathCollecte = Routing.generate('ordre_collecte_api');

let tableCollecte = $('#tableCollecte').DataTable({
    serverSide: true,
    processing: true,
    order: [[3, 'desc']],
    columnDefs: [
        {
            type: "customDate",
            targets: 3
        },
        {
            orderable: false,
            targets: 0
        }
    ],
    language: {
        url: "/js/i18n/dataTableLanguage.json",
    },
    ajax: {
        'url': pathCollecte,
        'data' : {
          'filterDemand': $('#filterDemand').val()
        },
        "type": "POST"
    },
    drawCallback: function() {
        overrideSearch($('#tableCollecte_filter input'), tableCollecte);
    },
    columns: [
        {"data": 'Actions', 'title': 'Actions', 'name': 'Actions'},
        {"data": 'Numéro', 'title': 'Numéro', 'name': 'Numéro'},
        {"data": 'Statut', 'title': 'Statut', 'name': 'Statut'},
        {"data": 'Date', 'title': 'Date de création', 'name': 'Date'},
        {"data": 'Opérateur', 'title': 'Opérateur', 'name': 'Opérateur'},
        {"data": 'Type', 'title': 'Type', 'name': 'Type'},
    ],
});

$.fn.dataTable.ext.search.push(
    function (settings, data, dataIndex) {
        let dateMin = $('#dateMin').val();
        let dateMax = $('#dateMax').val();
        let indexDate = tableCollecte.column('Date:name').index();

        if (typeof indexDate === "undefined") return true;

        let dateInit = (data[indexDate]).split('/').reverse().join('-') || 0;

        if (
            (dateMin == "" && dateMax == "")
            ||
            (dateMin == "" && moment(dateInit).isSameOrBefore(dateMax))
            ||
            (moment(dateInit).isSameOrAfter(dateMin) && dateMax == "")
            ||
            (moment(dateInit).isSameOrAfter(dateMin) && moment(dateInit).isSameOrBefore(dateMax))

        ) {
            return true;
        }
        return false;
    }
);

$(function() {
    initDateTimePicker();
    initSelect2('#statut', 'Statut');
    ajaxAutoDemandCollectInit($('.ajax-autocomplete-dem-collecte'));
    ajaxAutoUserInit($('.ajax-autocomplete-user'), 'Opérateurs');

    // cas d'un filtre par demande de collecte
    let filterDemand = $('#filterDemand').val();

    if (filterDemand) {
        let valueArray = filterDemand.split(':');
        let id = valueArray[0];
        let label = valueArray[1];
        let option = new Option(label, id, true, true);
        $('#demandCollect').append(option).trigger('change');
    } else {

        // filtres enregistrés en base pour chaque utilisateur
        let path = Routing.generate('filter_get_by_page');
        let params = JSON.stringify(PAGE_ORDRE_COLLECTE);

        $.post(path, params, function (data) {
            data.forEach(function (element) {
                if (element.field == 'utilisateurs') {
                    $('#utilisateur').val(element.value.split(',')).select2();
                } else if (element.field == 'demCollecte') {
                    let valueArray = element.value.split(':');
                    let id = valueArray[0];
                    let label = valueArray[1];
                    let option = new Option(label, id, true, true);
                    $('#demandCollect').append(option).trigger('change');
                }  else if (element.field == 'dateMin' || element.field == 'dateMax') {
                    $('#' + element.field).val(moment(element.value, 'YYYY-MM-DD').format('DD/MM/YYYY'));
                } else if (element.field == 'statut') {
                    $('#' + element.field).val(element.value).select2();
                } else {
                    $('#' + element.field).val(element.value);
                }
            });
        }, 'json');
    }
});

$submitSearchOrdreCollecte.on('click', function () {
    $('#dateMin').data("DateTimePicker").format('YYYY-MM-DD');
    $('#dateMax').data("DateTimePicker").format('YYYY-MM-DD');

    let filters = {
        page: PAGE_ORDRE_COLLECTE,
        dateMin: $('#dateMin').val(),
        dateMax: $('#dateMax').val(),
        statut: $('#statut').val(),
        type: $('#type').val(),
        users: $('#utilisateur').select2('data'),
    };

    $('#dateMin').data("DateTimePicker").format('DD/MM/YYYY');
    $('#dateMax').data("DateTimePicker").format('DD/MM/YYYY');

    saveFilters(filters, tableCollecte);
});

$.extend($.fn.dataTableExt.oSort, {
    "customDate-pre": function (a) {
        let dateParts = a.split('/'),
            year = parseInt(dateParts[2]) - 1900,
            month = parseInt(dateParts[1]),
            day = parseInt(dateParts[0]);
        return Date.UTC(year, month, day, 0, 0, 0);
    },
    "customDate-asc": function (a, b) {
        return ((a < b) ? -1 : ((a > b) ? 1 : 0));
    },
    "customDate-desc": function (a, b) {
        return ((a < b) ? 1 : ((a > b) ? -1 : 0));
    }
});
