$(function () {
    const $filtersContainer = $('.filters-container');

    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_STATUS);

    $.post(path, params, function(data) {
        displayFiltersSup(data);

        const {value: selectedCategoryId} = (data || []).find(({field}) => (field === 'statusEntity')) || {};
        if (selectedCategoryId) {
            $filtersContainer
                .find(`.filter-tab[data-id="${selectedCategoryId}"]`)
                .addClass('active');
        }
    }, 'json');

    $filtersContainer.find('.filter-tab').click(function() {
        const $button = $(this);
        const id = $button.data('id');

        $filtersContainer.find('.filter-tab').removeClass('active');
        $button.addClass('active');
        $button.trigger('blur');

        $filtersContainer.find('select[name="statusEntity"]').val(id);
        $filtersContainer.find('.submit-button').click();
    });

    let pathStatus = Routing.generate('status_param_api', true);
    let tableStatusConfig = {
        processing: true,
        serverSide: true,
        ajax: {
            "url": pathStatus,
            "type": "POST"
        },
        columns: [
            {"data": 'actions', "name": 'actions', 'title': '', className: 'noVis', orderable: false},
            {"data": 'category', "name": 'category', 'title': 'Entité'},
            {"data": 'label', "name": 'label', 'title': 'Libellé'},
            {"data": 'state', "name": 'state', 'title': 'État'},
            {"data": 'defaultStatus', "name": 'defaultStatus', 'title': 'Statut par défaut'},
            {"data": 'order', "name": 'order', 'title': 'Ordre'},
            {"data": 'type', "name": 'type', 'title': 'Type'},
        ],
        order: [
            ['category', 'asc'],
            ['order', 'asc']
        ],
        rowConfig: {
            needsRowClickAction: true,
        },
    };
    let tableStatus = initDataTable('tableStatus', tableStatusConfig);

    let modalNewStatus = $("#modalNewStatus");
    let submitNewStatus = $("#submitNewStatus");
    let urlNewStatus = Routing.generate('status_new', true);
    InitModal(modalNewStatus, submitNewStatus, urlNewStatus, {tables: [tableStatus]});

    let modalEditStatus = $('#modalEditStatus');
    let submitEditStatus = $('#submitEditStatus');
    let urlEditStatus = Routing.generate('status_edit', true);
    InitModal(modalEditStatus, submitEditStatus, urlEditStatus, {tables: [tableStatus]});

    let modalDeleteStatus = $("#modalDeleteStatus");
    let submitDeleteStatus = $("#submitDeleteStatus");
    let urlDeleteStatus = Routing.generate('status_delete', true)
    InitModal(modalDeleteStatus, submitDeleteStatus, urlDeleteStatus, {tables: [tableStatus]});
});

function hideOptionOnChange($modal, forceClear = true) {
    const $select = $modal.find('[name="category"]');
    const $dispatchFields = $modal.find('.dispatch-fields');
    const $handlingFields = $modal.find('.handling-fields');
    const $disputeFields = $modal.find('.dispute-fields');
    const $arrivalFields = $modal.find('.arrival-fields');
    const $purchaseRequestFields = $modal.find('.purchase-request-fields');

    $dispatchFields.addClass('d-none');
    $handlingFields.addClass('d-none');
    $disputeFields.addClass('d-none');
    $arrivalFields.addClass('d-none');
    $purchaseRequestFields.addClass('d-none');
    $modal.find('.field-needed').removeClass('needed');

    if (forceClear) {
        for(const $field of [$dispatchFields, $dispatchFields, $disputeFields, $arrivalFields, $purchaseRequestFields]) {
            const $select = $field.is('option')
                ? $field.closest('select')
                : $field.find('select');
            if ($select.length > 0) {
                $select.find('option:selected').prop("selected", false);
                $select.val('');
            }
        }
    }

    const category = Number($select.val());
    if (category) {
        const categoryStatusDispatchId = Number($('#categoryStatusDispatchId').val());
        const categoryStatusHandlingId = Number($('#categoryStatusHandlingId').val());
        const categoryStatusArrivalId = Number($('#categoryStatusArrivalId').val());
        const categoryStatusPurchaseRequest = Number($('#categoryStatusPurchaseRequestId').val());
        const $fields = (
            (category === categoryStatusDispatchId) ? $dispatchFields :
            (category === categoryStatusHandlingId) ? $handlingFields :
            (category === categoryStatusArrivalId) ? $arrivalFields :
            (category === categoryStatusPurchaseRequest) ? $purchaseRequestFields :
            $disputeFields
        );
        $fields.removeClass('d-none');
        $fields.find('.field-needed').addClass('needed');
    }
}

function statusStateChanged($select) {
    const $modal = $select.parents('.modal');
    const selectedEntityIsDispatch = $modal.find('select[name="category"]').find('option:selected').data('is-dispatch');
    const selectedEntityIsPurchaseRequest = $modal.find('select[name="category"]').find('option:selected').data('is-purchase-request');
    const selectedStatusNeedsNomadSync = $select.find('option:selected').data('needs-nomad-sync');
    const selectedStatusNeedsAutomaticReceptionCreation = $select.find('option:selected').data('needs-automatic-reception-creation');
    if (selectedEntityIsDispatch && !selectedStatusNeedsNomadSync) {
        $modal.find('.nomad-sync').addClass('d-none');
    } else if (selectedEntityIsDispatch && selectedStatusNeedsNomadSync) {
        $modal.find('.nomad-sync').removeClass('d-none');
    } else if (selectedEntityIsPurchaseRequest && !selectedStatusNeedsAutomaticReceptionCreation) {
        $modal.find('.automatic-reception-creation').addClass('d-none');
    } else if (selectedEntityIsPurchaseRequest && selectedStatusNeedsAutomaticReceptionCreation) {
        $modal.find('.automatic-reception-creation').removeClass('d-none');
    }
}
