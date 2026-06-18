<main class="page-content">
<div class="container mt-3">
    <h1>📝 Journal d'activité</h1>
    <p class="text-muted">Historique complet des actions effectuées par les utilisateurs.</p>
</div>

<div class="container-fluid">
    <div class="row mb-3">
        <div class="col-md-8">
            <a href="<?= site_url('archive/activity_log') ?>" class="btn btn-outline-primary">
                <i class="bi bi-arrow-clockwise"></i> Actualiser
            </a>
        </div>
        <?php if ($this->session->userdata('role') == 1): ?>
        <div class="col-md-4 text-end">
            <a href="<?= site_url('archive/export_activity_csv') ?>" class="btn btn-outline-success">
                <i class="bi bi-download"></i> Exporter CSV
            </a>
        </div>
        <?php endif; ?>
    </div>

    <div class="table-responsive">
        <table class="table table-striped table-bordered table-hover">
            <thead class="table-dark">
                <tr>
                    <th>ID</th><th>Utilisateur</th><th>Action</th><th>Description</th><th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($logs)) : ?>
                    <?php foreach ($logs as $log) : ?>
                        <tr>
                            <td><?= (int) $log['id'] ?></td>
                            <td><strong><?= html_escape($log['username']) ?></strong></td>
                            <td><?= html_escape($log['action']) ?></td>
                            <td><?= html_escape($log['description']) ?></td>
                            <td><?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr><td colspan="5" class="text-center">Aucune activité enregistrée.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</main>
