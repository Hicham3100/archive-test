<main class="page-content">
<div class="container mt-5">
    <h1>🗑️ Corbeille</h1>
    <p class="text-muted">Éléments supprimés (conservés 30 jours)</p>
</div>

<div class="container-fluid">
    <?php if ($this->session->flashdata('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= $this->session->flashdata('error') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($this->session->flashdata('success')): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= $this->session->flashdata('success') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="table table-striped table-bordered table-hover">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Type</th>
                    <th>Nom</th>
                    <th>Supprimé par</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($items)) : ?>
                    <?php foreach ($items as $item) : 
                        $data = json_decode($item['data'], true);
                        $name = '';
                        $typeLabel = '';
                        if ($item['table_name'] == 'tb_authentification') {
                            $name = $data['username'] ?? 'Utilisateur inconnu';
                            $typeLabel = '👤 Utilisateur';
                            $badgeClass = 'bg-primary';
                        } elseif ($item['table_name'] == 'tb_personnel_sortant') {
                            $name = ($data['nom'] ?? '') . ' ' . ($data['prenom'] ?? '');
                            $typeLabel = '👥 Personnel';
                            $badgeClass = 'bg-warning text-dark';
                        } elseif ($item['table_name'] == 'tb_documents') {
                            $name = $data['numero_boite'] ?? 'Document';
                            $typeLabel = '📄 Document';
                            $badgeClass = 'bg-info text-dark';
                        } elseif ($item['table_name'] == 'tb_archives') {
                            $name = $data['numero_boite'] ?? 'Archive';
                            $typeLabel = '📦 Archive';
                            $badgeClass = 'bg-success';
                        }
                    ?>
                        <tr>
                            <td><?= $item['id'] ?></td>
                            <td><span class="badge <?= $badgeClass ?>"><?= $typeLabel ?></span></td>
                            <td><strong><?= htmlspecialchars($name) ?></strong></td>
                            <td><?= htmlspecialchars($item['deleted_by']) ?></td>
                            <td><small><?= date('d/m/Y H:i', strtotime($item['deleted_at'])) ?></small></td>
                            <td>
                                <a href="<?= site_url('archive/restore/' . $item['id']) ?>" 
                                   class="btn btn-sm btn-success me-1" 
                                   title="Restaurer cet élément">
                                    <i class="bi bi-arrow-counterclockwise"></i> Restaurer
                                </a>
                                <a href="<?= site_url('archive/delete_forever/' . $item['id']) ?>" 
                                   class="btn btn-sm btn-danger" 
                                   onclick="return confirm('Supprimer définitivement ? Cette action est irréversible.')"
                                   title="Supprimer définitivement">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="6" class="text-center py-4">
                            <i class="bi bi-trash text-muted" style="font-size: 2rem;"></i>
                            <p class="mt-2 text-muted">La corbeille est vide.</p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</main>