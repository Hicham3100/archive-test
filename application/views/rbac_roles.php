<div class="container-fluid py-3">
    <?php if ($this->session->flashdata('success')): ?>
        <div class="alert alert-success"><?= html_escape($this->session->flashdata('success')) ?></div>
    <?php endif; ?>
    <?php if ($this->session->flashdata('error')): ?>
        <div class="alert alert-danger"><?= html_escape($this->session->flashdata('error')) ?></div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold">Attribution des rôles</div>
        <div class="card-body">
            <form method="post" action="<?= site_url('rbac/assign') ?>" class="row g-2 align-items-end">
                <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
                <div class="col-md-5">
                    <label class="form-label">Utilisateur</label>
                    <select name="user_id" class="form-select" required>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= (int) $u['id'] ?>"><?= html_escape($u['lname'].' '.$u['fname'].' ('.$u['username'].')') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label">Rôle</label>
                    <select name="role_id" class="form-select" required>
                        <?php foreach ($roles as $r): ?>
                            <?php if ($r['name'] === 'super_admin' && $this->session->userdata('grade') !== 'Super administrateur'): continue; endif; ?>
                            <option value="<?= (int) $r['id'] ?>"><?= html_escape($r['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2"><button class="btn btn-primary w-100" type="submit">Attribuer</button></div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold">Permissions par rôle</div>
        <div class="card-body">
            <?php foreach ($roles as $r): ?>
                <form method="post" action="<?= site_url('rbac/permissions') ?>" class="border rounded p-3 mb-3">
                    <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
                    <input type="hidden" name="role_id" value="<?= (int) $r['id'] ?>">
                    <h6><?= html_escape($r['label']) ?> <small class="text-muted">(<?= html_escape($r['name']) ?>)</small></h6>
                    <?php $selected = $this->Rbac_model->get_role_permission_ids($r['id']); ?>
                    <div class="row">
                        <?php foreach ($permissions as $p): ?>
                            <div class="col-md-4 col-sm-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="permission_ids[]" value="<?= (int) $p['id'] ?>" <?= in_array((int) $p['id'], $selected, true) ? 'checked' : '' ?> id="p<?= (int) $p['id'] ?>_r<?= (int) $r['id'] ?>">
                                    <label class="form-check-label" for="p<?= (int) $p['id'] ?>_r<?= (int) $r['id'] ?>"><?= html_escape($p['label']) ?></label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="submit" class="btn btn-sm btn-outline-primary mt-2">Enregistrer les permissions</button>
                </form>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold">Création d’un rôle personnalisé</div>
        <div class="card-body">
            <form method="post" action="<?= site_url('rbac/create_role') ?>" class="row g-2">
                <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
                <div class="col-md-3"><input type="text" name="name" class="form-control" placeholder="Nom technique" required pattern="[a-z][a-z0-9_]{1,49}"></div>
                <div class="col-md-3"><input type="text" name="label" class="form-control" placeholder="Libellé" required></div>
                <div class="col-md-4"><input type="text" name="description" class="form-control" placeholder="Description"></div>
                <div class="col-md-2"><button type="submit" class="btn btn-primary w-100">Créer</button></div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white fw-semibold">Audit des rôles</div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>Date</th><th>Acteur</th><th>Cible</th><th>Action</th><th>Détail</th></tr></thead>
                <tbody>
                    <?php foreach ($role_audit as $a): ?>
                        <tr>
                            <td><?= html_escape($a['created_at']) ?></td>
                            <td><?= (int) $a['actor_user_id'] ?></td>
                            <td><?= $a['target_user_id'] !== null ? (int) $a['target_user_id'] : '—' ?></td>
                            <td><?= html_escape($a['action']) ?></td>
                            <td><?= html_escape($a['details']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
