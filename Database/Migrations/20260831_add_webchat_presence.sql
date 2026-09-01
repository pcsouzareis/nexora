-- Última atividade do visitante do Webchat; usada para indicar presença recente.
ALTER TABLE n008 ADD COLUMN IF NOT EXISTS web008 TIMESTAMP WITH TIME ZONE;

CREATE INDEX IF NOT EXISTS ix_n008_web008
    ON n008 (web008 DESC)
    WHERE web008 IS NOT NULL;

COMMENT ON COLUMN n008.web008 IS
'Última atividade registrada pelo widget Webchat para indicar presença recente do visitante.';
