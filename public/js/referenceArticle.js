//REFERENCE ARTICLE 

const urlApiRefArticle = Routing.generate('ref_article_api', true);
var tableRefArticle = $('#tableRefArticle_id').DataTable({
    "language": {
        "url": "/js/i18n/dataTableLanguage.json"
    },
    ajax: {
        "url": urlApiRefArticle,
        "type": "POST"
    },
    columns: [
        { "data": 'Libellé' },
        { "data": 'Référence' },
        { "data": 'Quantité' },
        { "data": 'Actions' },
    ],
});

let ModalRefArticleNew = $("#modalNewRefArticle");
let ButtonSubmitRefArticleNew = $("#submitNewRefArticle");
let urlRefArticleNew = Routing.generate('reference_article_new', true);
InitialiserModal(ModalRefArticleNew, ButtonSubmitRefArticleNew, urlRefArticleNew, tableRefArticle);

let ModalDeleteRefArticle = $("#modalDeleteRefArticle");
let SubmitDeleteRefArticle = $("#submitDeleteRefArticle");
let urlDeleteRefArticle = Routing.generate('reference_article_delete', true);
InitialiserModal(ModalDeleteRefArticle, SubmitDeleteRefArticle, urlDeleteRefArticle, tableRefArticle);

let modalModifyRefArticle = $('#modalEditRefArticle');
let submitModifyRefArticle = $('#submitEditRefArticle');
let urlModifyRefArticle = Routing.generate('reference_article_edit', true);
InitialiserModal(modalModifyRefArticle, submitModifyRefArticle, urlModifyRefArticle,  tableRefArticle);


$('#myTab button').on('click', function (e) {
    $(this).siblings().removeClass('data');
    $(this).addClass('data');
  })
