<main class="page-content">
<div class="container-fluid mt-2">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h4 class="mb-0">📄 Pré-archives</h4>
        <?php if ($this->session->userdata('role') == 1): ?>
        <a href="<?= site_url('archive/export_prearchives_csv') ?>" class="btn btn-sm btn-outline-success"><i class="bi bi-download"></i> CSV</a>
        <?php endif; ?>
    </div>

    <!-- Filtres compacts -->
    <div class="row g-1 mb-2">
        <div class="col-md-3 col-6"><select id="searchEmetteur" class="form-select form-select-sm"><option value="">Émetteur</option></select></div>
        <div class="col-md-3 col-6"><select id="searchDestinataire" class="form-select form-select-sm"><option value="">Destinataire</option></select></div>
        <div class="col-md-3 col-6"><select id="searchSousNature" class="form-select form-select-sm"><option value="">Sous-nature</option></select></div>
        <?php
            $role = $this->session->userdata('role');
            $grade = $this->session->userdata('grade');
            // GRADES-01 : Admin + Gestionnaire (inclut alias legacy 'Directeur')
            $is_admin_or_gest = ($role == 1 || in_array($grade, ['Gestionnaire', 'Directeur']));
        ?>
        <?php if ($is_admin_or_gest): ?>
        <div class="col-md-3 col-6"><select id="searchStructure" class="form-select form-select-sm"><option value="">Structure</option></select></div>
        <?php endif; ?>
    </div>
    <div class="row g-1 mb-2">
        <div class="col-md-2 col-6"><input type="date" id="searchDateDebut" class="form-control form-control-sm" placeholder="Date début"></div>
        <div class="col-md-2 col-6"><input type="date" id="searchDateFin" class="form-control form-control-sm" placeholder="Date fin"></div>
        <div class="col-md-2 col-6"><button id="btnReset" class="btn btn-sm btn-outline-secondary w-100">Réinitialiser</button></div>
    </div>

    <div id="resultCount" class="small text-muted mb-1"></div>

    <div class="table-responsive">
        <table class="table table-sm table-striped table-bordered">
            <thead class="table-dark">
                <tr><th>N° boîte</th><th>Émetteur</th><th>Destinataire</th><th>Nature</th><th>Sous-nature</th><th>Structure</th><th>Référence</th><th>Date</th><th>Type</th><th>Créé par</th><th>Validation</th></tr>
            </thead>
            <tbody id="documentsBody"><tr><td colspan="11" class="text-center text-muted py-3">Chargement...</td></tr></tbody>
        </table>
    </div>
    <nav><ul id="paginationLinks" class="pagination pagination-sm justify-content-center"></ul></nav>
</div>

<!-- Modal validation -->
<div class="modal fade" id="validateModal" tabindex="-1">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <form id="validateForm" enctype="multipart/form-data">
                <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
                <div class="modal-header bg-success text-white"><h6 class="modal-title">Finaliser</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <input type="hidden" name="document_id" id="document_id">
                    <div class="mb-2"><label class="form-label small">Numéro de boîte</label><input type="text" name="numero_boite" class="form-control form-control-sm" required></div>
                    <div class="mb-2"><label class="form-label small">Fichier final (PDF)</label><input type="file" name="fichier" class="form-control form-control-sm" required></div>
                </div>
                <div class="modal-footer"><button type="submit" class="btn btn-sm btn-success">Archiver</button></div>
            </form>
        </div>
    </div>
</div>
</main>

<script>
var site_url = "<?= site_url() ?>";
var userRole = "<?= $this->session->userdata('role') ?>";
var userGrade = "<?= $this->session->userdata('grade') ?>";
var isAdminOrGest = <?= $is_admin_or_gest ? 'true' : 'false' ?>;
// GRADES-01 : Bouton Valider visible pour Admin + Gestionnaire
var canValidate = <?= ($this->session->userdata('role') == 1 || in_array($this->session->userdata('grade'), ['Gestionnaire', 'Directeur'])) ? 'true' : 'false' ?>;
var currentPage = 1, perPage = 10;

function escapeHtml(s){ return String(s??'').replace(/[&<>]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;'}[c];}); }

$(document).ready(function(){
    loadFilters();
    $('#searchEmetteur, #searchDestinataire, #searchSousNature, #searchDateDebut, #searchDateFin').on('change', function(){ fetchDocuments(1); });
    if(isAdminOrGest) $('#searchStructure').on('change', function(){ fetchDocuments(1); });
    $('#btnReset').on('click', function(){ $('#searchEmetteur,#searchDestinataire,#searchSousNature,#searchDateDebut,#searchDateFin').val(''); if(isAdminOrGest) $('#searchStructure').val(''); fetchDocuments(1); });
    $('#validateForm').on('submit', function(e){
        e.preventDefault();
        $.ajax({ url: site_url+'archive/validate_document', type:'POST', data:new FormData(this), contentType:false, processData:false, dataType:'json', success: function(res){ if(res.status){ $('#validateModal').modal('hide'); fetchDocuments(currentPage); } else alert(res.message); } });
    });
    fetchDocuments(1);
});

function loadFilters() {
    $.getJSON(site_url+'archive/get_emetteurs', d=>{ let o='<option value="">Tous</option>'; d.forEach(i=>o+='<option>'+escapeHtml(i)+'</option>'); $('#searchEmetteur').html(o); });
    $.getJSON(site_url+'archive/get_destinataires', d=>{ let o='<option value="">Tous</option>'; d.forEach(i=>o+='<option>'+escapeHtml(i)+'</option>'); $('#searchDestinataire').html(o); });
    $.getJSON(site_url+'archive/get_sous_natures', d=>{ let o='<option value="">Tous</option>'; d.forEach(i=>o+='<option>'+escapeHtml(i)+'</option>'); $('#searchSousNature').html(o); });
    if(isAdminOrGest) $.getJSON(site_url+'archive/get_structures_list', d=>{ let o='<option value="">Toutes</option>'; d.forEach(i=>o+='<option>'+escapeHtml(i)+'</option>'); $('#searchStructure').html(o); });
}

function fetchDocuments(page) {
    currentPage = page||1;
    let data = { emetteur:$('#searchEmetteur').val(), destinataire:$('#searchDestinataire').val(), sous_nature:$('#searchSousNature').val(), date_debut:$('#searchDateDebut').val(), date_fin:$('#searchDateFin').val(), page:currentPage, per_page:perPage };
    if(isAdminOrGest) data.structure = $('#searchStructure').val();
    $.post(site_url+'archive/search_documents_ajax', data, function(res){
        let d = res.data||[], total = res.total||0, totalPages = res.total_pages||1;
        let t = '';
        if(d.length){
            $('#resultCount').html(`<span class="badge bg-secondary">${total} résultat(s) - Page ${currentPage}/${totalPages}</span>`);
            d.forEach(doc=>{
                // GRADES-01 : Valider = Admin + Gestionnaire
                let valBtn = canValidate ? `<button class="btn btn-sm btn-success btn-validate" data-id="${doc.id}">Valider</button>` : '-';
                t += `<tr>
                    <td>${escapeHtml(doc.numero_boite)}</td><td>${escapeHtml(doc.emetteur)}</td><td>${escapeHtml(doc.destinataire)}</td>
                    <td>${escapeHtml(doc.nature_document)}</td><td>${escapeHtml(doc.sous_nature)}</td><td>${escapeHtml(doc.structure_dam)}</td>
                    <td>${escapeHtml(doc.reference)}</td><td>${escapeHtml(doc.date_creation)}</td><td>${escapeHtml(doc.type_document)}</td>
                    <td>${escapeHtml(doc.created_by)}</td><td>${valBtn}</td>
                </tr>`;
            });
        } else { t = '<tr><td colspan="11" class="text-center">Aucun document</td></tr>'; $('#resultCount').html('<span class="badge bg-warning">0 résultat</span>'); }
        $('#documentsBody').html(t);
        let pages='';
        if(totalPages>1){
            pages+=`<li class="page-item ${currentPage<=1?'disabled':''}"><a class="page-link" data-page="${currentPage-1}">←</a></li>`;
            for(let i=Math.max(1,currentPage-2); i<=Math.min(totalPages,currentPage+2); i++) pages+=`<li class="page-item ${i==currentPage?'active':''}"><a class="page-link" data-page="${i}">${i}</a></li>`;
            pages+=`<li class="page-item ${currentPage>=totalPages?'disabled':''}"><a class="page-link" data-page="${currentPage+1}">→</a></li>`;
        }
        $('#paginationLinks').html(pages);
        $('.btn-validate').click(function(){ $('#document_id').val($(this).data('id')); new bootstrap.Modal(document.getElementById('validateModal')).show(); });
    },'json');
}
$(document).on('click', '#paginationLinks .page-link', function(e){ e.preventDefault(); let p=$(this).data('page'); if(p&&p!=currentPage) fetchDocuments(p); });
</script>