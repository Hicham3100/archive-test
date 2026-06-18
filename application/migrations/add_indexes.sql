USE id_antique;

-- Index pour tb_documents
CREATE INDEX IF NOT EXISTS idx_documents_emetteur_destinataire ON tb_documents (emetteur, destinataire);
CREATE INDEX IF NOT EXISTS idx_documents_date_creation ON tb_documents (date_creation);

-- Index pour tb_archives
CREATE INDEX IF NOT EXISTS idx_archives_numero_boite_emetteur ON tb_archives (numero_boite, emetteur);
CREATE INDEX IF NOT EXISTS idx_archives_date_creation ON tb_archives (date_creation);