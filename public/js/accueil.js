function filtreDemande(valeur){
    let indicateurAccueil = valeur;
    let path = '';

    if(valeur === 'demandeT' || valeur === 'demandeP'){
        path = 'filtre_indicateur_accueil';
    } else if (valeur === 'collecte') {
        path = 'collecte_index';
    } else if (valeur === 'service'){
        path = 'service_index';
    }
    let params = {
        filtre: indicateurAccueil
    };
    let route = Routing.generate(path, params);
    window.location.href = route;
}

$(document).ready(() => {
    $('#statut').val($('#filtreRedirect').val());
    $('#submitSearchDemandeLivraison').click();
});

$('#submitSearchDemandeLivraison').on('click', function () {
    let statut = $('#statut').val();
    let type = $('#type').val();
    let utilisateur = $('#utilisateur').val()
    let utilisateurString = utilisateur.toString();
    let utilisateurPiped = utilisateurString.split(',').join('|');
    tableDemande
        .columns('Statut:name')
        .search(statut ? '^' + statut + '$' : '', true, false)
        .draw();

    tableDemande
        .columns('Type:name')
        .search(type ? '^' + type + '$' : '', true, false)
        .draw();

    tableDemande
        .columns('Demandeur:name')
        .search(utilisateurPiped ? '^' + utilisateurPiped + '$' : '', true, false)
        .draw();

    $.fn.dataTable.ext.search.push(
        function (settings, data, dataIndex) {
            let dateMin = $('#dateMin').val();
            let dateMax = $('#dateMax').val();
            let indexDate = tableDemande.column('Date:name').index();
            let dateInit = (data[indexDate]).split('/').reverse().join('-') || 0;

            if (
                (dateMin == "" && dateMax == "")
                ||
                (dateMin == "" && moment(dateInit).isSameOrBefore(dateMax))
                ||
                (moment(dateInit).isSameOrAfter(dateMin) && dateMax == "")
                ||
                (moment(dateInit).isSameOrAfter(dateMin) && moment(dateInit).isSameOrBefore(dateMax))

            ) {
                return true;
            }
            return false;
        }
    );
    tableDemande
        .draw();
});