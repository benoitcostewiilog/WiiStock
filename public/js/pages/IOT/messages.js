$(function () {
    let pathMessages = Routing.generate('device_messages_api');
    let tableMessageConfig = {
        processing: true,
        serverSide: true,
        searching: false,
        ordering: false,
        ajax: {
            "url": pathMessages,
            "type": "POST",
            'data' : {
                'device': $('#device').val()
            },
        },
        columns: [
            {"data": 'date', 'title': 'Date'},
            {"data": 'mainData', 'title': 'Donnée principale'},
            {"data": 'type', 'title': 'Type de message'},
        ],
        order: [[0, "desc"]],
    };
    initDataTable('tableMessages', tableMessageConfig);
});
