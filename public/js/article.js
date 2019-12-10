let pathArticle = Routing.generate('article_api', true);
let tableArticle;
$(function () {
    initTableArticle();
});

function initTableArticle() {
    $.post(Routing.generate('article_api_columns'), function (columns) {
        tableArticle = $('#tableArticle_id')
            .on('error.dt', function (e, settings, techNote, message) {
                console.log('An error has been reported by DataTables: ', message);
            }).DataTable({
                serverSide: true,
                processing: true,
                paging: true,
                scrollX: true,
                order: [[1, 'asc']],
                "language": {
                    url: "/js/i18n/dataTableLanguage.json",
                },
                ajax: {
                    "url": pathArticle,
                    "type": "POST",
                    'dataSrc': function (json) {
                        $('#listArticleIdToPrint').val(json.listId);
                        if (!$(".statutVisible").val()) {
                            tableArticle.column('Statut:name').visible(false);
                        }
                        return json.data;
                    }
                },
                initComplete: function () {
                    loadSpinnerAR($('#spinner'));
                    init();
                    hideAndShowColumns();
                    overrideSearchArticle();
                },
                "drawCallback": function (settings) {
                    resizeTable();
                },
                columns: columns,
            });
    });
}

function loadSpinnerAR(div) {
    div.removeClass('d-flex');
    div.addClass('d-none');
}

let resetNewArticle = function (element) {
    element.removeClass('d-block');
    element.addClass('d-none');
};

function init() {
    ajaxAutoFournisseurInit($('.ajax-autocompleteFournisseur'));
    let modalEditArticle = $("#modalEditArticle");
    let submitEditArticle = $("#submitEditArticle");
    let urlEditArticle = Routing.generate('article_edit', true);
    InitialiserModal(modalEditArticle, submitEditArticle, urlEditArticle, tableArticle);

    let modalNewArticle = $("#modalNewArticle");
    let submitNewArticle = $("#submitNewArticle");
    let urlNewArticle = Routing.generate('article_new', true);
    InitialiserModal(modalNewArticle, submitNewArticle, urlNewArticle, tableArticle);

    let modalDeleteArticle = $("#modalDeleteArticle");
    let submitDeleteArticle = $("#submitDeleteArticle");
    let urlDeleteArticle = Routing.generate('article_delete', true);
    InitialiserModal(modalDeleteArticle, submitDeleteArticle, urlDeleteArticle, tableArticle);

    let modalColumnVisible = $('#modalColumnVisible');
    let submitColumnVisible = $('#submitColumnVisible');
    let urlColumnVisible = Routing.generate('save_column_visible_for_article', true);
    InitialiserModal(modalColumnVisible, submitColumnVisible, urlColumnVisible);

    tableArticle.on('responsive-resize', function (e, datatable) {
        resizeTable();
    });

}
function resizeTable() {
    tableArticle
        .columns.adjust()
        .responsive.recalc();

function initNewArticleEditor(modal) {
    initEditor(modal + ' .editor-container-new');
};

function loadAndDisplayInfos(select) {
    if ($(select).val() !== null) {
        let path = Routing.generate('demande_reference_by_fournisseur', true)
        let fournisseur = $(select).val();
        let params = JSON.stringify(fournisseur);

        $.post(path, params, function (data) {
            $('#newContent').html(data);
            $('#modalNewArticle').find('div').find('div').find('.modal-footer').removeClass('d-none');
            initNewArticleEditor("#modalNewArticle");
            ajaxAutoCompleteEmplacementInit($('.ajax-autocompleteEmplacement'));
        })
    }
}

let getArticleFournisseur = function () {
    xhttp = new XMLHttpRequest();
    let $articleFourn = $('#newContent');
    let modalfooter = $('#modalNewArticle').find('.modal-footer');
    xhttp.onreadystatechange = function () {
        if (this.readyState == 4 && this.status == 200) {
            data = JSON.parse(this.responseText);

            if (data.content) {
                modalfooter.removeClass('d-none')
                $articleFourn.parent('div').addClass('d-block');
                $articleFourn.html(data.content);
                $('.error-msg').html('')
                ajaxAutoCompleteEmplacementInit($('.ajax-autocompleteEmplacement'));
                initNewArticleEditor("#modalNewArticle");
            } else if (data.error) {
                $('.error-msg').html(data.error)
            }
        }
    }
    path = Routing.generate('ajax_article_new_content', true)
    let data = {};
    $('#newContent').html('');
    data['referenceArticle'] = $('#referenceCEA').val();
    data['fournisseur'] = $('#fournisseurID').val();
    $articleFourn.html('')
    modalfooter.addClass('d-none')
    if (data['referenceArticle'] && data['fournisseur']) {
        json = JSON.stringify(data);
        xhttp.open("POST", path, true);
        xhttp.send(json);
    }
};

function clearNewArticleContent(button) {
    button.parent().addClass('d-none');
    let $modal = button.closest('.modal');
    $modal.find('#fournisseur').addClass('d-none');
    $modal.find('#referenceCEA').val(null).trigger('change');
    $('#newContent').html('');
    $('#reference').html('');
    clearModal('#' + $modal.attr('id'));
}

let ajaxGetFournisseurByRefArticle = function (select) {
    if (select.val()) {
        let fournisseur = $('#fournisseur');
        let modalfooter = $('#modalNewArticle').find('.modal-footer');
        xhttp = new XMLHttpRequest();
        xhttp.onreadystatechange = function () {
            if (this.readyState == 4 && this.status == 200) {
                data = JSON.parse(this.responseText);
                if (data === false) {
                    $('.error-msg').html('Vous ne pouvez par créer d\'article quand la quantité est gérée à la référence.');
                } else {
                    fournisseur.removeClass('d-none');
                    fournisseur.find('select').html(data);
                    $('.error-msg').html('');
                }
            }
        };
        path = Routing.generate('ajax_fournisseur_by_refarticle', true)
        $('#newContent').html('');
        fournisseur.addClass('d-none');
        modalfooter.addClass('d-none')
        let refArticleId = select.val();
        let json = {};
        json['refArticle'] = refArticleId;
        Json = JSON.stringify(json);
        xhttp.open("POST", path, true);
        xhttp.send(Json);
    }
};

function printSingleArticleBarcode(button) {
    let params = {
        'article': button.data('id')
    };
    $.post(Routing.generate('get_article_from_id'), JSON.stringify(params), function (response) {
        if (response.exists) {
            printBarcodes(
                [response.articleRef.barcode],
                response,
                'Etiquette article ' + response.articleRef.artLabel + '.pdf',
                [response.articleRef.barcodeLabel],
            );
        } else {
            $('#cannotGenerate').click();
        }
    });
}

function changeStatus(button) {
    let sel = $(button).data('title');
    let tog = $(button).data('toggle');
    $('#' + tog).prop('value', sel);

    $('span[data-toggle="' + tog + '"]').not('[data-title="' + sel + '"]').removeClass('active').addClass('not-active');
    $('span[data-toggle="' + tog + '"][data-title="' + sel + '"]').removeClass('not-active').addClass('active');
}

function overrideSearchArticle() {
    let $input = $('#tableArticle_id_filter input');
    $input.off();
    $input.on('keyup', function(e) {
        if (e.key === 'Enter') {
            if ($input.val() === '') {
                $('.justify-content-end').find('.printButton').addClass('btn-disabled');
                $('.justify-content-end').find('.printButton').removeClass('btn-primary');
            } else {
                $('.justify-content-end').find('.printButton').removeClass('btn-disabled');
                $('.justify-content-end').find('.printButton').addClass('btn-primary');
            }
            tableArticle.search(this.value).draw();
        } else if (e.key === 'Backspace' && $input.val() === '') {
            $('.justify-content-end').find('.printButton').addClass('btn-disabled');
            $('.justify-content-end').find('.printButton').removeClass('btn-primary');
        }
    });
    $input.attr('placeholder', 'entrée pour valider');
}

function getDataAndPrintLabels() {
    let path = Routing.generate('article_get_data_to_print', true);
    let listArticles = $("#listArticleIdToPrint").val();
    let params = JSON.stringify({
        listArticles: listArticles,
        start: tableArticle.page.info().start,
        length: tableArticle.page.info().length
    });
    $.post(path, params, function (response) {
        if (response.tags.exists) {
            printBarcodes(
                response.barcodes,
                response.tags,
                'Etiquettes-articles.pdf',
                response.barcodesLabels);
        }
    });
}

function saveRapidSearch() {
    let searchesWanted = [];
    $('#rapidSearch tbody td').each(function () {
        searchesWanted.push($(this).html());
    });
    let params = {
        recherches: searchesWanted
    };
    let json = JSON.stringify(params);
    $.post(Routing.generate('update_user_searches_for_article', true), json, function (data) {
        $("#modalRapidSearch").find('.close').click();
        tableArticle.search(tableArticle.search()).draw();
    });
}

function showOrHideColumn(check) {

    let columnName = check.data('name');

    let column = tableArticle.column(columnName + ':name');

    column.visible(!column.visible());

    let tableRefArticleColumn = $('#tableArticle_id_wrapper');
    tableRefArticleColumn.find('th, td').removeClass('hide');
    tableRefArticleColumn.find('th, td').addClass('display');
    check.toggleClass('data');
}

function hideAndShowColumns() {
    tableArticle.columns('.hide').visible(false);
    tableArticle.columns('.display').visible(true);
}