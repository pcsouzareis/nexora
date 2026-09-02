ALTER TABLE n011
    DROP CONSTRAINT IF EXISTS ck_n011_sts011;

ALTER TABLE n011
    ADD CONSTRAINT ck_n011_sts011
    CHECK (sts011 IN (
        'Pendente',
        'Aceito',
        'Transferido',
        'Encerrado',
        'Aceita',
        'Recusada',
        'Cancelada',
        'Concluída'
    ));
