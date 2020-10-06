$('.select2').select2();

let pathCollecte = Routing.generate('collecte_api', true);
let collecteTableConfig = {
    processing: true,
    serverSide: true,
    order: [[1, 'desc']],
    ajax: {
        "url": pathCollecte,
        "type": "POST",
        'data' : {
            'filterStatus': $('#filterStatus').val()
        },
    },
    rowConfig: {
        needsRowClickAction: true,
    },
    drawConfig: {
        needsSearchOverride: true,
    },
    columns: [
        {"data": 'Actions', 'name': 'Actions', 'title': '', className: 'noVis', orderable: false},
        {"data": 'Création', 'name': 'Création', 'title': 'Création'},
        {"data": 'Validation', 'name': 'Validation', 'title': 'Validation'},
        {"data": 'Demandeur', 'name': 'Demandeur', 'title': 'Demandeur'},
        {"data": 'Numéro', 'name': 'Numéro', 'title': 'Numéro'},
        {"data": 'Objet', 'name': 'Objet', 'title': 'Objet'},
        {"data": 'Statut', 'name': 'Statut', 'title': 'Statut'},
        {"data": 'Type', 'name': 'Type', 'title': 'Type'},
    ]
};
let table = initDataTable('tableCollecte_id', collecteTableConfig);


let modalNewCollecte = $("#modalNewCollecte");
let SubmitNewCollecte = $("#submitNewCollecte");
let urlNewCollecte = Routing.generate('collecte_new', true)
InitModal(modalNewCollecte, SubmitNewCollecte, urlNewCollecte, {tables: [table]});

let modalDeleteCollecte = $("#modalDeleteCollecte");
let submitDeleteCollecte = $("#submitDeleteCollecte");
let urlDeleteCollecte = Routing.generate('collecte_delete', true)
InitModal(modalDeleteCollecte, submitDeleteCollecte, urlDeleteCollecte, {tables: [table]});

let modalModifyCollecte = $('#modalEditCollecte');
let submitModifyCollecte = $('#submitEditCollecte');
let urlModifyCollecte = Routing.generate('collecte_edit', true);
InitModal(modalModifyCollecte, submitModifyCollecte, urlModifyCollecte, {tables: [table]});

let pathAddArticle = Routing.generate('collecte_article_api', {'id': id}, true);
let tableArticleConfig = {
    ajax: {
        "url": pathAddArticle,
        "type": "POST"
    },
    order: [[1, 'desc']],
    rowConfig: {
        needsRowClickAction: true,
    },
    columns: [
        {"data": 'Actions', 'title': '', className: 'noVis', orderable: false},
        {"data": 'Référence', 'title': 'Référence'},
        {"data": 'Libellé', 'title': 'Libellé'},
        {"data": 'Emplacement', 'title': 'Emplacement'},
        {"data": 'Quantité', 'title': 'Quantité'}
    ],
};
let tableArticle = initDataTable('tableArticle_id', tableArticleConfig);

$.fn.dataTable.ext.search.push(
    function (settings, data, dataIndex) {
        let dateMin = $('#dateMin').val();
        let dateMax = $('#dateMax').val();
        let indexDate = table.column('Création:name').index();

        if (typeof indexDate === "undefined") return true;

        let dateInit = (data[indexDate]).split('/').reverse().join('-') || 0;

        if (
            (dateMin === "" && dateMax === "")
            ||
            (dateMin === "" && moment(dateInit).isSameOrBefore(dateMax))
            ||
            (moment(dateInit).isSameOrAfter(dateMin) && dateMax === "")
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
InitModal(modal, submit, url, {tables: [tableArticle]});

let modalEditArticle = $("#modalEditArticle");
let submitEditArticle = $("#submitEditArticle");
let urlEditArticle = Routing.generate('collecte_edit_article', true);
InitModal(modalEditArticle, submitEditArticle, urlEditArticle, {tables: [tableArticle]});

let modalDeleteArticle = $("#modalDeleteArticle");
let submitDeleteArticle = $("#submitDeleteArticle");
let urlDeleteArticle = Routing.generate('collecte_remove_article', true);
InitModal(modalDeleteArticle, submitDeleteArticle, urlDeleteArticle, {tables: [tableArticle]});

$(function() {
    initDateTimePicker();
    Select2.init($('#statut'), 'Statuts');
    Select2.user('Demandeurs');

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
        let params = JSON.stringify(PAGE_DEM_COLLECTE);
        $.post(path, params, function (data) {
            displayFiltersSup(data);
        }, 'json');
    }

    const $modalNewCollecte = $('#modalNewCollecte');
    $modalNewCollecte.on('show.bs.modal', function () {
        initNewCollecteEditor("#modalNewCollecte");
        clearModal("#modalNewCollecte");
    });
});


function ajaxGetCollecteArticle(select) {
    let $selection = $('#selection');
    let $editNewArticle = $('#editNewArticle');
    let modalNewArticle = '#modalNewArticle';
    let xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function () {
        if (this.readyState == 4 && this.status == 200) {
            data = JSON.parse(this.responseText);
            $selection.html(data.selection);
            if (data.modif) {
                $editNewArticle.html(data.modif);
                registerNumberInputProtection($editNewArticle.find('input[type="number"]'));
            }
            $(modalNewArticle).find('.modal-footer').removeClass('d-none');
            toggleRequiredChampsLibres(select.closest('.modal').find('#type'), 'edit');
            Select2.location($('.ajax-autocomplete-location-edit'));
            initEditor(modalNewArticle + ' .editor-container-edit');
            $('.list-multiple').select2();
        }
    }
    path = Routing.generate('get_collecte_article_by_refArticle', true);
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

//initialisation editeur de texte une seule fois à la création
let editorNewCollecteAlreadyDone = false;

function initNewCollecteEditor(modal) {
    if (!editorNewCollecteAlreadyDone) {
        initEditorInModal(modal);
        editorNewCollecteAlreadyDone = true;
    }
    Select2.location($('.ajax-autocomplete-location'))
}

function validateCollecte(collecteId, $button) {
    let params = JSON.stringify({id: collecteId});

    wrapLoadingOnActionButton($button, () => (
        $.post({
            url: Routing.generate('demande_collecte_has_articles'),
            data: params
        })
            .then(function (resp) {
                if (resp === true) {
                    window.location.href = Routing.generate('ordre_collecte_new', {'id': collecteId});
                    return true;
                } else {
                    $('#cannotValidate').click();
                    return false;
                }
            })
    ), false);
}

let ajaxEditArticle = function (select) {
    let path = Routing.generate('article_show', true);
    let params = {id: select.val(), isADemand: 1};

    $.post(path, JSON.stringify(params), function(data) {
        $('#editNewArticle').html(data);
        Select2.location($('.ajax-autocomplete-location-edit'));
        initEditor('.editor-container-edit');
    }, 'json');
}

function callbackSaveFilter() {
    // supprime le filtre de l'url
    let str = window.location.href.split('/');
    if (str[5]) {
        window.location.href = Routing.generate('collecte_index');
    }
}