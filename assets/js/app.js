import 'bootstrap';
import 'select2';
import moment from 'moment';
import 'datatables.net';
import 'datatables.net-dt/js/dataTables.dataTables';
import '@fortawesome/fontawesome-free/js/all.js';
import Routing from '../../vendor/friendsofsymfony/jsrouting-bundle/Resources/public/js/router.min.js';
import Quill from 'quill/dist/quill.js';
import Toolbar from 'quill/modules/toolbar';
import Snow from 'quill/themes/snow';
import Wiistock from './general';

import '../scss/app.scss';

///////////////// Main

importWiistock();
importJquery();
importMoment();
importQuill();
importRouting();

///////////////// Functions

function importWiistock() {
    global.Wiistock = Wiistock;
}

function importJquery() {
    global.$ = global.jQuery = $;
}

function importMoment() {
    global.moment = moment;
}

function importQuill() {
    Quill.register({
        'modules/toolbar.js': Toolbar,
        'themes/snow.js': Snow,
    });

    global.Quill = Quill;
}

function importRouting() {
    const routes = require('../../public/generated/routes.json');
    Routing.setRoutingData(routes);

    global.Routing = Routing;
}
