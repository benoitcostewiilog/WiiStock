$(function () {
    initDateTimePicker();

    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_INV_ENTRIES);
    $.post(path, params, function(data) {
        data.forEach(function(element) {
            if (element.field == 'utilisateurs') {
                let values = element.value.split(',');
                let $utilisateur = $('#utilisateur');
                values.forEach((value) => {
                    let valueArray = value.split(':');
                    let id = valueArray[0];
                    let username = valueArray[1];
                    let option = new Option(username, id, true, true);
                    $utilisateur.append(option).trigger('change');
                });
            } else if (element.field == 'colis') {
                let $reference = $('#reference');
                let valueArray = element.value.split(':');
                let id = valueArray[0];
                let username = valueArray[1];
                let option = new Option(username, id, true, true);
                $reference.append(option).trigger('change');
            } else if (element.field == 'statut' || element.field == 'emplacement') {
                $('#' + element.field).val(element.value).select2();
            }  else if (element.field == 'dateMin' || element.field == 'dateMax') {
                $('#' + element.field).val(moment(element.value, 'YYYY-MM-DD').format('DD/MM/YYYY'));
            } else {
                $('#'+element.field).val(element.value);
            }
        });
    }, 'json');

    ajaxAutoUserInit($('.ajax-autocomplete-user'), 'Opérateur');

    initSearchDate(tableEntries);

    $('#emplacement').select2({
        placeholder: {
            id: 0,
            text: 'Emplacement',
        }
    });

    $('.ajax-autocomplete-inv-entries').select2({
        ajax: {
            url: Routing.generate('get_ref_and_articles', {activeOnly: 0}, true),
            dataType: 'json',
            delay: 250,
        },
        allowClear: true,
        placeholder: {
            id: 0,
            text: 'Référence',
        },
        language: {
            inputTooShort: function () {
                return 'Veuillez entrer au moins 3 caractères.';
            },
            searching: function () {
                return 'Recherche en cours...';
            },
            noResults: function () {
                return 'Aucun résultat.';
            }
        },
        minimumInputLength: 3,
    });
});

let mission = $('#missionId').val();
let pathEntry = Routing.generate('entries_api', { id: mission}, true);
let tableEntries = $('#tableMissionEntries').DataTable({
    responsive: true,
    serverSide: true,
    processing: true,
    language: {
        url: "/js/i18n/dataTableLanguage.json",
    },
    ajax:{
        "url": pathEntry,
        "type": "POST"
    },
    'drawCallback': function() {
        overrideSearch($('#tableMissionEntries_filter input'), tableEntries);
    },
    columns:[
        { "data": 'Ref', 'title' : 'Reférence', 'name': 'reference' },
        { "data": 'Label', 'title' : 'Libellé' },
        { "data": 'Date', 'title' : 'Date de saisie', 'name': 'date' },
        { "data": 'Location', 'title' : 'Emplacement', 'name': 'location' },
        { "data": 'Operator', 'title' : 'Opérateur', 'name': 'operator' },
        { "data": 'Quantity', 'title' : 'Quantité' }
    ],
});

let $submitSearchEntry = $('#submitSearchEntry');
$submitSearchEntry.on('click', function () {
    $('#dateMin').data("DateTimePicker").format('YYYY-MM-DD');
    $('#dateMax').data("DateTimePicker").format('YYYY-MM-DD');

    let filters = {
        page: PAGE_INV_ENTRIES,
        dateMin: $('#dateMin').val(),
        dateMax: $('#dateMax').val(),
        colis: $('#reference').select2('data'),
        location: $('#emplacement').val(),
        users: $('#utilisateur').select2('data'),
    };

    $('#dateMin').data("DateTimePicker").format('DD/MM/YYYY');
    $('#dateMax').data("DateTimePicker").format('DD/MM/YYYY');

    saveFilters(filters, tableEntries);
});
