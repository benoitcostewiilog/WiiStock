let pathTransporteur = Routing.generate('transporteur_api', true);
let tableTransporteurConfig = {
    order: [['Label', 'desc']],
    ajax: {
        "url": pathTransporteur,
        "type": "POST"
    },
    columns: [
        { "data": 'Actions', 'name': 'Actions', 'title': '', className: 'noVis', orderable: false },
        { "data": 'Label', 'name': 'Label', 'title': 'Nom' },
        { "data": 'Code', 'name': 'Code', 'title': 'Code' },
        { "data": 'Nombre_chauffeurs', 'name': 'Nombre_chauffeurs', 'title': 'Nombre de chauffeurs' },
    ],
    rowConfig: {
        needsRowClickAction: true
    }
};
let tableTransporteur = initDataTable('tableTransporteur_id', tableTransporteurConfig);

let modalNewTransporteur = $("#modalNewTransporteur");
let submitNewTransporteur = $("#submitNewTransporteur");
let urlNewTransporteur = Routing.generate('transporteur_new', true);
InitModal(modalNewTransporteur, submitNewTransporteur, urlNewTransporteur, {tables: [tableTransporteur]});

let modalModifyTransporteur = $('#modalEditTransporteur');
let submitModifyTransporteur = $('#submitEditTransporteur');
let urlModifyTransporteur = Routing.generate('transporteur_edit', true);
InitModal(modalModifyTransporteur, submitModifyTransporteur, urlModifyTransporteur, {tables: [tableTransporteur]});

let modalDeleteTransporteur = $('#modalDeleteTransporteur');
let submitDeleteTransporteur = $('#submitDeleteTransporteur');
let urlDeleteTransporteur = Routing.generate('transporteur_delete', true);
InitModal(modalDeleteTransporteur, submitDeleteTransporteur, urlDeleteTransporteur, {tables: [tableTransporteur]});
