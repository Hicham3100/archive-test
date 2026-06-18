<!-- Contenu principal -->
<main class="page-content">
<div class="container mt-5">
	<h1>
		Bienvenue
		<span class="name"><?= html_escape($user['lname']) ?> <?= html_escape($user['fname']) ?></span>
	</h1>
</div>

<div class="container mt-5">
	<h2>Que voulez-vous aujourd'hui !!</h2>

	<div class="row g-4">

    <?php
    $sess_role  = $this->session->userdata('role');
    $sess_grade = $this->session->userdata('grade');
    $is_admin_home    = ($sess_role == 1);
    $is_gest_home     = !$is_admin_home && in_array($sess_grade, ['Gestionnaire', 'Directeur']);
    $can_register_home = $is_admin_home || $is_gest_home; // GRADES-01

    // Tableau des cards : chaque card a un champ 'permission' (callback ou bool)
    $cards = [];

    // Card "Enregistrer des archives" — Admin + Gestionnaire uniquement
    if ($can_register_home) {
        $cards[] = [
            'title' => 'Enregistrer des archives',
            'url'   => 'documents/index',
            'color' => 'bg-blue',
            'desc'  => "Permet d'enregistrer des Pré-Archives dans la base de données, pour une validation ultérieure.",
            'modal' => true,
        ];
    }

    // Card "Pré-Archives" — Admin + Gestionnaire uniquement
    if ($can_register_home) {
        $cards[] = [
            'title' => 'Pré-Archives',
            'url'   => 'documents/prearchive',
            'color' => 'bg-green',
            'desc'  => 'Permet de visualiser les Pré-Archives enregistrés dans la base de données.',
        ];
    }

    // Card "Archives finales" — TOUS les utilisateurs connectés
    $cards[] = [
        'title' => 'Archives',
        'url'   => 'documents/archive_final',
        'color' => 'bg-petrol',
        'desc'  => 'Permet de visualiser les Archives validés, enregistrés dans la base de données.',
    ];

    // Card "Gestion des utilisateurs" — Administrateur uniquement
    if ($is_admin_home) {
        $cards[] = [
            'title' => 'Gestion des utilisateurs',
            'url'   => 'users/index',
            'color' => 'bg-orange',
            'desc'  => 'Créer, modifier, Activer/Désactiver et supprimer les utilisateurs',
        ];
    }
    ?>

    <?php foreach ($cards as $card): ?>

        <div class="col-md-4">
            <div class="archive-card"
                 <?= isset($card['modal']) && $card['modal'] ? 'data-bs-toggle="modal" data-bs-target="#documentModal"' : '' ?>
                 <?= !isset($card['modal']) ? "onclick=\"window.location='".site_url($card['url'])."'\"" : "" ?>>
                <div class="card-header <?= $card['color'] ?>">
                    <?= $card['title'] ?>
                </div>
                <div class="card-body">
                    <p><?= $card['desc'] ?></p>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

</div>

</div>

	<!-- Modal Bootstrap pour Document 1 -->
<div class="modal fade" id="documentModal" tabindex="-1" aria-labelledby="documentModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form id="documentForm" enctype="multipart/form-data" method="post" action="<?= site_url('archive/save_document') ?>">
      <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="documentModalLabel">Ajouter un document</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
                <label for="numero_boite" class="form-label">Numéro de boîte</label>
                <input type="text" class="form-control" name="numero_boite" id="numero_boite" required>
            </div>
            <div class="col-md-6">
                <label for="emetteur" class="form-label">Émetteur</label>
                <input type="text" class="form-control" name="emetteur" id="emetteur" required>
            </div>
            <div class="col-md-6">
                <label for="destinataire" class="form-label">Destinataire</label>
                <input type="text" class="form-control" name="destinataire" id="destinataire" required>
            </div>
            <div class="col-md-6">
                <label for="nature_document" class="form-label">Nature du document</label>
                <select class="form-select" name="nature_document" id="nature_document" required onchange="toggleDAM()">
                    <option value="">Sélectionner</option>
                    <option value="Interne">Interne</option>
                    <option value="Externe">Externe</option>
                </select>
            </div>
            <div class="col-md-6">
                <label for="sous_nature" class="form-label">Sous nature</label>
                <select class="form-select" name="sous_nature" id="sous_nature" disabled onchange="toggleStructureDAM()">
                    <option value="">-- Choisir --</option>
                    <option value="D.A.M">D.A.M</option>
                    <option value="D.I.D.A">D.I.D.A</option>
                    <option value="D.S.P.P">D.S.P.P</option>
                    <option value="D.R.I">D.R.I</option>
                    <option value="D.S.I.R.S">D.S.I.R.S</option>
                    <option value="D.G.S">D.G.S</option>
                    <option value="D.O.T">D.O.T</option>
                    <option value="D.E.G.P">D.E.G.P</option>
                </select>
            </div>
            <div class="col-md-6">
                <label for="structure_dam" class="form-label">Structure interne</label>
                <select class="form-select" name="structure_dam" id="structure_dam" disabled>
                    <option value="">-- Choisir --</option>
                    <option value="Direction">Direction</option>
                    <option value="SRH">SRH</option>
                    <option value="SFC">SFC</option>
                    <option value="SMG">SMG</option>
                </select>
            </div>
            <div class="col-md-6">
                <label for="structure_externe" class="form-label">Structure externe</label>
                <input type="text" class="form-control" name="structure_externe" id="structure_externe" disabled>
            </div>
            <div class="col-md-6">
                <label for="reference" class="form-label">Référence</label>
                <input type="text" class="form-control" name="reference" id="reference">
            </div>
            <div class="col-md-6">
                <label for="date_creation" class="form-label">Date de création</label>
                <input type="date" class="form-control" name="date_creation" id="date_creation">
            </div>
            <div class="col-md-6">
                <label for="type_document" class="form-label">Type de document</label>
                <input type="text" class="form-control" name="type_document" id="type_document">
            </div>
            <div class="col-12">
                <label for="description" class="form-label">Description</label>
                <textarea class="form-control" name="description" id="description" rows="3"></textarea>
            </div>
            <!-- <div class="col-12">
                <label for="fichier" class="form-label">Fichier</label>
                <input type="file" class="form-control" name="fichier" id="fichier">
            </div> -->
          </div>
        </div>
        <div class="modal-footer mt-3">
          <button type="submit" class="btn btn-primary">Enregistrer</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
    // Fonctions pour le formulaire d'enregistrement d'archive
    var allStructureOptions = [];

    document.addEventListener('DOMContentLoaded', function() {
        // Sauvegarde des options de structure
        var structureSelect = document.getElementById('structure_dam');
        if (structureSelect) {
            for (var i = 0; i < structureSelect.options.length; i++) {
                allStructureOptions.push({
                    value: structureSelect.options[i].value,
                    text: structureSelect.options[i].text
                });
            }
        }
    });

    function toggleDAM() {
        var nature = document.getElementById('nature_document').value;
        var sousNature = document.getElementById('sous_nature');
        var structureDAM = document.getElementById('structure_dam');
        var structureExterne = document.getElementById('structure_externe');

        if (nature === 'Interne') {
            sousNature.disabled = false;
            structureExterne.disabled = true;
            structureExterne.value = '';
            structureDAM.disabled = true;
            structureDAM.value = '';
        } else if (nature === 'Externe') {
            sousNature.disabled = true;
            sousNature.value = '';
            structureDAM.disabled = true;
            structureDAM.value = '';
            structureExterne.disabled = false;
        } else {
            sousNature.disabled = true;
            sousNature.value = '';
            structureDAM.disabled = true;
            structureDAM.value = '';
            structureExterne.disabled = true;
            structureExterne.value = '';
        }
        resetStructureOptions();
    }

    function toggleStructureDAM() {
        var sousNature = document.getElementById('sous_nature').value;
        var structureDAM = document.getElementById('structure_dam');

        if (sousNature === 'D.A.M') {
            structureDAM.disabled = false;
            var allowedValues = ['Direction', 'SRH', 'SFC', 'SMG'];

            structureDAM.innerHTML = '';
            var emptyOpt = document.createElement('option');
            emptyOpt.value = '';
            emptyOpt.text = '-- Choisir --';
            structureDAM.appendChild(emptyOpt);

            allStructureOptions.forEach(function(opt) {
                if (allowedValues.indexOf(opt.value) !== -1) {
                    var option = document.createElement('option');
                    option.value = opt.value;
                    option.text = opt.text;
                    structureDAM.appendChild(option);
                }
            });
        } else {
            structureDAM.disabled = true;
            structureDAM.value = '';
            resetStructureOptions();
        }
    }

    function resetStructureOptions() {
        var structureDAM = document.getElementById('structure_dam');
        if (!structureDAM) return;
        structureDAM.innerHTML = '';
        var emptyOpt = document.createElement('option');
        emptyOpt.value = '';
        emptyOpt.text = '-- Choisir --';
        structureDAM.appendChild(emptyOpt);
        allStructureOptions.forEach(function(opt) {
            if (opt.value !== '') {
                var option = document.createElement('option');
                option.value = opt.value;
                option.text = opt.text;
                structureDAM.appendChild(option);
            }
        });
    }
</script>