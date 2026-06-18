<main class="page-content">
<div class="container-fluid py-2">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-3">
        <div><h4 class="mb-0"><i class="bi bi-speedometer2 me-2"></i>Tableau de bord</h4><p class="text-muted small">Vue d'ensemble</p></div>
        <div class="btn-group btn-group-sm mt-2 mt-md-0">
            <a href="?periode=jour" class="btn btn-sm <?= $periode == 'jour' ? 'btn-primary' : 'btn-outline-primary' ?>">Jour</a>
            <a href="?periode=semaine" class="btn btn-sm <?= $periode == 'semaine' ? 'btn-primary' : 'btn-outline-primary' ?>">7j</a>
            <a href="?periode=mois" class="btn btn-sm <?= $periode == 'mois' ? 'btn-primary' : 'btn-outline-primary' ?>">30j</a>
            <a href="?periode=annee" class="btn btn-sm <?= $periode == 'annee' ? 'btn-primary' : 'btn-outline-primary' ?>">Année</a>
        </div>
    </div>

    <div class="row g-2 mb-3">
        <div class="col-6 col-md-3"><div class="card border-0 shadow-sm text-center p-2"><i class="bi bi-file-earmark-text fs-1 text-primary"></i><h5 class="mt-1 mb-0"><?= $total_documents ?></h5><small>Documents</small></div></div>
        <div class="col-6 col-md-3"><div class="card border-0 shadow-sm text-center p-2"><i class="bi bi-archive fs-1 text-success"></i><h5 class="mt-1 mb-0"><?= $total_archives ?></h5><small>Archives</small></div></div>
        <div class="col-6 col-md-3"><div class="card border-0 shadow-sm text-center p-2"><i class="bi bi-people fs-1 text-info"></i><h5 class="mt-1 mb-0"><?= $total_personnel ?></h5><small>Personnels</small></div></div>
        <div class="col-6 col-md-3"><div class="card border-0 shadow-sm text-center p-2"><i class="bi bi-person-gear fs-1 text-warning"></i><h5 class="mt-1 mb-0"><?= $total_users ?></h5><small>Utilisateurs</small></div></div>
    </div>

    <div class="row g-2 mb-3">
        <div class="col-12 col-lg-6"><div class="card border-0 shadow-sm"><div class="card-header bg-white fw-bold small">Évolution mensuelle</div><div class="card-body"><div style="height:220px;"><canvas id="evolutionChart"></canvas></div></div></div></div>
        <div class="col-12 col-lg-6"><div class="card border-0 shadow-sm"><div class="card-header bg-white fw-bold small">Répartition par structure</div><div class="card-body"><div style="height:220px;"><canvas id="structureChart"></canvas></div></div></div></div>
    </div>

    <div class="row g-2 mb-3">
        <div class="col-12 col-lg-6"><div class="card border-0 shadow-sm"><div class="card-header bg-white fw-bold small">Top 5 utilisateurs actifs (<?= $periode ?>)</div><div class="card-body p-0"><div class="table-responsive"><table class="table table-sm mb-0"><?php if(!empty($top_users)): foreach($top_users as $u): ?><tr><td><?= html_escape($u['username']) ?></td><td><?= $u['total'] ?></td></tr><?php endforeach; else: ?><tr><td colspan="2" class="text-center">Aucune activité</td></tr><?php endif; ?></table></div></div></div></div>
        <div class="col-12 col-lg-6"><div class="card border-0 shadow-sm"><div class="card-header bg-white fw-bold small">Consultations par utilisateur (<?= $periode ?>)</div><div class="card-body p-0"><div class="table-responsive"><table class="table table-sm mb-0"><?php if(!empty($consultations_par_user)): foreach($consultations_par_user as $c): ?><tr><td><?= html_escape($c['username']) ?></td><td><?= $c['total'] ?></td></tr><?php endforeach; else: ?><tr><td colspan="2" class="text-center">Aucune consultation</td></tr><?php endif; ?></table></div></div></div></div>
    </div>

    <div class="card border-0 shadow-sm"><div class="card-header bg-white fw-bold small">Dernières activités</div><div class="card-body p-0"><div class="table-responsive" style="max-height:250px; overflow-y:auto;"><table class="table table-sm mb-0"><thead class="table-light"><tr><th>Utilisateur</th><th>Action</th><th>Description</th><th>Date</th></tr></thead><tbody><?php if(!empty($dernieres_activites)): foreach($dernieres_activites as $log): ?><tr><td><?= html_escape($log['username']) ?></td><td><?= html_escape($log['action']) ?></td><td><?= html_escape($log['description']) ?></td><td><?= date('d/m/Y H:i', strtotime($log['created_at'])) ?></td></tr><?php endforeach; else: ?><tr><td colspan="4" class="text-center">Aucune activité récente</td></tr><?php endif; ?></tbody></table></div></div></div>
</div>
</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const evoData = <?= json_encode($evolution_docs) ?>;
    const evoCtx = document.getElementById('evolutionChart')?.getContext('2d');
    if(evoCtx) new Chart(evoCtx, { type:'line', data:{ labels:evoData.map(i=>i.mois), datasets:[{ label:'Documents', data:evoData.map(i=>i.total), borderColor:'#0d6efd', backgroundColor:'rgba(13,110,253,0.1)', fill:true, tension:0.3 }] }, options:{ responsive:true, maintainAspectRatio:true, plugins:{ legend:{ position:'top' } } } });
    const structData = <?= json_encode($repartition_structure) ?>;
    const structCtx = document.getElementById('structureChart')?.getContext('2d');
    if(structCtx) new Chart(structCtx, { type:'pie', data:{ labels:structData.map(i=>i.structure_dam||'Non défini'), datasets:[{ data:structData.map(i=>i.total), backgroundColor:['#0d6efd','#198754','#ffc107','#dc3545','#6f42c1','#fd7e14','#20c997'] }] }, options:{ responsive:true, maintainAspectRatio:true, plugins:{ legend:{ position:'right', labels:{ boxWidth:10, font:{ size:10 } } } } } });
});
</script>