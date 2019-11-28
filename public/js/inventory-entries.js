$(function () {
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
            } else if (element.field == 'emplacement') {
                $('#emplacement').val(element.value).select2();
            } else {
                $('#'+element.field).val(element.value);
            }
        });
        if (data.length > 0) $submitSearchEntry.click();
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
        overrideSearch();
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

function overrideSearch() {
    let $input = $('#tableMissionEntries_filter input');
    $input.off();
    $input.on('keyup', function(e) {
        if (e.key === 'Enter'){
            tableMvt.search(this.value).draw();
        }
    });
    $input.attr('placeholder', 'entrée pour valider');
}

let $submitSearchEntry = $('#submitSearchEntry');
$submitSearchEntry.on('click', function () {
    let dateMin = $('#dateMin').val();
    let dateMax = $('#dateMax').val();
    let reference = $('#reference').select2('data');
    let emplacement = $('#emplacement').val();
    let operator = $('#utilisateur').select2('data');

    saveFilters(PAGE_INV_ENTRIES, dateMin, dateMax, null, operator, null, emplacement, reference);

    tableEntries.draw();
});

function generateCSVEntries () {
    let path = Routing.generate('get_entries_for_csv', true);

    $.post(path, function(response) {
        if (response) {
            let csv = "";
            $.each(response, function (index, value) {
                csv += value.join(';');
                csv += '\n';
            });
            mFile(csv);
        }
    }, 'json');
}

let mFile = function (csv) {
    let exportedFilename = 'export-entries' + '.csv';
    let blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    if (navigator.msSaveBlob) { // IE 10+
        navigator.msSaveBlob(blob, exportedFilename);
    } else {
        let link = document.createElement("a");
        if (link.download !== undefined) {
            let url = URL.createObjectURL(blob);
            link.setAttribute("href", url);
            link.setAttribute("download", exportedFilename);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    }
}