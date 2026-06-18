SET FOREIGN_KEY_CHECKS=0;
USE db_archive;

-- Indexes
CREATE INDEX IF NOT EXISTS idx_authentification_username ON tb_authentification (username);
CREATE INDEX IF NOT EXISTS idx_authentification_grade ON tb_authentification (grade);
CREATE INDEX IF NOT EXISTS idx_authentification_status ON tb_authentification (status);
CREATE INDEX IF NOT EXISTS idx_authentification_created_at ON tb_authentification (created_at);

CREATE INDEX IF NOT EXISTS idx_documents_emetteur ON tb_documents (emetteur);
CREATE INDEX IF NOT EXISTS idx_documents_destinataire ON tb_documents (destinataire);
CREATE INDEX IF NOT EXISTS idx_documents_nature_document ON tb_documents (nature_document);
CREATE INDEX IF NOT EXISTS idx_documents_sous_nature ON tb_documents (sous_nature);
CREATE INDEX IF NOT EXISTS idx_documents_structure_dam ON tb_documents (structure_dam);
CREATE INDEX IF NOT EXISTS idx_documents_created_at ON tb_documents (created_at);
CREATE INDEX IF NOT EXISTS idx_documents_created_by ON tb_documents (created_by);

CREATE INDEX IF NOT EXISTS idx_archives_numero_boite ON tb_archives (numero_boite);
CREATE INDEX IF NOT EXISTS idx_archives_emetteur ON tb_archives (emetteur);
CREATE INDEX IF NOT EXISTS idx_archives_destinataire ON tb_archives (destinataire);
CREATE INDEX IF NOT EXISTS idx_archives_nature_document ON tb_archives (nature_document);
CREATE INDEX IF NOT EXISTS idx_archives_structure_dam ON tb_archives (structure_dam);
CREATE INDEX IF NOT EXISTS idx_archives_created_at ON tb_archives (created_at);
CREATE INDEX IF NOT EXISTS idx_archives_created_by ON tb_archives (created_by);

CREATE INDEX IF NOT EXISTS idx_activity_log_username ON activity_log (username);
CREATE INDEX IF NOT EXISTS idx_activity_log_created_at ON activity_log (created_at);

CREATE INDEX IF NOT EXISTS idx_consultation_log_username ON consultation_log (username);
CREATE INDEX IF NOT EXISTS idx_consultation_log_created_at ON consultation_log (created_at);
CREATE INDEX IF NOT EXISTS idx_consultation_log_document_id ON consultation_log (document_id);

-- Nettoyage des logs
DELETE FROM activity_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
DELETE FROM consultation_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
DELETE FROM login_history WHERE created_at < DATE_SUB(NOW(), INTERVAL 180 DAY);

SET FOREIGN_KEY_CHECKS=1;