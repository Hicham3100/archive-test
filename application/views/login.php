<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Service des Archives</title>

    <!-- Bootstrap 5 CSS — POST-01 : SRI ajouté -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
          rel="stylesheet"
          integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN"
          crossorigin="anonymous">
    <!-- Bootstrap Icons — POST-01 : version fixe + SRI -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
          rel="stylesheet"
          integrity="sha384-XGjxtQfXaH2tnPFa9x+ruJTuLE3Aa6LhHgbe2LLkvZ/KAhzXlU5B0bLSFVGAfv0"
          crossorigin="anonymous">

    <style>
        :root {
            --primary-color: #2b5d5d;
            --primary-light: rgba(43, 93, 93, 0.1);
            --text-light: rgba(255, 255, 255, 0.9);
        }

        body {
            background: url('<?= base_url('assets/pics/bg-4.jpg') ?>') no-repeat center center fixed;
            background-size: cover;
            min-height: 100vh;
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .page-header {
            position: absolute;
            top: 5%;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 30px;
            text-align: center;
            z-index: 10;
        }

        .page-header h1 {
            color: var(--text-light);
            font-size: 2rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
            margin: 0;
            font-weight: 600;
        }

        .header-logo {
            width: 65px;
            height: 65px;
            object-fit: contain;
            opacity: 0.9;
            filter: drop-shadow(0 0 5px rgba(0,0,0,0.2));
        }

        .login-container {
            display: flex;
            justify-content: center;
            align-items: center;
            flex: 1;
            padding: 20px;
        }

        .login-box {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            width: 100%;
            max-width: 450px;
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .login-box h2 {
            text-align: center;
            color: var(--primary-color);
            margin-bottom: 30px;
            font-weight: 600;
            font-size: 1.8rem;
        }

        .login-box .logo-icon {
            width: 60px;
            height: 60px;
            margin: 0 auto 20px;
            display: block;
            opacity: 0.8;
        }

        .form-floating {
            margin-bottom: 20px;
        }

        .form-floating input {
            border-radius: 10px;
            padding: 15px 20px;
            font-size: 15px;
            border: 1px solid #dee2e6;
        }

        .form-floating input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem var(--primary-light);
        }

        .form-floating label {
            color: #6c757d;
            font-size: 14px;
        }

        .btn-login {
            width: 100%;
            padding: 12px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            margin-top: 10px;
            transition: all 0.2s ease;
        }

        .btn-login:hover {
            background: #1e4444;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .forgot-password {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
        }

        .forgot-password a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }

        .forgot-password a:hover {
            text-decoration: underline;
        }

        .error-message {
            color: #dc3545;
            font-size: 14px;
            margin-top: -15px;
            margin-bottom: 15px;
            text-align: center;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                gap: 15px;
                top: 3%;
            }

            .page-header h1 {
                font-size: 1.5rem;
            }

            .login-box {
                padding: 30px 20px;
                margin: 20px 0;
            }
        }

        /* Animation */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .login-box {
            animation: fadeIn 0.5s ease-out;
        }
    </style>
</head>
<body>
    <div class="page-header">
        <img src="<?= base_url('assets/pics/asal.png') ?>" alt="ASAL Logo" class="header-logo">
        <h1>Agence Spatiale Algérienne<br>Centre des Techniques Spatiales</h1>
        <img src="<?= base_url('assets/pics/cts.png') ?>" alt="CTS Logo" class="header-logo">
    </div>

    <div class="login-container">
        <div class="login-box">
            <img src="<?= base_url('assets/pics/cts.png') ?>" alt="Logo" class="logo-icon">

            <h2>Service des Archives</h2>

            <?php if (isset($error)): ?>
                <div class="error-message"><?= html_escape($error) ?></div>
            <?php endif; ?>

            <form method="post" action="<?= site_url('archive/auth') ?>">
                <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
                <div class="form-floating mb-3">
                    <input type="text" class="form-control" id="username" name="username"
                           placeholder="Nom d'utilisateur" required autofocus>
                    <label for="username">Nom d'utilisateur</label>
                </div>

                <div class="form-floating mb-4">
                    <input type="password" class="form-control" id="password" name="password"
                           placeholder="Mot de passe" required>
                    <label for="password">Mot de passe</label>
                </div>

                <button type="submit" class="btn btn-login">
                    <i class="bi bi-box-arrow-in-right me-2"></i> Connexion
                </button>

                <div class="forgot-password">
                    <a href="#" data-bs-toggle="modal" data-bs-target="#forgotPasswordModal">
                        Mot de passe oublié ?
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Mot de passe oublié -->
    <div class="modal fade" id="forgotPasswordModal" tabindex="-1" aria-labelledby="forgotPasswordModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title" id="forgotPasswordModalLabel">
                        <i class="bi bi-shield-lock me-2"></i> Réinitialisation du mot de passe
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <i class="bi bi-envelope-check fs-1 text-primary mb-3"></i>
                        <p>Entrez votre adresse e-mail ou nom d'utilisateur pour recevoir un lien de réinitialisation.</p>
                    </div>

                    <form id="forgotPasswordForm">
                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="recoveryEmail" placeholder="Email ou username" required>
                            <label for="recoveryEmail">Email ou nom d'utilisateur</label>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-send me-2"></i> Envoyer le lien
                            </button>
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-0 pt-0 justify-content-center">
                    <small class="text-muted">Un email vous sera envoyé avec les instructions pour réinitialiser votre mot de passe.</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle — POST-01 : SRI ajouté -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL"
            crossorigin="anonymous"></script>

    <!-- Script pour la validation du formulaire -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Validation basique du formulaire
            const form = document.querySelector('form');
            form.addEventListener('submit', function(e) {
                const username = document.getElementById('username').value;
                const password = document.getElementById('password').value;

                if (!username || !password) {
                    e.preventDefault();
                    alert('Veuillez remplir tous les champs');
                }
            });

            // Gestion du modal de mot de passe oublié
            const forgotPasswordForm = document.getElementById('forgotPasswordForm');
            if (forgotPasswordForm) {
                forgotPasswordForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const email = document.getElementById('recoveryEmail').value;

                    if (!email) {
                        alert('Veuillez entrer votre email ou nom d\'utilisateur');
                        return;
                    }

                    // Simulation d'envoi (à remplacer par une vraie requête AJAX)
                    alert('Un email de réinitialisation a été envoyé à ' + email);
                    const modal = bootstrap.Modal.getInstance(document.getElementById('forgotPasswordModal'));
                    modal.hide();
                });
            }

            // Focus automatique sur le champ username
            document.getElementById('username').focus();
        });
    </script>
</body>
</html>