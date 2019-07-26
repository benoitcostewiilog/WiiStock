$('.select2').select2();

$('#utilisateur').select2({
    placeholder: {
        text: 'Opérateur',
    }
});

$('#emplacement').select2({
    placeholder: {
        id: 0,
        text: 'Emplacement',
    }
});

let pathMvt = Routing.generate('mouvement_stock_api', true);
let tableMvt = $('#tableMvts').DataTable({
    "language": {
        url: "/js/i18n/dataTableLanguage.json",
    },
    "order": [[0, "desc"]],
    ajax: {
        "url": pathMvt,
        "type": "POST"
    },
    columns: [
        {"data": 'date attendue', 'name': 'date attendue', 'title': 'Date attendue'},
        {"data": 'date', 'name': 'date', 'title': 'Date'},
        {"data": "refArticle", 'name': 'refArticle', 'title': 'Référence article'},
        {"data": "quantite", 'name': 'quantite', 'title': 'Quantité'},
        {"data": 'origine', 'name': 'origine', 'title': 'Origine'},
        {"data": 'destination', 'name': 'destination', 'title': 'Destination'},
        {"data": 'type', 'name': 'type', 'title': 'Type'},
        {"data": 'operateur', 'name': 'operateur', 'title': 'Opérateur'},
        {"data": 'actions', 'name': 'Actions', 'title': 'Actions'},
    ],

});
let modalDeleteArrivage = $('#modalDeleteMvtStock');
let submitDeleteArrivage = $('#submitDeleteMvtStock');
let urlDeleteArrivage = Routing.generate('mvt_stock_delete', true);
InitialiserModal(modalDeleteArrivage, submitDeleteArrivage, urlDeleteArrivage, tableMvt);

$('#submitSearchMvt').on('click', function () {

    let statut = $('#statut').val();
    let emplacement = $('#emplacement').val();
    let article = $('#colis').val();
    let demandeur = $('#utilisateur').val()
    let demandeurString = demandeur.toString();
    demandeurPiped = demandeurString.split(',').join('|')

    tableMvt
        .columns('type:name')
        .search(statut ? '^' + statut + '$' : '', true, false)
        .draw();
    tableMvt
        .columns('operateur:name')
        .search(demandeurPiped ? '^' + demandeurPiped + '$' : '', true, false)
        .draw();
    tableMvt
        .columns('refEmplacement:name')
        .search(emplacement ? '^' + emplacement + '$' : '', true, false)
        .draw();
    tableMvt
        .columns('refArticle:name')
        .search(article)
        .draw();

    $.fn.dataTable.ext.search.push(
        function (settings, data) {
            let dateMin = $('#dateMin').val();
            let dateMax = $('#dateMax').val();
            let indexDate = tableMvt.column('date:name').index();
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
    );
    tableMvt
        .draw();
});

function checkZero(data) {
    if (data.length == 1) {
        data = "0" + data;
    }
    return data;
}

function generateCSVMouvement () {
    let data = {};
    $('.filterService, select').first().find('input').each(function () {
        if ($(this).attr('name') !== undefined) {
            data[$(this).attr('name')] = $(this).val();
        }
    });

    if (data['dateMin'] && data['dateMax']) {
        let params = JSON.stringify(data);
        let path = Routing.generate('get_mouvements_stock_for_csv', true);

        $.post(path, params, function(response) {
            if (response) {
                $('.error-msg').empty();
                let csv = "";
                $.each(response, function (index, value) {
                    csv += value.join(';');
                    csv += '\n';
                });
                mFile(csv);
            }
        }, 'json');

    } else {
        $('.error-msg').html('<p>Saisissez une date de départ et une date de fin dans le filtre en en-tête de page.</p>');
    }
}

let mFile = function (csv) {
    let d = new Date();
    let date = checkZero(d.getDate() + '') + '-' + checkZero(d.getMonth() + 1 + '') + '-' + checkZero(d.getFullYear() + '');
    date += ' ' + checkZero(d.getHours() + '') + '-' + checkZero(d.getMinutes() + '') + '-' + checkZero(d.getSeconds() + '');
    let exportedFilename = 'export-mouvements-stock-' + date + '.csv';
    let blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    if (navigator.msSaveBlob) { // IE 10+
        navigator.msSaveBlob(blob, exportedFilename);
    } else {
        let link = document.createElement("a");
        if (link.download !== undefined) {
            let url = URL.createObjectURL(blob);
            link.setAttribute("href", url);
            link.setAttribute("download", exportedFilename);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    }
}