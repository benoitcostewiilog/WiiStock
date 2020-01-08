$('.select2').select2();

//ARTICLE DEMANDE
let pathArticle = Routing.generate('demande_article_api', {id: id}, true);
let tableArticle = $('#table-lignes').DataTable({
    processing: true,
    language: {
        url: "/js/i18n/dataTableLanguage.json",
    },
    order: [[1, "desc"]],
    ajax: {
        "url": pathArticle,
        "type": "POST"
    },
    columns: [
        {"data": 'Actions', 'title': 'Actions'},
        {"data": 'Référence', 'title': 'Référence'},
        {"data": 'Libellé', 'title': 'Libellé'},
        {"data": 'Emplacement', 'title': 'Emplacement'},
        {"data": 'Quantité', 'title': 'Quantité disponible'},
        {"data": 'Quantité à prélever', 'title': 'Quantité à prélever'},
    ],
    columnDefs: [
        {
            type: "customDate",
            targets: 0
        },
        {
            orderable: false,
            targets: 0
        }
    ],
});

let modalNewArticle = $("#modalNewArticle");
let submitNewArticle = $("#submitNewArticle");
let pathNewArticle = Routing.generate('demande_add_article', true);
InitialiserModal(modalNewArticle, submitNewArticle, pathNewArticle, tableArticle);

let modalDeleteArticle = $("#modalDeleteArticle");
let submitDeleteArticle = $("#submitDeleteArticle");
let pathDeleteArticle = Routing.generate('demande_remove_article', true);
InitialiserModal(modalDeleteArticle, submitDeleteArticle, pathDeleteArticle, tableArticle);

let modalEditArticle = $("#modalEditArticle");
let submitEditArticle = $("#submitEditArticle");
let pathEditArticle = Routing.generate('demande_article_edit', true);
InitialiserModal(modalEditArticle, submitEditArticle, pathEditArticle, tableArticle);

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

//DEMANDE
let pathDemande = Routing.generate('demande_api', true);
let tableDemande = $('#table_demande').DataTable({
    serverSide: true,
    processing: true,
    language: {
        url: "/js/i18n/dataTableLanguage.json",
    },
    order: [[1, 'desc']],
    ajax: {
        "url": pathDemande,
        "type": "POST",
        'data' : {
            'filterStatus': $('#statut').val(),
            'filterReception': $('#receptionFilter').val()
        },
    },
    'drawCallback': function() {
        overrideSearch($('#table_demande_filter input'), tableDemande);
    },
    columns: [
        {"data": 'Actions', 'name': 'Actions', 'title': 'Actions'},
        {"data": 'Date', 'name': 'Date', 'title': 'Date'},
        {"data": 'Demandeur', 'name': 'Demandeur', 'title': 'Demandeur'},
        {"data": 'Numéro', 'name': 'Numéro', 'title': 'Numéro'},
        {"data": 'Statut', 'name': 'Statut', 'title': 'Statut'},
        {"data": 'Type', 'name': 'Type', 'title': 'Type'},
    ],
    columnDefs: [
        {
            type: "customDate",
            targets: 1
        },
        {
            orderable: false,
            targets: 0
        }
    ],
});

let $submitSearchDemande = $('#submitSearchDemandeLivraison');
$submitSearchDemande.on('click', function () {
    $('#dateMin').data("DateTimePicker").format('YYYY-MM-DD');
    $('#dateMax').data("DateTimePicker").format('YYYY-MM-DD');

    let filters = {
        page: PAGE_DEM_LIVRAISON,
        dateMin: $('#dateMin').val(),
        dateMax: $('#dateMax').val(),
        statut: $('#statut').val(),
        users: $('#utilisateur').select2('data'),
        type: $('#type').val(),
    };

    $('#dateMin').data("DateTimePicker").format('DD/MM/YYYY');
    $('#dateMax').data("DateTimePicker").format('DD/MM/YYYY');

    saveFilters(filters, tableDemande);

    // supprime le filtre de l'url
    let str = window.location.href.split('/');
    if (str[5]) {
        window.location.href = Routing.generate('demande_index');
    }
});

$.fn.dataTable.ext.search.push(
    function (settings, data, dataIndex) {
        let dateMin = $('#dateMin').val();
        let dateMax = $('#dateMax').val();
        let indexDate = tableDemande.column('Date:name').index();

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

let urlNewDemande = Routing.generate('demande_new', true);
let modalNewDemande = $("#modalNewDemande");
let submitNewDemande = $("#submitNewDemande");
InitialiserModal(modalNewDemande, submitNewDemande, urlNewDemande, tableDemande);

let urlDeleteDemande = Routing.generate('demande_delete', true);
let modalDeleteDemande = $("#modalDeleteDemande");
let submitDeleteDemande = $("#submitDeleteDemande");
InitialiserModal(modalDeleteDemande, submitDeleteDemande, urlDeleteDemande, tableDemande);

let urlEditDemande = Routing.generate('demande_edit', true);
let modalEditDemande = $("#modalEditDemande");
let submitEditDemande = $("#submitEditDemande");
InitialiserModal(modalEditDemande, submitEditDemande, urlEditDemande, tableDemande);

let $submitSearchDemandeLivraison = $('#submitSearchDemandeLivraison');

function getCompareStock(submit) {

    let path = Routing.generate('compare_stock', true);
    let params = {'demande': submit.data('id')};

    $.post(path, JSON.stringify(params), function (data) {
        if (data.status === true) {
            $('.zone-entete').html(data.entete);
            $('#tableArticle_id').DataTable().ajax.reload();
            $('#boutonCollecteSup, #boutonCollecteInf').addClass('d-none');
            tableArticle.ajax.reload(function (json) {
                if (data !== undefined) {
                    $('#myInput').val(json.lastInput);
                }
            });
        } else {
            $('#restantQuantite').html(data.stock);
            $('#negativStock').click();
        }
    }, 'json');
}

function setMaxQuantity(select) {
    let params = {
        refArticleId: select.val(),
    };
    $.post(Routing.generate('get_quantity_ref_article'), params, function (data) {
        if (data) {
            let modalBody = select.closest(".modal-body");
            modalBody.find('#quantity-to-deliver').attr('max', data);
        }

    }, 'json');
}

// applique les filtres si pré-remplis
$(function () {
    initDateTimePicker();
    initSelect2('#statut', 'Statut');
    ajaxAutoRefArticleInit($('.ajax-autocomplete'));
    ajaxAutoUserInit($('.ajax-autocomplete-user'), 'Utilisateurs');

    let val = $('#statut').val();
    if (!val) {
        // filtres enregistrés en base pour chaque utilisateur
        let path = Routing.generate('filter_get_by_page');
        let params = JSON.stringify(PAGE_DEM_LIVRAISON);
        $.post(path, params, function (data) {
            data.forEach(function (element) {
                if (element.field == 'utilisateurs') {
                    let values = element.value.split(',');
                    let $utilisateur = $('#utilisateur');
                    values.forEach((value) => {
                        let valueArray = value.split(':');
                        let id = valueArray[0];
                        let username = valueArray[1];
                        let option = new Option(username, id, true, true);
                        $utilisateur.append(option).trigger('change');
                    });
                } else if (element.field == 'dateMin' || element.field == 'dateMax') {
                    $('#' + element.field).val(moment(element.value, 'YYYY-MM-DD').format('DD/MM/YYYY'));
                } else {
                    $('#' + element.field).val(element.value);
                }
            });
        }, 'json');
    }
});

let editorNewLivraisonAlreadyDone = false;

function initNewLivraisonEditor(modal) {
    if (!editorNewLivraisonAlreadyDone) {
        initEditorInModal(modal);
        editorNewLivraisonAlreadyDone = true;
    }
    ajaxAutoCompleteEmplacementInit($('.ajax-autocompleteEmplacement'))
};

function ajaxGetAndFillArticle(select) {
    if ($(select).val() !== null) {
        let path = Routing.generate('demande_article_by_refArticle', true)
        let refArticle = $(select).val();
        let params = JSON.stringify(refArticle);
        let selection = $('#selection');
        let editNewArticle = $('#editNewArticle');
        let modalFooter = $('#modalNewArticle').find('div').find('div').find('.modal-footer')

        selection.html('');
        editNewArticle.html('');
        modalFooter.addClass('d-none');

        $.post(path, params, function (data) {
            selection.html(data.selection);
            editNewArticle.html(data.modif);
            modalFooter.removeClass('d-none');
            toggleRequiredChampsLibres($('#typeEdit'), 'edit');
            ajaxAutoCompleteEmplacementInit($('.ajax-autocompleteEmplacement-edit'));
        }, 'json');
    }
}

function deleteRowDemande(button, modal, submit) {
    let id = button.data('id');
    let name = button.data('name');
    modal.find(submit).attr('value', id);
    modal.find(submit).attr('name', name);
}

function validateLivraison(livraisonId, elem) {
    let params = JSON.stringify({id: livraisonId});

    $.post(Routing.generate('demande_livraison_has_articles'), params, function (resp) {
        if (resp === true) {
            getCompareStock(elem);
        } else {
            $('#cannotValidate').click();
        }
    });
}

let ajaxEditArticle = function (select) {
    let path = Routing.generate('article_api_edit', true);
    let params = {id: select.val(), isADemand: 1};

    $.post(path, JSON.stringify(params), function (data) {
        if (data) {
            $('#editNewArticle').html(data);
            let quantityToTake = $('#quantityToTake');
            let valMax = $('#quantite').val();

            let attrMax = quantityToTake.find('input').attr('max');
            if (attrMax > valMax) quantityToTake.find('input').attr('max', valMax);
            quantityToTake.removeClass('d-none');
            ajaxAutoCompleteEmplacementInit($('.ajax-autocompleteEmplacement-edit'));
        }
    });
}

let generateCSVDemande = function () {
    loadSpinner($('#spinnerlivrai'));
    let data = {};
    $('.filterService, select').first().find('input').each(function () {
        if ($(this).attr('name') !== undefined) {
            data[$(this).attr('name')] = $(this).val();
        }
    });

    if (data['dateMin'] && data['dateMax']) {
        moment(data['dateMin'], 'DD/MM/YYYY').format('YYYY-MM-DD');
        moment(data['dateMax'], 'DD/MM/YYYY').format('YYYY-MM-DD');
        let params = JSON.stringify(data);
        let path = Routing.generate('get_livraisons_for_csv', true);

        $.post(path, params, function (response) {
            if (response) {
                let csv = "";
                $.each(response, function (index, value) {
                    csv += value.join(';');
                    csv += '\n';
                });
                dlFile(csv);
                hideSpinner($('#spinnerlivrai'));
            }
        }, 'json');
    } else {
        warningEmptyDatesForCsv();
        hideSpinner($('#spinnerlivrai'));
    }
}

let dlFile = function (csv) {
    let d = new Date();
    let date = checkZero(d.getDate() + '') + '-' + checkZero(d.getMonth() + 1 + '') + '-' + checkZero(d.getFullYear() + '');
    date += ' ' + checkZero(d.getHours() + '') + '-' + checkZero(d.getMinutes() + '') + '-' + checkZero(d.getSeconds() + '');
    var exportedFilenmae = 'export-demandes-' + date + '.csv';
    var blob = new Blob([csv], {type: 'text/csv;charset=utf-8;'});
    if (navigator.msSaveBlob) { // IE 10+
        navigator.msSaveBlob(blob, exportedFilenmae);
    } else {
        var link = document.createElement("a");
        if (link.download !== undefined) {
            var url = URL.createObjectURL(blob);
            link.setAttribute("href", url);
            link.setAttribute("download", exportedFilenmae);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    }
}

$submitSearchDemandeLivraison.on('keypress', function (e) {
    if (e.which === 13) {
        $submitSearchDemandeLivraison.click();
    }
});
