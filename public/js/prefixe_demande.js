function prefixDemand(){
    let prefixe = $('#prefixeDemande').val();
    let typeDemande = $('#typeDemandePrefixageDemande').val();

    let path = Routing.generate('ajax_prefixe_demande',true);
    let params = JSON.stringify({prefixe: prefixe, typeDemande: typeDemande});

    let msg = '';
    if(typeDemande === 'aucunPrefixe'){
        $('#typeDemandePrefixageDemande').addClass('is-invalid');
        msg += 'Veuillez sélectionner un type de demande.';
    } else {
        $('#typeDemandePrefixageDemande').removeClass('is-invalid');
        $('#buttonModalPrefixageSet').click();
    }
    $('.error-msg').html(msg);

    $.post(path, params, function(data){
    });
}