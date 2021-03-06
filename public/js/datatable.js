$(function () {
    $.fn.dataTable.ext.errMode = () => {
        alert('La requête n\'est pas parvenue au serveur. Veuillez contacter le support si cela se reproduit.');
    };

    $(window).on('shown.bs.collapse shown.bs.modal', (event) => {
        const $collapse = $(event.target);
        $collapse.find('.wii-table').each(function () {
            const $table = $(this);
            $table.DataTable().columns.adjust().draw();
        });
    });
});

function hideColumns(table, data) {
    data.forEach(function (col) {
        table.column(col + ':name').visible(false);
    })
}

function showColumns(table, data) {
    table.columns().visible(false);
    data.forEach(function (col) {
        table.columns(col + ':name').visible(true);
    })
}

function extendsDateSort(name) {
    $.extend($.fn.dataTableExt.oSort, {
        [name + "-pre"]: function (date) {
            const dateSplitted = date.split(' ');
            const dateDaysParts = dateSplitted[0].split('/');
            const year = parseInt(dateDaysParts[2]);
            const month = parseInt(dateDaysParts[1]);
            const day = parseInt(dateDaysParts[0]);

            const dateHoursParts = dateSplitted.length > 1 ? dateSplitted[1].split(':') : [];
            const hours = dateHoursParts.length > 0 ? parseInt(dateHoursParts[0]) : 0;
            const minutes = dateHoursParts.length > 1 ? parseInt(dateHoursParts[1]) : 0;
            const seconds = dateHoursParts.length > 2 ? parseInt(dateHoursParts[2]) : 0;

            const madeDate = new Date(year, month - 1, day, hours, minutes, seconds);
            return madeDate.getTime() || 0;
        },
        [name + "-asc"]: function (a, b) {
            return ((a < b) ? -1 : ((a > b) ? 1 : 0));
        },
        [name + "-desc"]: function (a, b) {
            return ((a < b) ? 1 : ((a > b) ? -1 : 0));
        }
    });
}

function initActionOnRow(row) {
    if ($(row).find('.action-on-click').get(0)) {
        $(row).addClass('pointer');
        $(row).on('mouseup', 'td:not(.noVis)', function (event) {
            const highlightedText = window.getSelection
                ? window.getSelection().toString()
                : undefined;

            if (!highlightedText) {
                const {which} = event || {};
                let $anchor = $(row).find('.action-on-click');
                const href = $anchor.attr('href');
                if (href) {
                    if (which === 1) {
                        window.location.href = href;
                    } else if (which === 2) {
                        window.open(href, '_blank');
                    }
                } else {
                    if (which === 1) {
                        $anchor.trigger('click');
                    }
                }
            }
        });
    }
}

function showOrHideColumn(check, concernedTable) {
    let columnName = check.data('name');
    let column = concernedTable.column(columnName + ':name');
    column.visible(!column.visible());
    check.toggleClass('data');
}

function manageArticleAndRefSearch($input, $printButton) {
    if ($input.val() === '' && $('#filters').find('.filter').length <= 0) {
        if ($printButton.is('button')) {
            $printButton
                .addClass('btn-disabled')
                .removeClass('btn-primary');
            managePrintButtonTooltip(true, $printButton.parent());
        } else {
            $printButton
                .removeClass('pointer')
                .addClass('disabled')
                .addClass('has-tooltip');
            managePrintButtonTooltip(true, $printButton);
        }

        managePrintButtonTooltip(true, $printButton);
    } else {

        if ($printButton.is('button')) {
            $printButton
                .addClass('btn-primary')
                .removeClass('btn-disabled');
            managePrintButtonTooltip(false, $printButton.parent());
        } else {
            $printButton
                .removeClass('disabled')
                .addClass('pointer')
                .removeClass('has-tooltip');
            managePrintButtonTooltip(false, $printButton);
        }
    }
}

function toggleInputRadioOnRow(tr) {
    const $checkbox = $(tr).find('input[type="checkbox"]');
    $checkbox.prop('checked', !$checkbox.is(':checked')).trigger("change");
}

function createDatatableDomFooter({information, length, pagination}) {
    return (information || length || pagination)
        ? (
            `<"row mt-2 align-items-center"
                ${length ? '<"col-auto"l>' : ''}
                ${information ? '<"col-auto"i>' : ''}
                ${pagination ? '<"col"p>' : ''}
            >`
        )
        : ''
}

function getAppropriateDom({needsFullDomOverride, needsPartialDomOverride, needsMinimalDomOverride, needsPaginationRemoval, removeInfo}) {
    const domFooter = createDatatableDomFooter({
        information: !removeInfo,
        length: true,
        pagination: !needsPaginationRemoval
    });
    let dtDefaultValue = (
        '<"row mb-2"' +
        '<"col-auto d-none"f>' +
        '>' +
        't' +
        domFooter +
        'r'
    );
    return needsFullDomOverride
        ? dtDefaultValue
        : needsPartialDomOverride
            ? (
                'r' +
                't' +
                createDatatableDomFooter({length: true, pagination: true})
            )
            : needsMinimalDomOverride
                ? 'tr'
                : dtDefaultValue;
}

function getAppropriateRowCallback({needsColor, classField, color, dataToCheck, needsRowClickAction, callback}) {
    return function (row, data) {
        if (needsColor
            && (data[dataToCheck] === true || data[dataToCheck] && data[dataToCheck].toLowerCase() !== 'non')) {
            $(row).addClass('table-' + color);
        }
        if (classField && data[classField]) {
            $(row).addClass(data[classField]);
        }
        if (needsRowClickAction) {
            initActionOnRow(row);
        }
        if (callback) {
            callback(row, data);
        }
    }
}

function overrideSearch($input, table, callback = null) {
    $input.off();
    $input.on('keyup', function (e) {
        if (e.key === 'Enter') {
            table.search(this.value).draw();
            if (callback) {
                callback($input);
            }
        }
    });

    $input.addClass('form-control');
    $input.attr('placeholder', 'Entrée pour valider');
}

function datatableDrawCallback({response, needsSearchOverride, needsColumnHide, needsColumnShow, needsResize, needsEmplacementSearchOverride, callback, table, $table}) {
    let $searchInputContainer = $table.parents('.dataTables_wrapper ').find('.dataTables_filter');
    let $searchInput = $searchInputContainer.find('input');

    if (needsSearchOverride && $searchInput.length > 0) {
        overrideSearch($searchInput, table);
    }
    if (needsColumnHide) {
        hideColumns(table, response.json.columnsToHide);
    }
    if (needsColumnShow) {
        showColumns(table, response.json.visible);
    }
    if (needsEmplacementSearchOverride) {
        overrideSearchSpecifEmplacement($searchInput);
    }
    if (callback) {
        callback();
    }
    renderDtInfo($(table.table().container()));
}

function moveSearchInputToHeader($searchInputContainer) {
    const $datatableCard = $searchInputContainer.parents('.wii-page-card');
    const $searchInput = $searchInputContainer.find('input');
    const $searchInputContainerCol = $searchInputContainer.parent();

    if ($datatableCard.length > 0) {
        let $datatableCardHeader = $datatableCard.find('.wii-page-card-header');
        $datatableCardHeader = ($datatableCardHeader.length > 1)
            ? $searchInputContainer.parents('.dt-parent').find('.wii-page-card-header')
            : $datatableCardHeader;
        if ($datatableCardHeader.length > 0) {
            $searchInput.addClass('search-input');
            $datatableCardHeader.prepend($searchInputContainerCol);
            $searchInputContainerCol.removeClass('d-none');
        } else {
            $searchInputContainerCol.removeClass('d-none');
        }
    } else {
        $searchInputContainerCol.removeClass('d-none');
    }
}

function initDataTable($table, options) {
    const domConfig = options.domConfig;
    const rowConfig = options.rowConfig;
    const drawConfig = options.drawConfig;
    const initCompleteCallback = options.initCompleteCallback;
    const hideColumnConfig = options.hideColumnConfig;
    const config = Object.assign({}, options);
    delete options.domConfig;
    delete options.rowConfig;
    delete options.drawConfig;
    delete options.initCompleteCallback;
    delete options.hideColumnConfig;

    let tooltips = [];
    (config.columns || []).forEach((column, id) => {
        if (column.translated) {
            tooltips.push({id, text: Trans.original(column.title)});
            column.title = Trans.translated(column.title);
        } else if (column.tooltip) {
            tooltips.push({id, text: column.tooltip});
        }

        if(config.order && Array.isArray(config.order)) {
            const newOrder = [];
            for(let [name, order] of config.order) {
                if(name === column.data || name === column.name) {
                    name = id;
                }

                newOrder.push([name, order]);
            }

            config.order = newOrder;
        }
    });

    let existingHeaderCallback = config.headerCallback;
    config.headerCallback = (thead) => {
        let $ths = $(thead).find('th');

        for (let data of tooltips) {
            $ths.eq(data.id).attr('title', data.text)
        }

        if (existingHeaderCallback) {
            return existingHeaderCallback();
        }
    }

    let datatableToReturn = null;
    $table = typeof $table === 'string' ? $('#' + $table) : $table;
    $table
        .addClass('wii-table')
        .addClass('w-100');

    datatableToReturn = $table
        .on('error.dt', function (e, settings, techNote, message) {
            console.log('An error has been reported by DataTables: ', message, e, $table.attr('id'));
        })
        .DataTable(Object.assign({
            fixedColumns:   {
                heightMatch: 'auto'
            },

            autoWidth: true,
            scrollX: true,
            language: {
                url: "/js/i18n/dataTableLanguage.json",
            },
            dom: getAppropriateDom(domConfig || {}),
            rowCallback: getAppropriateRowCallback(rowConfig || {}),
            drawCallback: (response) => {
                datatableDrawCallback(Object.assign({
                    table: datatableToReturn,
                    response,
                    $table
                }, drawConfig || {}));
            },
            initComplete: () => {
                let $searchInputContainer = $table.parents('.dataTables_wrapper').find('.dataTables_filter');
                moveSearchInputToHeader($searchInputContainer);
                tableCallback(hideColumnConfig || {}, datatableToReturn);
                if (initCompleteCallback) {
                    initCompleteCallback();
                }
                attachDropdownToBodyOnDropdownOpening($table);
            }
        }, config));
    return datatableToReturn;
}

function renderDtInfo($table) {
    $table
        .find('.dataTables_info')
        .addClass('pt-0');
}

function overrideSearchSpecifEmplacement($input) {
    $input.off();
    $input.on('keyup', function (e) {
        let $printButton = $('.printButton');

        if (e.key === 'Enter') {
            if ($input.val() === '') {
                $printButton
                    .addClass('user-select-none')
                    .addClass('disabled')
                    .addClass('has-tooltip')
                    .removeClass('pointer');
                managePrintButtonTooltip(true, $printButton);
            } else {
                $printButton
                    .removeClass('user-select-none')
                    .removeClass('disabled')
                    .removeClass('has-tooltip')
                    .addClass('pointer');
                managePrintButtonTooltip(false, $printButton);
            }

            $(`#locationsTable`).DataTable().search(this.value).draw();
        } else if (e.key === 'Backspace' && $input.val() === '') {
            $printButton
                .addClass('user-select-none')
                .addClass('disabled')
                .addClass('has-tooltip')
                .removeClass('pointer');
            managePrintButtonTooltip(true, $printButton);
        }
    });

    $input.addClass('form-control');
    $input.attr('placeholder', 'entrée pour valider');
}

function toggleActiveButton($button, table) {
    $button.toggleClass('active');
    $button.toggleClass('not-active');

    let value = $button.hasClass('active') ? 'true' : '';
    table
        .columns('Active:name')
        .search(value)
        .draw();
}

function initSearchDate(table) {
    $.fn.dataTable.ext.search.push(
        function (settings, data) {
            let dateMin = $('#dateMin').val();
            let dateMax = $('#dateMax').val();
            let indexDate = table.column('date:name').index();

            if (typeof indexDate === "undefined") return true;

            let dateInit = (data[indexDate]).split('/').reverse().join('-') || 0;

            if (
                (dateMin === "" && dateMax === "")
                ||
                (dateMin === "" && moment(dateInit).isSameOrBefore(dateMax))
                ||
                (moment(dateInit).isSameOrAfter(dateMin) && dateMax === "")
                ||
                (moment(dateInit).isSameOrAfter(dateMin) && moment(dateInit).isSameOrBefore(dateMax))

            ) {
                return true;
            }
            return false;
        }
    );
}

function hideAndShowColumns(columns, table) {
    table.columns().every(function (index) {
        this.visible(columns[index].isColumnVisible);
    });
}

function tableCallback({columns, tableFilter}, table) {
    if (columns) {
        hideSpinner($('#spinner'));
        hideAndShowColumns(columns, table);
        overrideSearch($('#' + tableFilter + ' input'), table, function ($input) {
            manageArticleAndRefSearch($input, $('#printTag'));
        });
    }
}

function attachDropdownToBodyOnDropdownOpening($table) {
    let dropdownMenu;

    $table.on('show.bs.dropdown', function (e) {
        const $target = $(e.target);
        dropdownMenu = $target.find('.dropdown-menu');
        let parentModal = $target.parents('.modal');
        dropdownMenu = $target.find('.dropdown-menu');
        $('body').append(dropdownMenu.detach());
        dropdownMenu.css('display', 'block');
        dropdownMenu.position({
            'my': 'right top',
            'at': 'right bottom',
            'of': $(e.relatedTarget)
        });
        if (parentModal.length > 0) {
            dropdownMenu.css('z-index', (parentModal.first().css('z-index') || 0) + 1)
        }
    });

    $table.on('hide.bs.dropdown', function (e) {
        const $target = $(e.target);
        $target.append(dropdownMenu.detach());
        dropdownMenu.hide();
    });
}

