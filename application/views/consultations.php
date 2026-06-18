<main class="page-content">
<div class="container mt-3">
    <h1>📋 Journal de consultation des utilisateurs</h1>
    <p class="text-muted">Historique de toutes les consultations de documents</p>
</div>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="btn-group">
                <a href="?periode=jour" class="btn btn-<?= $periode == 'jour' ? 'primary' : 'outline-primary' ?>">Aujourd'hui</a>
                <a href="?periode=semaine" class="btn btn-<?= $periode == 'semaine' ? 'primary' : 'outline-primary' ?>">7 jours</a>
                <a href="?periode=mois" class="btn btn-<?= $periode == 'mois' ? 'primary' : 'outline-primary' ?>">30 jours</a>
                <a href="?periode=annee" class="btn btn-<?= $periode == 'annee' ? 'primary' : 'outline-primary' ?>">Année</a>
            </div>
        </div>
        <?php if ($this->session->userdata('role') == 1): ?>
        <div class="col-md-4 text-end">
            <a href="<?= site_url('archive/export_consultations_csv?periode=' . rawurlencode($periode)) ?>" class="btn btn-outline-success">
                <i class="bi bi-download"></i> Exporter CSV
            </a>
        </div>
        <?php endif; ?>
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header"><h5>📊 Statistiques (<?= $periode ?>)</h5></div>
                <div class="card-body">
                    <table class="table table-sm">
                        <thead><tr><th>Utilisateur</th><th>Type</th><th>Total</th></tr></thead>
                        <tbody>
                            <?php if (!empty($stats)): ?>
                                <?php foreach ($stats as $s): ?>
                                <tr>
                                    <td><?= html_escape($s['username']) ?></td>
                                    <td><span class="badge bg-<?= $s['document_type']=='Archives'?'primary':'warning' ?>"><?= html_escape($s['document_type']) ?></span></td>
                                    <td><span class="badge bg-info"><?= (int) $s['total'] ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="3" class="text-center">Aucune statistique</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header"><h5>🕐 Journal détaillé (<?= $periode ?>)</h5></div>
                <div class="card-body" style="max-height: 600px; overflow-y: auto;">
                    <table class="table table-sm table-striped">
                        <thead><tr><th>Utilisateur</th><th>Type</th><th>Document</th><th>Date</th></tr></thead>
                        <tbody>
                            <?php if (!empty($logs)): ?>
                                <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><strong><?= html_escape($log['username']) ?></strong></td>
                                    <td><span class="badge bg-<?= $log['document_type']=='Archives'?'primary':'warning' ?>"><?= html_escape($log['document_type']) ?></span></td>
                                    <td><small><?= html_escape(basename($log['fichier'])) ?></small></td>
                                    <td><small><?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?></small></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="text-center">Aucune consultation</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
</main>
