-- Nexora - documentação funcional do schema PostgreSQL.
-- Este arquivo apenas adiciona comentários; não altera dados, índices ou estruturas.

BEGIN;

COMMENT ON TABLE n001 IS 'Empresas cadastradas na plataforma Nexora.';
COMMENT ON COLUMN n001.cod001 IS 'Identificador único da empresa.';
COMMENT ON COLUMN n001.des001 IS 'Razão social ou nome de exibição da empresa.';
COMMENT ON COLUMN n001.doc001 IS 'CPF ou CNPJ da empresa.';
COMMENT ON COLUMN n001.ema001 IS 'E-mail principal da empresa.';
COMMENT ON COLUMN n001.tel001 IS 'Telefone principal da empresa.';
COMMENT ON COLUMN n001.log001 IS 'Caminho ou referência da logomarca da empresa.';
COMMENT ON COLUMN n001.sts001 IS 'Indica se a empresa está ativa na plataforma.';
COMMENT ON COLUMN n001.cad001 IS 'Data e hora de cadastro da empresa.';
COMMENT ON COLUMN n001.atu001 IS 'Data e hora da última atualização da empresa.';
COMMENT ON COLUMN n001.cri001 IS 'Usuário que criou o cadastro da empresa.';

COMMENT ON TABLE n002 IS 'Usuários internos da plataforma Nexora.';
COMMENT ON COLUMN n002.cod002 IS 'Identificador único do usuário.';
COMMENT ON COLUMN n002.cod001 IS 'Empresa principal vinculada ao usuário.';
COMMENT ON COLUMN n002.des002 IS 'Nome do usuário.';
COMMENT ON COLUMN n002.ema002 IS 'E-mail do usuário.';
COMMENT ON COLUMN n002.rol002 IS 'Código temporário do papel do usuário: D administrador, S supervisor ou A atendente.';
COMMENT ON COLUMN n002.sts002 IS 'Indica se o usuário está ativo e pode autenticar.';
COMMENT ON COLUMN n002.cad002 IS 'Data e hora de cadastro do usuário.';
COMMENT ON COLUMN n002.atu002 IS 'Data e hora da última atualização do usuário.';
COMMENT ON COLUMN n002.sen002 IS 'Hash da senha do usuário; nunca armazena a senha em texto puro.';
COMMENT ON COLUMN n002.cod014 IS 'Perfil de acesso do usuário, vinculado à tabela n014.';
COMMENT ON COLUMN n002.cri002 IS 'Usuário responsável pela criação deste usuário.';

COMMENT ON TABLE n003 IS 'Canais de comunicação vinculados às empresas.';
COMMENT ON COLUMN n003.cod003 IS 'Identificador único do canal.';
COMMENT ON COLUMN n003.cod001 IS 'Empresa proprietária do canal.';
COMMENT ON COLUMN n003.des003 IS 'Descrição amigável do canal.';
COMMENT ON COLUMN n003.tip003 IS 'Tipo do canal: Web, WhatsApp, Facebook, Instagram, E-Mail, Telegram ou Outro.';
COMMENT ON COLUMN n003.api003 IS 'Hash da chave secreta usada para autenticar webhooks genéricos.';
COMMENT ON COLUMN n003.sts003 IS 'Indica se o canal está ativo.';
COMMENT ON COLUMN n003.cad003 IS 'Data e hora de cadastro do canal.';
COMMENT ON COLUMN n003.atu003 IS 'Data e hora da última atualização do canal.';
COMMENT ON COLUMN n003.cod005 IS 'Base de conhecimento padrão usada pelo canal.';
COMMENT ON COLUMN n003.pub003 IS 'Token público do Webchat ou token de rota do canal.';
COMMENT ON COLUMN n003.ins003 IS 'Identificador da instância externa, como a instância Z-API.';
COMMENT ON COLUMN n003.tok003 IS 'Token criptografado da integração Z-API.';
COMMENT ON COLUMN n003.cli003 IS 'Client-Token criptografado da integração Z-API.';
COMMENT ON COLUMN n003.out003 IS 'Indica se respostas humanas podem ser enviadas automaticamente pela Z-API.';
COMMENT ON COLUMN n003.pag003 IS 'ID da página Facebook ou da conta profissional Instagram.';
COMMENT ON COLUMN n003.met003 IS 'Access Token da Meta armazenado de forma criptografada.';
COMMENT ON COLUMN n003.sec003 IS 'App Secret da Meta armazenado de forma criptografada.';
COMMENT ON COLUMN n003.outmet003 IS 'Indica se respostas automáticas pela Meta estão habilitadas.';
COMMENT ON COLUMN n003.imh003 IS 'Host do servidor IMAP do canal de e-mail.';
COMMENT ON COLUMN n003.imp003 IS 'Porta do servidor IMAP.';
COMMENT ON COLUMN n003.ime003 IS 'Método de segurança IMAP: ssl, tls ou none.';
COMMENT ON COLUMN n003.imu003 IS 'Usuário da conta IMAP.';
COMMENT ON COLUMN n003.imw003 IS 'Senha IMAP armazenada de forma criptografada.';
COMMENT ON COLUMN n003.smh003 IS 'Host do servidor SMTP do canal de e-mail.';
COMMENT ON COLUMN n003.smp003 IS 'Porta do servidor SMTP.';
COMMENT ON COLUMN n003.sme003 IS 'Método de segurança SMTP: ssl, tls ou none.';
COMMENT ON COLUMN n003.smu003 IS 'Usuário da conta SMTP.';
COMMENT ON COLUMN n003.smw003 IS 'Senha SMTP armazenada de forma criptografada.';
COMMENT ON COLUMN n003.outema003 IS 'Indica se respostas automáticas podem ser enviadas por SMTP.';
COMMENT ON COLUMN n003.bot003 IS 'Token criptografado do bot Telegram.';
COMMENT ON COLUMN n003.upt003 IS 'Identificador da última atualização processada do Telegram.';
COMMENT ON COLUMN n003.outtel003 IS 'Indica se respostas automáticas pelo Telegram estão habilitadas.';

COMMENT ON TABLE n004 IS 'Perfis ou configurações de comportamento de IA por empresa.';
COMMENT ON COLUMN n004.cod004 IS 'Identificador único da configuração de IA.';
COMMENT ON COLUMN n004.cod001 IS 'Empresa proprietária da configuração.';
COMMENT ON COLUMN n004.des004 IS 'Descrição do perfil de IA.';
COMMENT ON COLUMN n004.mod004 IS 'Modelo de IA associado ao perfil.';
COMMENT ON COLUMN n004.ins004 IS 'Instruções ou prompt principal do perfil.';
COMMENT ON COLUMN n004.tem004 IS 'Temperatura usada pelo modelo de IA.';
COMMENT ON COLUMN n004.sts004 IS 'Indica se o perfil de IA está ativo.';
COMMENT ON COLUMN n004.cad004 IS 'Data e hora de cadastro.';
COMMENT ON COLUMN n004.atu004 IS 'Data e hora da última atualização.';

COMMENT ON TABLE n005 IS 'Bases de conhecimento usadas no aprendizado e nas respostas da IA.';
COMMENT ON COLUMN n005.cod005 IS 'Identificador único da base de conhecimento.';
COMMENT ON COLUMN n005.cod001 IS 'Empresa proprietária da base.';
COMMENT ON COLUMN n005.des005 IS 'Descrição da base de conhecimento.';
COMMENT ON COLUMN n005.sts005 IS 'Indica se a base está disponível para uso.';
COMMENT ON COLUMN n005.cad005 IS 'Data e hora de cadastro da base.';
COMMENT ON COLUMN n005.atu005 IS 'Data e hora da última atualização da base.';
COMMENT ON COLUMN n005.mod005 IS 'Modelo de IA específico da base; vazio usa a configuração da empresa.';
COMMENT ON COLUMN n005.tmp005 IS 'Temperatura específica da base; nula usa a configuração da empresa.';
COMMENT ON COLUMN n005.lim005 IS 'Limite de tokens específico da base; nulo usa a configuração da empresa.';
COMMENT ON COLUMN n005.ins005 IS 'Instruções específicas da base; nulas usam a configuração da empresa.';
COMMENT ON COLUMN n005.msg005 IS 'Mensagem de boas-vindas específica da base.';
COMMENT ON COLUMN n005.msgfim005 IS 'Mensagem de despedida específica da base.';
COMMENT ON COLUMN n005.nkh005 IS 'URL do webhook n8n associado à base de conhecimento.';

COMMENT ON TABLE n006 IS 'Artigos de conhecimento que compõem uma base de aprendizado.';
COMMENT ON COLUMN n006.cod006 IS 'Identificador único do artigo.';
COMMENT ON COLUMN n006.cod005 IS 'Base de conhecimento à qual o artigo pertence.';
COMMENT ON COLUMN n006.tit006 IS 'Título do artigo.';
COMMENT ON COLUMN n006.con006 IS 'Conteúdo textual usado como conhecimento pela IA.';
COMMENT ON COLUMN n006.url006 IS 'URL de referência externa do artigo, quando existir.';
COMMENT ON COLUMN n006.vis006 IS 'Visibilidade do artigo: 1 público, 2 interno ou 3 restrito.';
COMMENT ON COLUMN n006.sts006 IS 'Indica se o artigo está ativo para consulta.';
COMMENT ON COLUMN n006.cad006 IS 'Data e hora de cadastro do artigo.';
COMMENT ON COLUMN n006.atu006 IS 'Data e hora da última atualização do artigo.';
COMMENT ON COLUMN n006.ext006 IS 'Identificador externo do conteúdo importado.';
COMMENT ON COLUMN n006.sha006 IS 'Hash do conteúdo para detectar alterações em importações.';
COMMENT ON COLUMN n006.ori006 IS 'Origem do artigo, como manual ou n8n.';

COMMENT ON TABLE n007 IS 'Clientes ou contatos externos das empresas.';
COMMENT ON COLUMN n007.cod007 IS 'Identificador único do cliente.';
COMMENT ON COLUMN n007.cod001 IS 'Empresa à qual o cliente pertence.';
COMMENT ON COLUMN n007.des007 IS 'Nome do cliente ou contato.';
COMMENT ON COLUMN n007.doc007 IS 'CPF, CNPJ ou documento do cliente.';
COMMENT ON COLUMN n007.ema007 IS 'E-mail do cliente.';
COMMENT ON COLUMN n007.tel007 IS 'Telefone do cliente.';
COMMENT ON COLUMN n007.sts007 IS 'Indica se o cliente está ativo.';
COMMENT ON COLUMN n007.cad007 IS 'Data e hora de cadastro do cliente.';
COMMENT ON COLUMN n007.atu007 IS 'Data e hora da última atualização do cliente.';
COMMENT ON COLUMN n007.ide007 IS 'Identificador externo do cliente no canal de origem.';

COMMENT ON TABLE n008 IS 'Conversas de atendimento entre clientes, IA e atendentes.';
COMMENT ON COLUMN n008.cod008 IS 'Identificador único da conversa.';
COMMENT ON COLUMN n008.cod001 IS 'Empresa proprietária da conversa.';
COMMENT ON COLUMN n008.cod007 IS 'Cliente participante da conversa.';
COMMENT ON COLUMN n008.cod004 IS 'Perfil de IA associado à conversa, quando aplicável.';
COMMENT ON COLUMN n008.cod003 IS 'Canal de comunicação da conversa.';
COMMENT ON COLUMN n008.cod002 IS 'Atendente atualmente responsável pela conversa.';
COMMENT ON COLUMN n008.ide008 IS 'Identificador externo ou de sessão da conversa.';
COMMENT ON COLUMN n008.sts008 IS 'Status operacional da conversa: Aberta, Aguardando, Em Atendimento, Encerrada ou Cancelada.';
COMMENT ON COLUMN n008.pri008 IS 'Prioridade operacional da conversa.';
COMMENT ON COLUMN n008.ini008 IS 'Data e hora de início da conversa.';
COMMENT ON COLUMN n008.fim008 IS 'Data e hora de encerramento da conversa.';
COMMENT ON COLUMN n008.cod005 IS 'Base de conhecimento usada como contexto da conversa.';
COMMENT ON COLUMN n008.web008 IS 'Última atividade do Webchat para indicar presença online.';

COMMENT ON TABLE n009 IS 'Mensagens trocadas dentro das conversas.';
COMMENT ON COLUMN n009.cod009 IS 'Identificador único da mensagem.';
COMMENT ON COLUMN n009.cod008 IS 'Conversa à qual a mensagem pertence.';
COMMENT ON COLUMN n009.cod002 IS 'Usuário atendente que enviou a mensagem, quando houver.';
COMMENT ON COLUMN n009.con009 IS 'Conteúdo textual da mensagem.';
COMMENT ON COLUMN n009.arq009 IS 'Caminho do arquivo anexado à mensagem.';
COMMENT ON COLUMN n009.ori009 IS 'Origem da mensagem: Cliente, Chatbot ou Atendente.';
COMMENT ON COLUMN n009.tip009 IS 'Tipo de conteúdo da mensagem, como Texto, Imagem, Áudio ou Arquivo.';
COMMENT ON COLUMN n009.env009 IS 'Data e hora de envio ou recebimento da mensagem.';
COMMENT ON COLUMN n009.lid009 IS 'Indica se a mensagem foi lida.';
COMMENT ON COLUMN n009.tok009 IS 'Quantidade de tokens consumidos pela resposta de IA.';
COMMENT ON COLUMN n009.ide009 IS 'Identificador externo da mensagem para controle de duplicidade.';
COMMENT ON COLUMN n009.ref009 IS 'Mensagem de origem à qual esta resposta de IA se refere.';

COMMENT ON TABLE n010 IS 'Filas de atendimento humano por empresa.';
COMMENT ON COLUMN n010.cod010 IS 'Identificador único da fila.';
COMMENT ON COLUMN n010.cod001 IS 'Empresa proprietária da fila.';
COMMENT ON COLUMN n010.des010 IS 'Descrição da fila de atendimento.';
COMMENT ON COLUMN n010.pri010 IS 'Ordem de prioridade da fila.';
COMMENT ON COLUMN n010.sla010 IS 'Prazo alvo de atendimento da fila, em minutos.';
COMMENT ON COLUMN n010.sts010 IS 'Indica se a fila está ativa.';
COMMENT ON COLUMN n010.cad010 IS 'Data e hora de cadastro da fila.';
COMMENT ON COLUMN n010.atu010 IS 'Data e hora da última atualização da fila.';

COMMENT ON TABLE n011 IS 'Histórico de encaminhamento, aceite, transferência e encerramento da fila humana.';
COMMENT ON COLUMN n011.cod011 IS 'Identificador único do evento de fila.';
COMMENT ON COLUMN n011.cod008 IS 'Conversa vinculada ao evento de fila.';
COMMENT ON COLUMN n011.cod010 IS 'Fila de atendimento responsável pelo evento.';
COMMENT ON COLUMN n011.cod002 IS 'Atendente que assumiu o evento, quando aplicável.';
COMMENT ON COLUMN n011.mot011 IS 'Motivo ou observação do encaminhamento, transferência ou encerramento.';
COMMENT ON COLUMN n011.sts011 IS 'Status do evento: Pendente, Aceito, Transferido ou Encerrado.';
COMMENT ON COLUMN n011.enc011 IS 'Data e hora de encaminhamento para a fila.';
COMMENT ON COLUMN n011.ace011 IS 'Data e hora de aceite do atendimento pela pessoa responsável.';

COMMENT ON TABLE n012 IS 'Notificações operacionais relacionadas a clientes e conversas.';
COMMENT ON COLUMN n012.cod012 IS 'Identificador único da notificação.';
COMMENT ON COLUMN n012.cod008 IS 'Conversa relacionada à notificação.';
COMMENT ON COLUMN n012.cod007 IS 'Cliente destinatário ou relacionado à notificação.';
COMMENT ON COLUMN n012.not012 IS 'Código do tipo de notificação.';
COMMENT ON COLUMN n012.com012 IS 'Conteúdo, comentário ou detalhe da notificação.';
COMMENT ON COLUMN n012.dat012 IS 'Data e hora de geração da notificação.';

COMMENT ON TABLE n013 IS 'Configurações globais da inteligência artificial e limites por empresa.';
COMMENT ON COLUMN n013.cod013 IS 'Identificador único da configuração.';
COMMENT ON COLUMN n013.cod001 IS 'Empresa proprietária da configuração.';
COMMENT ON COLUMN n013.fuso013 IS 'Fuso horário padrão da empresa.';
COMMENT ON COLUMN n013.idio013 IS 'Idioma padrão das respostas.';
COMMENT ON COLUMN n013.temp013 IS 'Temperatura padrão do modelo de IA.';
COMMENT ON COLUMN n013.msg013 IS 'Mensagem padrão de boas-vindas.';
COMMENT ON COLUMN n013.msgfim013 IS 'Mensagem padrão de despedida.';
COMMENT ON COLUMN n013.sts013 IS 'Indica se a IA está ativa para a empresa.';
COMMENT ON COLUMN n013.cad013 IS 'Data e hora de cadastro da configuração.';
COMMENT ON COLUMN n013.atu013 IS 'Data e hora da última atualização da configuração.';
COMMENT ON COLUMN n013.mod013 IS 'Modelo de IA padrão da empresa.';
COMMENT ON COLUMN n013.ins013 IS 'Instruções ou prompt global da IA.';
COMMENT ON COLUMN n013.lim013 IS 'Limite padrão de tokens por resposta.';
COMMENT ON COLUMN n013.key013 IS 'Chave da API de IA armazenada de forma criptografada.';
COMMENT ON COLUMN n013.url013 IS 'URL base ou endpoint da API de IA.';
COMMENT ON COLUMN n013.jan013 IS 'Janela de tempo do rate limit do Webchat, em minutos.';
COMMENT ON COLUMN n013.lms013 IS 'Máximo de mensagens permitidas por sessão dentro da janela.';
COMMENT ON COLUMN n013.lmi013 IS 'Máximo de mensagens permitidas por IP dentro da janela.';

COMMENT ON TABLE n014 IS 'Perfis de acesso e permissões da plataforma.';
COMMENT ON COLUMN n014.cod014 IS 'Identificador do perfil de acesso.';
COMMENT ON COLUMN n014.des014 IS 'Nome descritivo do perfil.';
COMMENT ON COLUMN n014.ace014 IS 'Lista de códigos de permissões separados por barra vertical; nulo ou vazio concede acesso total.';

COMMENT ON TABLE n015 IS 'Vínculos entre supervisores e empresas que podem administrar.';
COMMENT ON COLUMN n015.cod015 IS 'Identificador único do vínculo.';
COMMENT ON COLUMN n015.cod002 IS 'Supervisor vinculado à empresa.';
COMMENT ON COLUMN n015.cod001 IS 'Empresa liberada para o supervisor.';
COMMENT ON COLUMN n015.sts015 IS 'Indica se o vínculo está ativo.';
COMMENT ON COLUMN n015.cad015 IS 'Data e hora de criação do vínculo.';
COMMENT ON COLUMN n015.atu015 IS 'Data e hora da última atualização do vínculo.';

COMMENT ON TABLE n016 IS 'Contadores de rate limit para sessões e endereços IP do Webchat.';
COMMENT ON COLUMN n016.cod016 IS 'Identificador único do contador de limite.';
COMMENT ON COLUMN n016.chv016 IS 'Chave composta que identifica o escopo do limite, como sessão ou IP.';
COMMENT ON COLUMN n016.jan016 IS 'Início da janela de tempo monitorada.';
COMMENT ON COLUMN n016.qtd016 IS 'Quantidade de mensagens registradas na janela.';

COMMENT ON TABLE n017 IS 'Trilha de auditoria das ações realizadas na plataforma.';
COMMENT ON COLUMN n017.cod017 IS 'Identificador único do evento de auditoria.';
COMMENT ON COLUMN n017.cod001 IS 'Empresa associada ao evento, quando aplicável.';
COMMENT ON COLUMN n017.cod002 IS 'Usuário responsável pelo evento, quando aplicável.';
COMMENT ON COLUMN n017.aca017 IS 'Ação executada, como CREATE, UPDATE, VIEW, REPLY ou CLOSE.';
COMMENT ON COLUMN n017.ent017 IS 'Entidade ou módulo afetado pelo evento.';
COMMENT ON COLUMN n017.ref017 IS 'Código de referência do registro afetado.';
COMMENT ON COLUMN n017.des017 IS 'Descrição detalhada do evento auditado.';
COMMENT ON COLUMN n017.ip017 IS 'Endereço IP de origem da ação.';
COMMENT ON COLUMN n017.cad017 IS 'Data e hora de registro do evento.';

COMMENT ON TABLE n018 IS 'Tentativas de entrega de mensagens enviadas pelo Nexora a canais externos.';
COMMENT ON COLUMN n018.cod018 IS 'Identificador único do registro de entrega.';
COMMENT ON COLUMN n018.cod001 IS 'Empresa proprietária da mensagem.';
COMMENT ON COLUMN n018.cod003 IS 'Canal externo utilizado para a entrega.';
COMMENT ON COLUMN n018.cod009 IS 'Mensagem interna enviada.';
COMMENT ON COLUMN n018.sta018 IS 'Status de entrega, como Pendente, Enviada, Entregue, Lida, Reproduzida ou Falha.';
COMMENT ON COLUMN n018.ext018 IS 'Identificador da mensagem retornado pelo provedor externo.';
COMMENT ON COLUMN n018.err018 IS 'Descrição do erro retornado pelo provedor, quando houver.';
COMMENT ON COLUMN n018.cad018 IS 'Data e hora de criação do registro de entrega.';
COMMENT ON COLUMN n018.atu018 IS 'Data e hora da última atualização do status de entrega.';

COMMENT ON TABLE n019 IS 'Histórico técnico de sincronizações, testes, recebimentos e falhas das integrações.';
COMMENT ON COLUMN n019.cod019 IS 'Identificador único do evento técnico.';
COMMENT ON COLUMN n019.cod001 IS 'Empresa proprietária da integração.';
COMMENT ON COLUMN n019.cod003 IS 'Canal associado ao evento técnico.';
COMMENT ON COLUMN n019.tip019 IS 'Tipo do evento: Sincronização, Envio, Teste ou Recebimento.';
COMMENT ON COLUMN n019.sts019 IS 'Resultado do evento: Sucesso ou Falha.';
COMMENT ON COLUMN n019.des019 IS 'Detalhe técnico, retorno ou mensagem de erro do evento.';
COMMENT ON COLUMN n019.cad019 IS 'Data e hora de registro do evento.';

COMMENT ON TABLE n020 IS 'Histórico de migrações de banco aplicadas pelo Nexora.';
COMMENT ON COLUMN n020.nom020 IS 'Nome do arquivo de migração aplicado.';
COMMENT ON COLUMN n020.sha020 IS 'Hash SHA-256 do arquivo de migração aplicado.';
COMMENT ON COLUMN n020.cad020 IS 'Data e hora de aplicação da migração.';

COMMENT ON TABLE n021 IS 'Controle de visualização e aceite do contrato de licença por supervisor e empresa.';
COMMENT ON COLUMN n021.cod021 IS 'Identificador único do registro de contrato.';
COMMENT ON COLUMN n021.cod001 IS 'Empresa cujo contrato foi apresentado.';
COMMENT ON COLUMN n021.cod002 IS 'Supervisor que visualizou ou aceitou o contrato.';
COMMENT ON COLUMN n021.cad021 IS 'Data e hora da primeira visualização do contrato.';
COMMENT ON COLUMN n021.ace021 IS 'Data e hora do aceite formal do contrato.';
COMMENT ON COLUMN n021.ver021 IS 'Versão do contrato aceita.';
COMMENT ON COLUMN n021.ip021 IS 'Endereço IP registrado no aceite.';
COMMENT ON COLUMN n021.pdf021 IS 'Caminho relativo do PDF imutável gerado no momento do aceite.';

COMMIT;
