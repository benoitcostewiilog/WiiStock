$(function () {
    initFreeSelect2($('select.select2-free'));

    $('.table').each(function () {
        const $table = $(this);
        initDataTable($table.attr('id'), {
            ajax: {
                "url": Routing.generate('fields_param_api', {entityCode: $table.parent().attr('id')}),
                "type": "POST"
            },
            columns: [
                {"data": 'Actions', 'title': '', className: 'noVis', orderable: false},
                {"data": 'fieldCode', 'title': 'Champ fixe'},
                {"data": 'mustCreate', 'title': 'Obligatoire à la création'},
                {"data": 'mustEdit', 'title': 'Obligatoire à la modification'},
                {"data": 'displayedForms', 'title': 'Affiché sur les formulaires'},
                {"data": 'displayedFilters', 'title': 'Affiché sur les filtres'},
            ],
            rowConfig: {
                needsRowClickAction: true,
            },
            order: [[4, "asc"]],
            info: false,
            filter: false,
            paging: false
        });
    });

    let $modalEditFields = $('#modalEditFields');
    let $submitEditFields = $('#submitEditFields');
    let urlEditFields = Routing.generate('fields_edit', true);
    InitModal($modalEditFields, $submitEditFields, urlEditFields, {success: displayErrorFields});
});

function displayErrorFields() {
    $('.table').each(function () {
        let table = $(this).DataTable();
        table.ajax.reload();
    });
}

function switchDisplay(checkbox) {
    if (!checkbox.is(':checked')) {
        $('.checkbox').prop('checked', false);
    }
}

function switchDisplayByMust(checkbox) {
    if (checkbox.is(':checked')) {
        $('.checkbox[name="displayed"]').prop('checked', true);
    }
}
