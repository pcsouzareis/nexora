ALTER TABLE n005
    ADD COLUMN IF NOT EXISTS nkh005 TEXT;

ALTER TABLE n006
    ADD COLUMN IF NOT EXISTS ext006 VARCHAR(190),
    ADD COLUMN IF NOT EXISTS sha006 CHAR(64),
    ADD COLUMN IF NOT EXISTS ori006 VARCHAR(20) NOT NULL DEFAULT 'Manual';

CREATE UNIQUE INDEX IF NOT EXISTS ux_n006_base_external_n8n
    ON n006 (cod005, ext006)
    WHERE ext006 IS NOT NULL;

COMMENT ON COLUMN n005.nkh005 IS 'Hash da chave exclusiva da integração n8n da base.';
COMMENT ON COLUMN n006.ext006 IS 'Identificador externo do artigo, usado para sincronização idempotente.';
COMMENT ON COLUMN n006.sha006 IS 'Hash SHA-256 do conteúdo sincronizado.';
COMMENT ON COLUMN n006.ori006 IS 'Origem do artigo: Manual ou n8n.';
