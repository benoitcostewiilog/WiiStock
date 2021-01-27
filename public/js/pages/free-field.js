$('.select2').select2();

const urlApiChampLibre = Routing.generate('free_field_api', {'id': $('#cl-type-id').val()}, true);
let tableChampLibreConfig = {
    order: [['Label', 'asc']],
    ajax: {
        "url": urlApiChampLibre,
        "type": "POST"
    },
    columns: [
        {"data": 'Actions', 'title': '', className: 'noVis', orderable: false},
        {"data": 'Label', 'title': 'Libellé'},
        {"data": "S'applique à", 'title': "S'applique à"},
        {"data": 'Typage', 'title': 'Typage'},
        {"data": 'Valeur par défaut', 'title': 'Valeur par défaut'},
        {"data": 'Elements', 'title': 'Éléments', className: 'noVis'},
        {"data": 'Affiché à la création', 'title': 'Affiché à la création'},
        {"data": 'Obligatoire à la création', 'title': 'Obligatoire à la création'},
        {"data": 'Obligatoire à la modification', 'title': 'Obligatoire à la modification'},
    ],
    rowConfig: {
        needsRowClickAction: true,
    },
};
let tableChampLibre = initDataTable('tableChamplibre_id', tableChampLibreConfig);

let $modalNewFreeField = $("#modalNewFreeField");
let $submitChampLibreNew = $("#submitChampLibreNew");
let urlChampLibreNew = Routing.generate('free_field_new', true);
InitModal($modalNewFreeField, $submitChampLibreNew, urlChampLibreNew, {tables: [tableChampLibre]});

let $modalDeleteChampLibre = $("#modalDeleteChampLibre");
let $submitChampLibreDelete = $("#submitChampLibreDelete");
let urlChampLibreDelete = Routing.generate('free_field_delete', true);
InitModal($modalDeleteChampLibre, $submitChampLibreDelete, urlChampLibreDelete, {tables: [tableChampLibre]});

let $modalEditFreeField = $("#modalEditFreeField");
let $submitEditChampLibre = $("#submitEditChampLibre");
let urlEditChampLibre = Routing.generate('free_field_edit', true);
InitModal($modalEditFreeField, $submitEditChampLibre, urlEditChampLibre, {tables: [tableChampLibre]});

function defaultValueForTypage($select) {
    const $modal = $select.closest('.modal');
    let valueDefault = $modal.find('.valueDefault');
    let typage = $select.val();
    let inputDefaultBlock;
    let label = "Valeur par défaut&nbsp;";
    let typeInput = typage;

    // cas modification
    let existingValue = $select.data('value');
    let existingElem = $select.data('elem');

    if (typage === 'list' || typage === 'list multiple') {
        const elements = `
            <div class="form-group">
                <label>Éléments (séparés par ';')</label><br>
                <input type="text" class="form-control cursor-default data" name="elem" value="` + (existingElem ? existingElem : '') + `">
            </div>`;

        let value = ``;
        if(typage === 'list') {
            value = `
                <div class="form-group">
                    <label>Valeur par défaut</label><br>
                    <input type="text" class="form-control cursor-default data" name="valeur" value="` + (existingValue ? existingValue : '') + `">
                </div>`;
        }

        valueDefault.html(elements + value);
    } else {
        if (typage === 'booleen') {
            inputDefaultBlock =
                `<div class="wii-switch">
                    <input type="radio" name="valeur" value="1" content="Oui" ` + (existingValue === 1 ? "checked" : "") + `>
                    <input type="radio" name="valeur" value="0" content="Non" ` + (existingValue === 0 ? "checked" : "") + `>
                    <input type="radio" name="valeur" value="-1" content="Aucune" ` + (existingValue === "" ? "checked" : "") + `>
                </div>`;
        } else {
            if (typage === 'datetime') {
                typeInput = 'datetime-local';
            } else if (typage === 'date') {
                typeInput = 'date';
            }

            inputDefaultBlock = `
                <input type="${typeInput}" class="form-control cursor-default data ${typeInput}" name="valeur" value="` + (existingValue ? existingValue : '') + `">
            `;
        }

        let defaultBlock = `
            <div class="form-group">
                <label>${label}</label><br>
                ${inputDefaultBlock}
            </div>
        `;

        valueDefault.html(defaultBlock);
    }
}

function toggleCreationMandatory($switch) {
    if(!$switch.is(':checked')) {
        $('input[name="requiredCreate"]').prop('checked', 0);
    }
}
