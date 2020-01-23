$(function() {
    initDateTimePicker();
    initSearchDate(tableMission);
    $('.select2').select2();

    // filtres enregistrés en base pour chaque utilisateur
    let path = Routing.generate('filter_get_by_page');
    let params = JSON.stringify(PAGE_INV_SHOW_MISSION);
    $.post(path, params, function(data) {
        displayFiltersSup(data);
    }, 'json');
});

let mission = $('#missionId').val();
let pathMission = Routing.generate('inv_entry_api', { id: mission}, true);
let tableMission = $('#tableMissionInv').DataTable({
    processing: true,
    serverSide: true,
    language: {
        url: "/js/i18n/dataTableLanguage.json",
    },
    order: [[2, 'desc']],
    ajax:{
        "url": pathMission,
        "type": "POST",
    },
    'drawCallback': function() {
        overrideSearch($('#tableMissionInv_filter input'), tableMission);
    },
    columns:[
        { "data": 'Ref', 'title' : 'Reférence' },
        { "data": 'Label', 'title' : 'Libellé' },
        { "data": 'Date', 'title' : 'Date de saisie', 'name': 'date' },
        { "data": 'Anomaly', 'title' : 'Anomalie', 'name' : 'anomaly'  }
    ],
});

let modalAddToMission = $("#modalAddToMission");
let submitAddToMission = $("#submitAddToMission");
let urlAddToMission = Routing.generate('add_to_mission', true);
InitialiserModal(modalAddToMission, submitAddToMission, urlAddToMission, tableMission, null);

$('#submitSearchMissionRef').on('click', function() {
    $('#dateMin').data("DateTimePicker").format('YYYY-MM-DD');
    $('#dateMax').data("DateTimePicker").format('YYYY-MM-DD');

    let filters = {
        page: PAGE_INV_SHOW_MISSION,
        dateMin: $('#dateMin').val(),
        dateMax: $('#dateMax').val(),
        anomaly: $('#anomaly').val(),
    };

    $('#dateMin').data("DateTimePicker").format('DD/MM/YYYY');
    $('#dateMax').data("DateTimePicker").format('DD/MM/YYYY');

    saveFilters(filters, tableMission);
});