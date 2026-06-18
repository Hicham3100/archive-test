$(document).ready(function(){

    /* ---------------------- FETCH DOCUMENTS TB_DOCUMENTS ---------------------- */
    function fetchDocumentsDocuments() {
        const emetteur = $('#searchEmetteur').val().trim();
        const destinataire = $('#searchDestinataire').val().trim();
        const sousNature = $('#searchSousNature').val().trim();
        const annee = $('#searchAnnee').val().trim();

        if(!emetteur && !destinataire && !sousNature && !annee) {
            $('#documentsBody').html('<tr><td colspan="12" class="text-center">Aucun document</td></tr>');
            return;
        }

        $.ajax({
            url: site_url+'archive/search_documents_ajax',
            method: "POST",
            data: { emetteur, destinataire, sous_nature: sousNature, annee },
            dataType: "json",
            success: function(response) {
                const data = Array.isArray(response) ? response : (response.data || []);
                let tbody = '';
                if(data && data.length > 0){
                    data.forEach(doc => {
                        tbody += `<tr>
                            <td>${doc.numero_boite || ''}</td>
                            <td>${doc.emetteur}</td>
                            <td>${doc.destinataire}</td>
                            <td>${doc.nature_document}</td>
                            <td>${doc.sous_nature || ''}</td>
                            <td>${doc.structure_dam || ''}</td>
                            <td>${doc.structure_externe || ''}</td>
                            <td>${doc.reference || ''}</td>
                            <td>${doc.date_creation || ''}</td>
                            <td>${doc.type_document || ''}</td>
                            <td>${doc.description || ''}</td>
                            <td>
                                <button class="btn btn-sm btn-success btn-validate" data-id="${doc.id}">
                                    Valider
                                </button>
                            </td>
                        </tr>`;
                    });
                } else {
                    tbody = '<tr><td colspan="12" class="text-center">Aucun document trouvé</td></tr>';
                }
                $('#documentsBody').html(tbody);
            },
            error: function(xhr, status, error){
                console.error("Erreur AJAX TB_DOCUMENTS:", error);
                $('#documentsBody').html('<tr><td colspan="12" class="text-center text-danger">Erreur lors de la récupération des documents</td></tr>');
            }
        });
    }

    /* ---------------------- FETCH DOCUMENTS TB_ARCHIVES ---------------------- */
    function fetchDocumentsArchives() {
        const numero_boite = $('#searchnumeroboite').val().trim();
        const emetteur = $('#searchEmetteur').val().trim();
        const destinataire = $('#searchDestinataire').val().trim();
        const sousNature = $('#searchSousNature').val().trim();
        const annee = $('#searchAnnee').val().trim();

        if(!numero_boite && !emetteur && !destinataire && !sousNature && !annee) {
            $('#documentsBody').html('<tr><td colspan="12" class="text-center">Aucun document</td></tr>');
            return;
        }

        $.ajax({
            url: site_url+'archive/search_archives_ajax',
            method: "POST",
            data: { numero_boite, emetteur, destinataire, sous_nature: sousNature, annee },
            dataType: "json",
            success: function(response) {
                const data = Array.isArray(response) ? response : (response.data || []);
                let tbody = '';
                if(data && data.length > 0){
                    data.forEach(doc => {
                        tbody += `<tr>
                            <td>${doc.numero_boite}</td>
                            <td>${doc.emetteur}</td>
                            <td>${doc.destinataire}</td>
                            <td>${doc.nature_document}</td>
                            <td>${doc.sous_nature || ''}</td>
                            <td>${doc.structure_dam || ''}</td>
                            <td>${doc.structure_externe || ''}</td>
                            <td>${doc.reference || ''}</td>
                            <td>${doc.date_creation || ''}</td>
                            <td>${doc.type_document || ''}</td>
                            <td>${doc.description || ''}</td>
                            <td>${doc.fichier ? archivePdfLink(doc.fichier) : 'N/A'}</td>
                        </tr>`;
                    });
                } else {
                    tbody = '<tr><td colspan="12" class="text-center">Aucun document trouvé</td></tr>';
                }
                $('#documentsBody').html(tbody);
            },
            error: function(xhr, status, error){
                console.error("Erreur AJAX TB_ARCHIVES:", error);
                $('#documentsBody').html('<tr><td colspan="12" class="text-center text-danger">Erreur lors de la récupération des documents</td></tr>');
            }
        });
    }

    /* ---------------------- RECHERCHE EN TEMPS RÉEL ---------------------- */
    $('#searchEmetteur, #searchDestinataire, #searchSousNature, #searchAnnee').on('input', fetchDocumentsDocuments);
    $('#searchnumeroboite, #searchEmetteur, #searchDestinataire, #searchSousNature, #searchAnnee').on('input', fetchDocumentsArchives);

    // Initialisation du tableau vide
    $('#documentsBody').html('<tr><td colspan="12" class="text-center">Aucun document</td></tr>');

    /* ---------------------- MODAL VALIDATION ---------------------- */
    $(document).on('click', '.btn-validate', function () {
        $('#document_id').val($(this).data('id'));
        var modal = new bootstrap.Modal(document.getElementById('validateModal'));
        modal.show();
    });

    $('#validateForm').on('submit', function(e){
        e.preventDefault();
        let formData = new FormData(this);

        $.ajax({
            url: site_url+'archive/validate_document',
            type: "POST",
            data: formData,
            contentType: false,
            processData: false,
            dataType: "json",
            success: function(res){
                if(res.status){
                    $('#validateModal').modal('hide');
                    fetchDocumentsDocuments();
                    fetchDocumentsArchives();
                } else {
                    alert(res.message);
                }
            },
            error: function(){
                alert("Erreur lors de la validation");
            }
        });
    });

    /* ---------------------- FONCTIONS TOGGLE ---------------------- */
    window.toggleDAM = function() {
        const nature = document.getElementById('nature_document').value;
        const sousNature = document.getElementById('sous_nature');
        const structureDAM = document.getElementById('structure_dam');
        const structureExterne = document.getElementById('structure_externe');

        if (nature === 'Interne') {
            sousNature.disabled = false;
            structureExterne.disabled = true;
            structureExterne.value = '';
        } else if (nature === 'Externe') {
            sousNature.disabled = true;
            sousNature.value = '';
            structureDAM.disabled = true;
            structureDAM.value = '';
            structureExterne.disabled = false;
        } else {
            sousNature.disabled = true;
            sousNature.value = '';
            structureDAM.disabled = true;
            structureDAM.value = '';
            structureExterne.disabled = true;
            structureExterne.value = '';
        }
    }

    window.toggleStructureDAM = function() {
        const sousNature = document.getElementById('sous_nature').value;
        const structureDAM = document.getElementById('structure_dam');

        if (sousNature === 'D.A.M') {
            structureDAM.disabled = false;
        } else {
            structureDAM.disabled = true;
            structureDAM.value = '';
        }
    }

    /* ---------------------- Mise à jour le role des utilisateurs ---------------------- */
	$(document).on('change', '.user-role', function () {
		const username = $(this).data('username');
		const role = $(this).val();

		$.ajax({
			url: site_url + 'archive/update_user_role',
			type: 'POST',
			dataType: 'json',
			data: { username: username, role: role },
			success: function (res) {
				if (!res.status) {
					alert(res.message || 'Erreur lors de la mise à jour du rôle');
				}
			},
			error: function () {
				alert('Erreur serveur (role)');
			}
		});
	});

    /* ---------------------- Mise à jour le status des utilisateurs ---------------------- */
	$(document).on('change', '.user-status', function () {
		const username = $(this).data('username');
		const status = $(this).val();

		$.ajax({
			url: site_url + 'archive/update_user_status',
			type: 'POST',
			dataType: 'json',
			data: { username: username, status: status },
			success: function (res) {
				if (!res.status) {
					alert(res.message || 'Erreur lors de la mise à jour du statut');
				}
			},
			error: function () {
				alert('Erreur serveur (status)');
			}
		});
	});

    /* ---------------------- L'affichage des erreurs d'ajout des utilisateurs ---------------------- */
	// Ouverture automatique du modal si alert présent
    if ($('.alert').length > 0) {
        var modal = new bootstrap.Modal(document.getElementById('adduser'));
        modal.show();
    }
	
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
                        modal.hide();
                    }, 1500);
                }
            },
            error: function(){
                form.find('.modal-body').prepend('<div class="alert alert-danger" role="alert">Erreur serveur</div>');
            }
        });
    });

    // --- Si tu veux que le modal s'ouvre automatiquement si des alertes existent ---
    if ($('.alert').length > 0) {
        var modal = new bootstrap.Modal(document.getElementById('profileModal'));
        modal.show();
    }

});

function archivePdfLink(path) {
    const parts = String(path || '').split('/');
    if (parts.length < 4) return 'N/A';
    const url = site_url + 'archive/view_pdf/' + encodeURIComponent(parts[2]) + '/' + encodeURIComponent(parts[3]);
    return `<a href='${url}' target='_blank'><center><img src='${base_url}assets/pics/pdf.png' height='23px'></center></a>`;
}
