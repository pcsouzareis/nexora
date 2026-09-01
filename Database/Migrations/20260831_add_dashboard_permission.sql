-- Permissão D0: acesso ao Dashboard.
-- Mantém o comportamento atual: Administrador, Supervisor e Atendente
-- podem acessar o Dashboard; os demais acessos são controlados por E0-E3 e U0-U3.

UPDATE n014
SET ace014 = CASE
    WHEN BTRIM(ace014) = '' THEN 'D0'
    ELSE 'D0|' || ace014
END
WHERE des014 IN ('Administrador', 'Supervisor', 'Atendente')
  AND NOT ('D0' = ANY (STRING_TO_ARRAY(ace014, '|')));
