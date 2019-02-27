var path = Routing.generate('reception_api', true);
var table = $('#table_id').DataTable({
    order: [[ 1, "desc" ]],
    language: {
        "url": "//cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/French.json"
    },
    ajax:{ 
        "url": path,
        "type": "POST"
    },
    columns: [
        { "data": 'Statut' },
        { "data": 'Date commande' },
        { "data": 'Date attendue' },
        { "data": 'Fournisseur' },
        { "data": 'Référence' },
        { "data": 'Actions' }
    ],
});

var modalPath = Routing.generate('createReception', true);
var dataModal = $("#dataModalCenter");
var ButtonSubmit = $("#submitButton");
InitialiserModal(dataModal, ButtonSubmit, modalPath);