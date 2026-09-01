-- Relaciona a resposta do chatbot à mensagem recebida que a originou.
ALTER TABLE n009 ADD COLUMN IF NOT EXISTS ref009 BIGINT;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'fk_n009_ref009'
    ) THEN
        ALTER TABLE n009
            ADD CONSTRAINT fk_n009_ref009
            FOREIGN KEY (ref009) REFERENCES n009(cod009)
            ON UPDATE CASCADE ON DELETE CASCADE;
    END IF;
END $$;

CREATE UNIQUE INDEX IF NOT EXISTS uq_n009_resposta_chatbot
    ON n009 (ref009)
    WHERE ref009 IS NOT NULL AND ori009 = 'Chatbot';

COMMENT ON COLUMN n009.ref009 IS
'Código da mensagem que originou esta resposta. Usado para vincular a resposta do chatbot à mensagem do cliente.';
