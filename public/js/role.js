let pathRoles = Routing.generate('role_api', true);
let tableRoles = $('#tableRoles').DataTable({
    "language": {
        url: "/js/i18n/dataTableLanguage.json",
    },
    ajax:{ 
        "url": pathRoles,
        "type": "POST"
    },
    columns:[
        { "data": 'Nom' },
        { "data": 'Actif' },
        { "data": 'Actions' }
    ],
});

let modalNewRole = $("#modalNewRole");
let submitNewRole = $("#submitNewRole");
let urlNewRole = Routing.generate('role_new', true);
InitialiserModal(modalNewRole, submitNewRole, urlNewRole, tableRoles, displayError, false);

let modalEditRole = $('#modalEditRole');
let submitEditRole = $('#submitEditRole');
let urlEditRole = Routing.generate('role_edit', true);
InitialiserModal(modalEditRole, submitEditRole, urlEditRole, tableRoles);

let ModalDeleteRole = $("#modalDeleteRole");
let SubmitDeleteRole = $("#submitDeleteRole");
let urlDeleteRole = Routing.generate('role_delete', true)
InitialiserModal(ModalDeleteRole, SubmitDeleteRole, urlDeleteRole, tableRoles);

function displayError(data) {
    let modal = $("#modalNewRole");
    if (data === false) {
        let msg = 'Ce nom de rôle existe déjà. Veuillez en choisir un autre.';
        modal.find('.error-msg').html(msg);
    } else {
        modal.find('.close').click();
    }
}