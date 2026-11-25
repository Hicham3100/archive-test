<div class="container-fluid py-3">
    <?php if ($this->session->flashdata('success')): ?>
        <div class="alert alert-success"><?= html_escape($this->session->flashdata('success')) ?></div>
    <?php endif; ?>

    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Plan de classement</h4>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-primary" href="<?= site_url('classification/export_json?fonds_id='.(int)($selected_fonds['id'] ?? 0)) ?>">Exporter JSON</a>
            <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#classificationNodeModal">Ajouter un nœud</button>
        </div>
    </div>

    <form method="get" action="<?= site_url('classification/index') ?>" class="mb-3">
        <label class="form-label">Fonds</label>
        <select name="fonds_id" class="form-select" onchange="this.form.submit()">
            <?php foreach ($fonds as $fond): ?>
                <option value="<?= (int)$fond['id'] ?>" <?= (isset($selected_fonds['id']) && (int)$selected_fonds['id'] === (int)$fond['id']) ? 'selected' : '' ?>><?= html_escape($fond['code'].' — '.$fond['label']) ?></option>
            <?php endforeach; ?>
        </select>
    </form>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold">Arborescence</div>
                <div class="card-body" id="classificationTree">
                    <?php
                    $render = function($nodes, $depth = 0) use (&$render) {
                        if (empty($nodes)) echo '<p class="text-muted mb-0">Aucun nœud.</p>';
                        echo '<ul class="classification-list list-unstyled ms-'.min($depth * 2, 6).'">';
                        foreach ($nodes as $node) {
                            echo '<li class="classification-node border rounded p-2 mb-2" draggable="true" data-node-id="'.(int)$node['id'].'" data-parent-id="'.(int)($node['parent_id'] ?: 0).'">';
                            echo '<span class="fw-semibold">'.html_escape($node['libelle']).'</span>';
                            if (!empty($node['cote'])) echo ' <code>'.html_escape($node['cote']).'</code>';
                            echo ' <small class="text-muted">'.html_escape($node['type']).'</small>';
                            echo ' <button type="button" class="btn btn-sm btn-outline-secondary float-end move-node">Déplacer</button>';
                            $render($node['children'], $depth + 1);
                            echo '</li>';
                        }
                        echo '</ul>';
                    };
                    $render($tree);
                    ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold">Import JSON transactionnel</div>
                <div class="card-body">
                    <form method="post" action="<?= site_url('classification/import_json') ?>" enctype="multipart/form-data">
                        <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
                        <input type="hidden" name="fonds_id" value="<?= (int)($selected_fonds['id'] ?? 0) ?>">
                        <input type="file" name="plan_file" accept="application/json,.json" class="form-control mb-2" required>
                        <button type="submit" class="btn btn-warning w-100" onclick="return confirm('Le plan actuel sera remplacé. Continuer ?')">Remplacer le plan</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="classificationNodeModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <form method="post" action="<?= site_url('classification/create_node') ?>">
                <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
                <input type="hidden" name="fonds_id" value="<?= (int)($selected_fonds['id'] ?? 0) ?>">
                <div class="modal-content">
                    <div class="modal-header"><h5 class="modal-title">Nouveau nœud</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body row g-3">
                        <div class="col-md-6"><label class="form-label">Parent</label><input type="number" name="parent_id" class="form-control" value="0" min="0"></div>
                        <div class="col-md-6"><label class="form-label">Type</label><select name="type" class="form-select"><option value="sous_fonds">Sous-fonds</option><option value="serie">Série</option><option value="sous_serie">Sous-série</option><option value="dossier">Dossier</option><option value="piece">Pièce</option></select></div>
                        <div class="col-md-6"><label class="form-label">Libellé</label><input type="text" name="libelle" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label">Cote</label><input type="text" name="cote" class="form-control"></div>
                        <div class="col-md-4"><label class="form-label">Ordre</label><input type="number" name="ordre" class="form-control" value="0" min="0"></div>
                        <div class="col-md-4"><label class="form-label">Classement</label><select name="sort_type" class="form-select"><option value="manuel">Manuel</option><option value="chronologique">Chronologique</option><option value="alphabetique">Alphabétique</option><option value="numerique">Numérique</option><option value="thematique">Thématique</option></select></div>
                        <div class="col-md-4"><label class="form-label">Service</label><input type="text" name="service_id" class="form-control"></div>
                        <div class="col-12"><label class="form-label">Métadonnées obligatoires (JSON)</label><textarea name="metadata_schema" class="form-control" rows="3" placeholder='["date","reference"]'></textarea></div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button><button type="submit" class="btn btn-primary">Créer</button></div>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="classificationMoveModal" tabindex="-1">
        <div class="modal-dialog">
            <form id="classificationMoveForm">
                <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
                <input type="hidden" name="node_id" id="moveNodeId">
                <div class="modal-content">
                    <div class="modal-header"><h5 class="modal-title">Déplacer le nœud</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body"><label class="form-label">ID du nouveau parent (0 = racine)</label><input type="number" name="parent_id" id="moveParentId" class="form-control" min="0" value="0"><label class="form-label mt-2">Ordre</label><input type="number" name="ordre" class="form-control" min="0" value="0"><label class="form-label mt-2">Motif</label><input type="text" name="reason" class="form-control" required></div>
                    <div class="modal-footer"><button type="submit" class="btn btn-primary">Déplacer</button></div>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
(function(){
    document.querySelectorAll('.move-node').forEach(function(btn){
        btn.addEventListener('click', function(){
            var li=btn.closest('.classification-node');
            document.getElementById('moveNodeId').value=li.dataset.nodeId;
            document.getElementById('moveParentId').value='0';
            new bootstrap.Modal(document.getElementById('classificationMoveModal')).show();
        });
    });
    document.querySelectorAll('.classification-node').forEach(function(li){
        li.addEventListener('dragstart',function(e){e.dataTransfer.setData('text/plain',li.dataset.nodeId);});
        li.addEventListener('dragover',function(e){e.preventDefault();});
        li.addEventListener('drop',function(e){
            e.preventDefault();
            var nodeId=e.dataTransfer.getData('text/plain');
            var reason=prompt('Motif du déplacement');
            if(!reason)return;
            var form=new FormData();
            form.append('node_id',nodeId); form.append('parent_id',li.dataset.nodeId); form.append('ordre','0'); form.append('reason',reason);
            form.append("<?= $this->security->get_csrf_token_name() ?>","<?= $this->security->get_csrf_hash() ?>");
            fetch("<?= site_url('classification/move_node') ?>",{method:'POST',body:form,credentials:'same-origin'}).then(r=>r.json()).then(r=>{alert(r.message); if(r.status)location.reload();});
        });
    });
})();
</script>
