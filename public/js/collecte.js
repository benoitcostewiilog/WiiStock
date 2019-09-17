$('.select2').select2();

$('#utilisateur').select2({
    placeholder: {
        text: 'Demandeur',
    }
});

let pathCollecte = Routing.generate('collecte_api', true);
let table = $('#tableCollecte_id').DataTable({
    order: [[0, 'desc']],
    "columnDefs": [
        {
            "type": "customDate",
            "targets": 0
        }
    ],
    "language": {
        url: "/js/i18n/dataTableLanguage.json",
    },
    ajax: {
        "url": pathCollecte,
        "type": "POST"
    },
    columns: [
        {"data": 'Création', 'name': 'Création', 'title': 'Création'},
        {"data": 'Validation', 'name': 'Validation', 'title': 'Validation'},
        {"data": 'Demandeur', 'name': 'Demandeur', 'title': 'Demandeur'},
        {"data": 'Objet', 'name': 'Objet', 'title': 'Objet'},
        {"data": 'Statut', 'name': 'Statut', 'title': 'Statut'},
        {"data": 'Type', 'name': 'Type', 'title': 'Type'},
        {"data": 'Actions', 'name': 'Actions', 'title': 'Actions'}
    ],
});

// recherche par défaut demandeur = utilisateur courant
// let demandeur = $('.current-username').val();
// if (demandeur !== undefined) {
//     let demandeurPiped = demandeur.split(',').join('|')
//     table
//         .columns('Demandeur:name')
//         .search(demandeurPiped ? '^' + demandeurPiped + '$' : '', true, false)
//         .draw();
//     // affichage par défaut du filtre select2 demandeur = utilisateur courant
//     $('#utilisateur').val(demandeur).trigger('change');
// }

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

$.extend($.fn.dataTableExt.oSort, {
    "customDate-pre": function (a) {
        let dateParts = a.split('/'),
            year = parseInt(dateParts[2]) - 1900,
            month = parseInt(dateParts[1]),
            day = parseInt(dateParts[0]);
        return Date.UTC(year, month, day, 0, 0, 0);
    },
    "customDate-asc": function (a, b) {
        return ((a < b) ? -1 : ((a > b) ? 1 : 0));
    },
    "customDate-desc": function (a, b) {
        return ((a < b) ? 1 : ((a > b) ? -1 : 0));
    }
});

//AJOUTE_ARTICLE
let pathAddArticle = Routing.generate('collecte_article_api', {'id': id}, true);
let tableArticle = $('#tableArticle_id').DataTable({
    language: {
        url: "/js/i18n/dataTableLanguage.json",
    },
    ajax: {
        "url": pathAddArticle,
        "type": "POST"
    },
    columns: [
        {"data": 'Référence', 'title': 'Référence'},
        {"data": 'Libellé', 'title': 'Libellé'},
        {"data": 'Emplacement', 'title': 'Emplacement'},
        {"data": 'Quantité', 'title': 'Quantité'},
        {"data": 'Actions', 'title': 'Actions'}
    ],
});

$.fn.dataTable.ext.search.push(
    function (settings, data, dataIndex) {
        let dateMin = $('#dateMin').val();
        let dateMax = $('#dateMax').val();
        let indexDate = table.column('Création:name').index();

        if (typeof indexDate === "undefined") return true;

        let dateInit = (data[indexDate]).split('/').reverse().join('-') || 0;

        if (
            (dateMin == "" && dateMax == "")
            ||
            (dateMin == "" && moment(dateInit).isSameOrBefore(dateMax))
            ||
            (moment(dateInit).isSameOrAfter(dateMin) && dateMax == "")
            ||
            (moment(dateInit).isSameOrAfter(dateMin) && moment(dateInit).isSameOrBefore(dateMax))
        ) {
            return true;
        }
        return false;
    }
);

let modal = $("#modalNewArticle");
let submit = $("#submitNewArticle");
let url = Routing.generate('collecte_add_article', true);
InitialiserModal(modal, submit, url, tableArticle);

let modalEditArticle = $("#modalEditArticle");
let submitEditArticle = $("#submitEditArticle");
let urlEditArticle = Routing.generate('collecte_edit_article', true);
InitialiserModal(modalEditArticle, submitEditArticle, urlEditArticle, tableArticle);

let modalDeleteArticle = $("#modalDeleteArticle");
let submitDeleteArticle = $("#submitDeleteArticle");
let urlDeleteArticle = Routing.generate('collecte_remove_article', true);
InitialiserModal(modalDeleteArticle, submitDeleteArticle, urlDeleteArticle, tableArticle);

let $submitSearchCollecte = $('#submitSearchCollecte');

// applique les filtres si pré-remplis
$(function() {
    let val = $('#statut').val();
    if (val != null && val != '') {
        $submitSearchCollecte.click();
    }

    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_DEM_COLLECTE);;
    $.post(path, params, function(data) {
        data.forEach(function(element) {
            if (element.field == 'utilisateurs') {
                $('#utilisateur').val(element.value.split(',')).select2();
            } else {
                $('#'+element.field).val(element.value);
            }
        });
        if (data.length > 0)$submitSearchCollecte.click();
    }, 'json');
});

function ajaxGetCollecteArticle(select) {
    let $selection = $('#selection');
    let $editNewArticle = $('#editNewArticle');
    let modalNewArticle = '#modalNewArticle';
    xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function () {
        if (this.readyState == 4 && this.status == 200) {
            data = JSON.parse(this.responseText);
            $selection.html(data.selection);
            if (data.modif) $editNewArticle.html(data.modif);
            $(modalNewArticle).find('.modal-footer').removeClass('d-none');
            toggleRequiredChampsLibres(select.closest('.modal').find('#type'), 'edit');
            ajaxAutoCompleteEmplacementInit($('.ajax-autocompleteEmplacement-edit'));
            initEditor(modalNewArticle + ' .editor-container-edit');
        }
    }
    path = Routing.generate('get_collecte_article_by_refArticle', true)
    $selection.html('');
    $editNewArticle.html('');
    let data = {};
    data['referenceArticle'] = $(select).val();
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


// //initialisation editeur de texte une seule fois à l'édit
// let editorEditCollecteAlreadyDone = false;
// function initEditCollecteEditor(modal) {
//     if (!editorEditCollecteAlreadyDone) {
//         initEditor(modal);
//         editorEditCollecteAlreadyDone = true;
//     }
// };

//initialisation editeur de texte une seule fois à la création
let editorNewCollecteAlreadyDone = false;

function initNewCollecteEditor(modal) {
    if (!editorNewCollecteAlreadyDone) {
        initEditorInModal(modal);
        editorNewCollecteAlreadyDone = true;
    }
    ajaxAutoCompleteEmplacementInit($('.ajax-autocompleteEmplacement'))
};

$submitSearchCollecte.on('click', function () {
    let dateMin = $('#dateMin').val();
    let dateMax = $('#dateMax').val();
    let statut = $('#statut').val();
    let demandeur = $('#utilisateur').val()
    let demandeurString = demandeur.toString();
    let demandeurPiped = demandeurString.split(',').join('|')
    let type = $('#type').val();
    saveFilters(PAGE_DEM_COLLECTE, dateMin, dateMax, statut, demandeurPiped, type);

    table
        .columns('Statut:name')
        .search(statut ? '^' + statut + '$' : '', true, false)
        .draw();

    table
        .columns('Type:name')
        .search(type ? '^' + type + '$' : '', true, false)
        .draw();

    table
        .columns('Demandeur:name')
        .search(demandeurPiped ? '^' + demandeurPiped + '$' : '', true, false)
        .draw();

    table.draw();
});

function validateCollecte(collecteId) {
    let params = JSON.stringify({id: collecteId});

    $.post(Routing.generate('demande_collecte_has_articles'), params, function (resp) {
        if (resp === true) {
            window.location.href = Routing.generate('ordre_collecte_new', {'id': collecteId});
        } else {
            $('#cannotValidate').click();
        }
    });
}

let ajaxEditArticle = function (select) {
    xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function () {
        if (this.readyState == 4 && this.status == 200) {
            dataReponse = JSON.parse(this.responseText);
            if (dataReponse) {
                $('#editNewArticle').html(dataReponse);
                ajaxAutoCompleteEmplacementInit($('.ajax-autocompleteEmplacement-edit'));
                initEditor('.editor-container-edit');
            } else {
                //TODO gérer erreur
            }
        }
    }
    let json = {id: select.val(), isADemand: 1};
    let path = Routing.generate('article_api_edit', true);
    xhttp.open("POST", path, true);
    xhttp.send(JSON.stringify(json));
}

//TODO MH utilisé ?
$submitSearchCollecte.on('keypress', function (e) {
    if (e.which === 13) {
        $submitSearchCollecte.click();
    }
});
