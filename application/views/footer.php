<!-- BUG-02 corrigé : le modal #validateModal est défini dans prearchive.php uniquement -->


</main>

<!--------------- Footer --------------->
<footer class="archive-footer mt-5">
    <hr>
    <p class="text-center mb-0">
        Agence Spatiale Algérienne - Centre des Techniques Spatiales
    </p>
    <p class="text-center mb-0">
        © 
        <?php
            $annee_creation = 2025;
            $annee_courante = date('Y');
            echo ($annee_creation == $annee_courante) ? $annee_creation : $annee_creation . ' - ' . $annee_courante;
        ?>
        — Services des Archives. Tous droits réservés.
    </p>
</footer>

<!-- 1. jQuery 3.7.1 — SEC-07 : SRI + mise à jour vers version récente -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"
        integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo="
        crossorigin="anonymous"></script>

<!-- 2. Bootstrap JS — SEC-07 : SRI ajouté -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL"
        crossorigin="anonymous"></script>

<!-- 3. archive.js chargé SAUF si le contrôleur le désactive (POST-05) -->
<?php if (empty($skip_archive_js)): ?>
    <script src="<?= base_url('assets/js/archive.js') ?>"></script>
<?php endif; ?>

<!-- 4. Variables globales et script pour le module Personnel -->
<script>
    var site_url = "<?= site_url() ?>";
    var base_url = "<?= base_url() ?>";
    var csrf_token_name = "<?= $this->security->get_csrf_token_name() ?>";
    var csrf_hash = "<?= $this->security->get_csrf_hash() ?>";

    $(document).ready(function() {
        $.ajaxPrefilter(function (options, originalOptions, jqXHR) {
            if (options.type.toLowerCase() === 'post') {
                if (typeof options.data === 'string') {
                    if (options.data.indexOf(csrf_token_name) === -1) {
                        options.data += (options.data.length > 0 ? '&' : '') + csrf_token_name + '=' + csrf_hash;
                    }
                } else if (typeof options.data === 'object' && !(options.data instanceof FormData)) {
                    options.data[csrf_token_name] = csrf_hash;
                }
            }
        });
    });
</script>
</body>
</html>