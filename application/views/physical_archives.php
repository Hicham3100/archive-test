<div class="container-fluid py-3">
    <?php if ($this->session->flashdata('success')): ?><div class="alert alert-success"><?=html_escape($this->session->flashdata('success'))?></div><?php endif; ?>

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Archives physiques</h4>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#locationModal">Nouvel emplacement</button>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#boxModal">Nouvelle boîte</button>
        </div>
    </div>

    <form method="get" action="<?=site_url('physical/index')?>" class="card border-0 shadow-sm mb-3 p-3">
        <div class="row g-2">
            <div class="col-md-4"><label class="form-label">Cote</label><input type="text" name="cote" value="<?=html_escape($filters['cote'])?>" class="form-control"></div>
            <div class="col-md-3"><label class="form-label">Service</label><input type="text" name="service" value="<?=html_escape($filters['service'])?>" class="form-control"></div>
            <div class="col-md-3"><label class="form-label">Localisation</label><input type="number" name="location_id" value="<?=html_escape($filters['location_id'])?>" min="1" class="form-control"></div>
            <div class="col-md-2 d-flex align-items-end"><button type="submit" class="btn btn-outline-primary w-100">Rechercher</button></div>
        </div>
    </form>

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold">Localisations</div>
                <div class="card-body">
                    <?php $render=function($tree,$depth=0) use (&$render){
                        echo '<ul class="list-unstyled '.($depth?'ms-3':'').'">';
                        foreach($tree as $location){
                            echo '<li class="mb-2"><a href="'.site_url('physical/index?location_id='.(int)$location['id']).'">'.html_escape($location['code'].' — '.$location['label']).'</a><small class="text-muted d-block">'.html_escape($location['type']).'</small>';
                            if(!empty($location['children']))$render($location['children'],$depth+1);
                            echo '</li>';
                        }
                        echo '</ul>';
                    }; $render($locations);
                    ?>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold d-flex justify-content-between"><span><?= (int)$box_count ?> résultat(s)</span><button type="button" class="btn btn-sm btn-outline-primary" id="printLabels">Imprimer QR</button></div>
                <div class="table-responsive">
                    <table class="table table-striped align-middle mb-0">
                        <thead><tr><th>Sélection</th><th>Cote</th><th>Intitulé</th><th>Type</th><th>Service</th><th>Période</th><th>Pièces</th><th>État</th><th>Localisation</th></tr></thead>
                        <tbody>
                        <?php if(empty($boxes)): ?><tr><td colspan="9" class="text-center text-muted">Aucune boîte.</td></tr><?php endif; ?>
                        <?php foreach($boxes as $box): ?>
                            <tr><td><input type="checkbox" class="box-select" value="<?= (int)$box['id'] ?>"></td><td><strong><?=html_escape($box['cote'])?></strong></td><td><?=html_escape($box['title'])?></td><td><?=html_escape($box['unit_type'])?></td><td><?=html_escape($box['producer_service'])?></td><td><?=html_escape($box['period_start'].' '.$box['period_end'])?></td><td><?= (int)$box['pieces_count'] ?></td><td><?=html_escape($box['material_state'])?></td><td><?=html_escape($box['location_code'].' — '.$box['location_label'])?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="locationModal" tabindex="-1">
        <div class="modal-dialog"><form method="post" action="<?=site_url('physical/create_location')?>">
            <input type="hidden" name="<?=$this->security->get_csrf_token_name()?>" value="<?=$this->security->get_csrf_hash()?>">
            <div class="modal-content"><div class="modal-header"><h5 class="modal-title">Nouvel emplacement</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body row g-3">
                <div class="col-md-6"><label class="form-label">Parent</label><input type="number" name="parent_id" class="form-control" value="0" min="0"></div>
                <div class="col-md-6"><label class="form-label">Type</label><select name="type" class="form-select" required><option value="site">Site</option><option value="batiment">Bâtiment</option><option value="etage">Étage</option><option value="salle">Salle</option><option value="magasin">Magasin</option><option value="travee">Travée</option><option value="rayonnage">Rayonnage</option><option value="etagere">Étagère</option></select></div>
                <div class="col-md-4"><label class="form-label">Code</label><input type="text" name="code" class="form-control" required></div>
                <div class="col-md-8"><label class="form-label">Intitulé</label><input type="text" name="label" class="form-control" required></div>
                <div class="col-12"><label class="form-label">Capacité</label><input type="number" name="capacity" class="form-control" min="1"></div>
            </div><div class="modal-footer"><button type="submit" class="btn btn-primary">Créer</button></div></div></form>
        </div>
    </div>

    <div class="modal fade" id="boxModal" tabindex="-1">
        <div class="modal-dialog modal-lg"><form method="post" action="<?=site_url('physical/create_box')?>">
            <input type="hidden" name="<?=$this->security->get_csrf_token_name()?>" value="<?=$this->security->get_csrf_hash()?>">
            <div class="modal-content"><div class="modal-header"><h5 class="modal-title">Nouvelle unité physique</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body row g-3">
                <div class="col-md-4"><label class="form-label">Cote globale</label><input type="text" name="cote" class="form-control" required></div>
                <div class="col-md-8"><label class="form-label">Intitulé</label><input type="text" name="title" class="form-control" required></div>
                <div class="col-md-4"><label class="form-label">Type</label><select name="unit_type" class="form-select"><option value="boite">Boîte</option><option value="carton">Carton</option><option value="registre">Registre</option><option value="classeur">Classeur</option></select></div>
                <div class="col-md-4"><label class="form-label">Service producteur</label><input type="text" name="producer_service" class="form-control"></div>
                <div class="col-md-4"><label class="form-label">Localisation ID</label><input type="number" name="location_id" class="form-control" min="1" required></div>
                <div class="col-md-3"><label class="form-label">Début période</label><input type="date" name="period_start" class="form-control"></div>
                <div class="col-md-3"><label class="form-label">Fin période</label><input type="date" name="period_end" class="form-control"></div>
                <div class="col-md-3"><label class="form-label">Type de document</label><input type="text" name="document_type" class="form-control"></div>
                <div class="col-md-3"><label class="form-label">Nombre de pièces</label><input type="number" name="pieces_count" class="form-control" min="0" value="0"></div>
                <div class="col-md-6"><label class="form-label">État matériel</label><select name="material_state" class="form-select"><option value="bon">Bon</option><option value="neuf">Neuf</option><option value="usage">Usagé</option><option value="fragile">Fragile</option><option value="degrade">Dégradé</option></select></div>
                <div class="col-md-6"><label class="form-label">Conservation</label><select name="conservation_status" class="form-select"><option value="non_classe">Non classé</option><option value="court">Court</option><option value="intermediaire">Intermédiaire</option><option value="permanent">Permanent</option><option value="detruit">Détruit</option></select></div>
            </div><div class="modal-footer"><button type="submit" class="btn btn-primary">Créer</button></div></div></form>
        </div>
    </div>
</div>
<script>
document.getElementById('printLabels').addEventListener('click',function(){const ids=[...document.querySelectorAll('.box-select:checked')].map(x=>x.value);if(!ids.length){alert('Sélectionnez au moins une boîte');return;}window.open('<?=site_url('physical/labels_pdf')?>?ids='+encodeURIComponent(ids.join(',')));});
</script>
