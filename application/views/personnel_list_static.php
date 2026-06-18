<main class="page-content">
<div class="container-fluid mt-2">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h4 class="mb-0">👥 Dossiers du personnel sortant</h4>
        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#personnelModal" onclick="prepareAdd()"><i class="bi bi-plus"></i> Ajouter</button>
    </div>

    <!-- Alertes flash -->
    <?php if ($this->session->flashdata('success')): ?>
        <div class="alert alert-success alert-dismissible fade show py-2 mb-2" role="alert">
            <?= $this->session->flashdata('success') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($this->session->flashdata('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show py-2 mb-2" role="alert">
            <?= $this->session->flashdata('error') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Recherche en mode GET (Server-side) -->
    <div class="mb-2">
        <form method="get" action="<?= site_url('personnel/index') ?>">
            <div class="input-group input-group-sm">
                <input type="text" name="search" class="form-control" placeholder="Rechercher par nom ou prénom" value="<?= html_escape($this->input->get('search')) ?>">
                <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i> Rechercher</button>
                <?php if($this->input->get('search')): ?>
                <a href="<?= site_url('personnel/index') ?>" class="btn btn-outline-danger"><i class="bi bi-x"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-sm table-striped table-bordered">
            <thead class="table-dark"><tr><th>Nom</th><th>Prénom</th><th>N° boîte</th><th>Fichiers</th><th>Actions</th></tr></thead>
            <tbody>
                <?php if(!empty($personnel)): ?>
                    <?php foreach($personnel as $p): ?>
                        <tr>
                            <td><?= html_escape($p['nom']) ?></td>
                            <td><?= html_escape($p['prenom']) ?></td>
                            <td><?= html_escape($p['numero_boite']) ?></td>
                            <td>
                                <?php
                                $files_html = '-';
                                if (!empty($p['fichiers'])) {
                                    $files = json_decode($p['fichiers'], true);
                                    if (is_array($files)) {
                                        $files_html = '';
                                        foreach ($files as $i => $f) {
                                            $files_html .= '<a href="' . site_url('archive/serve_personnel_file?file='.urlencode($f)) . '" target="_blank" class="me-1"><i class="bi bi-file-earmark-pdf"></i> Voir '.($i+1).'</a>';
                                        }
                                    } else {
                                        $files_html = '<a href="' . site_url('archive/serve_personnel_file?file='.urlencode($p['fichiers'])) . '" target="_blank"><i class="bi bi-file-earmark-pdf"></i> Voir</a>';
                                    }
                                } elseif (!empty($p['fichier'])) {
                                    $files_html = '<a href="' . site_url('archive/serve_personnel_file?file='.urlencode($p['fichier'])) . '" target="_blank"><i class="bi bi-file-earmark-pdf"></i> Voir</a>';
                                }
                                echo $files_html;
                                ?>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-warning" onclick="editPersonnel(<?= $p['id'] ?>)"><i class="bi bi-pencil"></i></button>
                                <a href="<?= site_url('personnel/supprimer/' . $p['id']) ?>" class="btn btn-sm btn-danger" onclick="return confirm('Supprimer ce dossier ?')"><i class="bi bi-trash"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5" class="text-center">Aucun dossier</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Pagination (Server-side) -->
    <?= $pagination ?? '' ?>

</div>

<!-- Modal add/edit -->
<div class="modal fade" id="personnelModal" tabindex="-1">
    <div class="modal-dialog">
        <form id="personnelForm" enctype="multipart/form-data" method="post">
            <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Dossier</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="personnel_id">
                    <div class="mb-2"><label class="form-label small">Nom</label><input type="text" name="nom" id="nom" class="form-control form-control-sm" required></div>
                    <div class="mb-2"><label class="form-label small">Prénom</label><input type="text" name="prenom" id="prenom" class="form-control form-control-sm" required></div>
                    <div class="mb-2"><label class="form-label small">Numéro boîte</label><input type="text" name="numero_boite" id="numero_boite" class="form-control form-control-sm" required></div>
                    <div class="mb-2"><label class="form-label small">Fichiers (PDF, images)</label><input type="file" name="fichiers[]" class="form-control form-control-sm" multiple></div>
                    <div id="currentFile" class="small text-muted"></div>
                </div>
                <div class="modal-footer"><button type="submit" class="btn btn-sm btn-primary">Enregistrer</button></div>
            </div>
        </form>
    </div>
</div>

<script>
var site_url = "<?= site_url() ?>";

function prepareAdd() { 
    $('#personnelForm').attr('action', site_url+'personnel/ajouter'); 
    $('#personnel_id').val(''); 
    $('#nom,#prenom,#numero_boite').val(''); 
    $('#currentFile').text(''); 
}

function editPersonnel(id) {
    $.getJSON(site_url+'personnel/get_details/'+id, function(res){
        if(res.status=='success'){
            let p=res.data;
            $('#personnelForm').attr('action', site_url+'personnel/modifier');
            $('#personnel_id').val(p.id);
            $('#nom').val(p.nom);
            $('#prenom').val(p.prenom);
            $('#numero_boite').val(p.numero_boite);
            let displayFiles = '';
            if (p.fichiers) {
                try {
                    let fileArr = JSON.parse(p.fichiers);
                    if (Array.isArray(fileArr)) {
                        displayFiles = 'Fichier(s) actuel(s) : ' + fileArr.map(f => f.split('/').pop()).join(', ');
                    } else {
                        displayFiles = 'Fichier actuel : ' + p.fichiers.split('/').pop();
                    }
                } catch(e) {
                    displayFiles = 'Fichier actuel : ' + p.fichiers.split('/').pop();
                }
            } else if (p.fichier) {
                displayFiles = 'Fichier actuel : ' + p.fichier.split('/').pop();
            }
            $('#currentFile').text(displayFiles);
            $('#personnelModal').modal('show');
        } else {
            alert('Impossible de charger les détails du dossier.');
        }
    });
}
</script>
</main>