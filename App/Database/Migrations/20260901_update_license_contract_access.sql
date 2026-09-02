ALTER TABLE n021
    ADD COLUMN IF NOT EXISTS ace021 TIMESTAMP WITH TIME ZONE NULL,
    ADD COLUMN IF NOT EXISTS ver021 VARCHAR(50) NULL,
    ADD COLUMN IF NOT EXISTS ip021 VARCHAR(45) NULL;

CREATE INDEX IF NOT EXISTS ix_n021_aceite ON n021 (ace021 DESC);

COMMENT ON COLUMN n021.ace021 IS 'Data e hora do aceite formal do contrato.';
COMMENT ON COLUMN n021.ver021 IS 'Versão do contrato aceita pelo supervisor.';
COMMENT ON COLUMN n021.ip021 IS 'Endereço IP registrado no aceite formal.';
