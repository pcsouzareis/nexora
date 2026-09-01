-- ============================================================================
-- NEXORA - dados consistentes para teste do webhook genérico
--
-- ATENÇÃO: este script APAGA os canais, bases/artigos de aprendizado, clientes,
-- conversas e mensagens atuais. Não execute em produção.
--
-- Preserva: empresas (n001), usuários/perfis (n002/n014) e configuração de IA
-- (n013). Os dados abaixo são vinculados à empresa de código 1.
-- ============================================================================

BEGIN;

-- Compatibilidade com a migration do webhook. Pode ser executado mais de uma
-- vez sem erro; garante que a conversa possa registrar a base utilizada.
ALTER TABLE n007 ADD COLUMN IF NOT EXISTS ide007 VARCHAR(120);
ALTER TABLE n008 ADD COLUMN IF NOT EXISTS cod005 BIGINT;
ALTER TABLE n009 ADD COLUMN IF NOT EXISTS ide009 VARCHAR(120);
ALTER TABLE n009 ADD COLUMN IF NOT EXISTS ref009 BIGINT;
ALTER TABLE n003 ADD COLUMN IF NOT EXISTS cod005 BIGINT;
ALTER TABLE n003 ADD COLUMN IF NOT EXISTS pub003 VARCHAR(64);

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

CREATE UNIQUE INDEX IF NOT EXISTS uq_n003_token_publico
    ON n003 (pub003)
    WHERE pub003 IS NOT NULL;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM n001
        WHERE cod001 = 1 AND sts001 = TRUE
    ) THEN
        RAISE EXCEPTION
            'A empresa ativa de código 1 é necessária para executar esta carga.';
    END IF;
END $$;

-- Limpa somente o escopo operacional que será recriado abaixo.
-- n011 e n012 são dependências de conversas e, por isso, entram no reset.
TRUNCATE TABLE
    n009,
    n012,
    n011,
    n008,
    n007,
    n006,
    n005,
    n003
RESTART IDENTITY;

-- ---------------------------------------------------------------------------
-- Canal público para receber o webhook.
-- Chave pura de teste: nexora-webhook-test-2026
-- A coluna api003 recebe APENAS o hash BCrypt; nunca grave a chave pura nela.
-- ---------------------------------------------------------------------------
INSERT INTO n003 (cod001, des003, tip003, api003, sts003)
VALUES (
    1,
    'Webhook de Teste',
    'Web',
    '$2y$12$e0CKumLHfql2DIsarqtEd.yf5GuP2ZuC.yrFtojYObEqIBrOnEsAO',
    TRUE
);

-- Base ativa que poderá ser informada como base_id no webhook.
INSERT INTO n005 (cod001, des005, sts005)
VALUES (1, 'Base de Teste do Webhook', TRUE);

-- Artigos ativos para a futura etapa de resposta da IA.
INSERT INTO n006 (cod005, tit006, con006, vis006, sts006)
SELECT
    b.cod005,
    'Horário de atendimento',
    'O atendimento da Nexora ocorre de segunda a sexta-feira, das 8h às 18h.',
    1,
    TRUE
FROM n005 b
WHERE b.cod001 = 1
  AND b.des005 = 'Base de Teste do Webhook';

UPDATE n003
SET cod005 = (
        SELECT cod005 FROM n005
        WHERE cod001 = 1 AND des005 = 'Base de Teste do Webhook'
    ),
    pub003 = 'nexora-webchat-teste-2026'
WHERE cod001 = 1 AND des003 = 'Webhook de Teste';

INSERT INTO n006 (cod005, tit006, con006, vis006, sts006)
SELECT
    b.cod005,
    'Encaminhamento para atendente',
    'Quando necessário, solicite os dados de contato do cliente e informe que um atendente dará continuidade ao atendimento.',
    2,
    TRUE
FROM n005 b
WHERE b.cod001 = 1
  AND b.des005 = 'Base de Teste do Webhook';

-- Cliente, conversa e mensagem já existentes: permitem conferir as listagens.
INSERT INTO n007 (cod001, des007, ema007, tel007, ide007, sts007)
VALUES (
    1,
    'Cliente de Teste',
    'cliente.teste@example.test',
    '+5585999990001',
    'cliente-teste-001',
    TRUE
);

INSERT INTO n008 (cod001, cod007, cod003, cod005, ide008, sts008, pri008)
SELECT
    1,
    cli.cod007,
    canal.cod003,
    base.cod005,
    'conversa-teste-001',
    'Aberta',
    3
FROM n007 cli
INNER JOIN n003 canal
    ON canal.cod001 = 1 AND canal.des003 = 'Webhook de Teste'
INNER JOIN n005 base
    ON base.cod001 = 1 AND base.des005 = 'Base de Teste do Webhook'
WHERE cli.cod001 = 1
  AND cli.ide007 = 'cliente-teste-001';

INSERT INTO n009 (cod008, con009, ori009, tip009, ide009, lid009)
SELECT
    c.cod008,
    'Olá! Esta é uma mensagem existente para conferência da carga.',
    'Cliente',
    'Texto',
    'mensagem-seed-001',
    FALSE
FROM n008 c
WHERE c.ide008 = 'conversa-teste-001';

COMMIT;

-- Dados para a chamada do webhook:
-- URL: http://chat.net/api/webhooks/1
-- Header: X-Nexora-Webhook-Key: nexora-webhook-test-2026
-- Payload deve usar base_id = 1 e um message_id ainda não utilizado.

SELECT cod003 AS canal_id, cod001 AS empresa_id, des003, tip003, sts003 AS ativo
FROM n003
ORDER BY cod003;

SELECT cod005 AS base_id, cod001 AS empresa_id, des005, sts005 AS ativa
FROM n005
ORDER BY cod005;

SELECT c.cod008 AS conversa_id, c.ide008 AS conversa_externa,
       cli.des007 AS cliente, b.des005 AS base, m.ide009 AS mensagem_externa
FROM n008 c
INNER JOIN n007 cli ON cli.cod007 = c.cod007
INNER JOIN n005 b ON b.cod005 = c.cod005
INNER JOIN n009 m ON m.cod008 = c.cod008
ORDER BY c.cod008, m.cod009;
