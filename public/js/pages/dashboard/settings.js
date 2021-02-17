const MODE_EDIT = 0;
const MODE_DISPLAY = 1;
const MODE_EXTERNAL = 2;

const MAX_NUMBER_ROWS = 6;
const MAX_COMPONENTS_IN_CELL = 2;
const MAX_NUMBER_PAGES = 8;

/**
 * @type {{
 *     dashboardIndex: int
 *     name: string,
 *     updated: boolean,
 *     rows: {
 *         size: int,
 *         updated: boolean,
 *         rowIndex: int,
 *         components: {
 *             updated: boolean,
 *             type: int,
 *             meterKey: string,
 *             columnIndex: int,
 *             cellIndex: int,
 *             config: Object.<string,*>,
 *         }[],
 *     }[],
 * }[]}
 */
let dashboards = [];
let currentDashboard = null;
let somePagesDeleted = false;
let mode = undefined;

const $addRowButton = $('button.add-row-modal-submit');
const $dashboard = $('.dashboard');
const $pagination = $('.dashboard-pagination');
const $dashboardRowSelector = $('.dashboard-row-selector');
const $modalComponentTypeFirstStep = $('#modalComponentTypeFistStep');
const $modalComponentTypeSecondStep = $('#modalComponentTypeSecondStep');

$(window).resize(function () {
    clearTimeout(window.resizedFinished);
    window.resizedFinished = setTimeout(function(){
        renderCurrentDashboard();
    }, 100);
});

function loadDashboards(m) {
    mode = m;
    if(mode === undefined) {
        alert("Configuration invalide");
    }

    dashboards = JSON.parse($(`.dashboards-data`).val());
    loadCurrentDashboard(true);

    $(window).on("hashchange", loadCurrentDashboard);

    $(`.save-dashboards`).on('click', function() {
        wrapLoadingOnActionButton($(this), onDashboardSaved);
    });

    $(document).keydown(onArrowNavigation);
    $addRowButton.on('click', onRowAdded);
    $dashboard.on(`click`, `.delete-row`, onRowDeleted);

    $modalComponentTypeSecondStep.on(`click`, `.select-all-types`, onSelectAll);
    $modalComponentTypeSecondStep.on(`click`, `.select-all-statuses`, onSelectAll);

    $('button.add-dashboard-modal-submit').on('click', onPageAdded);
    $pagination.on(`click`, `.delete-dashboard`, onPageDeleted);

    $(window).bind('beforeunload', hasEditDashboard);

    if(mode === MODE_DISPLAY || mode === MODE_EXTERNAL) {
        // all 5 min
        setInterval(function() {
            $.get(Routing.generate("dashboards_fetch", {mode}), function(response) {
                dashboards = JSON.parse(response.dashboards);
                currentDashboard = dashboards.find(({dashboardIndex: currentDashboardIndex}) => currentDashboardIndex === currentDashboard.dashboardIndex);

                renderCurrentDashboard();
                renderDashboardPagination();
                renderRefreshDate(response.refreshed);
            })
        }, 5 * 60 * 1000);
    }

    $(document)
        .arrive(".segments-list .segment-hour", function() {
            onSegmentInputChange($(this), true);
        });
}

function onArrowNavigation(e) {
    const LEFT = 37;
    const RIGHT = 39;
    let requestedDashboardIndex;

    if (e.which === LEFT) {
        requestedDashboardIndex = currentDashboard.dashboardIndex - 1;
    }
    else if (e.which === RIGHT) {
        requestedDashboardIndex = currentDashboard.dashboardIndex + 1;
    }

    const requestedDashboard = dashboards[requestedDashboardIndex];

    if (requestedDashboard) {
        currentDashboard = requestedDashboard;
        location.hash = `#${requestedDashboardIndex + 1}`;
        renderCurrentDashboard();
        updateAddRowButton();
        renderDashboardPagination();
        e.preventDefault(); // prevent the default action (scroll / move caret)
    }
}

function onSelectAll() {
    const $select = $(this).closest(`.input-group`).find(`select`);

    $select.find(`option:not([disabled])`).each(function() {
        $(this).prop(`selected`, true);
    });

    $select.trigger(`change`);
}

$(`.download-trace`).click(function() {
    const blob = new Blob([$(`[name="error-context"]`).val()]);
    saveAs(blob, `dashboards-error.txt`);
})

$dashboardRowSelector.click(function() {
    const button = $(this);

    $(`input[name="new-row-columns-count"]`).val(button.data(`columns`));
    $dashboardRowSelector.removeClass(`selected`);
    button.addClass(`selected`);
    $addRowButton.attr(`disabled`, false);
});

$pagination.on(`click`, `[data-target="#rename-dashboard-modal"]`, function() {
    const dashboard = $(this).data(`dashboard-index`);
    const $indexInput = $(`input[name="rename-dashboard-index"]`);
    const $nameInput = $(`input[name="rename-dashboard-name"]`);

    $indexInput.val(dashboard);
    $nameInput.val(dashboards[dashboard].name);
});

$(`.rename-dashboard-modal-submit`).click(function() {
    const dashboard = $(`input[name="rename-dashboard-index"]`).val();
    const $dashboardNameInput = $('input[name="rename-dashboard-name"]');
    const name = $dashboardNameInput.val();
    const $modal = $dashboardNameInput.closest('.modal');
    if(name) {
        $dashboardNameInput.val(``);

        if(dashboards[dashboard].name !== name) {
            dashboards[dashboard].name = name;
            dashboards[dashboard].updated = true;
        }
        renderDashboardPagination();
        $modal.modal('hide');
    } else {
        showBSAlert("Veuillez renseigner un nom de dashboard.", "danger");
    }
});


function recalculateIndexes() {
    dashboards.forEach((dashboard, dashboardIndex) => {
        dashboard.dashboardIndex = dashboardIndex;

        (dashboard.rows || []).forEach((row, rowIndex) => {
            if(dashboard === currentDashboard) {
                $(`[data-row-index="${row.rowIndex}"]`)
                    .data(`row-index`, rowIndex)
                    .attr(`data-row-index`, rowIndex); //update the dom too
            }

            row.rowIndex = rowIndex;
        });
    });
}

function renderRefreshDate(date) {
    $(`.refresh-date`).html(`Actualisé le : ${date}`);
}

function renderCurrentDashboard() {
    $dashboard.empty();
    if(currentDashboard) {
        updateCurrentDashboardSize();

        Object.keys(currentDashboard.rows)
            .map((key) => currentDashboard.rows[key])
            .map(renderRow)
            .forEach((row) => {
                const $dashboardCurrentRow = $dashboard.find(`.dashboard-row[data-row-index="${row.data('row-index')}"]`);
                if ($dashboardCurrentRow.length > 0) {
                    $dashboardCurrentRow.replaceWith(row);
                } else {
                    $dashboard.append(row);
                }
            });
    }

    if(mode === MODE_DISPLAY || mode === MODE_EXTERNAL) {
        $(`.header-title`).html(`Dashboard | <span class="bold">${currentDashboard.name}</span>`);
        document.title = document.title.split('|')[0] + ` | ${currentDashboard.name}`;
    }
}

function updateCurrentDashboardSize() {
    const currentSize = $dashboard.data('size');
    const prefixSize = `dashboard-size-`;
    if (currentSize) {
        $dashboard.removeClass(`${prefixSize}${currentSize}`);
    }

    const dashboardSize = currentDashboard.rows ? currentDashboard.rows.length : 0;
    $dashboard
        .addClass(`${prefixSize}${dashboardSize}`)
        .data('size', dashboardSize);
}

function updateAddRowButton() {
    $(`[data-target="#add-row-modal"]`).prop(`disabled`, !currentDashboard || currentDashboard.rows.length >= MAX_NUMBER_ROWS);
}

function renderRow(row) {
    const $rowWrapper = $(`<div/>`, {class: `dashboard-row-wrapper`});
    const flexFill = mode !== MODE_EXTERNAL ? 'flex-fill' : '';
    const $row = $(`<div/>`, {
        class: `dashboard-row dashboard-row-size-${row.size} ${flexFill}`,
        'data-row-index': `${row.rowIndex}`,
        'data-size': `${row.size}`,
        html: $rowWrapper
    });

    const orderedComponent = getRowComponentsGroupedByColumn(row);

    for (let columnIndex = 0; columnIndex < orderedComponent.length; columnIndex++) {
        const cellComponents = orderedComponent[columnIndex];
        let $component;
        if (cellComponents.length === 0
            || (
                cellComponents.length === 1
                && (
                    !cellComponents[0]
                    || cellComponents[0].cellIndex === null
                )
            )) {
            $component = renderCardComponent({
                component: $.deepCopy(cellComponents[0]),
                columnIndex
            });
        }
        else {
            $component = createComponentContainer(columnIndex);
            $component.addClass('dashboard-component-split');
            for (let cellIndex = 0; cellIndex < cellComponents.length; cellIndex++) {
                const component = cellComponents[cellIndex];
                const $cardComponent = renderCardComponent({
                    component: $.deepCopy(component),
                    columnIndex,
                    cellIndex
                });
                $component.append($cardComponent);
            }
        }

        $rowWrapper.append($component);
    }

    if(mode === MODE_EDIT) {
        $row.append(`
            <div class="delete-row-container"><i class="fa fa-trash ml-1 delete-row pointer"></i></div>
        `);
    }

    return $row;
}

function createComponentContainer(columnIndex, cellIndex) {
    const $container = $('<div/>', {
        class: `dashboard-component`,
        'data-column-index': columnIndex
    });

    cellIndex = convertIndex(cellIndex);

    if (cellIndex !== null) {
        $container
            .data('cell-index', cellIndex)
            .attr('data-cell-index', cellIndex);
    }
    return $container;
}

function renderCardComponent({columnIndex, cellIndex, component}) {
    const $componentContainer = createComponentContainer(columnIndex, cellIndex);
    cellIndex = convertIndex(cellIndex);

    if(component && typeof component === 'object') {
        $componentContainer.pushLoader('black', 'normal');
        renderComponentWithData(
            $componentContainer,
            component,
            component.config || {}
        )
            .then(() => {
                $componentContainer.popLoader();
                if($componentContainer.children().length === 0) {
                    $componentContainer.append($('<div/>', {
                        class: 'text-danger d-flex flex-fill align-items-center justify-content-center',
                        html: `<i class="fas fa-exclamation-triangle mr-2"></i>Erreur lors de l'affichage du composant`
                    }));
                }

                if(mode === MODE_EDIT) {
                    const $editButton = component.template
                        ? $('<div/>', {
                            class: 'dropdown-item pointer',
                            role: 'button',
                            html: '<i class="fa fa-pen mr-2"></i> Modifier',
                            click: onComponentEdited
                        })
                        : null;

                    const $deleteButton = $(`<div/>`, {
                        class: 'dropdown-item pointer',
                        role: 'button',
                        html: '<i class="fa fa-trash mr-2"></i> Supprimer',
                        click: onComponentDeleted
                    });

                    $componentContainer.append($(`<div/>`, {
                        class: 'component-toolbox dropdown',
                        html: [
                            '<i class="fas fa-cog" data-toggle="dropdown"></i>',
                            $(`<div/>`, {
                                class: 'dropdown-menu dropdown-follow-gt dropdown-menu-right pointer',
                                html: [
                                    $editButton,
                                    $deleteButton
                                ]
                            })
                        ]
                    }));
                }
            });
    }
    else {
        $componentContainer.addClass('empty');
        if (mode === MODE_EDIT) {
            const isSplitCell = cellIndex !== null;
            const $addComponent = $('<button/>', {
                class: 'btn btn-light',
                name: 'add-component-button',
                click: ({target} = {}) => openModalComponentTypeFirstStep($(target), isSplitCell),
                html: `<i class="fas fa-plus mr-2"></i> Ajouter un composant`
            });

            const $splitCell = !isSplitCell
                ? $('<button/>', {
                    class: 'btn btn-light mt-2',
                    click: splitCell,
                    html: `<i class="fas fa-cut"></i> Diviser`,
                })
                : ``;

            $componentContainer.html($('<div/>', {
                class: 'd-flex flex-column align-content-center',
                html: [
                    $addComponent,
                    $splitCell,
                ],
            }));
        }
    }

    return $componentContainer;
}

function renderDashboardPagination() {
    $('.dashboard-pagination').empty();

    dashboards
        .map(dashboard => createDashboardSelectorItem(dashboard))
        .reverse()
        .forEach($item => $pagination.prepend($item));

    if(mode === MODE_EDIT) {
        $(`.dashboard-pagination`).append(`
            <button class="btn btn-primary btn-ripple mx-1"
                    data-toggle="modal"
                    data-target="#add-dashboard-modal">
                <span class="fa fa-plus mr-2"></span> Ajouter un dashboard
            </button>
        `);
    }

    $('[data-target="#add-dashboard-modal"]')
        .attr(`disabled`, dashboards.length >= MAX_NUMBER_PAGES);
}

function createDashboardSelectorItem(dashboard) {
    const index = dashboard.dashboardIndex;
    const currentDashboardIndex = currentDashboard && currentDashboard.dashboardIndex;
    const subContainerClasses = (index === currentDashboardIndex ? 'bg-light rounded bold' : '');

    let name;

    if(dashboard.name.length >= 20) {
        name = $.trim(dashboard.name).substring(0, 17) + "...";
    } else {
        name = dashboard.name;
    }

    const $link = $('<a/>', {
        href: `#${index + 1}`,
        title: dashboard.name,
        text: name,
        class: 'mr-2'
    });

    let $editable = ``;
    if(mode === MODE_EDIT) {
        const externalRoute = Routing.generate('dashboards_external', {
            token: $(`.dashboards-token`).val(),
        });

        $editable = `
            <div class="dropdown d-inline-flex">
                <span class="pointer" data-toggle="dropdown">
                    <i class="fas fa-cog"></i>
                </span>
                <div class="dropdown-menu dropdown-follow-gt pointer">
                    <a class="dropdown-item rename-dashboard" role="button" data-dashboard-index="${dashboard.dashboardIndex}"
                         data-toggle="modal" data-target="#rename-dashboard-modal">
                        <i class="fas fa-edit mr-2"></i>Renommer
                    </a>
                    <a class="dropdown-item delete-dashboard" role="button" data-dashboard-index="${dashboard.dashboardIndex}">
                        <i class="fas fa-trash mr-2"></i>Supprimer
                    </a>
                    <a class="dropdown-item" href="${externalRoute}#${dashboard.dashboardIndex + 1}" target="_blank">
                        <i class="fas fa-external-link-alt"></i> Dashboard externe
                    </a>
                </div>
            </div>
        `;
    }

    return $('<div/>', {
        class: `d-flex align-items-center mx-1 p-2 justify-content-center ${subContainerClasses}`,
        html: [
            $link,
            $editable,
        ]
    });
}

/**
 * @param {boolean=false} init
 */
function loadCurrentDashboard(init = false) {
    if(init) {
        recalculateIndexes();
    }

    if(dashboards && dashboards.length > 0) {
        let hash = window.location.hash.replace(`#`, ``);
        if(!hash || !dashboards[hash - 1]) {
            hash = 1;
        }

        if(!dashboards[hash - 1]) {
            console.error(`Unknown dashboard "${hash}"`);
        } else {
            currentDashboard = dashboards[hash - 1];
            window.location.hash = `#${hash}`;
        }
    }
    // no pages already saved
    else if(window.location.hash) {
        window.location.hash = '';
    }

    renderCurrentDashboard();
    updateAddRowButton();
    renderDashboardPagination();
}

function onDashboardSaved() {
    const content = {
        dashboards: JSON.stringify(dashboards)
    };

    return $.post(Routing.generate(`save_dashboard_settings`), content)
        .then(function(data) {
            if(data.success) {
                showBSAlert("Modifications enregistrés avec succès", "success");
                dashboards = JSON.parse(data.dashboards);
                loadCurrentDashboard(false);
            } else if(data.msg) {
                showBSAlert(data.msg, "danger");
            } else {
                throw data;
            }
        })
        .catch(function(error) {
            const date = new Date().toISOString();
            error.responseText = undefined;

            const context = {
                date,
                error,
                dashboards
            };

            $(`[name="error-context"]`).val(JSON.stringify(context));
            $(`#error-modal`).modal(`show`);

            showBSAlert("Une erreur est survenue lors de la sauvegarde des dashboards", "danger");
        });
}

function onPageAdded() {
    const $dashboardNameInput = $('input[name="add-dashboard-name"]');
    const name = $dashboardNameInput.val();
    const $modal = $dashboardNameInput.closest('.modal');
    if(name) {
        $dashboardNameInput.val(``);

        if(dashboards.length >= MAX_NUMBER_PAGES) {
            console.error("Too many dashboards");
        } else {
            currentDashboard = {
                updated: true,
                dashboardIndex: dashboards.length,
                name,
                rows: [],
            };
            dashboards.push(currentDashboard);

            renderDashboardPagination();
            renderCurrentDashboard();
            updateAddRowButton();
            $modal.modal('hide');

            window.location.hash = `#${currentDashboard.dashboardIndex + 1}`;
        }
    } else {
        showBSAlert("Veuillez renseigner un nom de dashboard.", "danger");
    }
}

function onPageDeleted() {
    const $modal = $(`#delete-dashboard-modal`);
    const dashboard = convertIndex($(this).data(`dashboard-index`));

    $modal.find(`[name="delete-dashboard-index"]`).val(dashboard);
    $modal.find(`.delete-dashboard-name`).text(dashboards[dashboard].name);
    $modal.modal(`show`);
}

function onConfirmPageDeleted() {
    const $modal = $(`#delete-dashboard-modal`);
    const dashboard = convertIndex($modal.find(`[name="delete-dashboard-index"]`).val());

    dashboards.splice(dashboard, 1);
    recalculateIndexes();

    if(dashboards.length === 0) {
        currentDashboard = undefined;
    } else if(dashboard === currentDashboard.dashboardIndex) {
        currentDashboard = dashboards[0];
    }
    somePagesDeleted = true;

    renderCurrentDashboard();
    renderDashboardPagination();
    updateAddRowButton();

    $modal.modal(`hide`);
}

function onRowAdded() {
    const $newRowColumnsCountInput = $('input[name="new-row-columns-count"]');

    const columns = $newRowColumnsCountInput.val();
    $newRowColumnsCountInput.val(``);
    $dashboardRowSelector.removeClass("selected");
    $addRowButton.attr(`disabled`, true);

    if(currentDashboard) {
        currentDashboard.updated = true;
        currentDashboard.rows.push({
            rowIndex: currentDashboard.rows.length,
            size: columns,
            updated: true,
            components: []
        });

        renderCurrentDashboard();
    }

    updateAddRowButton();
    updateCurrentDashboardSize();
    $addRowButton.closest('.modal').modal('hide');
}

function onRowDeleted() {
    const $row = $(this).parents('.dashboard-row');
    const rowIndex = $row.data(`row-index`);

    $row.remove();
    if(currentDashboard) {
        currentDashboard.updated = true;
        currentDashboard.rows.splice(rowIndex, 1);
    }

    recalculateIndexes();
    updateAddRowButton();
    updateCurrentDashboardSize();
}

function onComponentEdited() {
    const $button = $(this);
    const {row, component} = getComponentFromTooltipButton($button);

    openModalComponentTypeSecondStep($button, row.rowIndex, component);
}

function onComponentDeleted() {
    const $button = $(this);
    const $component = $button.closest('.dashboard-component');

    const {row, component} = getComponentFromTooltipButton($button);
    if (component) {
        const columnIndex = convertIndex(component.columnIndex);
        const cellIndex = convertIndex(component.cellIndex);

        currentDashboard.updated = true;
        row.updated = true;

        const indexOfComponentToDelete = row.components
            .filter(c => !!c)
            .findIndex((component) => (
                (component.columnIndex === columnIndex)
                && (component.cellIndex === cellIndex)
            ));

        if (indexOfComponentToDelete !== -1) {
            row.components.splice(indexOfComponentToDelete, 1);
        }

        $component.replaceWith(renderCardComponent({columnIndex, cellIndex}));
    }
}

function getComponentFromTooltipButton($button) {
    const $component = $button.closest('.dashboard-component');
    const columnIndex = $component.data(`column-index`);
    const cellIndex = $component.data(`cell-index`);
    const row = currentDashboard.rows[$component.closest(`.dashboard-row`).data(`row-index`)];

    return {
        row,
        component: getRowComponent(row, columnIndex, cellIndex),
    };
}

function openModalComponentTypeFirstStep($button, isSplitCell = false) {
    const $component = $button.closest(`.dashboard-component`);
    const $row = $component.closest(`.dashboard-row`);

    const $componentButtonsInSplitCell = $modalComponentTypeFirstStep.find('[data-component-in-split-cell="0"]');
    const $componentButtonsHint = $componentButtonsInSplitCell.siblings('.points');
    if (isSplitCell) {
        $componentButtonsInSplitCell.addClass('d-none');
        $componentButtonsHint.addClass('d-none');
    }
    else {
        $componentButtonsInSplitCell.removeClass('d-none');
        $componentButtonsHint.removeClass('d-none');
    }

    $modalComponentTypeFirstStep
        .find(`input[name="columnIndex"]`)
        .val($component.data(`column-index`));

    $modalComponentTypeFirstStep
        .find(`input[name="cellIndex"]`)
        .val($component.data(`cell-index`));

    $modalComponentTypeFirstStep
        .find(`input[name="rowIndex"]`)
        .val($row.data(`row-index`));

    $modalComponentTypeFirstStep.modal(`show`);
}

function openModalComponentTypeNextStep($button) {
    const firstStepIsShown = $modalComponentTypeFirstStep.hasClass('show');
    if(firstStepIsShown) {
        const componentTypeId = $button.data('component-type-id');
        const $form = $button.closest('.form');
        const rowIndex = $form.find('[name="rowIndex"]').val();
        const columnIndex = $form.find('[name="columnIndex"]').val();
        const cellIndex = $form.find('[name="cellIndex"]').val();
        const componentTypeName = $button.data('component-type-name');
        const componentTypeMeterKey = $button.data('component-meter-key');
        const componentTypeTemplate = $button.data('component-template');

        openModalComponentTypeSecondStep($button, rowIndex, {
            columnIndex,
            cellIndex,
            config: {
                title: componentTypeName,
            },
            type: componentTypeId,
            meterKey: componentTypeMeterKey,
            template: componentTypeTemplate,
        });
    }
}

function openModalComponentTypeSecondStep($button, rowIndex, component) {
    const route = Routing.generate(`dashboard_component_type_form`, {componentType: component.type});
    const content = {
        rowIndex,
        columnIndex: component.columnIndex,
        cellIndex: component.cellIndex,
        values: JSON.stringify(component.config || {})
    };

    wrapLoadingOnActionButton($button, () => $.post(route, content, function(data) {
        if(data.html) {
            initSecondStep(data.html);
        } else {
            editComponent(convertIndex(rowIndex), convertIndex(component.columnIndex), convertIndex(component.cellIndex), {
                config: component.config,
                type: component.type,
                meterKey: component.meterKey,
                template: component.template,
            });
        }

        $modalComponentTypeFirstStep.modal(`hide`);

        if(data.html) {
            $modalComponentTypeSecondStep.modal(`show`);
        }
    }, 'json'), true);
}

function onComponentSaved($modal) {
    clearFormErrors($modal);
    const {success, errorMessages, $isInvalidElements, data} = processSecondModalForm($modal);
    if(success) {
        const rowIndex = data.rowIndex;
        const columnIndex = data.columnIndex;
        const meterKey = data.meterKey;
        const componentType = data.componentType;
        const cellIndex = data.cellIndex;
        const template = data.template;
        const config = Object.assign({}, data);
        delete config.rowIndex;
        delete config.columnIndex;
        delete config.meterKey;
        delete config.componentType;
        delete config.cellIndex;
        delete config.template;

        editComponent(convertIndex(rowIndex), convertIndex(columnIndex), convertIndex(cellIndex), {
            config,
            type: componentType,
            meterKey,
            template
        });

        $modalComponentTypeSecondStep.modal('hide');
    } else {
        displayFormErrors($modal, {
            $isInvalidElements,
            errorMessages
        });
    }
}

function processSecondModalForm($modal) {
    const meterKey = $modal.find(`input[name="meterKey"]`).val();

    const processFormResult = ProcessForm($modal, null, () => {
        if(meterKey === ENTRIES_TO_HANDLE) {
            let previous = null;
            const allFilled = $modal
                .find(`.segment-hour:not(.display-previous)`)
                .toArray()
                .every(elem => elem.value);

            const correctOrder = $modal
                .find(`.segment-hour:not(.display-previous)`)
                .toArray()
                .every(elem => {
                    let valid = previous
                        ? elem.value && clearSegmentHourValues(previous.value) < clearSegmentHourValues(elem.value)
                        : elem.value;

                    previous = elem;
                    return valid;
                });

            const $invalidElements = $modal.find(`.segment-hour`)
                .toArray()
                .map(elem => $(elem))
                .filter($elem => $elem.val());

            return {
                success: correctOrder && allFilled,
                errorMessages: [
                    !allFilled ? `Les segments ne peuvent pas être vides` : undefined,
                    allFilled && !correctOrder ? `L'ordre des segments n'est pas valide` : undefined,
                ],
                $isInvalidElements: $invalidElements,
            };
        }
    });
    const data = processFormResult.data;
    const remaining = Object.assign({}, processFormResult);
    delete remaining.data;

    if(meterKey === ENTRIES_TO_HANDLE && data.segments) {
        data.segments = data.segments.map(clearSegmentHourValues);
    }

    return Object.assign({}, remaining, {data});
}

function editComponent(rowIndex, columnIndex, cellIndex, {config, type, meterKey, template = null}) {
    const currentRow = getCurrentDashboardRow(rowIndex);
    cellIndex = convertIndex(cellIndex);

    if(currentRow) {
        currentDashboard.updated = true;
        currentRow.updated = true;

        let currentComponent = getRowComponent(currentRow, columnIndex, cellIndex);

        if(!currentComponent) {
            currentComponent = {columnIndex, cellIndex};
            currentRow.components.push(currentComponent);
        }

        currentComponent.updated = true;
        currentComponent.config = config;
        currentComponent.type = type;
        currentComponent.meterKey = meterKey;
        currentComponent.template = template;
        currentComponent.initData = undefined;

        let $currentComponent = $dashboard
            .find(`.dashboard-row[data-row-index="${rowIndex}"]`)
            .find(`.dashboard-component[data-column-index="${columnIndex}"]`);

        if (cellIndex === null) {
            $currentComponent = $currentComponent.filter(`:not([data-cell-index])`);
        }
        else {
            $currentComponent = $currentComponent.filter(`[data-cell-index="${cellIndex}"]`);
        }

        $currentComponent.replaceWith(renderCardComponent({
            columnIndex,
            cellIndex,
            component: currentComponent
        }));
    }
}

function initSecondStep(html) {
    const $modalComponentTypeSecondStepContent = $modalComponentTypeSecondStep.find('.content');
    $modalComponentTypeSecondStepContent.html('');
    $modalComponentTypeSecondStepContent.html(html);

    const $entitySelect = $modalComponentTypeSecondStepContent.find('select[name="entity"].init-entity-change');
    if ($entitySelect.length > 0) {
        onEntityChange($entitySelect, true);
    }

    $modalComponentTypeSecondStep.find(`.select2`).select2();
    Select2.location($modalComponentTypeSecondStep.find('.ajax-autocomplete-location'));
    Select2.carrier($modalComponentTypeSecondStep.find('.ajax-autocomplete-carrier'));

    const $submitButton = $modalComponentTypeSecondStep.find('button[type="submit"]');
    $submitButton.off('click');
    $submitButton.on('click', () => onComponentSaved($modalComponentTypeSecondStep));

    renderFormComponentExample();

    $modalComponentTypeSecondStep.off('change.secondStepComponentType');
    $modalComponentTypeSecondStep.on('change.secondStepComponentType', 'select.data, input.data, input.data-array, input.checkbox', () => renderFormComponentExample())

    const $segmentsList = $modalComponentTypeSecondStepContent.find('.segments-list');
    if($segmentsList.length > 0) {
        const segments = $segmentsList.data(`segments`);
        if(segments.length > 0) {
            initializeEntryTimeIntervals(segments);
        } else {
            addEntryTimeInterval($segmentsList.find('.add-time-interval'));
        }
    }
}

function getRowComponent(row, columnIndex, cellIndex = null) {
    cellIndex = convertIndex(cellIndex);
    if (row
        && columnIndex < row.size
        && row.components
        && Array.isArray(row.components)) {
        let component;
        for(let currentIndex = 0; currentIndex < row.components.length; currentIndex++){
            const currentComponent = row.components[currentIndex];
            if (currentComponent) {
                const {columnIndex: currentColumnIndex, cellIndex: currentCellIndex} = currentComponent;
                // noinspection EqualityComparisonWithCoercionJS
                if (currentColumnIndex == columnIndex
                    && currentCellIndex == cellIndex) {
                    component = currentComponent;
                    break;
                }
            }
        }
        return component;
    }
    else {
        return undefined;
    }
}

function getRowComponentsGroupedByColumn(row) {
    const size = row.size || 0;
    const res = new Array(size);
    for (let columnIndex = 0; columnIndex < size; columnIndex++) {
        res[columnIndex] = [];

        for (let cellIndex = 0; cellIndex < MAX_COMPONENTS_IN_CELL; cellIndex++) {
            const component = getRowComponent(row, columnIndex, cellIndex);
            if (component) {
                res[columnIndex][cellIndex] = component;
            }
        }

        // if cell was not split we check if a component existing in
        if (res[columnIndex].length === 0) {
            const uniqueComponent = getRowComponent(row, columnIndex);
            if (uniqueComponent) {
                res[columnIndex][0] = uniqueComponent;
            }
        }

        // wee add an empty component
        if (res[columnIndex].length === 0
            || (
                res[columnIndex].length === 1
                && res[columnIndex][0].cellIndex !== null
            )) {
            res[columnIndex].push(undefined);
        }
    }
    return res;
}

/**
 * Retrieve the row for the given row index
 * and retrieve the split component if there
 * is a split component at the given index.
 *
 * @param rowIndex
 * @returns {{components}|any}
 */
function getCurrentDashboardRow(rowIndex) {
    // noinspection EqualityComparisonWithCoercionJS
    return currentDashboard.rows.find(({rowIndex: currentRowIndex}) => (currentRowIndex == rowIndex));
}

/**
 * @returns {boolean}
 */
function hasEditDashboard() {
    // we return undefined to not trigger the browser alert
    return somePagesDeleted
        || dashboards.some(({updated: pageUpdated, rows}) => (
            pageUpdated
            || (
                rows
                && rows.some(({updated: rowUpdated, components: savedComponents}) => {
                    const components = Object
                        .keys(savedComponents)
                        .map((key) => savedComponents[key]);
                    return rowUpdated
                        || (
                            components
                            && components.some(({updated: componentUpdated}) => componentUpdated)
                        );
                })
            )
        ))
        || undefined;
}

function renderFormComponentExample() {
    const $exampleContainer = $modalComponentTypeSecondStep.find('.component-example-container');
    const $exampleContainerParent = $exampleContainer.parent();

    const componentType = $exampleContainer.data('component-type');
    const {data: formData} = processSecondModalForm($modalComponentTypeSecondStep);

    const component = {
        type: componentType,
        meterKey: $exampleContainer.data(`meter-key`),
    };

    return renderComponentWithData($exampleContainer, component, formData)
        .then((renderingSuccess) => {
            if(renderingSuccess) {
                $exampleContainerParent.removeClass('d-none');
            } else {
                $exampleContainerParent.addClass('d-none');
            }
        })
        .catch(() => {
            $exampleContainerParent.addClass('d-none');
        });
}

function renderComponentWithData($container, component, formData = null) {
    let exampleValuesPromise;
    if(component.initData) {
        exampleValuesPromise = new Promise((resolve) => {
            resolve({
                exampleValues: component.initData
            });
        });
    } else {
        exampleValuesPromise = $.post(
            Routing.generate('dashboard_component_type_example_values', {
                componentType: component.type
            }),
            formData ? {values: JSON.stringify(formData)} : null
        );
    }

    return exampleValuesPromise
        .then(({exampleValues}) => renderComponent(component, $container, exampleValues))
        .catch((error) => {
            console.error(error);
        });
}

function initializeEntryTimeIntervals(segments) {
    const $button = $(`.add-time-interval`);

    for(const segment of segments) {
        addEntryTimeInterval($button, segment);
    }
}

function addEntryTimeInterval($button, time = null, notEmptySegment = false) {
    const current = $button.data(`current`);

    if($('.segment-container').length === 5) {
        showBSAlert('Il n\'est pas possible d\'ajouter plus de 5 segments à ce composant', 'danger');
        return false;
    }

    if(notEmptySegment) {
        const lastSegmentHourEndValue = $('.segment-hour').last().val();
        const lastSegmentLabel = $('.segment-container label').last().text();

        if(!lastSegmentHourEndValue) {
            showBSAlert('Le <strong>' + lastSegmentLabel.toLowerCase() + '</strong> doit contenir une valeur de fin' , 'danger');
            return false;
        }
    }

    const $newSegmentInput = $(`
        <div class="segment-container interval">
            <div class="form-group row align-items-center">
                <label class="col-3">Segment <span class="segment-value">0</span></label>
                <div class="input-group col-7">
                    <input type="text"
                           class="data needed form-control text-center display-previous segment-hour"
                           ${current === 0 ? 'value="1h"' : ''}
                           title="Heure de début du segment"
                           style="border: none; background-color: #e9ecef; color: #b1b1b1"
                           disabled />
                    <div class="input-group-append input-group-prepend">
                        <span class="input-group-text" style="border: none;">à</span>
                    </div>
                    <input type="text"
                           class="data-array form-control needed text-center segment-hour"
                           name="segments"
                           data-no-stringify
                           title="Heure de fin du segment"
                           style="border: none; background-color: #e9ecef;"
                           ${time !== null ? 'value="' + time + '"' : ''}
                           onkeyup="onSegmentInputChange($(this), false)"
                           onfocusout="onSegmentInputChange($(this), true)" />
                </div>
                <div class="col-2">
                    <button class="btn d-block" onclick="deleteEntryTimeInterval($(this))"><i class="far fa-trash-alt"></i></button>
                </div>
            </div>
        </div>
    `);

    $button.data("current", current + 1);
    const $lastSegmentValues = $button.closest('.modal').find('.segment-value');
    const $currentSegmentValue = $newSegmentInput.find('.segment-value');
    const $lastSegmentValue = $lastSegmentValues.last();
    const lastSegmentValue = parseInt($lastSegmentValue.text() || '0');
    $currentSegmentValue.text(lastSegmentValue + 1);

    $newSegmentInput.insertBefore($button);
    recalculateIntervals();
}

function deleteEntryTimeInterval($button) {
    const $segmentContainer = $('.segment-container');

    if($segmentContainer.length === 1) {
        showBSAlert('Au moins un segment doit être renseigné', 'danger');
        event.preventDefault();
        return false;
    }

    const $currentSegmentContainer = $button.closest('.segment-container');
    const $nextsegmentContainers = $currentSegmentContainer.nextAll().not('button');

    $nextsegmentContainers.each(function() {
        const $currentSegment = $(this);
        const $segmentValue = $currentSegment.find('.segment-value');
        $segmentValue.text(parseInt($segmentValue.text()) - 1);
    });
    $currentSegmentContainer.remove();
    recalculateIntervals();
}

function recalculateIntervals() {
    let previous = null;

    $(`.segments-list > .interval`).each(function() {
        if(previous) {
            $(this).find(`.display-previous`).val(previous);
        }

        previous = $(this).find(`input[name="segments"]`).val();
    });
}

function onSegmentInputChange($input, isChanged = false) {
    const value = $input.val();
    const smartValue = clearSegmentHourValues(value);
    const newVal = smartValue && (parseInt(smartValue) + (isChanged ? 'h' : ''));

    $input.val(newVal);

    if(isChanged) {
        recalculateIntervals();
    }
}

function clearSegmentHourValues(value) {
    const cleared = (value || ``).replace(/[^\d]/g, ``);
    return cleared ? parseInt(cleared) : ``;
}

function onEntityChange($select, onInit = false) {
    const $modal = $select.closest('.modal');

    const $selectedOption = $select.find(`option[value="${$select.val()}"]`);
    const categoryType = $selectedOption.data('category-type');
    const categoryStatus = $selectedOption.data('category-status');
    const $selectAllAvailableTypes = $modal.find('.select-all-types');
    const $selectAllAvailableStatuses = $modal.find('.select-all-statuses');
    const $selectType = $modal.find('select[name="entityTypes"]');
    const $selectStatus = $modal.find('select[name="entityStatuses"]');

    const $correspondingTypes = $selectType.find(`option[data-category-label="${categoryType}"]`);
    const $correspondingStatuses = $selectStatus.find(`option[data-category-label="${categoryStatus}"]`);
    const $otherTypes = $selectType.find(`option[data-category-label!="${categoryType}"]`);
    const $otherStatuses = $selectStatus.find(`option[data-category-label!="${categoryStatus}"]`);

    const disabledSelect = (
        !categoryType
        || !categoryStatus
        || $correspondingTypes.length === 0
        || $correspondingStatuses.length === 0
    );

    $selectType.prop('disabled', disabledSelect);
    $selectStatus.prop('disabled', disabledSelect);
    $selectAllAvailableTypes.prop('disabled', disabledSelect);
    $selectAllAvailableStatuses.prop('disabled', disabledSelect);
    $otherTypes.prop('disabled', true);
    $otherStatuses.prop('disabled', true);

    if (!onInit) {
        $selectType.val(null);
        $selectStatus.val(null);

        if (!disabledSelect) {
            $correspondingTypes.prop('disabled', false);
            $correspondingStatuses.prop('disabled', false);

            if ($correspondingTypes.length === 1) {
                $selectType.val($correspondingTypes[0].value);
            }
            if ($correspondingStatuses.length === 1) {
                $selectStatus.val($correspondingStatuses[0].value);
            }
        }
    }

    $selectType.trigger('change');
    $selectStatus.trigger('change');
}

function toggleTreatmentDelay($checkbox) {
    const $modal = $checkbox.closest('.modal');
    const $treatmentDelay = $modal.find('[name=treatmentDelay]');
    $treatmentDelay.val('');
    if(!$checkbox.prop('checked')) {
        $treatmentDelay.prop('disabled', true);
    } else {
        $treatmentDelay.prop('disabled', false);
    }
}

function splitCell() {
    const $button = $(this);
    const $componentContainer = $button.closest('.dashboard-component');

    $button.remove();
    const $addComponentButton = $componentContainer.find('button[name="add-component-button"]');
    $addComponentButton.off('click');
    $addComponentButton.on('click', ({target} = {}) => openModalComponentTypeFirstStep($(target), true));

    const $first = $componentContainer.clone(true);
    $first
        .data('cell-index', 0)
        .attr('data-cell-index', 0);

    const $last = $componentContainer.clone(true);
    $last
        .data('cell-index', 1)
        .attr('data-cell-index', 1);

    $componentContainer.html([$first, $last]);
    $componentContainer
        .addClass('dashboard-component-split')
        .removeClass('empty');
}

function convertIndex(indexStr) {
    return (indexStr === undefined || indexStr === null || indexStr === '') ? null : Number(indexStr);
}
