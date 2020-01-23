$('.select2').select2();

let $submitSearchLitigesArr = $('#submitSearchLitiges');

$(function() {
    initDateTimePicker();
    initSelect2('#carriers', 'Transporteurs');
    initSelect2('#statut', 'Statut');
    initSelect2('#litigeOrigin', 'Origine');
    ajaxAutoUserInit($('.ajax-autocomplete-user'), 'Acheteurs');
    ajaxAutoFournisseurInit($('.filters').find('.ajax-autocomplete-fournisseur'), 'Fournisseurs');

    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_LITIGE_ARR);
    $.post(path, params, function(data) {
        displayFiltersSup(data);
    }, 'json');

    ajaxAutoUserInit($('.ajax-autocomplete-user'), 'Acheteurs');
    ajaxAutoFournisseurInit($('.ajax-autocomplete-fournisseur'), 'Fournisseurs');

});

let pathLitiges = Routing.generate('litige_api', true);
let tableLitiges = $('#tableLitiges').DataTable({
    responsive: true,
    serverSide: true,
    processing: true,
    language: {
        url: "/js/i18n/dataTableLanguage.json",
    },
    order: [[5, 'desc']],
    ajax: {
        "url": pathLitiges,
        "type": "POST",
    },
    'drawCallback': function() {
        overrideSearch($('#tableLitiges_filter input'), tableLitiges);
    },
    columns: [
        {"data": 'actions', 'name': 'Actions', 'title': 'Actions'},
        {"data": 'type', 'name': 'type', 'title': 'Type'},
        {"data": "arrivalNumber", 'name': 'arrivalNumber', 'title': "N° " + $('#trans-arrivage').val()},
        {"data": 'buyers', 'name': 'buyers', 'title': 'Acheteurs'},
        {"data": 'receptionNumber', 'name': 'receptionNumber', 'title': 'N° ' + $('#trans-reception').val()},
        {"data": 'lastHistoric', 'name': 'lastHistoric', 'title': 'Dernier historique'},
        {"data": 'creationDate', 'name': 'creationDate', 'title': 'Créé le'},
        {"data": 'updateDate', 'name': 'updateDate', 'title': 'Modifié le'},
        {"data": 'status', 'name': 'status', 'title': 'Statut', 'target': 7},
    ],
    columnDefs: [
        {
            orderable: false,
            targets: [0]
        }
    ],
    dom: '<"row"<"col-4"B><"col-4"l><"col-4"f>>t<"bottom"ip>r',
    buttons: [
        {
            extend: 'colvis',
            columns: ':not(.noVis)',
            className: 'dt-btn'
        },
        // {
        //     extend: 'csv',
        //     className: 'dt-btn'
        // }
    ]
});

let modalNewLitiges = $('#modalNewLitiges');
let submitNewLitiges = $('#submitNewLitiges');
let urlNewLitiges = Routing.generate('litige_new', true);
InitialiserModal(modalNewLitiges, submitNewLitiges, urlNewLitiges, tableLitiges);

let modalEditLitige = $('#modalEditLitige');
let submitEditLitige = $('#submitEditLitige');
let urlEditLitige = Routing.generate('litige_edit', true);
initModalWithAttachments(modalEditLitige, submitEditLitige, urlEditLitige, tableLitiges);

let ModalDeleteLitige = $("#modalDeleteLitige");
let SubmitDeleteLitige = $("#submitDeleteLitige");
let urlDeleteLitige = Routing.generate('litige_delete', true);
InitialiserModal(ModalDeleteLitige, SubmitDeleteLitige, urlDeleteLitige, tableLitiges);

function editRowLitige(button, afterLoadingEditModal = () => {}, isArrivage, arrivageOrReceptionId, litigeId) {
    let route = isArrivage ? 'litige_api_edit' : 'litige_api_edit_reception';
    let path = Routing.generate(route, true);
    let modal = $('#modalEditLitige');
    let submit = $('#submitEditLitige');

    let params = {
        litigeId: litigeId,
    };

    if (isArrivage) {
        params.arrivageId = arrivageOrReceptionId;
    } else {
        params.reception = arrivageOrReceptionId;
    }

    $.post(path, JSON.stringify(params), function (data) {
        modal.find('.error-msg').html('');
        modal.find('.modal-body').html(data.html);

        if (isArrivage) {
            modal.find('#colisEditLitige').val(data.colis).select2();
        } else {
            ajaxAutoArticlesReceptionInit(modal.find('.select2-autocomplete-articles'), arrivageOrReceptionId);

            let values = [];
            data.colis.forEach(val => {
                values.push({
                    id: val.id,
                    text: val.text
                })
            });
            values.forEach(value => {
                $('#colisEditLitige').select2("trigger", "select", {
                    data: value
                });
            });

            modal.find('#acheteursLitigeEdit').val(data.acheteurs).select2();
        }

        afterLoadingEditModal();

    }, 'json');

    modal.find(submit).attr('value', litigeId);
}

let tableHistoLitige;
function openTableHisto() {

    let pathHistoLitige = Routing.generate('histo_litige_api', {litige: $('#litigeId').val()}, true);
    tableHistoLitige = $('#tableHistoLitige').DataTable({
        language: {
            url: "/js/i18n/dataTableLanguage.json",
        },
        ajax: {
            "url": pathHistoLitige,
            "type": "POST"
        },
        columns: [
            {"data": 'user', 'name': 'Utilisateur', 'title': 'Utilisateur'},
            {"data": 'date', 'name': 'date', 'title': 'Date'},
            {"data": 'commentaire', 'name': 'commentaire', 'title': 'Commentaire'},
        ],
        dom: '<"top">rt<"bottom"lp><"clear">'
    });
}

$.fn.dataTable.ext.search.push(
    function (settings, data) {
        let dateMin = $('#dateMin').val();
        let dateMax = $('#dateMax').val();
        let indexDate = tableLitiges.column('creationDate:name').index();

        if (typeof indexDate === "undefined") return true;
        if (typeof data[indexDate] !== "undefined") {
            let dateInit = (data[indexDate]).split(' ')[0].split('/').reverse().join('-') || 0;

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
        return true;
    }
);

$submitSearchLitigesArr.on('click', function () {
    $('#dateMin').data("DateTimePicker").format('YYYY-MM-DD');
    $('#dateMax').data("DateTimePicker").format('YYYY-MM-DD');

    let filters = {
        page: PAGE_LITIGE_ARR,
        dateMin: $('#dateMin').val(),
        dateMax: $('#dateMax').val(),
        statut: $('#statut').val(),
        type: $('#type').val(),
        litigeOrigin: $('#litigeOrigin').val(),
        carriers: $('#carriers').select2('data'),
        providers: $('#providers').select2('data'),
        users: $('#utilisateur').select2('data'),
    };

    $('#dateMin').data("DateTimePicker").format('DD/MM/YYYY');
    $('#dateMax').data("DateTimePicker").format('DD/MM/YYYY');

    saveFilters(filters, tableLitiges);
});

function getCommentAndAddHisto()
{
    let path = Routing.generate('add_comment', {litige: $('#litigeId').val()}, true);
    let commentLitige = $('#modalEditLitige').find('#litige-edit-commentaire');
    let dataComment = commentLitige.val();

    $.post(path, JSON.stringify(dataComment), function () {
        tableHistoLitige.ajax.reload();
        commentLitige.val('');
    });
}
