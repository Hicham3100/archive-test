<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_add_indexes extends CI_Migration {

    public function up()
    {
        // Index pour tb_authentification
        $this->db->query("CREATE INDEX idx_authentification_username ON tb_authentification (username)");
        $this->db->query("CREATE INDEX idx_authentification_grade ON tb_authentification (grade)");
        $this->db->query("CREATE INDEX idx_authentification_status ON tb_authentification (status)");
        $this->db->query("CREATE INDEX idx_authentification_created_at ON tb_authentification (created_at)");

        // Index pour tb_documents
        $this->db->query("CREATE INDEX idx_documents_emetteur ON tb_documents (emetteur)");
        $this->db->query("CREATE INDEX idx_documents_destinataire ON tb_documents (destinataire)");
        $this->db->query("CREATE INDEX idx_documents_nature_document ON tb_documents (nature_document)");
        $this->db->query("CREATE INDEX idx_documents_sous_nature ON tb_documents (sous_nature)");
        $this->db->query("CREATE INDEX idx_documents_structure_dam ON tb_documents (structure_dam)");
        $this->db->query("CREATE INDEX idx_documents_created_at ON tb_documents (created_at)");
        $this->db->query("CREATE INDEX idx_documents_created_by ON tb_documents (created_by)");

        // Index pour tb_archives
        $this->db->query("CREATE INDEX idx_archives_numero_boite ON tb_archives (numero_boite)");
        $this->db->query("CREATE INDEX idx_archives_emetteur ON tb_archives (emetteur)");
        $this->db->query("CREATE INDEX idx_archives_destinataire ON tb_archives (destinataire)");
        $this->db->query("CREATE INDEX idx_archives_nature_document ON tb_archives (nature_document)");
        $this->db->query("CREATE INDEX idx_archives_structure_dam ON tb_archives (structure_dam)");
        $this->db->query("CREATE INDEX idx_archives_created_at ON tb_archives (created_at)");
        $this->db->query("CREATE INDEX idx_archives_created_by ON tb_archives (created_by)");

        // Index pour activity_log
        $this->db->query("CREATE INDEX idx_activity_log_username ON activity_log (username)");
        $this->db->query("CREATE INDEX idx_activity_log_created_at ON activity_log (created_at)");

        // Index pour consultation_log
        $this->db->query("CREATE INDEX idx_consultation_log_username ON consultation_log (username)");
        $this->db->query("CREATE INDEX idx_consultation_log_created_at ON consultation_log (created_at)");
        $this->db->query("CREATE INDEX idx_consultation_log_document_id ON consultation_log (document_id)");
    }

    public function down()
    {
        // Suppression des index
        $this->db->query("DROP INDEX idx_authentification_username ON tb_authentification");
        $this->db->query("DROP INDEX idx_authentification_grade ON tb_authentification");
        $this->db->query("DROP INDEX idx_authentification_status ON tb_authentification");
        $this->db->query("DROP INDEX idx_authentification_created_at ON tb_authentification");

        $this->db->query("DROP INDEX idx_documents_emetteur ON tb_documents");
        $this->db->query("DROP INDEX idx_documents_destinataire ON tb_documents");
        $this->db->query("DROP INDEX idx_documents_nature_document ON tb_documents");
        $this->db->query("DROP INDEX idx_documents_sous_nature ON tb_documents");
        $this->db->query("DROP INDEX idx_documents_structure_dam ON tb_documents");
        $this->db->query("DROP INDEX idx_documents_created_at ON tb_documents");
        $this->db->query("DROP INDEX idx_documents_created_by ON tb_documents");

        $this->db->query("DROP INDEX idx_archives_numero_boite ON tb_archives");
        $this->db->query("DROP INDEX idx_archives_emetteur ON tb_archives");
        $this->db->query("DROP INDEX idx_archives_destinataire ON tb_archives");
        $this->db->query("DROP INDEX idx_archives_nature_document ON tb_archives");
        $this->db->query("DROP INDEX idx_archives_structure_dam ON tb_archives");
        $this->db->query("DROP INDEX idx_archives_created_at ON tb_archives");
        $this->db->query("DROP INDEX idx_archives_created_by ON tb_archives");

        $this->db->query("DROP INDEX idx_activity_log_username ON activity_log");
        $this->db->query("DROP INDEX idx_activity_log_created_at ON activity_log");

        $this->db->query("DROP INDEX idx_consultation_log_username ON consultation_log");
        $this->db->query("DROP INDEX idx_consultation_log_created_at ON consultation_log");
        $this->db->query("DROP INDEX idx_consultation_log_document_id ON consultation_log");
    }
}