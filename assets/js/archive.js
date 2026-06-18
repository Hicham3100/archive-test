$(document).ready(function(){
    // --- Gestion de la soumission du formulaire du profil ---
    $('#profileForm').on('submit', function(e){
        e.preventDefault();
        var form = $(this);

        $.ajax({
            url: form.attr('action'),
            type: 'POST',
            data: form.serialize(),
            dataType: 'json',
            success: function(res){
                // Supprimer les messages précédents
                form.find('.alert').remove();

                // Ajouter le message d'alerte
                var alertClass = res.status === 'success' ? 'alert-success' : 'alert-danger';
                form.find('.modal-body').prepend('<div class="alert '+alertClass+'" role="alert">'+res.message+'</div>');

                // Si succès, fermer le modal automatiquement après 1.5s
                if(res.status === 'success'){
                    setTimeout(function(){
                        var modalEl = document.getElementById('profileModal');
                        var modal = bootstrap.Modal.getInstance(modalEl);
                        if (modal) {
                            modal.hide();
                        } else {
                            $('#profileModal').modal('hide');
                        }
                    }, 1500);
                }
            },
            error: function(){
                form.find('.modal-body').prepend('<div class="alert alert-danger" role="alert">Erreur serveur</div>');
            }
        });
    });

    // Ouverture automatique du modal de profil si des alertes existent
    if ($('.alert').length > 0 && document.getElementById('profileModal')) {
        var modalEl = document.getElementById('profileModal');
        var modal = new bootstrap.Modal(modalEl);
        modal.show();
    }
});
