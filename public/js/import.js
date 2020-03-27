$(function() {
    initDateTimePicker('#dateMin, #dateMax');
    initSelect2($('#statut'), 'Statut');
    ajaxAutoUserInit($('.filters .ajax-autocomplete-user'), 'Utilisateurs');

    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_IMPORT);

    $.post(path, params, function (data) {
        displayFiltersSup(data);
    }, 'json');
});

let pathImport = Routing.generate('import_api');
let tableImport = $('#tableImport').DataTable({
    processing: true,
    serverSide: true,
    "language": {
        url: "/js/i18n/dataTableLanguage.json",
    },
    ajax:{
        "url": pathImport,
        "type": "POST"
    },
    columns: [
        { "data": 'actions', 'title': 'Actions', orderable: false },
        { "data": 'id', visible: false },
        { "data": 'status', 'title': 'Statut' },
        { "data": 'startDate', 'title': 'Date début' },
        { "data": 'endDate', 'title': 'Date fin' },
        { "data": 'label', 'title': 'Nom import' },
        { "data": 'newEntries', 'title': 'Nvx enreg.' },
        { "data": 'updatedEntries', 'title': 'Mises à jour' },
        { "data": 'nbErrors', 'title': "Nombre d'erreurs" },
        { "data": 'user', 'title': 'Utilisateur' },
    ],
    order: [[1, "desc"]],
    drawCallback: function() {
        overrideSearch($('#tableImport_filter input'), tableImport);
        initTooltips($('.has-tooltip'));
        initDoubleClick('.status-planifié');
    }
});

let $modalNewImport = $("#modalNewImport");
let $submitNewImport = $("#submitNewImport");

function displayFirstModal(importId = null) {
    let $inputImportId = $modalNewImport.find('[name="importId"]');

    clearModal($modalNewImport);
    $inputImportId.val('');
    $submitNewImport.off();
    let urlNewImportFirst = Routing.generate('import_new', true);
    initModalWithAttachments($modalNewImport, $submitNewImport, urlNewImportFirst, tableImport, displaySecondModal, false);

    $.get(Routing.generate('get_first_modal_content', {importId: importId}, true), function(resp) {
        $modalNewImport.find('.modal-body').html(resp);
        if (importId) {
            $inputImportId.val(importId);
        }
        $modalNewImport.modal({
            backdrop: 'static',
            show: true
        });
    });
}

function displaySecondModal(data) {
    if (data.success) {
        $modalNewImport.find('.modal-body').html(data.html);
        $modalNewImport.find('[name="importId"]').val(data.importId);
        $submitNewImport.off();

        let urlNewImportSecond = Routing.generate('import_links', true);
        InitialiserModal($modalNewImport, $submitNewImport, urlNewImportSecond, null, displayConfirmationModal, false);
    } else {
        $modalNewImport.find('.error-msg').html(data.msg);
    }
}

function displayConfirmationModal(data) {
    $modalNewImport.find('.modal-body').html(data.html);
    $submitNewImport.off();

    let urlNewImportConfirm = Routing.generate('import_confirm', true);
    InitialiserModal($modalNewImport, $submitNewImport, urlNewImportConfirm, tableImport, launchImport);
}

function launchImport(data) {
    $.post(Routing.generate('import_launch'), data, (resp) => {
        tableImport.ajax.reload();
        if (resp.success) {
            alertSuccessMsg(resp.msg);
        } else {
            alertErrorMsg(resp.msg);
        }
    });
}

function openConfirmCancelModal(importId) {
    let $submitCancelImport = $('#submitCancelImport');
    $submitCancelImport.off();
    $submitCancelImport.on('click', function() {
        $.post(Routing.generate('import_cancel'), {importId: importId}, function() {
            tableImport.ajax.reload();
        });
    });
    $('#modalConfirmCancel').modal('show');
}

function deleteImport($btn) {
    let importId = $btn.closest('.modal').find('[name="importId"]').val();

    if (importId) {
        $.post(Routing.generate('import_delete'), {importId: importId}, () => {
            tableImport.ajax.reload();
        });
    }
}

function updateOptions($select) {
    let $tbody = $select.closest('tbody');
    let $allSelects = $tbody.find('select');
    let selectValue = $select.val();
    let selectedValues = [];

    $allSelects.each((index, element) => {
        $(element).find('option').removeAttr('disabled');
        let selectedValue = $(element).val();
        if (selectedValue != '') {
            selectedValues.push('option[value=' + selectedValue + ']');
        }
    });

    if (selectedValues.length > 0) {
        let $optionsToDisable = $tbody.find(selectedValues.join(','));
        $optionsToDisable.each(function() {
            if ($(this).closest('select').val() !== $(this).val()) {
                $(this).attr('disabled', 'disabled');
            }
        });
    }

    if (selectValue != '') {
        $select.find('option[value=' + selectValue + ']').removeAttr('disabled');
    }
}

function initDoubleClick(elem) {
    if ($(elem).length > 0) {
        document.querySelector(elem).addEventListener('click', function (e) {
            if (e.detail === 10) {
                let $modal = $('#modalLaunchPlanifiedImport');
                $modal.find('#importIdToLaunch').data('id', $(elem).data('id'));
                $modal.modal('show');
            }
        });
    }
}

function launchPlanifiedImport($btn) {
    let params = { importId : $btn.data('id'), force: 1 };

    $.post(Routing.generate('import_confirm'), JSON.stringify(params), (resp) => {
        if (resp.success) {
            alertSuccessMsg('Votre import a bien été lancé. Vous pouvez poursuivre votre navigation.');
            launchImport(params);
        } else {
            alertErrorMsg('Une erreur est survenue lors du lancement de votre import.');
        }
        tableImport.ajax.reload();
    });
}