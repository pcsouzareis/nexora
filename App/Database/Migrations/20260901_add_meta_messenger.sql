ALTER TABLE n003
    ADD COLUMN IF NOT EXISTS pag003 VARCHAR(120),
    ADD COLUMN IF NOT EXISTS met003 TEXT,
    ADD COLUMN IF NOT EXISTS sec003 TEXT,
    ADD COLUMN IF NOT EXISTS outmet003 BOOLEAN NOT NULL DEFAULT FALSE;

COMMENT ON COLUMN n003.pag003 IS 'ID da Página Facebook Messenger.';
COMMENT ON COLUMN n003.met003 IS 'Page Access Token da Meta criptografado.';
COMMENT ON COLUMN n003.sec003 IS 'App Secret da Meta criptografado para validar webhooks.';
