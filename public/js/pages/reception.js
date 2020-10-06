//initialisation editeur de texte une seule fois
let editorNewReceptionAlreadyDone = false;
let onFlyFormOpened = {};
let tableReception;

$(function () {
    $('.select2').select2();
    initDateTimePicker();
    Select2.init($('#statut'), 'Statuts');
    initOnTheFlyCopies($('.copyOnTheFly'));

    // RECEPTION
    let pathTableReception = Routing.generate('reception_api', true);
    let tableReceptionConfig = {
        serverSide: true,
        processing: true,
        order: [[8, "desc"], [1, "desc"]],
        ajax: {
            "url": pathTableReception,
            "type": "POST",
        },
        drawConfig: {
            needsSearchOverride: true,
            needsColumnHide: true,
        },
        headerCallback: function(thead) {
            $(thead).find('th').eq(5).attr('title', "n° de réception");
        },
        columns: [
            {"data": 'Actions', 'name': 'actions', 'title': '', className: 'noVis', orderable: false},
            {"data": 'Date', 'name': 'date', 'title': 'Date création'},
            {"data": 'DateFin', 'name': 'dateFin', 'title': 'Date fin'},
            {"data": 'Numéro de commande', 'name': 'numCommande', 'title': 'Numéro commande'},
            {"data": 'Fournisseur', 'name': 'fournisseur', 'title': 'Fournisseur'},
            {"data": 'Référence', 'name': 'reference', 'title': 'réception.n° de réception', translated: true},
            {"data": 'Statut', 'name': 'statut', 'title': 'Statut'},
            {"data": 'Commentaire', 'name': 'commentaire', 'title': 'Commentaire'},
            {"data": 'urgence', 'name': 'urgence', 'title': 'urgence', visible: false},
        ],
        rowConfig: {
            needsColor: true,
            color: 'danger',
            needsRowClickAction: true,
            dataToCheck: 'urgence'
        }
    };
    tableReception = initDataTable('tableReception_id', tableReceptionConfig);

    let $modalReceptionNew = $("#modalNewReception");
    let $submitNewReception = $("#submitReceptionButton");
    let urlReceptionIndex = Routing.generate('reception_new', true);
    InitModal($modalReceptionNew, $submitNewReception, urlReceptionIndex);

    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_RECEPTION);
    $.post(path, params, function (data) {
        displayFiltersSup(data);
    }, 'json');

    Select2.provider($('.filters').find('.ajax-autocomplete-fournisseur'), 'Fournisseurs');
});

function initNewReceptionEditor(modal) {
    let $modal = $(modal);
    onFlyFormOpened = {};
    onFlyFormToggle('fournisseurDisplay', 'addFournisseur', true);
    onFlyFormToggle('transporteurDisplay', 'addTransporteur', true);
    if (!editorNewReceptionAlreadyDone) {
        initEditorInModal(modal);
        editorNewReceptionAlreadyDone = true;
    }
    Select2.provider($('.ajax-autocomplete-fournisseur'));
    Select2.location($('.ajax-autocomplete-location'));
    Select2.carrier($modal.find('.ajax-autocomplete-transporteur'));
    initDateTimePicker('#dateCommande, #dateAttendue');

    $('.date-cl').each(function() {
        initDateTimePicker('#' + $(this).attr('id'));
    });

    $modal.find('.list-multiple').select2();
}

function initReceptionLocation() {
    // initialise valeur champs select2 ajax
    let $receptionLocationSelect = $('#receptionLocation');
    let dataReceptionLocation = $('#receptionLocationValue').data();
    if (dataReceptionLocation.id && dataReceptionLocation.text) {
        let option = new Option(dataReceptionLocation.text, dataReceptionLocation.id, true, true);
        $receptionLocationSelect.append(option).trigger('change');
    }
}