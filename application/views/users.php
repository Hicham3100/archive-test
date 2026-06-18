<main class="page-content">
<div class="container-fluid mt-2">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h4 class="mb-0">👥 Gestion des utilisateurs</h4>
        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#userModal" onclick="prepareAdd()">
            <i class="bi bi-plus"></i> Ajouter
        </button>
    </div>

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

    <div class="table-responsive">
        <table class="table table-sm table-striped table-bordered">
            <thead class="table-dark">
                <tr>
                    <th>Nom</th><th>Prénom</th><th>Username</th>
                    <th>Grade</th><th>Structures</th><th>Statut</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= html_escape($u['lname']) ?></td>
                    <td><?= html_escape($u['fname']) ?></td>
                    <td><?= html_escape($u['username']) ?></td>
                    <td>
                        <?php
                        $badge_class = match($u['grade'] ?? '') {
                            'Administrateur' => 'bg-danger',
                            'Gestionnaire'   => 'bg-primary',
                            'Utilisateur'    => 'bg-secondary',
                            default          => 'bg-dark',
                        };
                        ?>
                        <span class="badge <?= $badge_class ?>"><?= html_escape($u['grade'] ?? '—') ?></span>
                    </td>
                    <td><small class="text-muted"><?= html_escape($u['structures'] ?? '—') ?></small></td>
                    <td>
                        <span class="badge <?= $u['status'] == 'enabled' ? 'bg-success' : 'bg-secondary' ?>">
                            <?= $u['status'] == 'enabled' ? 'Actif' : 'Inactif' ?>
                        </span>
                    </td>
                    <td class="text-nowrap">
                        <button class="btn btn-sm btn-warning" onclick="editUser('<?= html_escape($u['username']) ?>')" title="Modifier">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <a href="<?= site_url('users/toggle_status/' . $u['username']) ?>"
                           class="btn btn-sm btn-info" title="Activer/Désactiver">
                            <i class="bi bi-power"></i>
                        </a>
                        <?php if ($u['username'] !== $this->session->userdata('username')): ?>
                        <a href="<?= site_url('users/delete/' . $u['username']) ?>"
                           class="btn btn-sm btn-danger"
                           onclick="return confirm('Déplacer <?= html_escape($u['username']) ?> dans la corbeille ?')"
                           title="Supprimer">
                            <i class="bi bi-trash"></i>
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?= $pagination ?? '' ?>
</div>

<!-- ============================================================
     Modal Ajout / Édition utilisateur
     Structures : cases à cocher (format CSV en BD)
     Grade      : Administrateur | Gestionnaire | Utilisateur
     ============================================================ -->
<div class="modal fade" id="userModal" tabindex="-1" aria-labelledby="userModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="post" id="userForm" action="<?= site_url('users/add') ?>">
            <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
            <input type="hidden" name="original_username" id="original_username">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="userModalLabel">Utilisateur</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2">

                        <!-- Nom -->
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Nom <span class="text-danger">*</span></label>
                            <input type="text" name="lname" id="lname" class="form-control form-control-sm" required>
                        </div>

                        <!-- Prénom -->
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Prénom <span class="text-danger">*</span></label>
                            <input type="text" name="fname" id="fname" class="form-control form-control-sm" required>
                        </div>

                        <!-- Username -->
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Username <span class="text-danger">*</span></label>
                            <input type="text" name="username" id="username" class="form-control form-control-sm" required autocomplete="off">
                        </div>

                        <!-- Mot de passe -->
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Mot de passe <small class="text-muted">(laisser vide pour ne pas changer)</small></label>
                            <input type="password" name="password" id="password" class="form-control form-control-sm" autocomplete="new-password">
                        </div>

                        <!-- Grade — GRADES-01 : 3 niveaux -->
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Grade <span class="text-danger">*</span></label>
                            <select name="grade" id="grade" class="form-select form-select-sm">
                                <option value="Administrateur">Administrateur — Accès complet</option>
                                <option value="Gestionnaire" selected>Gestionnaire — Consultation + Ajout/Modification</option>
                                <option value="Utilisateur">Utilisateur — Consultation uniquement</option>
                            </select>
                            <div id="gradeHelp" class="form-text small text-muted mt-1"></div>
                        </div>

                        <!-- Statut -->
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Statut</label>
                            <select name="status" id="status" class="form-select form-select-sm">
                                <option value="enabled">Activé</option>
                                <option value="disabled">Désactivé</option>
                            </select>
                        </div>

                        <!-- Structures — cases à cocher (format original) -->
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Structures</label>
                            <div class="border rounded p-2 bg-light">
                                <?php
                                $all_structures = [
                                    'Direction', 'SRH', 'SFC', 'SMG', 'DAM',
                                    'DIDA', 'DSPP', 'DRI', 'DSIRS', 'DGS', 'DOT', 'DEGP'
                                ];
                                ?>
                                <div class="row g-1">
                                    <?php foreach ($all_structures as $struct): ?>
                                    <div class="col-6 col-sm-4 col-md-3">
                                        <div class="form-check form-check-sm">
                                            <input class="form-check-input structure-cb" type="checkbox"
                                                   name="structures[]"
                                                   value="<?= $struct ?>"
                                                   id="struct_<?= $struct ?>">
                                            <label class="form-check-label small" for="struct_<?= $struct ?>">
                                                <?= $struct ?>
                                            </label>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="mt-1">
                                    <button type="button" class="btn btn-xs btn-outline-secondary btn-sm py-0 px-2 me-1" onclick="toggleAllStructures(true)">Tout cocher</button>
                                    <button type="button" class="btn btn-xs btn-outline-secondary btn-sm py-0 px-2" onclick="toggleAllStructures(false)">Tout décocher</button>
                                </div>
                            </div>
                            <div class="form-text small text-muted">Sélectionner les structures auxquelles l'utilisateur a accès.</div>
                        </div>

                    </div><!-- /row -->
                </div><!-- /modal-body -->
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="bi bi-save me-1"></i>Enregistrer
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
/* ------------------------------------------------------------------ */
/* Descriptions des grades pour l'aide contextuelle                    */
/* ------------------------------------------------------------------ */
const gradeDescriptions = {
    'Administrateur': 'Accès complet : gestion des utilisateurs, administration, archives, personnel.',
    'Gestionnaire'  : 'Consultation + ajout/modification des archives. Pas d\'accès à l\'administration.',
    'Utilisateur'   : 'Lecture seule : consultation des archives uniquement.'
};

document.getElementById('grade').addEventListener('change', function () {
    document.getElementById('gradeHelp').textContent = gradeDescriptions[this.value] || '';
});

/* ------------------------------------------------------------------ */
/* Cocher / Décocher toutes les structures                             */
/* ------------------------------------------------------------------ */
function toggleAllStructures(state) {
    document.querySelectorAll('.structure-cb').forEach(cb => cb.checked = state);
}

/* ------------------------------------------------------------------ */
/* Définit les structures cochées depuis une chaîne CSV                */
/* ------------------------------------------------------------------ */
function setStructures(csvString) {
    // Décocher tout d'abord
    document.querySelectorAll('.structure-cb').forEach(cb => cb.checked = false);
    if (!csvString) return;
    const parts = csvString.split(',').map(s => s.trim());
    parts.forEach(function (s) {
        const cb = document.getElementById('struct_' + s);
        if (cb) cb.checked = true;
    });
}

/* ------------------------------------------------------------------ */
/* Prépare le modal pour l'ajout d'un nouvel utilisateur              */
/* ------------------------------------------------------------------ */
function prepareAdd() {
    document.getElementById('userForm').action = site_url + 'users/add';
    document.getElementById('original_username').value = '';
    document.getElementById('lname').value   = '';
    document.getElementById('fname').value   = '';
    document.getElementById('username').value = '';
    document.getElementById('password').value = '';
    document.getElementById('grade').value   = 'Gestionnaire';
    document.getElementById('status').value  = 'enabled';
    document.getElementById('gradeHelp').textContent = gradeDescriptions['Gestionnaire'];
    document.getElementById('userModalLabel').textContent = 'Ajouter un utilisateur';
    setStructures('');
    var modal = new bootstrap.Modal(document.getElementById('userModal'));
    modal.show();
}

/* ------------------------------------------------------------------ */
/* Prépare le modal pour l'édition d'un utilisateur existant          */
/* ------------------------------------------------------------------ */
function editUser(username) {
    $.getJSON(site_url + 'users/get_details/' + username, function (u) {
        if (!u || u.status === 'error') {
            alert('Impossible de charger les données de cet utilisateur.');
            return;
        }
        document.getElementById('userForm').action = site_url + 'users/edit';
        document.getElementById('original_username').value = u.username;
        document.getElementById('lname').value    = u.lname   || '';
        document.getElementById('fname').value    = u.fname   || '';
        document.getElementById('username').value = u.username || '';
        document.getElementById('grade').value    = u.grade   || 'Utilisateur';
        document.getElementById('status').value   = u.status  || 'enabled';
        document.getElementById('password').value = '';
        document.getElementById('gradeHelp').textContent = gradeDescriptions[u.grade] || '';
        document.getElementById('userModalLabel').textContent = 'Modifier : ' + u.username;
        setStructures(u.structures);
        var modal = new bootstrap.Modal(document.getElementById('userModal'));
        modal.show();
    });
}
</script>
</main>