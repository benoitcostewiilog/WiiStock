$(function() {
    $('.select2').select2();
    initDateTimePicker();
    Select2.init($('.filter-select2[name="natures"]'), 'Natures');

    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_PACK);
    $.post(path, params, function(data) {
        displayFiltersSup(data);
    }, 'json');

    Select2.location($('.ajax-autocomplete-emplacements'), {}, "Emplacement", 3);

    const packsTable = initDataTable('packsTable', {
        responsive: true,
        serverSide: true,
        processing: true,
        order: [['quantity', "desc"]],
        ajax: {
            "url": Routing.generate('pack_api', true),
            "type": "POST",
        },
        drawConfig: {
            needsSearchOverride: true,
        },
        rowConfig: {
            needsRowClickAction: true
        },
        columns: [
            {"data": 'actions', 'name': 'actions', 'title': '', className: 'noVis', orderable: false},
            {"data": 'packNum', 'name': 'packNum', 'title': 'colis.Numéro colis', translated: true},
            {"data": 'packNature', 'name': 'packNature', 'title': 'natures.Nature de colis', translated: true},
            {"data": "quantity", 'name': 'quantity', 'title': 'Quantité'},
            {"data": 'packLastDate', 'name': 'packLastDate', 'title': 'Date du dernier mouvement'},
            {"data": "packOrigin", 'name': 'packOrigin', 'title': 'Issu de', className: 'noVis', orderable: false},
            {"data": "packLocation", 'name': 'packLocation', 'title': 'Emplacement'},
            {"data": "arrivageType", 'name': 'arrivageType', 'title': 'Type d\'arrivage'},

        ]
    });

    const $modalEditPack = $('#modalEditPack');
    const $submitEditPack = $('#submitEditPack');
    const urlEditPack = Routing.generate('pack_edit', true);
    InitModal($modalEditPack, $submitEditPack, urlEditPack, {tables: [packsTable]});


    let modalDeletePack = $("#modalDeletePack");
    let SubmitDeletePack = $("#submitDeletePack");
    let urlDeletePack = Routing.generate('pack_delete', true);
    InitModal(modalDeletePack, SubmitDeletePack, urlDeletePack, {tables: [packsTable], clearOnClose: true});
});
