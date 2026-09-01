-- Vínculo de empresas que cada Supervisor pode administrar.
CREATE TABLE IF NOT EXISTS n015 (
    cod015 SERIAL PRIMARY KEY,
    cod002 INTEGER NOT NULL REFERENCES n002(cod002) ON DELETE CASCADE,
    cod001 INTEGER NOT NULL REFERENCES n001(cod001) ON DELETE CASCADE,
    sts015 BOOLEAN NOT NULL DEFAULT TRUE,
    cad015 TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atu015 TIMESTAMP NULL,
    CONSTRAINT uq015_usuario_empresa UNIQUE (cod002, cod001)
);

CREATE INDEX IF NOT EXISTS idx015_usuario_ativo
    ON n015 (cod002, sts015);

-- Preserva a empresa principal dos Supervisores já cadastrados.
INSERT INTO n015 (cod002, cod001, sts015)
SELECT u.cod002, u.cod001, TRUE
FROM n002 u
WHERE u.rol002 = 'S'
ON CONFLICT (cod002, cod001)
DO UPDATE SET sts015 = TRUE, atu015 = CURRENT_TIMESTAMP;

-- Permite que Supervisores criem empresas e recebam o vínculo automaticamente.
UPDATE n014
SET ace014 = BTRIM(ace014) || '|E1'
WHERE des014 = 'Supervisor'
  AND BTRIM(COALESCE(ace014, '')) <> ''
  AND NOT ('E1' = ANY (STRING_TO_ARRAY(ace014, '|')));

COMMENT ON TABLE n015 IS 'Empresas autorizadas para cada Supervisor.';
COMMENT ON COLUMN n015.cod002 IS 'Código do usuário Supervisor.';
COMMENT ON COLUMN n015.cod001 IS 'Código da empresa autorizada para o Supervisor.';
COMMENT ON COLUMN n015.sts015 IS 'Indica se o vínculo Supervisor x Empresa está ativo.';
