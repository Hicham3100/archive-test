<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? html_escape($page_title) . ' — Archives' : 'Service des Archives' ?></title>

    <!-- Bootstrap CSS — SEC-07 : SRI ajouté -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
          rel="stylesheet"
          integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN"
          crossorigin="anonymous">
    <!-- Bootstrap Icons — SEC-07 : version fixe (pas 'latest') + SRI -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
          rel="stylesheet"
          integrity="sha384-XGjxtQfXaH2tnPFa9x+ruJTuLE3Aa6LhHgbe2LLkvZ/KAhzXlU5B0bLSFVGAfv0"
          crossorigin="anonymous">

    <!-- CSS spécifique archive -->
    <link rel="stylesheet" href="<?= base_url('assets/css/archive.css') ?>">
</head>
<body class="page-wrapper">
    <!-- Background - Overlay semi-transparent -->
    <div class="bg-overlay"></div>

    <!-- Navbar avec dégradé -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-gradient">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center" href="#">
                <img src="<?= base_url('assets/pics/cts.png') ?>" alt="Logo" width="40" height="40" class="d-inline-block align-text-top me-2">
                Service des Archives
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <!-- Menu centré avec gestion des sous-menus -->
            <div class="collapse navbar-collapse justify-content-center" id="navbarMenu">
                <ul class="navbar-nav text-center">
                    <?php if (isset($menus) && is_array($menus)): ?>
                        <?php foreach ($menus as $menu):
                            if (!isset($menu['url'])) continue;
                            // Vérifier si c'est un sous-menu (présence de 'submenus')
                            if (isset($menu['submenus']) && is_array($menu['submenus'])): ?>
                                <li class="nav-item dropdown mx-2">
                                    <a class="nav-link dropdown-toggle <?= (in_array($this->uri->segment(1), ['admin', 'users']) || ($this->uri->segment(1) == 'archive' && in_array($this->uri->segment(2), ['dashboard','users','activity_log','consultations','login_history','trash']))) ? 'active' : '' ?>" 
                                       href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <?= $menu['title'] ?>
                                    </a>
                                    <ul class="dropdown-menu" aria-labelledby="adminDropdown">
                                        <?php foreach ($menu['submenus'] as $sub): ?>
                                            <li>
                                                <!-- Correction : $sub['url'] contient l'URL complète (pas de préfixe archive/ à ajouter) -->
                                                <a class="dropdown-item" href="<?= site_url($sub['url']) ?>">
                                                    <?php if (isset($sub['icone'])): ?><i class="<?= $sub['icone'] ?> me-2"></i><?php endif; ?>
                                                    <?= $sub['title'] ?>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </li>
                            <?php else:
                                $segments = explode('/', $menu['url']);
                                $segment1 = $segments[0] ?? '';
                                $segment2 = $segments[1] ?? '';
                            ?>
                                <li class="nav-item mx-2">
                                    <a class="nav-link <?= ($this->uri->segment(1) == $segment1 && $this->uri->segment(2) == $segment2) ? 'active' : '' ?>" 
                                       href="<?= site_url($menu['url']) ?>">
                                        <?= $menu['title'] ?>
                                    </a>
                                </li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                <ul class="navbar-nav align-items-center">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle profile-link p-0" href="#" id="profileDropdown"
                           role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <img src="<?= base_url('assets/pics/profil.png') ?>"
                                 alt="Profil"
                                 class="rounded-circle"
                                 width="40"
                                 height="40"
                                 style="border: 2px solid #fff;">
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow">
                            <li>
                                <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#profileModal">
                                    <i class="bi bi-person-circle me-2"></i> Profil
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-danger" href="<?= site_url('archive/logout') ?>">
                                    <i class="bi bi-box-arrow-right me-2"></i> Déconnexion
                                </a>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Modal informations personnels -->
    <?php
    $user_session = $this->session->userdata('username');
    $current_user = $this->Archive_model->get_user_by_username($user_session);
    ?>

    <div class="modal fade" id="profileModal" tabindex="-1" aria-labelledby="profileModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <form id="profileForm" enctype="multipart/form-data" method="post" action="<?= site_url('archive/update_user_information') ?>">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="profileModalLabel">Information Personnel</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="lname" class="form-label">Nom</label>
                                <input type="text" class="form-control" name="lname" id="lname" value="<?= isset($current_user['lname']) ? html_escape($current_user['lname']) : '' ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="fname" class="form-label">Prénom</label>
                                <input type="text" class="form-control" name="fname" id="fname" value="<?= isset($current_user['fname']) ? html_escape($current_user['fname']) : '' ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="password" class="form-label">Mot de passe</label>
                                <input type="password" class="form-control" name="password" id="password">
                            </div>
                            <div class="col-md-6">
                                <label for="passwordverify" class="form-label">Confirmer Mot de passe</label>
                                <input type="password" class="form-control" name="passwordverify" id="passwordverify">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <p class="form-control-plaintext"><strong>
                                    <?= isset($current_user['status']) ? ($current_user['status'] == 'enabled' ? 'Activé' : 'Désactivé') : '' ?>
                                </strong></p>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Role</label>
                                <p class="form-control-plaintext"><strong>
                                    <?= isset($current_user['role']) ? ($current_user['role'] == '1' ? 'Administrateur' : ($current_user['role'] == '2' ? 'Gestionnaire' : 'Utilisateur')) : '' ?>
                                </strong></p>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer mt-3">
                        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Fermer</button>
                        <button type="submit" class="btn btn-success">Mettre à jour</button>
                    </div>
                </div>
            </form>
        </div>
    </div>