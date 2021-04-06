const maxSizeFileAllowed = 10000000;
let allowedLogoExtensions = ['PNG', 'png', 'JPEG', 'jpeg', 'JPG','jpg','svg'];
let pathDays = Routing.generate('days_param_api', true);
let disabledDates = [];
let tableDaysConfig = {
    ajax: {
        "url": pathDays,
        "type": "POST"
    },
    columns: [
        {"data": 'Actions', 'title': '', className: 'noVis', orderable: false},
        {"data": 'Day', 'title': 'Jour'},
        {"data": 'Worked', 'title': 'Travaillé'},
        {"data": 'Times', 'title': 'Horaires de travail'},
        {"data": 'Order', 'title': 'Ordre'},
    ],
    order: [['Order', 'asc']],
    rowConfig: {
        needsRowClickAction: true,
    }
};

let tableDays = initDataTable('tableDays', tableDaysConfig);
let workFreeDaysTable;

let modalEditDays = $('#modalEditDays');
let submitEditDays = $('#submitEditDays');
let urlEditDays = Routing.generate('days_edit', true);
InitModal(modalEditDays, submitEditDays, urlEditDays, {tables: [tableDays]});

const resetLogos = {
    website: false,
    mailLogo: false,
    nomadeAccueil: false,
    nomadeHeader: false,
};

const dispatchColorHasChanged = {
    after: false,
    DDay: false,
    before: false
};

const handlingColorHasChanged = {
    after: false,
    DDay: false,
    before: false
};

$(function () {
    Select2Old.init($('#locationArrivageDest'));
    Select2Old.location($('[name=param-default-location-if-custom]'))
    Select2Old.location($('[name=param-default-location-if-emergency]'))
    Select2Old.init($('#listNaturesColis'));
    Select2Old.initFree($('select[name="businessUnit"]'));
    Select2Old.initFree($('select[name="dispatchEmergencies"]'));
    Select2Old.location($('.ajax-autocomplete-location'));
    Select2Old.carrier($('.ajax-autocomplete-transporteur'));
    Select2Old.initValues($('#receptionLocation'), $('#receptionLocationValue'));

    updateImagePreview('#preview-label-logo', '#upload-label-logo');
    updateImagePreview('#preview-emergency-icon', '#upload-emergency-icon');
    updateImagePreview('#preview-custom-icon', '#upload-custom-icon');
    updateImagePreview('#preview-website-logo', '#upload-website-logo');
    updateImagePreview('#preview-email-logo', '#upload-email-logo');
    updateImagePreview('#preview-mobile-logo-header', '#upload-mobile-logo-header');
    updateImagePreview('#preview-mobile-logo-login', '#upload-mobile-logo-login');

    updateImagePreview('#preview-delivery-note-logo', '#upload-delivery-note-logo');
    updateImagePreview('#preview-waybill-logo', '#upload-waybill-logo');
    updateImagePreview('#preview-overconsumption-logo', '#upload-overconsumption-logo');

    // config tableau de bord : emplacements
    initValuesForDashboard();

    $('#receptionLocation').on('change', function () {
        editParamLocations($(this), $('#receptionLocationValue'));
    });

    $('#locationArrivageDest').on('change', function () {
        editParamLocations($(this), $('#locationArrivageDestValue'))
    });

    $('[name=param-default-location-if-custom]').on('change', function () {
        editParamLocations($(this), $('#customsArrivalsLocation'))
    });

    $('[name=param-default-location-if-emergency]').on('change', function () {
        editParamLocations($(this), $('#emergenciesArrivalsLocation'))
    });

    $('#locationDemandeLivraison').on('change', function() {
        editParamLocations($(this), $('#locationDemandeLivraisonValue'));
    });

    // config tableau de bord : transporteurs

    const inputWorkFreeDayAlreadyAdd = JSON.parse($('#workFreeDays input[type="hidden"][name="already-work-free-days"]').val());
    disabledDates = inputWorkFreeDayAlreadyAdd.map((dateStr) => moment(dateStr, 'YYYY-MM-DD'));
    initDateTimePicker(
        '#workFreeDays input[name="newWorkFreeDay"]',
        "DD/MM/YYYY",
        false,
        null,
        null,
        disabledDates
    );

    let tableNonWorkedDaysConfig = {
        ajax: {
            "url": Routing.generate('workFreeDays_table_api', true),
            "type": "GET"
        },
        columns: [
            { "data": 'actions', className: 'noVis', orderable: false},
            { "data": 'day', 'title': 'Jour', orderable: false },
        ],
        rowConfig: {
            needsRowClickAction: true,
        },
        order: [],
    };
    workFreeDaysTable = initDataTable('tableWorkFreeDays', tableNonWorkedDaysConfig);
});

function initValuesForDashboard() {
    Select2Old.initValues($('#locationToTreat'), $('#locationToTreatValue'));
    Select2Old.initValues($('#locationWaitingDock'), $( '#locationWaitingDockValue'));
    Select2Old.initValues($('#locationWaitingAdmin'), $( '#locationWaitingAdminValue'));
    Select2Old.initValues($('#locationAvailable'), $( '#locationAvailableValue'));
    Select2Old.initValues($('#locationDropZone'), $( '#locationDropZoneValue'));
    Select2Old.initValues($('#locationLitiges'), $( '#locationLitigesValue'));
    Select2Old.initValues($('#locationUrgences'), $( '#locationUrgencesValue'));
    Select2Old.initValues($('#locationsFirstGraph'), $( '#locationsFirstGraphValue'));
    Select2Old.initValues($('#locationsSecondGraph'), $( '#locationsSecondGraphValue'));

    // Set location values for arrivals
    Select2Old.initValues($('#locationArrivageDest'), $( '#locationArrivageDestValue'));
    Select2Old.initValues($('[name=param-default-location-if-custom]'), $( '#customsArrivalsLocation'));
    Select2Old.initValues($('[name=param-default-location-if-emergency]'), $( '#emergenciesArrivalsLocation'));

    Select2Old.initValues($('#locationDemandeLivraison'), $('#locationDemandeLivraisonValue'));
    Select2Old.initValues($('#packaging1'), $('#packagingLocation1'));
    Select2Old.initValues($('#packaging2'), $('#packagingLocation2'));
    Select2Old.initValues($('#packaging3'), $('#packagingLocation3'));
    Select2Old.initValues($('#packaging4'), $('#packagingLocation4'));
    Select2Old.initValues($('#packaging5'), $('#packagingLocation5'));
    Select2Old.initValues($('#packaging6'), $('#packagingLocation6'));
    Select2Old.initValues($('#packaging7'), $('#packagingLocation7'));
    Select2Old.initValues($('#packaging8'), $('#packagingLocation8'));
    Select2Old.initValues($('#packaging9'), $('#packagingLocation9'));
    Select2Old.initValues($('#packaging10'), $('#packagingLocation10'));
    Select2Old.initValues($('#packagingRPA'), $('#packagingLocationRPA'));
    Select2Old.initValues($('#packagingLitige'), $('#packagingLocationLitige'));
    Select2Old.initValues($('#packagingKitting'), $( '#packagingLocationKitting'));
    Select2Old.initValues($('#packagingUrgence'), $('#packagingLocationUrgence'));
    Select2Old.initValues($('#packagingDSQR'), $('#packagingLocationDSQR'));
    Select2Old.initValues($('#packagingDestinationGT'), $('#packagingLocationDestinationGT'));
    Select2Old.initValues($('#packagingOrigineGT'), $('#packagingLocationOrigineGT'));
    Select2Old.initValues($('#carrierDock'), $( '#carrierDockValue'));
}

function updateToggledParam(switchButton) {
    let params = {
        val: switchButton.is(':checked'),
        param: switchButton.data('param'),
    };
    $.post(Routing.generate('toggle_params', true), JSON.stringify(params), function (resp) {
        if (resp) {
            showBSAlert('La modification du paramétrage a bien été prise en compte.', 'success');
        } else {
            showBSAlert('Une erreur est survenue lors de la modification du paramétrage.', 'danger');
        }
    }, 'json');
}

function ajaxMailerServer() {
    xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function () {
        if (this.readyState == 4 && this.status == 200) {
            showBSAlert('La configuration du serveur mail a bien été mise à jour.', 'success');
        }
    }
    let data = $('#mailServerSettings').find('.data');
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
    const $logoFile = $('#upload-label-logo');
    const $customIconFile = $('#upload-custom-icon');
    const $emergencyIconFile = $('#upload-emergency-icon');

    let data = new FormData();
    let dataInputs = $('#labelSettings').find('.data');
    dataInputs.each(function () {
        let val = $(this).attr('type') === 'checkbox' ? $(this).is(':checked') : $(this).val();
        let name = $(this).attr("name");
        data.append(name, val);
    });
    if ($logoFile[0].files && $logoFile[0].files[0]) {
        data.append('logo', $logoFile[0].files[0]);
    }
    if ($customIconFile[0].files && $customIconFile[0].files[0]) {
        data.append('custom-icon', $customIconFile[0].files[0]);
    }
    if ($emergencyIconFile[0].files && $emergencyIconFile[0].files[0]) {
        data.append('emergency-icon', $emergencyIconFile[0].files[0]);
    }
    $.ajax({
        url: Routing.generate('ajax_dimensions_etiquettes', true),
        data: data,
        type: 'post',
        contentType: false,
        processData: false,
        cache: false,
        dataType: 'json',
        success: (response) => {
            showBSAlert('La configuration des étiquettes a bien été mise à jour.', 'success');
            $('.blChosen').text("\"" + response['param-cl-etiquette'] + "\"");
        }
    });
}

function ajaxDocuments() {
    let $deliveryNote = $('[name="logo-delivery-note"]');
    let $waybill = $('[name="logo-waybill"]');

    let data = new FormData();

    if ($deliveryNote[0].files && $deliveryNote[0].files[0]) {
        data.append('logo-delivery-note', $deliveryNote[0].files[0]);
    }

    if ($waybill[0].files && $waybill[0].files[0]) {
        data.append('logo-waybill', $waybill[0].files[0]);
    }

    $.ajax({
        url: Routing.generate('ajax_documents', true),
        data: data,
        type: 'post',
        contentType: false,
        processData: false,
        cache: false,
        dataType: 'json',
        success: () => {
            showBSAlert('La configuration des étiquettes a bien été mise à jour.', 'success');
        }
    });
}

function updatePrefixDemand() {
    let prefixe = $('#prefixeDemande').val();
    let typeDemande = $('#typeDemandePrefixageDemande').val();

    let path = Routing.generate('ajax_update_prefix_demand', true);
    let params = JSON.stringify({prefixe: prefixe, typeDemande: typeDemande});

    let msg = '';
    if (typeDemande === 'aucunPrefixe') {
        $('#typeDemandePrefixageDemande').addClass('is-invalid');
        msg += 'Veuillez sélectionner un type de demande.';
    } else {
        $.post(path, params, () => {
            $('#typeDemandePrefixageDemande').removeClass('is-invalid');
            showBSAlert('Le préfixage des noms de demandes a bien été mis à jour.', 'success');
        });
    }
    $('.error-msg').html(msg);
}

function updateStockParam() {
    let expirationDelay = $('[name="expirationDelay"]').val();

    Promise
        .all([
            $.post(Routing.generate('toggle_params'), JSON.stringify({param: 'SEND_MAIL_MANAGER_WARNING_THRESHOLD', val: $('[name="param-security-threshold"]').val()})),
            $.post(Routing.generate('toggle_params'), JSON.stringify({param: 'SEND_MAIL_MANAGER_SECURITY_THRESHOLD', val: $('[name="param-alert-threshold"]').val()})),
            $.post(Routing.generate('ajax_update_expiration_delay', true), {expirationDelay})
        ])
        .then(([successAlert, successSecurity, {success: successDelay, msg: msgDelay}]) => {
            if (successAlert && successSecurity && successDelay) {
                showBSAlert('Vos paramétrages ont bien été mis à jour.', 'success');
            }
            else if (!successDelay) {
                showBSAlert(msgDelay, 'danger');
            }
            else {
                showBSAlert('Erreur, il y a eu un problème lors de la sauvegarde de vos paramètres', 'danger');
            }
        });
}

function updateAppClient() {
    const appClient = $('select[name=appClient]').val();

    $.post(Routing.generate('toggle_app_client'), JSON.stringify(appClient))
        .then(data => {
            if (data.success) {
                showBSAlert(data.msg, 'success');
                window.location.reload();
            } else {
                showBSAlert(data.msg, 'danger');
            }
        });
}

function getPrefixDemand(select) {
    let typeDemande = select.val();

    let path = Routing.generate('ajax_get_prefix_demand', true);
    let params = JSON.stringify(typeDemande);

    $.post(path, params, function (data) {
        $('#prefixeDemande').val(data);
    }, 'json');
}

function saveTranslations() {
    let $inputs = $('#translation').find('.translate');
    let data = [];
    $inputs.each(function () {
        let name = $(this).attr('name');
        let val = $(this).val();
        data.push({id: name, val: val});
    });

    let path = Routing.generate('save_translations');
    const $spinner = $('#spinnerSaveTranslations');
    showBSAlert('Mise à jour de votre personnalisation des libellés : merci de patienter.', 'success', false);
    loadSpinner($spinner);
    $.post(path, JSON.stringify(data), (resp) => {
        $('html,body').animate({scrollTop: 0});
        if (resp) {
            location.reload();
        } else {
            hideSpinner($spinner);
            showBSAlert('Une erreur est survenue lors de la personnalisation des libellés.', 'danger');
        }
    });
}

function ajaxEncodage() {
    $.post(Routing.generate('save_encodage'), JSON.stringify($('select[name="param-type-encodage"]').val()), function () {
        showBSAlert('Mise à jour de vos préférences d\'encodage réussie.', 'success');
    });
}

function editAppearance() {
    let path = Routing.generate('edit_appearance', true);

    let data = new FormData();
    data.append("font-family", $('select[name="param-font-family"]').val());

    const $websiteLogo = $('#upload-website-logo');
    if ($websiteLogo[0].files && $websiteLogo[0].files[0]) {
        data.append("website-logo", $websiteLogo[0].files[0]);
    }

    const $emailLogo = $('#upload-email-logo');
    if ($emailLogo[0].files && $emailLogo[0].files[0]) {
        data.append("email-logo", $emailLogo[0].files[0]);
    }

    const $mobileLogos = $('#upload-mobile-logo-login, #upload-mobile-logo-header');
    $mobileLogos.each(function() {
        const $mobileLogo = $(this);
        if ($mobileLogo[0].files && $mobileLogo[0].files[0]) {
            data.append($mobileLogo.attr('name'), $mobileLogo[0].files[0]);
        }
    });

    showBSAlert("Mise à jour de l'apparence. Veuillez patienter.", 'success', false);
    data.append('reset-logos', JSON.stringify(resetLogos));
    $.ajax(path, {
        data: data,
        type: 'POST',
        contentType: false,
        processData: false,
        cache: false,
        dataType: 'json',
        success: (response) => {
            if (response.success) {
                location.reload();
            } else {
                showBSAlert("Une erreur est survenue lors de la mise à jour du choix de l'apparence", "danger");
            }
        }
    })
}

function editParamLocations($select, $inputValue) {
    const data = $inputValue.data();
    if (data && data.label) {
        $.post(Routing.generate('edit_param_location', {label: data.label}), $select.val(), (resp) => {
            if (resp) {
                showBSAlert("L\'emplacement a bien été mis à jour.", 'success');
            } else {
                showBSAlert("Une erreur est survenue lors de la mise à jour de l\'emplacement.", 'danger');
            }
        });
    }
}

function editReceptionStatus() {
    let path = Routing.generate('edit_status_receptions');
    let $inputs = $('#receptionSettings').find('.status');

    let param = {};
    $inputs.each(function () {
        let name = $(this).attr('name');
        let val = $(this).val();
        param[name] = val;
    });

    $.post(path, param, (resp) => {
        if (resp) {
            showBSAlert("Les statuts de réception ont bien été mis à jour.", 'success');
        } else {
            showBSAlert("Une erreur est survenue lors de la mise à jour des statuts de réception.", 'danger');
        }
    });
}


function updateImagePreview(preview, upload) {
    let $upload = $(upload)[0];

    $(upload).change(() => {
        if ($upload.files && $upload.files[0]) {
            let fileNameWithExtension = $upload.files[0].name.split('.');
            let extension = fileNameWithExtension[fileNameWithExtension.length - 1];

            if ($upload.files[0].size < maxSizeFileAllowed) {

                if (allowedLogoExtensions.indexOf(extension) !== -1) {
                        let reader = new FileReader();
                        reader.onload = function (e) {
                            $(preview)
                                .attr('src', e.target.result)
                                .removeClass('d-none');
                        };

                        reader.readAsDataURL($upload.files[0]);
                    } else {
                        showBSAlert('Veuillez choisir une image valide (png, jpeg, jpg, svg).', 'danger')
                    }
                } else {
                    showBSAlert('La taille du fichier est supérieure à 10 mo.', 'danger')
                }
        }
    })
}

function addWorkFreeDay($button) {
    const $input = $button.siblings('input[name="newWorkFreeDay"]');
    if ($input.val()) {
        const date = moment($input.val(), 'DD/MM/YYYY').format('YYYY-MM-DD');
        let path = Routing.generate('workFreeDay_new', true);
        $.post(path, {date}, (resp) => {
            if (resp.success) {
                let datetimeMoment = moment(date);
                disabledDates.push(datetimeMoment);
                $input.data('DateTimePicker').disabledDates(disabledDates);
                $input.val('');

                workFreeDaysTable.ajax.reload();
                showBSAlert(resp.text, 'success');
            } else {
                showBSAlert(resp.text, 'danger');
            }
        });
    } else {
        showBSAlert('Veuillez sélectionner une date valide.', 'danger');
    }
}

function deleteWorkFreeDay(id, date) {
    $.ajax({
        url: Routing.generate('workFreeDay_delete', true),
        type: 'DELETE',
        data: {id},
        success: (resp) => {
            if (resp.success) {
                const $input = $('#workFreeDays input[name="newWorkFreeDay"]');
                let datetimeToRemove = moment(date);
                const indexOfDeleted = disabledDates.findIndex((dateSaved) => datetimeToRemove.isSame(dateSaved.format('YYYY-MM-DD')));
                if (indexOfDeleted > -1) {
                    disabledDates.splice(indexOfDeleted, 1);
                    $input.data('DateTimePicker').disabledDates(disabledDates);
                }
                workFreeDaysTable.ajax.reload();
                showBSAlert(resp.message, 'success');
            } else {
                showBSAlert(resp.message, 'danger');
            }
        }
    });
}

function saveDispatchesParam() {
    const $form = $('#dispatchSettings');

    const $overconsumptionLogo = $form.find('#upload-overconsumption-logo');

    let data = new FormData();
    if ($overconsumptionLogo[0].files && $overconsumptionLogo[0].files[0]) {
        data.append("overconsumption-logo", $overconsumptionLogo[0].files[0]);
    }


    const $expectedDateColorAfter = $form.find('[name="expectedDateColorAfter"]');
    const $expectedDateColorDDay = $form.find('[name="expectedDateColorDDay"]');
    const $expectedDateColorBefore = $form.find('[name="expectedDateColorBefore"]');

    Promise.all([
        $.post(Routing.generate('toggle_params'), JSON.stringify({param: 'DISPATCH_WAYBILL_CARRIER', val: $form.find('[name="waybillCarrier"]').val()})),
        $.post(Routing.generate('toggle_params'), JSON.stringify({param: 'DISPATCH_WAYBILL_CONSIGNER', val: $form.find('[name="waybillConsigner"]').val()})),
        $.post(Routing.generate('toggle_params'), JSON.stringify({param: 'DISPATCH_WAYBILL_RECEIVER', val: $form.find('[name="waybillReceiver"]').val()})),
        $.post(Routing.generate('toggle_params'), JSON.stringify({param: 'DISPATCH_WAYBILL_LOCATION_FROM', val: $form.find('[name="waybillLocationFrom"]').val()})),
        $.post(Routing.generate('toggle_params'), JSON.stringify({param: 'DISPATCH_WAYBILL_LOCATION_TO', val: $form.find('[name="waybillLocationTo"]').val()})),
        $.post(Routing.generate('toggle_params'), JSON.stringify({param: 'DISPATCH_WAYBILL_CONTACT_PHONE_OR_MAIL', val: $form.find('[name="waybillContactPhoneMail"]').val()})),
        $.post(Routing.generate('toggle_params'), JSON.stringify({param: 'DISPATCH_WAYBILL_CONTACT_NAME', val: $form.find('[name="waybillContactName"]').val()})),
        $.post(Routing.generate('toggle_params'), JSON.stringify({
            param: 'DISPATCH_OVERCONSUMPTION_BILL_TYPE_AND_STATUS',
            val: $('[name="overconsumptionBillType"]').val() + ';' + $form.find('[name="overconsumptionBillStatut"]').val()
        })),
        $.ajax(Routing.generate('edit_overconsumption_logo'), {
            data: data,
            type: 'POST',
            contentType: false,
            processData: false,
            dataType: 'json',
        }),
        ...(dispatchColorHasChanged.after
            ? [$.post(Routing.generate('toggle_params'), JSON.stringify({param: 'DISPATCH_EXPECTED_DATE_COLOR_AFTER', val: $expectedDateColorAfter.val()}))]
            : []),
        ...(dispatchColorHasChanged.DDay
            ? [$.post(Routing.generate('toggle_params'), JSON.stringify({param: 'DISPATCH_EXPECTED_DATE_COLOR_D_DAY', val: $expectedDateColorDDay.val()}))]
            : []),
        ...(dispatchColorHasChanged.before
            ? [$.post(Routing.generate('toggle_params'), JSON.stringify({param: 'DISPATCH_EXPECTED_DATE_COLOR_BEFORE', val: $expectedDateColorBefore.val()}))]
            : [])
    ])
        .then((res) => {
            if (res.every((success) => success)) {
                showBSAlert("Les paramétrages d'acheminements ont bien été mis à jour.", 'success');
            } else {
                showBSAlert("Une erreur est survenue lors de la mise à jour des paramétrages d'acheminements.", 'danger');
            }
        });
}

function toggleRecipient($checkbox) {
     if ($checkbox.attr('name') === 'param-add-destination-location-article-label'
        && $checkbox.prop('checked')) {
        $('.checkbox[name="param-add-recipient-dropzone-location-article-label"]').prop('checked', false);
    } else if ($checkbox.attr('name') === 'param-add-recipient-dropzone-location-article-label'
        && $checkbox.prop('checked')) {
        $('.checkbox[name="param-add-destination-location-article-label"]').prop('checked', false);
    }
}

function onResetLogoClicked($button) {
    const $defaultValue = $button.siblings('.default-value');
    const $logoImg = $button.siblings('.logo');
    const $inputFile = $button.siblings('[type="file"]');
    const defaultValue = $defaultValue.val();

    $inputFile.val('');
    $logoImg.attr('src', defaultValue);
    const name = $button.data('name');
    resetLogos[name] = true;

}

function saveHandlingParams() {
    const $form = $('#handlingSettings');

    const $removeHoursDateTimeSwitch = $form.find('[name="removeHoursDateTime"]');
    const $expectedDateColorAfter = $form.find('[name="expectedDateColorAfter"]');
    const $expectedDateColorDDay = $form.find('[name="expectedDateColorDDay"]');
    const $expectedDateColorBefore = $form.find('[name="expectedDateColorBefore"]');

    Promise.all([
        $.post(Routing.generate('toggle_params', true), JSON.stringify({val: $removeHoursDateTimeSwitch.is(':checked'), param: $removeHoursDateTimeSwitch.data('param')})),
        ...(handlingColorHasChanged.after
            ? [$.post(Routing.generate('toggle_params'), JSON.stringify({param: 'HANDLING_EXPECTED_DATE_COLOR_AFTER', val: $expectedDateColorAfter.val()}))]
            : []),
        ...(handlingColorHasChanged.DDay
            ? [$.post(Routing.generate('toggle_params'), JSON.stringify({param: 'HANDLING_EXPECTED_DATE_COLOR_D_DAY', val: $expectedDateColorDDay.val()}))]
            : []),
        ...(handlingColorHasChanged.before
            ? [$.post(Routing.generate('toggle_params'), JSON.stringify({param: 'HANDLING_EXPECTED_DATE_COLOR_BEFORE', val: $expectedDateColorBefore.val()}))]
            : [])
    ]).then(function (responses) {
        if (responses.every(result => result)) {
            showBSAlert('La modification du paramétrage a bien été prise en compte.', 'success');
        } else {
            showBSAlert('Une erreur est survenue lors de la modification du paramétrage.', 'danger');
        }
    });
}

function saveEmergencyTriggeringFields() {
    const $select = $(`select[name="arrival-emergency-triggering-fields"]`);
    const value = $select.val() || [];

    if(value.length === 0) {
        showBSAlert(`Au moins un champ déclencheur d'urgence doit être renseigné`, `danger`);
    } else {
        editMultipleSelect($select, `ARRIVAL_EMERGENCY_TRIGGERING_FIELDS`);
    }
}

function editMultipleSelect($select, paramName) {
    const val = $select.val() || [];
    const valStr = JSON.stringify(val);
    $.post(Routing.generate('toggle_params'), JSON.stringify({param: paramName, val: valStr})).then((resp) => {
        if (resp) {
            showBSAlert("La valeur a bien été mise à jour", "success");
        } else {
            showBSAlert("Une erreur est survenue lors de la mise à jour de la valeur", "success");
        }
    })
}
