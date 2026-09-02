ALTER TABLE n003
    ADD COLUMN IF NOT EXISTS bot003 TEXT,
    ADD COLUMN IF NOT EXISTS upt003 BIGINT,
    ADD COLUMN IF NOT EXISTS outtel003 BOOLEAN NOT NULL DEFAULT FALSE;

COMMENT ON COLUMN n003.bot003 IS 'Token do bot Telegram criptografado.';
COMMENT ON COLUMN n003.upt003 IS 'Último update_id do Telegram já sincronizado.';
COMMENT ON COLUMN n003.outtel003 IS 'Define se respostas automáticas serão enviadas pelo Telegram.';
