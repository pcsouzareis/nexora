CREATE INDEX IF NOT EXISTS ix_n011_conversa_status_data
    ON n011 (cod008, sts011, enc011 DESC);

CREATE INDEX IF NOT EXISTS ix_n011_atendente_status
    ON n011 (cod002, sts011)
    WHERE cod002 IS NOT NULL;
