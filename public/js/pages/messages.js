$(function () {
    let pathMessages = Routing.generate('messages_api');
    let tableMessageConfig = {
        processing: true,
        serverSide: true,
        searching: false,
        ordering: false,
        ajax: {
            "url": pathMessages,
            "type": "POST"
        },
        columns: [
            {"data": 'device', 'title': 'Capteur'},
            {"data": 'date', 'title': 'Date'},
            {"data": 'mainData', 'title': 'Donnée principale'},
            {"data": 'type', 'title': 'Type de message'},
            {"data": 'profile', 'title': 'Type de capteur'},
            {"data": 'battery', 'title': 'Niveau de batterie'},
        ],
        order: [[1, "desc"]],
    };
    initDataTable('tableMessages', tableMessageConfig);
});
