$(function () {
    let pathDevices = Routing.generate('devices_api');
    let tableDevicesConfig = {
        processing: true,
        serverSide: true,
        searching: false,
        ordering: false,
        ajax: {
            "url": pathDevices,
            "type": "POST"
        },
        columns: [
            {"data": 'action', 'title': 'Actions'},
            {"data": 'code', 'title': 'Code'},
            {"data": 'battery', 'title': 'Niveau de batterie'},
            {"data": 'profile', 'title': 'Profil de capteur'},
        ],
    };
    initDataTable('tableDevices', tableDevicesConfig);
});
