let pathDays = Routing.generate('days_param_api', true);
let tableDays = $('#tableDays').DataTable({
    "language": {
        url: "/js/i18n/dataTableLanguage.json",
    },
    ajax:{
        "url": pathDays,
        "type": "POST"
    },
    columns:[
        { "data": 'Day', 'title' : 'Jour' },
        { "data": 'Worked', 'title' : 'Travaillé' },
        { "data": 'Times', 'title' : 'Horaires de travail' },
        { "data": 'Order', 'title' : 'Ordre' },
        { "data": 'Actions', 'title' : 'Actions' },
    ],
    order: [
        [3, 'asc']
    ],
    columnDefs: [
        {
            'targets': [3],
            'visible': false
        }
    ],
});

let modalEditDays = $('#modalEditDays');
let submitEditDays = $('#submitEditDays');
let urlEditDays = Routing.generate('days_edit', true);
InitialiserModal(modalEditDays, submitEditDays, urlEditDays, tableDays, errorEditDays, false, false);

function errorEditDays(data) {
    let modal = $("#modalEditDays");
    if (data.success === false) {
        displayError(modal, data.msg, data.success);
    } else {
        modal.find('.close').click();
        alertSuccessMsg(data.msg);
    }
}

function toggleActiveDemandeLivraison(switchButton, path) {
    $.post(path, JSON.stringify({val: switchButton.is(':checked')}), function () {
    }, 'json');
}

function ajaxMailerServer() {
    xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function () {
        if (this.readyState == 4 && this.status == 200) {
            alertSuccessMsg('La configuration du serveur mail a bien été mise à jour.');
        }
    }
    let data = $('#mailerServerForm').find('.data');
    let json = {};
    data.each(function () {
        let val = $(this).val();
        let name = $(this).attr("name");
        json[name] = val;
    })
    let Json = JSON.stringify(json);
    let path = Routing.generate('ajax_mailer_server', true);
    xhttp.open("POST", path, true);
    xhttp.send(Json);
}

function ajaxDims() {
    xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function () {
        if (this.readyState == 4 && this.status == 200) {
            alertSuccessMsg('La configuration des étiquettes a bien été mise à jour.');
        }
    }
    let data = $('#dimsForm').find('.data');
    let json = {};
    data.each(function () {
        let val = $(this).attr('type') === 'checkbox' ? $(this).is(':checked') : $(this).val();
        let name = $(this).attr("name");
        json[name] = val;
    });
    let Json = JSON.stringify(json);
    let path = Routing.generate('ajax_dimensions_etiquettes', true);
    xhttp.open("POST", path, true);
    xhttp.send(Json);
    //TODO passer en jquery
}

function updatePrefixDemand(){
    let prefixe = $('#prefixeDemande').val();
    let typeDemande = $('#typeDemandePrefixageDemande').val();

    let path = Routing.generate('ajax_update_prefix_demand',true);
    let params = JSON.stringify({prefixe: prefixe, typeDemande: typeDemande});

    let msg = '';
    if(typeDemande === 'aucunPrefixe'){
        $('#typeDemandePrefixageDemande').addClass('is-invalid');
        msg += 'Veuillez sélectionner un type de demande.';
    } else {
        $.post(path, params, () => {
            $('#typeDemandePrefixageDemande').removeClass('is-invalid');
            alertSuccessMsg('Le préfixage des noms de demandes a bien été mis à jour.');
        });
    }
    $('.error-msg').html(msg);
}

function getPrefixDemand(select) {
    let typeDemande = select.val();

    let path = Routing.generate('ajax_get_prefix_demand', true);
    let params = JSON.stringify(typeDemande);

    $.post(path, params, function(data) {
        $('#prefixeDemande').val(data);
    }, 'json');
}

function saveTranslations() {
    let $inputs = $('#translation').find('.translate');
    let data = [];
    $inputs.each(function() {
        let name = $(this).attr('name');
        let val = $(this).val();
        data.push({id: name, val: val});
    });

    let path = Routing.generate('save_translations');
    const $spinner = $('#spinnerSaveTranslations');
    console.log($('#spinnerSaveTranslations')[0].classList);
    loadSpinner($spinner);
    console.log($('#spinnerSaveTranslations')[0].classList);
    $.post(path, JSON.stringify(data), (resp) => {
        $('html,body').animate({scrollTop: 0});
        if (resp) {
            alertSuccessMsg('Rechargement de la page pour mettre à jour votre personnalisation des libellés.');
            setTimeout(() => {
                location.reload();
            }, 1900);
        } else {
            hideSpinner($spinner);
            alertErrorMsg('Une erreur est survenue lors de la personnalisation des libellés.');
        }
    });
}
