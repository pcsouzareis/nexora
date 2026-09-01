-- Mantém o responsável pela criação de empresas e usuários.
-- Administradores continuam com acesso global; supervisores enxergam apenas
-- os registros cujo criador é o próprio supervisor.

ALTER TABLE n001
    ADD COLUMN IF NOT EXISTS cri001 BIGINT NULL
        REFERENCES n002(cod002) ON UPDATE CASCADE ON DELETE SET NULL;

ALTER TABLE n002
    ADD COLUMN IF NOT EXISTS cri002 BIGINT NULL
        REFERENCES n002(cod002) ON UPDATE CASCADE ON DELETE SET NULL;

CREATE INDEX IF NOT EXISTS ix_n001_criador
    ON n001 (cri001);

CREATE INDEX IF NOT EXISTS ix_n002_criador
    ON n002 (cri002);

-- Aproveita a auditoria para preencher os cadastros criados após a ativação
-- do módulo de auditoria. Registros antigos sem auditoria permanecem sem dono
-- e serão visíveis somente ao Administrador.
UPDATE n001 empresa
SET cri001 = auditoria.cod002
FROM (
    SELECT DISTINCT ON (ref017) ref017, cod002
    FROM n017
    WHERE aca017 = 'CREATE'
      AND ent017 = 'Empresa'
      AND ref017 IS NOT NULL
      AND cod002 IS NOT NULL
    ORDER BY ref017, cad017 ASC, cod017 ASC
) auditoria
WHERE empresa.cod001 = auditoria.ref017
  AND empresa.cri001 IS NULL;

UPDATE n002 usuario
SET cri002 = auditoria.cod002
FROM (
    SELECT DISTINCT ON (ref017) ref017, cod002
    FROM n017
    WHERE aca017 = 'CREATE'
      AND ent017 = 'Usuário'
      AND ref017 IS NOT NULL
      AND cod002 IS NOT NULL
    ORDER BY ref017, cad017 ASC, cod017 ASC
) auditoria
WHERE usuario.cod002 = auditoria.ref017
  AND usuario.cri002 IS NULL;

COMMENT ON COLUMN n001.cri001 IS 'Código do usuário (n002) que criou a empresa.';
COMMENT ON COLUMN n002.cri002 IS 'Código do usuário (n002) que criou o usuário.';
