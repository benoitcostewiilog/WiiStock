$('.select2').select2();

let pathHandling = Routing.generate('handling_api', true);
let tableHandlingConfig = {
    serverSide: true,
    processing: true,
    order: [[1, 'desc']],
    columnDefs: [
        {
            "type": "customDate",
            "targets": 1
        },
        {
            "orderable" : false,
            "targets" : 0
        }
    ],
    rowConfig: {
        needsRowClickAction: true,
    },
    drawConfig: {
        needsSearchOverride: true,
    },
    ajax: {
        "url": pathHandling,
        "type": "POST",
        'data' : {
            'filterStatus': $('#filterStatus').val()
        },
    },
    columns: [
        { "data": 'Actions', 'name': 'Actions', 'title': '', className: 'noVis' },
        { "data": 'Date demande', 'name': 'Date demande', 'title': 'Date demande' },
        { "data": 'Demandeur', 'name': 'Demandeur', 'title': 'Demandeur' },
        { "data": 'Libellé', 'name': 'Libellé', 'title': 'Libellé' },
        { "data": 'Date souhaitée', 'name': 'Date souhaitée', 'title': 'Date souhaitée' },
        { "data": 'Date de réalisation', 'name': 'Date de réalisation', 'title': 'Date de réalisation' },
        { "data": 'Statut', 'name': 'Statut', 'title': 'Statut' },
    ],
};
let tableHandling = initDataTable('tableHandling_id', tableHandlingConfig);

$(function() {
    initDateTimePicker();
    initSelect2($('.filter-select2[name="statut"]'), 'Statuts');
    ajaxAutoUserInit($('.ajax-autocomplete-user'), 'Demandeurs');
    // applique les filtres si pré-remplis
    let val = $('#filterStatus').val();

        // sinon, filtres enregistrés en base pour chaque utilisateur
        let path = Routing.generate('filter_get_by_page');
        let params = JSON.stringify(PAGE_HAND);
        $.post(path, params, function (data) {
            displayFiltersSup(data);
        }, 'json');
});

// filtres de recheches

let modalNewHandling = $("#modalNewHandling");
let submitNewHandling = $("#submitNewHandling");
let urlNewHandling = Routing.generate('handling_new', true);
InitialiserModal(modalNewHandling, submitNewHandling, urlNewHandling, tableHandling);

let modalModifyHandling = $('#modalEditHandling');
let submitModifyHandling = $('#submitEditHandling');
let urlModifyHandling = Routing.generate('handling_edit', true);
InitialiserModal(modalModifyHandling, submitModifyHandling, urlModifyHandling, tableHandling);

let modalDeleteHandling = $('#modalDeleteHandling');
let submitDeleteHandling = $('#submitDeleteHandling');
let urlDeleteHandling = Routing.generate('handling_delete', true);
InitialiserModal(modalDeleteHandling, submitDeleteHandling, urlDeleteHandling, tableHandling);

//initialisation editeur de texte une seule fois
let editorNewHandlingAlreadyDone = false;
function initNewHandlingEditor(modal) {
    if (!editorNewHandlingAlreadyDone) {
        initEditor('.editor-container-new');
        editorNewHandlingAlreadyDone = true;
    }
    ajaxAutoCompleteEmplacementInit($('.ajax-autocompleteEmplacement'))
}

function changeStatus(button) {
    let sel = $(button).data('title');
    let tog = $(button).data('toggle');
    let $statusHandling = $("#statusHandling");

    if ($(button).hasClass('not-active')) {
        if ($statusHandling.val() === "0") {
            $statusHandling.val("1");
        } else {
            $statusHandling.val("0");
        }
    }

    $('span[data-toggle="' + tog + '"]').not('[data-title="' + sel + '"]').removeClass('active').addClass('not-active');
    $('span[data-toggle="' + tog + '"][data-title="' + sel + '"]').removeClass('not-active').addClass('active');
}

function toggleHandlingQuill() {
    let enable = modalModifyHandling.find('#statut').val() === '1';
    toggleQuill(modalModifyHandling, enable);
}

function callbackSaveFilter() {
    // supprime le filtre de l'url
    let str = window.location.href.split('/');
    if (str[5]) {
        window.location.href = Routing.generate('handling_index');
    }
}
