<main class="page-content">
<div class="container-fluid mt-2">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h4 class="mb-0">📦 Archives finales</h4>
        <?php if ($this->session->userdata('role') == 1): ?>
        <a href="<?= site_url('archive/export_archives_csv') ?>" class="btn btn-sm btn-outline-success"><i class="bi bi-download"></i> CSV</a>
        <?php endif; ?>
    </div>

    <div class="row g-1 mb-2">
        <div class="col-md-2 col-6"><select id="searchnumeroboite" class="form-select form-select-sm"><option value="">N° boîte</option></select></div>
        <div class="col-md-2 col-6"><select id="searchEmetteur" class="form-select form-select-sm"><option value="">Émetteur</option></select></div>
        <div class="col-md-2 col-6"><select id="searchDestinataire" class="form-select form-select-sm"><option value="">Destinataire</option></select></div>
        <div class="col-md-2 col-6"><select id="searchSousNature" class="form-select form-select-sm"><option value="">Sous-nature</option></select></div>
        <?php
            $role = $this->session->userdata('role');
            $grade = $this->session->userdata('grade');
            // GRADES-01 : Admin + Gestionnaire (inclut alias legacy 'Directeur')
            $is_admin_or_gest = ($role == 1 || in_array($grade, ['Gestionnaire', 'Directeur']));
        ?>
        <?php if ($is_admin_or_gest): ?>
        <div class="col-md-2 col-6"><select id="searchStructure" class="form-select form-select-sm"><option value="">Structure</option></select></div>
        <?php endif; ?>
        <div class="col-md-2 col-6"><input type="text" id="searchMotCle" class="form-control form-control-sm" placeholder="Recherche"></div>
    </div>
    <div class="row g-1 mb-2">
        <div class="col-md-2 col-6"><input type="date" id="searchDateDebut" class="form-control form-control-sm"></div>
        <div class="col-md-2 col-6"><input type="date" id="searchDateFin" class="form-control form-control-sm"></div>
        <div class="col-md-2 col-6"><button id="btnReset" class="btn btn-sm btn-outline-secondary w-100">Réinitialiser</button></div>
    </div>

    <div id="resultCount" class="small text-muted mb-1"></div>
    <div class="table-responsive">
        <table class="table table-sm table-striped table-bordered">
            <thead class="table-dark"><tr><th>N° boîte</th><th>Émetteur</th><th>Destinataire</th><th>Nature</th><th>Sous-nature</th><th>Référence</th><th>Date</th><th>Fichier</th></tr></thead>
            <tbody id="documentsBody"><tr><td colspan="8" class="text-center">Chargement...</td></tr></tbody>
        </table>
    </div>
    <nav><ul id="paginationLinks" class="pagination pagination-sm justify-content-center"></ul></nav>
</div>
</main>

<script>
var site_url = "<?= site_url() ?>";
var isAdminOrGest = <?= $is_admin_or_gest ? 'true' : 'false' ?>;
var currentPage = 1, perPage = 10;
function escapeHtml(s){ return String(s??'').replace(/[&<>]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;'}[c];}); }

$(document).ready(function(){
    loadFilters();
    $('#searchnumeroboite, #searchEmetteur, #searchDestinataire, #searchSousNature, #searchDateDebut, #searchDateFin, #searchMotCle').on('change input', function(){ fetchDocuments(1); });
    if(isAdminOrGest) $('#searchStructure').on('change', function(){ fetchDocuments(1); });
    $('#btnReset').on('click', function(){ $('#searchnumeroboite,#searchEmetteur,#searchDestinataire,#searchSousNature,#searchDateDebut,#searchDateFin,#searchMotCle').val(''); if(isAdminOrGest) $('#searchStructure').val(''); fetchDocuments(1); });
    fetchDocuments(1);
});

function loadFilters() {
    $.getJSON(site_url+'archive/get_numeroboite', d=>{ let o='<option value="">Tous</option>'; d.forEach(i=>o+='<option>'+escapeHtml(i)+'</option>'); $('#searchnumeroboite').html(o); });
    $.getJSON(site_url+'archive/get_emetteurs', d=>{ let o='<option value="">Tous</option>'; d.forEach(i=>o+='<option>'+escapeHtml(i)+'</option>'); $('#searchEmetteur').html(o); });
    $.getJSON(site_url+'archive/get_destinataires', d=>{ let o='<option value="">Tous</option>'; d.forEach(i=>o+='<option>'+escapeHtml(i)+'</option>'); $('#searchDestinataire').html(o); });
    $.getJSON(site_url+'archive/get_sous_natures', d=>{ let o='<option value="">Tous</option>'; d.forEach(i=>o+='<option>'+escapeHtml(i)+'</option>'); $('#searchSousNature').html(o); });
    if(isAdminOrGest) $.getJSON(site_url+'archive/get_structures_list', d=>{ let o='<option value="">Toutes</option>'; d.forEach(i=>o+='<option>'+escapeHtml(i)+'</option>'); $('#searchStructure').html(o); });
}

function fetchDocuments(page) {
    currentPage = page||1;
    let data = { numero_boite:$('#searchnumeroboite').val(), emetteur:$('#searchEmetteur').val(), destinataire:$('#searchDestinataire').val(), sous_nature:$('#searchSousNature').val(), date_debut:$('#searchDateDebut').val(), date_fin:$('#searchDateFin').val(), mot_cle:$('#searchMotCle').val(), page:currentPage, per_page:perPage };
    if(isAdminOrGest) data.structure = $('#searchStructure').val();
    $.post(site_url+'archive/search_archives_ajax', data, function(res){
        let d = res.data||[], total = res.total||0, totalPages = res.total_pages||1;
        let t = '';
        if(d.length){
            $('#resultCount').html(`<span class="badge bg-secondary">${total} résultat(s) - Page ${currentPage}/${totalPages}</span>`);
            d.forEach(doc=>{
                let pdfLink = doc.fichier ? `<a href="${site_url}archive/view_pdf_protected?file=${encodeURIComponent(doc.fichier)}" target="_blank" class="pdf-link" data-file="${doc.fichier}" data-type="Archives" data-id="${doc.id}"><i class="bi bi-eye"></i></a>` : '-';
                t += `<tr><td>${escapeHtml(doc.numero_boite)}</td><td>${escapeHtml(doc.emetteur)}</td><td>${escapeHtml(doc.destinataire)}</td><td>${escapeHtml(doc.nature_document)}</td><td>${escapeHtml(doc.sous_nature)}</td><td>${escapeHtml(doc.reference)}</td><td>${escapeHtml(doc.date_creation)}</td><td>${pdfLink}</td></tr>`;
            });
        } else { t = '<tr><td colspan="8" class="text-center">Aucune archive</td></tr>'; $('#resultCount').html('<span class="badge bg-warning">0 résultat</span>'); }
        $('#documentsBody').html(t);
        let pages='';
        if(totalPages>1){
            pages+=`<li class="page-item ${currentPage<=1?'disabled':''}"><a class="page-link" data-page="${currentPage-1}">←</a></li>`;
            for(let i=Math.max(1,currentPage-2); i<=Math.min(totalPages,currentPage+2); i++) pages+=`<li class="page-item ${i==currentPage?'active':''}"><a class="page-link" data-page="${i}">${i}</a></li>`;
            pages+=`<li class="page-item ${currentPage>=totalPages?'disabled':''}"><a class="page-link" data-page="${currentPage+1}">→</a></li>`;
        }
        $('#paginationLinks').html(pages);
        $('.pdf-link').click(function(){ let f=$(this).data('file'), t=$(this).data('type'), id=$(this).data('id'); $.post(site_url+'archive/log_consultation_ajax',{file:f,type:t,document_id:id}); });
    },'json');
}
$(document).on('click', '#paginationLinks .page-link', function(e){ e.preventDefault(); let p=$(this).data('page'); if(p&&p!=currentPage) fetchDocuments(p); });
</script>