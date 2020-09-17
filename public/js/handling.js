$('.select2').select2();

let pathHandling = Routing.generate('handling_api', true);
let tableHandlingConfig = {
    serverSide: true,
    processing: true,
    order: [[2, 'desc']],
    rowConfig: {
        needsRowClickAction: true,
        needsColor: true,
        color: 'danger',
        dataToCheck: 'emergency'
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
        { "data": 'Actions', 'name': 'Actions', 'title': '', className: 'noVis', orderable: false},
        { "data": 'number', 'name': 'number', 'title': 'Numéro de demande' },
        { "data": 'creationDate', 'name': 'creationDate', 'title': 'Date demande' },
        { "data": 'type', 'name': 'type', 'title': 'Type' },
        { "data": 'requester', 'name': 'requester', 'title': 'Demandeur' },
        { "data": 'subject', 'name': 'subject', 'title': 'Objet' },
        { "data": 'desiredDate', 'name': 'desiredDate', 'title': 'Date souhaitée' },
        { "data": 'validationDate', 'name': 'validationDate', 'title': 'Date de réalisation' },
        { "data": 'status', 'name': 'status', 'title': 'Statut' },
        { "data": 'emergency', 'name': 'emergency', 'title': 'Urgence' },
    ],
};
let tableHandling = initDataTable('tableHandling_id', tableHandlingConfig);

$(function() {
    initDateTimePicker();
    initSelect2($('.filter-select2[name="statut"]'), 'Statuts');
    ajaxAutoUserInit($('.ajax-autocomplete-user'), 'Demandeurs');
    // applique les filtres si pré-remplis
    let val = $('#filterStatus').val();


    if (val && val.length > 0) {
        let valuesStr = val.split(',');
        let valuesInt = [];
        valuesStr.forEach((value) => {
            valuesInt.push(parseInt(value));
        })
        $('#statut').val(valuesInt).select2();
    } else {
        // sinon, filtres enregistrés en base pour chaque utilisateur
        let path = Routing.generate('filter_get_by_page');
        let params = JSON.stringify(PAGE_HAND);
        $.post(path, params, function (data) {
            displayFiltersSup(data);
        }, 'json');
    }
});

// filtres de recheches

let $modalNewHandling = $("#modalNewHandling");
let $submitNewHandling = $("#submitNewHandling");
let urlNewHandling = Routing.generate('handling_new', true);
InitModal($modalNewHandling, $submitNewHandling, urlNewHandling, {tables: [tableHandling]});

let $modalModifyHandling = $('#modalEditHandling');
let $submitModifyHandling = $('#submitEditHandling');
let urlModifyHandling = Routing.generate('handling_edit', true);
InitModal($modalModifyHandling, $submitModifyHandling, urlModifyHandling, {tables: [tableHandling]});

let $modalDeleteHandling = $('#modalDeleteHandling');
let $submitDeleteHandling = $('#submitDeleteHandling');
let urlDeleteHandling = Routing.generate('handling_delete', true);
InitModal($modalDeleteHandling, $submitDeleteHandling, urlDeleteHandling, {tables: [tableHandling]});

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
    let enable = $modalModifyHandling.find('#statut').val() === '1';
    toggleQuill($modalModifyHandling, enable);
}

function callbackSaveFilter() {
    // supprime le filtre de l'url
    let str = window.location.href.split('/');
    if (str[5]) {
        window.location.href = Routing.generate('handling_index');
    }
}

function onHandlingTypeChange($select) {
    toggleRequiredChampsLibres($select, 'create');

    const type = parseInt($select.val());
    let $modalNewHandling = $("#modalNewHandling");
    const $selectStatus = $modalNewHandling.find('select[name="status"]');

    $selectStatus.removeAttr('disabled');
    $selectStatus.find('option[data-type-id="' + type + '"]').removeClass('d-none');
    $selectStatus.find('option[data-type-id!="' + type + '"]').addClass('d-none');
    $selectStatus.val(null).trigger('change');

    if($selectStatus.find('option:not(.d-none)').length === 0) {
        $selectStatus.siblings('.error-empty-status').removeClass('d-none');
        $selectStatus.addClass('d-none');
    } else {
        $selectStatus.siblings('.error-empty-status').addClass('d-none');
        $selectStatus.removeClass('d-none');

        const handlingDefaultStatus = JSON.parse($selectStatus.siblings('input[name="handlingDefaultStatus"]').val() || '{}');
        if (handlingDefaultStatus[type]) {
            $selectStatus.val(handlingDefaultStatus[type]);
        }

        $selectStatus.find('option');
    }
}
