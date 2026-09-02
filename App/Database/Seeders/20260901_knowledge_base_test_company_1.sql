-- ============================================================================
-- NEXORA - Base de aprendizado de teste para a empresa 1
--
-- ATENÇÃO: este script apaga TODAS as bases (n005) e artigos (n006) atuais.
-- Execute somente em ambiente de teste ou quando essa limpeza for desejada.
-- Canais e conversas são preservados, porém deixam de apontar para as bases
-- removidas.
-- ============================================================================

BEGIN;

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM n001 WHERE cod001 = 1) THEN
        RAISE EXCEPTION 'A empresa de código 1 não foi encontrada.';
    END IF;
END $$;

-- Evita violação de chave estrangeira ao remover as bases anteriores.
UPDATE n003 SET cod005 = NULL WHERE cod005 IS NOT NULL;
UPDATE n008 SET cod005 = NULL WHERE cod005 IS NOT NULL;

-- n005 é referenciada por canais e conversas. PostgreSQL não permite seu
-- TRUNCATE isolado mesmo após os vínculos serem removidos; por isso os
-- artigos são truncados e as bases são removidas com DELETE, preservando
-- os demais módulos. A sequência da base é reinicializada em seguida.
TRUNCATE TABLE n006 RESTART IDENTITY;
DELETE FROM n005;
SELECT setval(pg_get_serial_sequence('n005', 'cod005'), 1, FALSE);

INSERT INTO n005 (cod001, des005, sts005)
VALUES (1, 'Base de Atendimento - Perguntas Frequentes', TRUE);

INSERT INTO n006 (cod005, tit006, con006, vis006, sts006)
SELECT
    cod005,
    'Perguntas frequentes de atendimento',
    $conteudo$
Esta base orienta o atendimento inicial aos clientes. Responda de forma cordial, objetiva e em português do Brasil. Quando a dúvida não estiver coberta abaixo, informe que será necessário o apoio de um atendente humano. Não invente informações, prazos ou políticas.

1. Qual é o horário de atendimento?
Resposta: O atendimento funciona de segunda a sexta-feira, das 8h às 18h, exceto feriados.

2. Como faço para criar minha conta?
Resposta: Solicite o cadastro ao responsável pela sua empresa. Após receber o código de usuário e a senha inicial, acesse a tela de login para entrar.

3. Esqueci minha senha. Como recupero o acesso?
Resposta: Na tela de login, informe o código de usuário. Se já estiver conectado, use o menu Minha senha para trocar a senha informando a senha atual.

4. Posso alterar minha senha?
Resposta: Sim. Acesse Minha senha, informe a senha atual e depois a nova senha com a confirmação.

5. Qual é a senha inicial de um novo usuário?
Resposta: A senha inicial é o próprio código do usuário. Por segurança, ela deve ser alterada no primeiro acesso.

6. Como atualizo meus dados de contato?
Resposta: Solicite a atualização ao administrador ou supervisor responsável pelo cadastro de usuários da empresa.

7. Como abrir uma conversa de atendimento?
Resposta: Envie uma mensagem por um canal disponível, como Webchat, WhatsApp, Telegram, E-mail, Facebook ou Instagram, quando configurado pela empresa.

8. Posso acompanhar uma conversa já iniciada?
Resposta: Sim. A equipe autorizada pode abrir o menu Conversas, localizar o atendimento e consultar todo o histórico de mensagens.

9. O que significa o status Aguardando?
Resposta: Significa que o cliente enviou uma mensagem e aguarda uma nova resposta da equipe ou da IA.

10. O que significa o status Em Atendimento?
Resposta: Significa que um atendente assumiu a conversa. Nesse momento, a IA fica pausada para aquela conversa.

11. Como encerro uma conversa?
Resposta: Abra a conversa e utilize o botão Encerrar conversa. Depois do encerramento, novas respostas devem ser iniciadas em um novo atendimento quando necessário.

12. A IA responde todas as perguntas automaticamente?
Resposta: A IA responde apenas quando a configuração está ativa, o canal está habilitado e existe conteúdo público e ativo na base de aprendizado relacionada.

13. Como melhorar as respostas da IA?
Resposta: Inclua artigos claros, atualizados e objetivos na base de aprendizado. Use títulos descritivos e informe regras, procedimentos, prazos e contatos corretos.

14. Quem pode criar artigos na base de aprendizado?
Resposta: Somente usuários que possuam a permissão de acesso e manutenção da Base de aprendizado podem criar ou alterar artigos.

15. O que significa um artigo Público?
Resposta: Um artigo Público pode ser usado pela IA para responder clientes em canais externos.

16. O que significa um artigo Interno?
Resposta: Um artigo Interno é destinado à equipe e não deve ser usado como referência pública automática ao cliente.

17. O que significa um artigo Restrito?
Resposta: Um artigo Restrito contém informação sensível e deve ser acessado somente por usuários autorizados.

18. Posso desativar temporariamente um artigo?
Resposta: Sim. Altere o status do artigo para Inativo. Ele será preservado, mas deixará de ser utilizado nas respostas da IA.

19. Posso desativar toda a base de aprendizado?
Resposta: Sim. Altere o status da base para Inativa. Os artigos permanecerão cadastrados, mas a base não será utilizada pela IA.

20. Como testar a configuração da IA?
Resposta: Acesse Configuração da IA e use o botão Testar conexão. Confirme a URL, a chave da API e o modelo configurado para a empresa atual.

21. O que fazer quando a IA não souber responder?
Resposta: Informe ao cliente que a informação será verificada e ofereça encaminhamento para um atendente humano.

22. Minhas informações são seguras?
Resposta: As credenciais configuradas no Nexora são armazenadas de forma protegida. Nunca envie senhas, chaves de API ou dados sensíveis pelo chat.

23. Como informo um problema técnico?
Resposta: Descreva o problema, informe a tela onde ocorreu, a data e hora aproximadas e, se possível, envie uma captura de tela ao responsável pelo suporte.

24. Como falar com um atendente humano?
Resposta: Solicite atendimento humano durante a conversa. A equipe responsável dará continuidade assim que estiver disponível.
$conteudo$,
    1,
    TRUE
FROM n005
WHERE cod001 = 1
  AND des005 = 'Base de Atendimento - Perguntas Frequentes';

COMMIT;
