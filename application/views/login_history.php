<main class="page-content">
<div class="container mt-5">
    <h1>🔐 Historique des connexions</h1>
    <p class="text-muted">Dernières connexions des utilisateurs</p>
</div>

<div class="container-fluid">
    <?php if ($this->session->userdata('role') == 1): ?>
    <div class="row mb-3">
        <div class="col-md-12 text-end">
            <a href="<?= site_url('archive/export_logins_csv') ?>" class="btn btn-outline-success">
                <i class="bi bi-download"></i> Exporter CSV
            </a>
        </div>
    </div>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="table table-striped table-bordered table-hover">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Utilisateur</th>
                    <th>Adresse IP</th>
                    <th>Statut</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($logins)) : ?>
                    <?php foreach ($logins as $log) : ?>
                        <tr class="<?= $log['status'] == 'failed' ? 'table-danger' : ($log['status'] == 'disabled' ? 'table-warning' : '') ?>">
                            <td><?= (int) $log['id'] ?></td>
                            <td><strong><?= html_escape($log['username']) ?></strong></td>
                            <td><code><?= html_escape($log['ip_address']) ?></code></td>
                            <td>
                                <?php if ($log['status'] == 'success'): ?>
                                    <span class="badge bg-success">✅ Réussie</span>
                                <?php elseif ($log['status'] == 'failed'): ?>
                                    <span class="badge bg-danger">❌ Échouée</span>
                                <?php else: ?>
                                    <span class="badge bg-warning">⚠️ Désactivé</span>
                                <?php endif; ?>
                            </td>
                            <td><?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr><td colspan="5" class="text-center">Aucune connexion enregistrée.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</main>
