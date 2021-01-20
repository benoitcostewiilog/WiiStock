$(function () {
    let pathMessages = Routing.generate('messages_api');
    let tableMessageConfig = {
        processing: true,
        serverSide: true,
        searching: false,
        ajax: {
            "url": pathMessages,
            "type": "POST"
        },
        columns: [
            {"data": 'device', 'title': 'Capteur'},
            {"data": 'date', 'title': 'Date'},
            {"data": 'mainData', 'title': 'Donnée principale'},
            {"data": 'type', 'title': 'Type de message'},
        ],
        order: [[1, "desc"]],
    };
    let tableMessages = initDataTable('tableMessages', tableMessageConfig);
});
