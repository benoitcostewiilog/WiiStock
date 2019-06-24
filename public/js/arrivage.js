$('.select2').select2();

$('#utilisateur').select2({
    placeholder: {
        text: 'Destinataire',
    }
});

let pathArrivage = Routing.generate('arrivage_api', true);
let tableArrivage = $('#tableArrivages').DataTable({
    "language": {
        url: "/js/i18n/dataTableLanguage.json",
    },
    "order": [[0, "desc"]],
    ajax: {
        "url": pathArrivage,
        "type": "POST"
    },
    columns: [
        { "data": "NumeroArrivage", 'name': 'NumeroArrivage', 'title': "N° d'arrivage" },
        { "data": 'Transporteur', 'name': 'Transporteur', 'title': 'Transporteur' },
        { "data": 'Chauffeur', 'name': 'Chauffeur', 'title': 'Chauffeur' },
        { "data": 'NoTracking', 'name': 'NoTracking', 'title': 'N° tracking transporteur' },
        { "data": 'NumeroBL', 'name': 'NumeroBL', 'title': 'N° commande / BL' },
        { "data": 'Fournisseur', 'name': 'Fournisseur', 'title': 'Fournisseur' },
        { "data": 'Destinataire', 'name': 'Destinataire', 'title': 'Destinataire' },
        { "data": 'Acheteurs', 'name': 'Acheteurs', 'title': 'Acheteurs' },
        { "data": 'NbUM', 'name': 'NbUM', 'title': 'Nb UM' },
        { "data": 'Statut', 'name': 'Statut', 'title': 'Statut' },
        { "data": 'Date', 'name': 'Date', 'title': 'Date' },
        { "data": 'Utilisateur', 'name': 'Utilisateur', 'title': 'Utilisateur' },
        { "data": 'Actions', 'name': 'Actions', 'title': 'Actions' },
    ],

});

let editorNewArrivageAlreadyDone = false;
function initNewArrivageEditor(modal) {
    if (!editorNewArrivageAlreadyDone) {
        initEditor(modal + ' .editor-container-new');
        $('.select2').select2();
        console.log(editorNewArrivageAlreadyDone, 't0');
        editorNewArrivageAlreadyDone = true;
    }
console.log($('.select2').select2())
    console.log(editorNewArrivageAlreadyDone, 't1');
};



let modalNewArrivage = $("#modalNewArrivage");
let submitNewArrivage = $("#submitNewArrivage");
let urlNewArrivage = Routing.generate('arrivage_new', true);
InitialiserModalArrivage(modalNewArrivage, submitNewArrivage, urlNewArrivage, tableArrivage);

let modalModifyArrivage = $('#modalEditArrivage');
let submitModifyArrivage = $('#submitEditArrivage');
let urlModifyArrivage = Routing.generate('arrivage_edit', true);
InitialiserModal(modalModifyArrivage, submitModifyArrivage, urlModifyArrivage, tableArrivage);

let modalDeleteArrivage = $('#modalDeleteArrivage');
let submitDeleteArrivage = $('#submitDeleteArrivage');
let urlDeleteArrivage = Routing.generate('arrivage_delete', true);
InitialiserModal(modalDeleteArrivage, submitDeleteArrivage, urlDeleteArrivage, tableArrivage);

function toggleLitige(select) {
    let bloc = select.closest('.modal').find('#litigeBloc');
    let status = select.find('option:selected').text();

    let litigeType = bloc.find('#litigeType');
    let constantConform = $('#constantConforme').val();

    if (status === constantConform) {
        litigeType.removeClass('needed');
        bloc.addClass('d-none');

    } else {
        bloc.removeClass('d-none');
        litigeType.addClass('needed');
    }
}

function deleteRowArrivage(button, modal, submit, hasLitige) {
    deleteRow(button, modal, submit);
    let hasLitigeText = modal.find('.hasLitige');
    if (hasLitige) {
        hasLitigeText.removeClass('d-none');
    } else {
        hasLitigeText.addClass('d-none');
    }
}

function dragEnterDiv(event, div) {
    div.css('border', '3px dashed red');
}

function dragOverDiv(event, div) {
    event.preventDefault();
    event.stopPropagation();
    div.css('border', '3px dashed red');
    return false;
};

function dragLeaveDiv(event, div) {
    event.preventDefault();
    event.stopPropagation();
    div.css('border', '3px dashed #BBBBBB');
    return false;
}

function dropOnDiv(event, div) {
    if (event.dataTransfer) {
        if (event.dataTransfer.files.length) {
            // Stop the propagation of the event
            event.preventDefault();
            event.stopPropagation();
            div.css('border', '3px dashed green');
            // Main function to upload
            upload(event.dataTransfer.files);
        }
    } else {
        div.css('border', '3px dashed #BBBBBB');
    }
    return false;
}

function upload(files) {

    let formData = new FormData();
    $.each(files, function (index, file) {
        formData.append('file' + index, file);
    });
    let path = Routing.generate('arrivage_depose', true);

    let arrivageId = $('#dropfile').data('arrivage-id');
    formData.append('id', arrivageId);

    let filepath = '/uploads/attachements/';

    $.ajax({
        url: path,
        data: formData,
        type:"post",
        contentType:false,
        processData:false,
        cache:false,
        dataType:"json",
        success:function(html){
            let dropfile = $('#dropfile');
            dropfile.css('border', '3px dashed #BBBBBB');
            dropfile.after(html);
        }
    });
}

function editRowArrivage(button) {
    let path = Routing.generate('arrivage_edit_api', true);
    let modal = $('#modalEditArrivage');
    let submit = $('#submitEditArrivage');
    let id = button.data('id');
    let params = { id: id };

    $.post(path, JSON.stringify(params), function(data) {
        modal.find('.modal-body').html(data.html);
        initEditor('.editor-container-edit');
        $('#modalEditArrivage').find('#acheteurs').val(data.acheteurs).select2();
    }, 'json');

    modal.find(submit).attr('value', id);
}

function InitialiserModalArrivage(modal, submit, path, table, callback = null, close = true) {
    submit.click(function () {
        submitActionArrivage(modal, path, table, callback, close);
    });
}

function submitActionArrivage(modal, path, table, callback, close) {
    xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function () {

        if (this.readyState == 4 && this.status == 200) {
            $('.errorMessage').html(JSON.parse(this.responseText));
            data = JSON.parse(this.responseText);

            if (data.redirect) {
                window.location.href = data.redirect;
                return;
            }
            // pour mise à jour des données d'en-tête après modification
            if (data.entete) {
                $('.zone-entete').html(data.entete)
            }
            table.ajax.reload(function (json) {
                if (this.responseText !== undefined) {
                    $('#myInput').val(json.lastInput);
                }
            });

            clearModal(modal);
            let nbUm = data.nbUm;
            let printUm = data.printUm;
            let printArrivage = data.printArrivage;
            let d = new Date();
            let date = checkZero(d.getDate() + '') + '-' + checkZero(d.getMonth() + 1 + '') + '-' + checkZero(d.getFullYear() + '');
            date += ' ' + checkZero(d.getHours() + '') + '-' + checkZero(d.getMinutes() + '') + '-' + checkZero(d.getSeconds() + '');
            if (data.exists) {
                let doc = adjustScalesForDoc(data);
                $("#barcodes").empty();
                if (printUm) {
                    for (let i = 0; i < nbUm; i++) {
                        $('#barcodes').append('<img id="barcode' + i + '">');
                        JsBarcode("#barcode" + i, data.arrivage + '-' + i, {
                            format: "CODE128",
                        });
                    }

                } if (printArrivage) {
                    $('#barcodes').append('<img id="barcodeArrivage">');
                    JsBarcode("#barcodeArrivage", data.arrivage, {
                        format: "CODE128",
                    });
                } if (printArrivage || printUm) {
                    $("#barcodes").find('img').each(function () {
                        doc.addImage($(this).attr('src'), 'JPEG', 0, 0, doc.internal.pageSize.getWidth(), doc.internal.pageSize.getHeight());
                        doc.addPage();
                    });
                    doc.deletePage(doc.internal.getNumberOfPages())
                    doc.save('Etiquettes du ' + date + '.pdf');
                }
            } else {
                $('#cannotGenerate').click();
            }
            if (callback !== null) callback(data);
        }
    };

    // On récupère toutes les données qui nous intéressent
    // dans les inputs...
    let inputs = modal.find(".data");
    let Data = {};
    let missingInputs = [];
    let wrongNumberInputs = [];
    let passwordIsValid = true;
    inputs.each(function () {
        let val = $(this).val();
        let name = $(this).attr("name");
        Data[name] = val;
        // validation données obligatoires
        if ($(this).hasClass('needed') && (val === undefined || val === '' || val === null)) {
            let label = $(this).closest('.form-group').find('label').text();
            // on enlève l'éventuelle * du nom du label
            label = label.replace(/\*/, '');
            missingInputs.push(label);
            $(this).addClass('is-invalid');
        }
        // validation valeur des inputs de type number
        if ($(this).attr('type') === 'number') {
            let val = parseInt($(this).val());
            let min = parseInt($(this).attr('min'));
            let max = parseInt($(this).attr('max'));
            if (val > max || val < min) {
                wrongNumberInputs.push($(this));
                $(this).addClass('is-invalid');
            }
        }
        // validation valeur des inputs de type password
        if ($(this).attr('type') === 'password') {
            let password = $(this).val();
            let isNotChanged = $(this).hasClass('optional-password') && password === "";
            if (!isNotChanged) {
                if (password.length < 8) {
                    modal.find('.password-error-msg').html('Le mot de passe doit faire au moins 8 caractères.');
                    passwordIsValid = false;
                } else if (!password.match(/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/)) {
                    modal.find('.password-error-msg').html('Le mot de passe doit contenir au moins une majuscule, une minuscule, un chiffre, un caractère spécial (@$!%*?&).');
                    passwordIsValid = false;
                } else {
                    passwordIsValid = true;
                }
            }
        }
    });

    // ... et dans les checkboxes
    let checkboxes = modal.find('.checkbox');
    checkboxes.each(function () {
        Data[$(this).attr("name")] = $(this).is(':checked');
    });
    $("div[name='id']").each(function () {
        Data[$(this).attr("name")] = $(this).attr('value');
    });
    modal.find(".elem").remove();
    // si tout va bien on envoie la requête ajax...
    if (missingInputs.length == 0 && wrongNumberInputs.length == 0 && passwordIsValid) {
        if (close == true) modal.find('.close').click();
        Json = {};
        Json = JSON.stringify(Data);
        xhttp.open("POST", path, true);
        xhttp.send(Json);
    } else {

        // ... sinon on construit les messages d'erreur
        let msg = '';

        // cas où il manque des champs obligatoires
        if (missingInputs.length > 0) {
            if (missingInputs.length == 1) {
                msg += 'Veuillez renseigner le champ ' + missingInputs[0] + ".<br>";
            } else {
                msg += 'Veuillez renseigner les champs : ' + missingInputs.join(', ') + ".<br>";
            }
        }
        // cas où les champs number ne respectent pas les valeurs imposées (min et max)
        if (wrongNumberInputs.length > 0) {
            wrongNumberInputs.forEach(function (elem) {
                let label = elem.closest('.form-group').find('label').text();

                msg += 'La valeur du champ ' + label;

                let min = elem.attr('min');
                let max = elem.attr('max');

                if (typeof (min) !== 'undefined' && typeof (max) !== 'undefined') {
                    if (min > max) {
                        msg += " doit être inférieure à " + max + ".<br>";
                    } else {
                        msg += ' doit être comprise entre ' + min + ' et ' + max + ".<br>";
                    }
                } else if (typeof (min) == 'undefined') {
                    msg += ' doit être inférieure à ' + max + ".<br>";
                } else if (typeof (max) == 'undefined') {
                    msg += ' doit être supérieure à ' + min + ".<br>";
                } else if (min < 1) {
                    msg += ' ne peut pas être rempli'
                }

            })
        }

        modal.find('.error-msg').html(msg);
    }
}

function checkZero(data) {
    if (data.length == 1) {
        data = "0" + data;
    }
    return data;
}

$('#submitSearchArrivage').on('click', function () {

    let statut = $('#statut').val();
    let utilisateur = $('#utilisateur').val();
    let utilisateurString = utilisateur.toString();
    let utilisateurPiped = utilisateurString.split(',').join('|');
    tableArrivage
        .columns('Statut:name')
        .search(statut)
        .draw();

    tableArrivage
        .columns('Destinataire:name')
        .search(utilisateurPiped ? '^' + utilisateurPiped + '$' : '', true, false)
        .draw();

    $.fn.dataTable.ext.search.push(
        function (settings, data, dataIndex) {
            let dateMin = $('#dateMin').val();
            let dateMax = $('#dateMax').val();
            let indexDate = tableArrivage.column('Date:name').index();
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
    tableArrivage
        .draw();
});

function deleteAttachement(arrivageId, pj, pjWithoutExtension) {

    let path = Routing.generate('arrivage_delete_attachement');
    let params = {
        arrivageId: arrivageId,
        pj: pj
    };

    $.post(path, JSON.stringify(params), function(data) {

        if(data === true) {
            $('#' + pjWithoutExtension).remove();
        }
    });
}