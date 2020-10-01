$('.select2').select2();

let modalColumnVisible = $('#modalColumnVisibleArrivage');
let submitColumnVisible = $('#submitColumnVisibleArrivage');
let urlColumnVisible = Routing.generate('save_column_visible_for_arrivage', true);
let onFlyFormOpened = {};
let clicked = false;
let pageLength;
let arrivalsTable;

$(function () {
    const $filtersContainer = $('.filters-container');
    initDateTimePicker('#dateMin, #dateMax, .date-cl');
    initSelect2($('#statut'), 'Statuts');
    initSelect2($filtersContainer.find('[name="carriers"]'), 'Transporteurs');
    initOnTheFlyCopies($('.copyOnTheFly'));

    initTableArrival().then((returnedArrivalsTable) => {
        arrivalsTable = returnedArrivalsTable;
        let $modalColumnVisible = $('#modalColumnVisibleArrivage');
        let $submitColumnVisible = $('#submitColumnVisibleArrivage');
        let urlColumnVisible = Routing.generate('save_column_visible_for_arrivage', true);
        InitModal($modalColumnVisible, $submitColumnVisible, urlColumnVisible, {tables: [arrivalsTable]});

        let $modalNewArrivage = $("#modalNewArrivage");
        let submitNewArrivage = $("#submitNewArrivage");
        let urlNewArrivage = Routing.generate('arrivage_new', true);
        InitModal(
            $modalNewArrivage,
            submitNewArrivage,
            urlNewArrivage,
            {
                keepForm: true,
                keepModal: true,
                success: (res) => {
                    res = res || {};
                    arrivalCallback(
                        true,
                        {
                            ...(res || {}),
                            success: () => {
                                $modalNewArrivage.find('.list-multiple').select2();

                                let isPrintColisChecked = $modalNewArrivage.find('#printColisChecked').val();
                                $modalNewArrivage.find('#printColis').prop('checked', isPrintColisChecked);

                                clearModal($modalNewArrivage);
                            }
                        },
                        arrivalsTable
                    )
                }
            });

        onTypeChange($modalNewArrivage.find('[name="type"]'));
    });

    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_ARRIVAGE);
    $.post(path, params, function (data) {
        displayFiltersSup(data);
        initFilterDateToday();
    }, 'json');
    pageLength = Number($('#pageLengthForArrivage').val());
    ajaxAutoUserInit($('.filters .ajax-autocomplete-user'), 'Destinataires');
    ajaxAutoFournisseurInit($('.ajax-autocomplete-fournisseur'), 'Fournisseurs');
    $('select[name="tableArrivages_length"]').on('change', function () {
        let newValue = Number($(this).val());
        if (newValue && newValue !== pageLength) {
            $.post(Routing.generate('update_user_page_length_for_arrivage'), JSON.stringify(newValue));
            pageLength = newValue;
        }
    });
});

function initTableArrival() {
    let pathArrivage = Routing.generate('arrivage_api', true);

    return $
        .post(Routing.generate('arrival_api_columns'))
        .then((columns) => {
            let tableArrivageConfig = {
                serverSide: true,
                processing: true,
                pageLength: Number($('#pageLengthForArrivage').val()),
                order: [[1, "desc"]],
                ajax: {
                    "url": pathArrivage,
                    "type": "POST",
                    'data': {
                        'clicked': () => clicked,
                    }
                },
                columns: columns.map(function (column) {
                    return {
                        ...column,
                        class: column.title === 'Actions' ? 'noVis' : undefined,
                        title: column.title === 'Actions' ? '' : column.title,
                        orderable: column.title === 'Actions'
                            ? column.orderable = false
                            : column.orderable
                    }
                }),
                drawConfig: {
                    needsResize: true
                },
                rowConfig: {
                    needsColor: true,
                    color: 'danger',
                    needsRowClickAction: true,
                    dataToCheck: 'emergency'
                },
                buttons: [
                    {
                        extend: 'colvis',
                        columns: ':not(.noVis)',
                        className: 'd-none'
                    },

                ],
                hideColumnConfig: {
                    columns,
                    tableFilter: 'tableArrival'
                },
                'lengthMenu': [10, 25, 50, 100],
            };

            const arrivalsTable = initDataTable('tableArrivages', tableArrivageConfig);
            arrivalsTable.on('responsive-resize', function () {
                resizeTable(arrivalsTable);
            });
            return arrivalsTable;
        });
}

function resizeTable(arrivalsTable) {
    arrivalsTable
        .columns.adjust()
        .responsive.recalc();
}

function listColis(elem) {
    let arrivageId = elem.data('id');
    let path = Routing.generate('arrivage_list_colis_api', true);
    let modal = $('#modalListColis');
    let params = {id: arrivageId};

    $.post(path, JSON.stringify(params), function (data) {
        modal.find('.modal-body').html(data);
    }, 'json');
}

let editorNewArrivageAlreadyDone = false;
let quillNew;

function initNewArrivageEditor(modal) {
    let $modal = $(modal);
    clearModal($modal);
    onFlyFormOpened = {};
    onFlyFormToggle('fournisseurDisplay', 'addFournisseur', true);
    onFlyFormToggle('transporteurDisplay', 'addTransporteur', true);
    onFlyFormToggle('chauffeurDisplay', 'addChauffeur', true);
    if (!editorNewArrivageAlreadyDone) {
        quillNew = initEditor(modal + ' .editor-container-new');
        editorNewArrivageAlreadyDone = true;
    }
    initSelect2($modal.find('.ajax-autocomplete-fournisseur'));
    initSelect2($modal.find('.ajax-autocomplete-transporteur'));
    initSelect2($modal.find('.ajax-autocomplete-chauffeur'));
    initSelect2($modal.find('.ajax-autocomplete-user'), '', 1);
    $modal.find('.list-multiple').select2();
    initFreeSelect2($('.select2-free'));
}
