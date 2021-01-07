const MODE_EDIT = 0;
const MODE_DISPLAY = 1;
const MODE_EXTERNAL = 2;

const MAX_NUMBER_ROWS = 6;
const MAX_NUMBER_PAGES = 8;

/**
 * @type {{
 *     index: int
 *     name: string,
 *     updated: boolean,
 *     rows: {
 *         size: int,
 *         updated: boolean,
 *         components: {
 *             updated: boolean,
 *             type: int,
 *             meterKey: string,
 *             index: int,
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

    $addRowButton.on('click', onRowAdded);
    $dashboard.on(`click`, `.delete-row`, onRowDeleted);

    $dashboard.on(`click`, `.edit-component`, onComponentEdited);
    $dashboard.on(`click`, `.delete-component`, onComponentDeleted);
    $modalComponentTypeSecondStep.on(`click`, `.select-all-arrival-types`, onSelectAll);
    $modalComponentTypeSecondStep.on(`click`, `.select-all-arrival-statuses`, onSelectAll);

    $('button.add-dashboard-modal-submit').on('click', onPageAdded);
    $pagination.on(`click`, `.delete-dashboard`, onPageDeleted);

    $(window).bind('beforeunload', hasEditDashboard);

    if(mode === MODE_DISPLAY || mode === MODE_EXTERNAL) {
        setInterval(function() {
            $.get(Routing.generate("dashboards_fetch", {mode}), function(response) {
                dashboards = JSON.parse(response.dashboards);
                currentDashboard = dashboards.find(d => d.index === currentDashboard.index);

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

function onSelectAll() {
    const $select = $(this).closest(`.input-group`).find(`select`);

    $select.find(`option`).each(function() {
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
    const dashboard = $(this).data(`dashboard`);
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
        dashboard.index = dashboardIndex;

        (dashboard.rows || []).forEach((row, rowIndex) => {
            if(dashboard === currentDashboard) {
                $(`[data-row="${row.index}"]`)
                    .data(`row`, rowIndex)
                    .attr(`data-row`, rowIndex); //update the dom too
            }

            row.index = rowIndex;
        });
    });
}

function renderRefreshDate(date) {
    $(`.refresh-date`).html(`Actualisé le : ${date}`);
}

function renderCurrentDashboard() {
    $dashboard.empty();
    if(currentDashboard) {
        Object.values(currentDashboard.rows)
            .map(renderRow)
            .forEach(row => $dashboard.append(row));
    }
}

function updateAddRowButton() {
    $(`[data-target="#add-row-modal"]`).prop(`disabled`, !currentDashboard || currentDashboard.rows.length >= MAX_NUMBER_ROWS);
}

function renderRow(row) {
    const $rowWrapper = $(`<div/>`, {class: `dashboard-row-wrapper`});
    const $row = $(`<div/>`, {
        class: `dashboard-row dashboard-row-size-${row.size}`,
        'data-row': `${row.index}`,
        html: $rowWrapper
    });

    for(let componentIndex = 0; componentIndex < row.size; ++componentIndex) {
        const component = getRowComponent(row, componentIndex);
        $rowWrapper.append(renderCardComponent($.deepCopy(component) || componentIndex, true));
    }

    if(mode === MODE_EDIT) {
        $row.append(`
                <div class="delete-row-container"><i class="fa fa-trash ml-1 delete-row pointer"></i></div>
        `);
    }

    return $row;
}

function renderCardComponent(component, init = false) {
    let $componentContainer;
    if(component && typeof component === 'object') {
        $componentContainer = $('<div/>', {
            class: 'dashboard-component',
            'data-component': component.index
        });
        $componentContainer.pushLoader('black', 'normal');
        renderComponentWithData(
            $componentContainer,
            component.type,
            component.meterKey,
            component.config || {},
            init ? component.initData : undefined
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
                    $componentContainer.append($(`
                        <div class="component-toolbox dropdown">
                            <i class="fas fa-cog" data-toggle="dropdown"></i>
                            <div class="dropdown-menu dropdown-menu-right pointer">
                                <div class="dropdown-item edit-component pointer ${!component.template ? 'd-none' : ''}" role="button">
                                    <i class="fa fa-pen mr-2"></i> Modifier
                                </div>
                                <div class="dropdown-item delete-component pointer" role="button">
                                    <i class="fa fa-trash mr-2"></i> Supprimer
                                </div>
                            </div>
                        </div>
                    `));
                }
            });
    } else {
        $componentContainer = $('<div/>', {
            class: 'dashboard-component empty',
            'data-component': component,
            html: mode === MODE_EDIT
                ? $('<button/>', {
                    class: 'btn btn-light',
                    click: openModalComponentTypeFirstStep,
                    html: `<i class="fas fa-plus mr-2"></i> Ajouter un composant`
                })
                : ``,
        });
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
            <button class="btn btn-primary btn-ripple mx-1" data-toggle="modal" data-target="#add-dashboard-modal">
                <span class="fa fa-plus mr-2"></span> Ajouter un dashboard
            </button>
        `);
    }

    $('[data-target="#add-dashboard-modal"]')
        .attr(`disabled`, dashboards.length >= MAX_NUMBER_PAGES);
}

function createDashboardSelectorItem(dashboard) {
    const index = dashboard.index;
    const currentDashboardIndex = currentDashboard && currentDashboard.index;
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
                <div class="dropdown-menu pointer">
                    <a class="dropdown-item rename-dashboard" role="button" data-dashboard="${dashboard.index}"
                         data-toggle="modal" data-target="#rename-dashboard-modal">
                        <i class="fas fa-edit mr-2"></i>Renommer
                    </a>
                    <a class="dropdown-item delete-dashboard" role="button" data-dashboard="${dashboard.index}">
                        <i class="fas fa-trash mr-2"></i>Supprimer
                    </a>
                    <a class="dropdown-item" href="${externalRoute}#${dashboard.index + 1}" target="_blank">
                        <i class="fas fa-external-link-alt"></i> Dashboard externe
                    </a>
                </div>
            </div>
        `;
    }

    return $('<div/>', {
        class: `d-flex align-items-center mx-1 p-2 ${subContainerClasses}`,
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
            }
            else if(data.msg) {
                showBSAlert(data.msg, "danger");
            }
            else {
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
                index: dashboards.length,
                name,
                rows: [],
            };
            dashboards.push(currentDashboard);

            renderDashboardPagination();
            renderCurrentDashboard();
            updateAddRowButton();
            $modal.modal('hide');
        }
    } else {
        showBSAlert("Veuillez renseigner un nom de dashboard.", "danger");
    }
}

function onPageDeleted() {
    const $modal = $(`#delete-dashboard-modal`);
    const dashboard = Number($(this).data(`dashboard`));

    $modal.find(`.delete-dashboard-index`).val(dashboard);
    $modal.find(`.delete-dashboard-name`).text(dashboards[dashboard].name);
    $modal.modal(`show`);
}

function onConfirmPageDeleted() {
    const $modal = $(`#delete-dashboard-modal`);
    const dashboard = Number($modal.find(`.delete-dashboard-index`).val());

    dashboards.splice(dashboard, 1);
    recalculateIndexes();

    if(dashboards.length === 0) {
        currentDashboard = undefined;
    } else if(dashboard === currentDashboard.index) {
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
            index: currentDashboard.rows.length,
            size: columns,
            updated: true,
            components: []
        });

        renderCurrentDashboard();
    }

    updateAddRowButton();
    $addRowButton.closest('.modal').modal('hide');
}

function onRowDeleted() {
    const $row = $(this).parents('.dashboard-row');
    const rowIndex = $row.data(`row`);

    $row.remove();
    if(currentDashboard) {
        currentDashboard.updated = true;
        currentDashboard.rows.splice(rowIndex, 1);
    }

    recalculateIndexes();
    updateAddRowButton();
}

function onComponentEdited() {
    const $button = $(this);
    const {row, component} = getComponentFromTooltipButton($button);

    openModalComponentTypeSecondStep(
        $button,
        row.index,
        component
    );
}

function onComponentDeleted() {
    const $button = $(this);
    const $component = $button.closest('.dashboard-component');

    const {row, component} = getComponentFromTooltipButton($button);
    const componentIndex = Number(component.index);

    currentDashboard.updated = true;
    row.updated = true;

    const indexOfComponentToDelete = row.components
        .filter(c => !!c)
        .findIndex((component) => component.index === componentIndex);

    if(indexOfComponentToDelete !== -1) {
        row.components.splice(indexOfComponentToDelete, 1);
    }

    $component.replaceWith(renderCardComponent(componentIndex));
}

function getComponentFromTooltipButton($button) {
    const $component = $button.closest('.dashboard-component');
    const componentIndex = $component.data(`component`);
    const row = currentDashboard.rows[$component.closest(`.dashboard-row`).data(`row`)];

    return {
        row,
        component: getRowComponent(row, componentIndex),
    };
}

function openModalComponentTypeFirstStep() {
    $modalComponentTypeFirstStep.modal(`show`);

    const $button = $(this);
    const $component = $button.closest(`.dashboard-component`);
    const $row = $component.closest(`.dashboard-row`);

    $modalComponentTypeFirstStep
        .find(`input[name="componentIndex"]`)
        .val($component.data(`component`));

    $modalComponentTypeFirstStep
        .find(`input[name="rowIndex"]`)
        .val($row.data(`row`));
}

function openModalComponentTypeNextStep($button) {
    const firstStepIsShown = $modalComponentTypeFirstStep.hasClass('show');
    if(firstStepIsShown) {
        const componentTypeId = $button.data('component-type-id');
        const $form = $button.closest('.form');
        const rowIndex = $form.find('[name="rowIndex"]').val();
        const componentIndex = $form.find('[name="componentIndex"]').val();
        const componentTypeName = $button.data('component-type-name');
        const componentTypeMeterKey = $button.data('component-meter-key');
        const componentTypeTemplate = $button.data('component-template');

        openModalComponentTypeSecondStep($button, rowIndex, {
            index: componentIndex,
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
        componentIndex: component.index,
        values: JSON.stringify(component.config || {})
    };

    wrapLoadingOnActionButton($button, () => $.post(route, content, function(data) {
        if(data.html) {
            initSecondStep(data.html);
        } else {
            editComponent(Number(rowIndex), Number(component.index), {
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
        const {rowIndex, componentIndex, meterKey, template, componentType, ...config} = data;
        editComponent(Number(rowIndex), Number(componentIndex), {
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
    const {data, ...remaining} = ProcessForm($modal);
    if(data.segments) {
        data.segments = data.segments.map(clearSegmentHourValues);
    }
    return {data, ...remaining};
}

function editComponent(rowIndex, componentIndex, {config, type, meterKey, template = null}) {
    const currentRow = getCurrentDashboardRow(rowIndex);
    if(currentRow && componentIndex < currentRow.size) {
        currentDashboard.updated = true;
        currentRow.updated = true;

        let currentComponent = getRowComponent(currentRow, componentIndex);
        if(!currentComponent) {
            currentComponent = {index: componentIndex};
            currentRow.components.push(currentComponent);
        }
        currentComponent.updated = true;
        currentComponent.config = config;
        currentComponent.type = type;
        currentComponent.meterKey = meterKey;
        currentComponent.template = template;

        const $currentComponent = $dashboard
            .find(`.dashboard-row[data-row="${rowIndex}"]`)
            .find(`.dashboard-component[data-component="${componentIndex}"]`);
        $currentComponent.replaceWith(renderCardComponent(currentComponent));
    }
}

function initSecondStep(html) {
    const $modalComponentTypeSecondStepContent = $modalComponentTypeSecondStep.find('.content');
    $modalComponentTypeSecondStepContent.html('');
    $modalComponentTypeSecondStepContent.html(html);

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

function getRowComponent(row, componentIndex) {
    // noinspection EqualityComparisonWithCoercionJS
    return (row && componentIndex < row.size)
        ? row.components.find(({index} = {}) => (index == componentIndex))
        : undefined;
}

function getCurrentDashboardRow(rowIndex) {
    // noinspection EqualityComparisonWithCoercionJS
    return currentDashboard.rows.find(({index}) => (index == rowIndex));
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
                && rows.some(({updated: rowUpdated, components}) => {
                    components = Object.values(components);
                    return rowUpdated
                    || (
                        components
                        && components.some(({updated: componentUpdated}) => componentUpdated)
                    )
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

    return renderComponentWithData($exampleContainer, componentType, $exampleContainer.data('meter-key'), formData)
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

function renderComponentWithData($container, componentType, meterKey, formData = null, initData = null) {
    let exampleValuesPromise;
    if(initData) {
        exampleValuesPromise = new Promise((resolve) => {
            resolve({
                exampleValues: initData
            });
        });
    } else {
        exampleValuesPromise = $.post(
            Routing.generate('dashboard_component_type_example_values', {componentType}),
            formData ? {values: JSON.stringify(formData)} : null
        );
    }

    return exampleValuesPromise
        .then(({exampleValues}) => renderComponent(meterKey, $container, exampleValues))
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

function addEntryTimeInterval($button, time = null) {
    const current = $button.data(`current`);

    const $newSegmentInput = $(`
        <div class="segment-container interval">
            <div class="form-group row align-items-center">
                <label class="col-3">Segment <span class="segment-value">0</span></label>
                <div class="input-group col-7">
                    <input type="text"
                           class="data needed form-control text-center display-previous segment-hour"
                           ${current === 0 ? 'value="1h"' : ''}
                           title="Heure de début du segment"
                           style="border: none; background-color: #e9ecef;"
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
                           onchange="onSegmentInputChange($(this), true)" />
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
    $button.closest('.segment-container').remove();
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
    return (value || '').replace(/[^\d]/g, '');
}
