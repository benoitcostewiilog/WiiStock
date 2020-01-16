let pathacheminements = Routing.generate('acheminements_api', true);
let tableAcheminements = $('#tableAcheminement').DataTable({
    serverSide: true,
    processing: true,
    order: [[1, "desc"]],
    columnDefs: [
        {
            "orderable" : false,
            "targets" : [0]
        }
    ],
    language: {
        url: "/js/i18n/dataTableLanguage.json",
    },
    ajax: {
        "url": pathacheminements,
        "type": "POST",
    },
    columns: [
        { "data": 'Actions', 'name': 'Actions', 'title': 'Actions' },
        { "data": 'Date', 'name': 'Date', 'title': 'Date demande' },
        { "data": 'Demandeur', 'name': 'Demandeur', 'title': 'Demandeur' },
        { "data": 'Destinataire', 'name': 'Destinataire', 'title': 'Destinataire' },
        { "data": 'Emplacement prise', 'name': 'Emplacement prise', 'title': 'Emplacement prise' },
        { "data": 'Emplacement de dépose', 'name': 'Emplacement de dépose', 'title': 'Emplacement de dépose' },
        { "data": 'Nb Colis', 'name': 'Nb Colis', 'title': 'Nb Colis' },
        { "data": 'Statut', 'name': 'Statut', 'title': 'Statut' },
    ],
});

let modalNewAcheminements = $("#modalNewAcheminements");
let submitNewAcheminements = $("#submitNewAcheminements");
let urlNewAcheminements = Routing.generate('acheminements_new', true);
InitialiserModal(modalNewAcheminements, submitNewAcheminements, urlNewAcheminements, tableAcheminements, (data) => printColis(data));

let modalModifyAcheminements = $('#modalEditAcheminements');
let submitModifyAcheminements = $('#submitEditAcheminements');
let urlModifyAcheminements = Routing.generate('acheminement_edit', true);
InitialiserModal(modalModifyAcheminements, submitModifyAcheminements, urlModifyAcheminements, tableAcheminements, (data) => printColis(data));

let modalDeleteAcheminements = $('#modalDeleteAcheminements');
let submitDeleteAcheminements = $('#submitDeleteAcheminements');
let urlDeleteAcheminements = Routing.generate('acheminement_delete', true);
InitialiserModal(modalDeleteAcheminements, submitDeleteAcheminements, urlDeleteAcheminements, tableAcheminements);

function printAcheminement(id) {
    let params = {
        id: id
    };
    let json = JSON.stringify(params);
    $.post(Routing.generate('get_info_to_print', true), json, function(data) {
        printColis(data);
    });
}

let $submitSearchAcheminements = $('#submitSearchAcheminements');
$submitSearchAcheminements.on('click', function () {
    $('#dateMin').data("DateTimePicker").format('YYYY-MM-DD');
    $('#dateMax').data("DateTimePicker").format('YYYY-MM-DD');

    let filters = {
        page: PAGE_ACHEMINEMENTS,
        dateMin: $('#dateMin').val(),
        dateMax: $('#dateMax').val(),
        statut: $('#statut').val(),
    };

    $('#dateMin').data("DateTimePicker").format('DD/MM/YYYY');
    $('#dateMax').data("DateTimePicker").format('DD/MM/YYYY');

    saveFilters(filters, tableAcheminements);
});

$(function() {
    initSelect2('#statut', 'Statut');
    initDateTimePicker();

    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_ACHEMINEMENTS);
    $.post(path, params, function(data) {
        data.forEach(function(element) {
            if (element.field == 'utilisateurs') {
                $('#utilisateur').val(element.value.split(',')).select2();
            }  else if (element.field == 'dateMin' || element.field == 'dateMax') {
                $('#' + element.field).val(moment(element.value, 'YYYY-MM-DD').format('DD/MM/YYYY'));
            } else if (element.field == 'statut') {
                $('#' + element.field).val(element.value).select2();
            } else {
                $('#'+element.field).val(element.value);
            }
        });
    }, 'json');
});

function printColis(data) {
    let a4 = [3508, 2480];
    if (data.exists) {
        let pdf = new jsPDF('p', 'mm', a4);
        const docSize = pdf.internal.pageSize;
        const imageLoaded = (new Array(data.codes.length)).fill(false);
        $("#barcodes").empty();
        data.codes.forEach( function(code, index) {
            const $img = $('<img/>', {id: "barcode" + index});
            $img.on('load', function() {
                const $img2 = $('<img src="data:image/jpeg;base64,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ay3UHEczxK0kf0YjI/Co73wxpup38d1cafY3F1F9yaSBXkT6MRkV5WKy+pVlOanZtWWnwrrbXd9z9G4f42wWW0MPhJ4Xnpwk6lRc1va1F/D5vdf7un/JrdttvXT8fv2lfE9j/wAEff2DbX4ZaDdJJ8X/AInWxuvEF+rbri0jlyH+bqCFygx3G7vmvJ/+CWvwC0P4HfDbxF+1N8VI1Xwx4PV/7Dt7kc6pfEZDKp5fHGBznc3XHH7q6t4O0jX7gTX2labezKNoee2SRgPTLA06TwlpM2k/2e2l6e1iDkWxtkMIPrsxj9K5sPkNJYynWrPmp07WgtNvPXV77H1GM8Z8U+HsVlmDouGLxUnKrXcruV+kYqK5YpWjFcztG9tXc/kZ/a//AGpvEX7ZP7QHiDx94muJJLzWrlnihLZSzhz+7hX/AGVXC++K9q/4J9/8Fm/i5/wT4szpOh3Vr4i8Ks+8aLq26S3hY9TGykOmfRWAzzjk1/TZ/wAKo8Lf9C14f/8ABdD/APE0f8Ko8Lf9C14f/wDBdD/8TX7BV40wlWj9XqYW8Nrc3b/t0/lunwpiadb28MRaXe3/AAT8O/HX/B198TtX0F7fQ/h/4P0u8kXb9pkM8hiPqoMhH5g18C/tY/t//Fj9tfXDd/ELxdqGsQK26KxUiCzh9MRIFQkf3iCfev6vv+FUeFv+ha8P/wDguh/+Jo/4VR4W/wCha8P/APguh/8Aia5sHxRl+Flz0MIk+/Nd/e0bYrh3HYlctbFNrty6fgz+Nuiv7JP+FUeFv+ha8P8A/guh/wDiaP8AhVHhb/oWvD//AILof/ia9T/iIUf+fD/8C/8AtTz/APUeX/P7/wAl/wCCfxwWNjNqd5Fb28bzTTMEjRBlmY8AAV/Sr/wQp/4JqR/sLfs2x614gsUj+IPjKKO51JnX95YxY3Lbg9sZ+YDqVHpX2ZH8LPC8Tqy+G9BVlOQRp8QI/wDHa3gMCvCz7i6eYUFh6cOSPXW9+y2R7GTcMwwVV1py5n00tbv1YUUUV8efUBRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQB//9k=\n"/>');
                pdf.addImage($img2.attr('src'), 'JPEG', 40, 50, 515, 119);
                pdf.addImage($img.attr('src'), 'JPEG', 40, 210, this.width, this.height);
                pdf.setFontSize(57).text('Acheminement n°', 50, 200);
                pdf.setFontSize(59).text(data.acheminements, 210, 200);
                pdf.setFontSize(57).text('Date d\'acheminement :', 50, 250 + this.height);
                pdf.setFontSize(59).text(data.date, 420, 250 + this.height);
                pdf.setFontSize(57).text('Demandeur :', 50, 300 + this.height);
                pdf.setFontSize(59).text(data.demandeur, 420, 300 + this.height);
                pdf.setFontSize(57).text('Destinataire :', 50, 350 + this.height);
                pdf.setFontSize(59).text(data.destinataire, 420, 350 + this.height);
                pdf.setFontSize(57).text('Emplacement de dépose :', 50, 400 + this.height);
                pdf.setFontSize(59).text(data.depose, 420, 400 + this.height);
                pdf.setFontSize(57).text('Emplacement de prise :', 50, 450 + this.height);
                pdf.setFontSize(59).text(data.prise, 420, 450 + this.height);
                pdf.line(40, 275 + this.height, 780, 275 + this.height);
                pdf.line(40, 325 + this.height, 780, 325 + this.height);
                pdf.line(40, 375 + this.height, 780, 375 + this.height);
                pdf.line(40, 425 + this.height, 780, 425 + this.height);
                pdf.line(375,  210 + this.height, 375, 482 + this.height);
                pdf.rect(40 , 210 + this.height, 740, 130 + this.height);
                pdf.addPage();
                imageLoaded[index] = true;
                if (imageLoaded.every(loaded => loaded)) {
                    pdf.deletePage(pdf.internal.getNumberOfPages());
                    pdf.save('Acheminement ' + data.acheminements + '.pdf');
                }
            });
            $('#barcodes').append($img);
            JsBarcode("#barcode" + index, code, {
                format: "CODE128",
            });

        });
    } else {
        alertErrorMsg('Les dimensions étiquettes ne sont pas connues, veuillez les renseigner depuis le menu Paramétrage.', true);
    }
}

function addInputColisClone(button)
{
    let $modal = button.closest('.modal-body');
    let $toClone = $modal.find('.inputColisClone').first();
    let $parent = $toClone.parent();
    $toClone.clone().appendTo($parent);
    $parent.children().last().find('.data-array').val('');
}

function changeStatus(button) {
    let sel = $(button).data('title');
    let tog = $(button).data('toggle');
    if ($(button).hasClass('not-active')) {
        if ($("#s").val() == "0") {
            $("#s").val("1");
        } else {
            $("#s").val("0");
        }
    }

    $('span[data-toggle="' + tog + '"]').not('[data-title="' + sel + '"]').removeClass('active').addClass('not-active');
    $('span[data-toggle="' + tog + '"][data-title="' + sel + '"]').removeClass('not-active').addClass('active');
}

