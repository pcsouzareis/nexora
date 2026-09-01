-- Identificadores externos usados pelo webhook genérico.
ALTER TABLE n007 ADD COLUMN IF NOT EXISTS ide007 VARCHAR(120);
ALTER TABLE n008 ADD COLUMN IF NOT EXISTS cod005 BIGINT;
ALTER TABLE n009 ADD COLUMN IF NOT EXISTS ide009 VARCHAR(120);

CREATE UNIQUE INDEX IF NOT EXISTS uq_n007_empresa_identificador
    ON n007 (cod001, ide007)
    WHERE ide007 IS NOT NULL;

CREATE UNIQUE INDEX IF NOT EXISTS uq_n009_conversa_mensagem_externa
    ON n009 (cod008, ide009)
    WHERE ide009 IS NOT NULL;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'fk_n008_n005'
    ) THEN
        ALTER TABLE n008
            ADD CONSTRAINT fk_n008_n005
            FOREIGN KEY (cod005) REFERENCES n005(cod005)
            ON UPDATE CASCADE ON DELETE SET NULL;
    END IF;
END $$;

COMMENT ON COLUMN n003.api003 IS
'Hash BCrypt da chave do webhook genérico do canal. A chave pura não é armazenada.';
COMMENT ON COLUMN n007.ide007 IS
'Identificador externo do cliente recebido pelo webhook.';
COMMENT ON COLUMN n008.cod005 IS
'Base de conhecimento usada pela conversa recebida.';
COMMENT ON COLUMN n009.ide009 IS
'Identificador externo da mensagem, usado para idempotência do webhook.';
