-- Configuração pública do Webchat por canal.
ALTER TABLE n003 ADD COLUMN IF NOT EXISTS cod005 BIGINT;
ALTER TABLE n003 ADD COLUMN IF NOT EXISTS pub003 VARCHAR(64);

CREATE UNIQUE INDEX IF NOT EXISTS uq_n003_token_publico
    ON n003 (pub003)
    WHERE pub003 IS NOT NULL;

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_n003_n005') THEN
        ALTER TABLE n003
            ADD CONSTRAINT fk_n003_n005
            FOREIGN KEY (cod005) REFERENCES n005(cod005)
            ON UPDATE CASCADE ON DELETE SET NULL;
    END IF;
END $$;

COMMENT ON COLUMN n003.cod005 IS
'Base de conhecimento padrão usada pelo Webchat deste canal.';
COMMENT ON COLUMN n003.pub003 IS
'Token público não secreto do widget Webchat. Deve ser longo e difícil de adivinhar.';
