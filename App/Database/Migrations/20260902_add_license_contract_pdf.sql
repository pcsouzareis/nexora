ALTER TABLE n021
    ADD COLUMN IF NOT EXISTS pdf021 VARCHAR(500) NULL;

COMMENT ON COLUMN n021.pdf021 IS 'Caminho relativo do PDF imutável gerado no momento do aceite do contrato.';
