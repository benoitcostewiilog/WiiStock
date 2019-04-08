$('.select2').select2();

var pathCollecte = Routing.generate('collecte_api', true);
var table = $('#tableCollecte_id').DataTable({
       "language": {
        "url": "//cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/French.json"
    },
    ajax: {
        "url": pathCollecte,
        "type": "POST"
    },
    columns: [
        { "data": 'Date' },
        { "data": 'Demandeur' },
        { "data": 'Objet' },
        { "data": 'Statut' },
        { "data": 'Actions' }
    ],
});


let modalNewCollecte = $("#modalNewCollecte");
let SubmitNewCollecte = $("#submitNewCollecte");
let urlNewCollecte = Routing.generate('collecte_new', true)
InitialiserModal(modalNewCollecte, SubmitNewCollecte, urlNewCollecte, table);

let modalDeleteCollecte = $("#modalDeleteCollecte");
let submitDeleteCollecte = $("#submitDeleteCollecte");
let urlDeleteCollecte = Routing.generate('collecte_delete', true)
InitialiserModal(modalDeleteCollecte, submitDeleteCollecte, urlDeleteCollecte, table);

let modalModifyCollecte = $('#modalEditCollecte');
let submitModifyCollecte = $('#submitEditCollecte');
let urlModifyCollecte = Routing.generate('collecte_edit', true);
InitialiserModal(modalModifyCollecte, submitModifyCollecte, urlModifyCollecte, table);


//AJOUTE_ARTICLE
let pathAddArticle = Routing.generate('collecte_article_api', { 'id': id }, true);
let tableArticle = $('#tableArticle_id').DataTable({
    language: {
        "url": "//cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/French.json"
    },
    ajax: {
        "url": pathAddArticle,
        "type": "POST"
    },
    columns: [
        { "data": 'Référence CEA' },
        { "data": 'Libellé' },
        { "data": 'Emplacement' },
        { "data": 'Quantité' },
        { "data": 'Actions' }
    ],
});

let modal = $("#modalNewArticle");
let submit = $("#submitNewArticle");
let url = Routing.generate('collecte_add_article', true);
InitialiserModal(modal, submit, url, tableArticle);

let modalDeleteArticle = $("#modalDeleteArticle");
let submitDeleteArticle = $("#submitDeleteArticle");
let urlDeleteArticle = Routing.generate('collecte_remove_article', true);
InitialiserModal(modalDeleteArticle, submitDeleteArticle, urlDeleteArticle, tableArticle);

function finishCollecte(submit, tableArticle) {
    xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function () {
        if (this.readyState == 4 && this.status == 200) {
            data = JSON.parse(this.responseText);
            $('#tableArticle_id').DataTable().ajax.reload();
            $('.zone-entete').html(data.entete)
        }
    }
    path =  Routing.generate('finish_collecte', true)
    let data = {};
    data['collecte'] = submit.data('id')
    json = JSON.stringify(data);
    xhttp.open("POST", path, true);
    xhttp.send(json);
}

$('.ajax-autocomplete').select2({
    ajax: {
        url: Routing.generate('get_ref_articles'),
        dataType: 'json',
        delay: 250,
    },
    language: {
        inputTooShort: function() {
            return 'Veuillez entrer au moins 1 caractère.';
        },
        searching: function() {
            return 'Recherche en cours...';
        },
        noResults: function() {
            return 'Aucun résultat.';
        }
    },
    minimumInputLength: 1,
});

function ajaxGetArticle(select) {
    xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function () {
        if (this.readyState == 4 && this.status == 200) {
            data = JSON.parse(this.responseText);
           $('#newContent').html(data);
           $('#modalNewArticle').find('div').find('div').find('.modal-footer').removeClass('d-none');

        }
    }
    path =  Routing.generate('get_article_by_refArticle', true)
    let data = {};
    data['referenceArticle'] = select.val();
    json = JSON.stringify(data);
    xhttp.open("POST", path, true);
    xhttp.send(json);
}

function deleteRowCollecte(button, modal, submit) {
    let id = button.data('id');
    let name = button.data('name');
    modal.find(submit).attr('value', id);
    modal.find(submit).attr('name', name);
}

function clearNewContent(button) {
        button.parent().addClass('d-none');
        $('#newContent').html('');
        $('#reference').html('');
}
