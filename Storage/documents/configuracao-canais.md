# Configuração dos canais de comunicação

Este documento descreve os canais disponíveis no Nexora e como configurá-los. Os canais são cadastrados em **Menu Sistema → Cadastro → Canais** e pertencem à **empresa atualmente selecionada**.

## Regras gerais

1. Informe um nome que identifique claramente o canal, por exemplo `WhatsApp Comercial`.
2. Mantenha o canal ativo somente depois de concluir os testes.
3. Para Webchat, WhatsApp, WhatsApp Cloud, Facebook, Instagram e Telegram, selecione uma **base de aprendizado ativa**. Ela define o conhecimento usado pela IA naquele canal.
4. Credenciais informadas na tela são armazenadas criptografadas e não são exibidas novamente. Ao trocar uma senha ou token, informe o novo valor e salve.
5. Nunca publique tokens, Client-Token, App Secret, chaves de webhook ou senhas em páginas, repositórios ou mensagens.

Substitua `https://seu-dominio` pelo domínio HTTPS público do Nexora nas URLs deste documento.

---

## 1. Webchat

Use o tipo **Web** para disponibilizar um chat no site da empresa.

### Cadastro

- **Nome do canal:** identificação exibida no Nexora.
- **Base padrão:** obrigatória; será usada nas mensagens do visitante.
- **Canal ativo:** precisa estar marcado para que o endereço público funcione.

Após salvar, a tela do canal exibe o caminho público:

```text
/chat/{token-publico}
```

Exemplo de uso em um iframe:

```html
<iframe
  src="https://seu-dominio/chat/{token-publico}"
  title="Atendimento"
  width="100%"
  height="650"
  style="border: 0">
</iframe>
```

### Operação

O webchat possui limite de mensagens por sessão e por IP, conforme a **Configuração da IA** da empresa. O botão **Gerar novo token público** invalida imediatamente o endereço anterior; atualize o site após usá-lo.

---

## 2. WhatsApp via Z-API

Use o tipo **WhatsApp** para receber mensagens pela Z-API e enviar respostas automáticas ou respostas do atendimento humano.

### Cadastro

- **Base padrão:** obrigatória.
- **ID da instância:** identificador da instância Z-API.
- **Token da instância:** token da instância Z-API.
- **Client-Token:** Client-Token configurado na Z-API.
- **Enviar respostas humanas automaticamente pela Z-API:** habilita a entrega de respostas registradas pelo operador humano.

Para habilitar o envio, os três dados da Z-API são obrigatórios: ID da instância, Token e Client-Token.

### Webhooks da Z-API

Depois de salvar o canal, configure estes três endereços no painel da instância Z-API:

```text
Recebimento: https://seu-dominio/api/zapi/{token-do-canal}/receber
Entrega:     https://seu-dominio/api/zapi/{token-do-canal}/entrega
Status:      https://seu-dominio/api/zapi/{token-do-canal}/status
```

Todos devem usar HTTPS. O token é gerado pelo Nexora e fica visível no card **Webhooks Z-API** da tela do canal.

### Comportamento

- Mensagens enviadas pelo próprio número, de grupos, newsletters e eventos de status são ignorados no recebimento.
- Mensagens de texto recebidas geram ou atualizam uma conversa no Nexora.
- Entrega, leitura e falhas reportadas pela Z-API atualizam o histórico de envio quando o provedor envia os eventos correspondentes.

---

## 3. Facebook Messenger

Use o tipo **Facebook** para mensagens de uma Página do Facebook conectada a um aplicativo Meta.

### Cadastro

- **Base padrão:** obrigatória.
- **ID da Página:** identificador numérico da Página Facebook.
- **Access Token:** token de acesso da Página/aplicativo, com permissões de mensagens.
- **App Secret:** segredo do aplicativo Meta.
- **Enviar respostas automaticamente pela Meta:** habilita respostas automáticas enviadas pelo Nexora.

### Webhook Meta

O mesmo endereço atende a verificação e o recebimento:

```text
https://seu-dominio/api/meta/{token-do-canal}
```

No painel Meta, informe esse endereço como **Callback URL** e use o mesmo `{token-do-canal}` como **Verify Token**. O Nexora valida a assinatura `X-Hub-Signature-256` com o App Secret antes de processar uma mensagem.

Assine os eventos de mensagens da Página no aplicativo Meta. Utilize uma Página e um aplicativo que tenham acesso à API Messenger conforme as exigências da Meta.

---

## 4. WhatsApp Cloud API

Use o tipo **WhatsApp Cloud** para conectar um número do WhatsApp Business diretamente à plataforma Meta, sem a Z-API.

### Cadastro

- **Base padrão:** obrigatória.
- **Phone Number ID:** identificador do número no WhatsApp Manager, não é o número de telefone.
- **Access Token:** token permanente do sistema com permissões de WhatsApp Business.
- **App Secret:** segredo do aplicativo Meta, usado para validar a assinatura do webhook.
- **Enviar respostas automaticamente pela Meta:** habilita as respostas da IA pela Cloud API.

### Webhook

Na configuração do aplicativo Meta, use o endereço exibido no canal:

```text
https://seu-dominio/api/meta/{token-do-canal}
```

Use o mesmo `{token-do-canal}` como Verify Token e assine o campo `messages` do produto WhatsApp. O servidor precisa estar publicado em HTTPS. O Nexora valida `X-Hub-Signature-256`, recebe mensagens de texto e responde pelo endpoint Graph API do Phone Number ID.

### Configuração no Meta Developers

1. Crie ou selecione um aplicativo do tipo **Business** no Meta Developers.
2. Adicione o produto **WhatsApp** e vincule o número de telefone do WhatsApp Business.
3. No produto WhatsApp, abra a configuração de webhooks e informe a **Callback URL** e o **Verify Token** exibidos no canal do Nexora.
4. Conclua a verificação e assine o campo `messages` para a conta do WhatsApp Business.
5. Gere um token permanente de sistema com as permissões `whatsapp_business_management` e `whatsapp_business_messaging`.
6. Cadastre no Nexora o **Phone Number ID**, o token permanente e o **App Secret** do aplicativo.

O endereço Cloud usa apenas `/api/meta/{token-do-canal}`. Não configure `/api/zapi/.../receber`, `/entrega` ou `/status` para este tipo de canal. A Cloud API não usa Instance ID, Token da Z-API ou Client-Token.

### Mensagens e limitações

- O Nexora processa mensagens de texto recebidas pelo evento `messages`.
- Eventos de status, mídia e mensagens sem texto são reconhecidos pelo webhook, mas não geram processamento de texto.
- Para iniciar uma conversa fora da janela de atendimento da Meta, use um template aprovado pela Meta; respostas livres são destinadas às conversas dentro da janela permitida.
- O envio automático depende de **Enviar respostas automaticamente pela Meta** estar habilitado no canal.

---

## 5. Instagram Direct

Use o tipo **Instagram** para mensagens diretas da conta profissional conectada ao ecossistema Meta.

### Cadastro

- **Base padrão:** obrigatória.
- **ID da conta profissional do Instagram:** não é o @usuário; é o identificador da conta profissional.
- **Access Token:** token com permissões de mensagens para Instagram.
- **App Secret:** segredo do aplicativo Meta.
- **Enviar respostas automaticamente pela Meta:** habilita o envio automático.

### Webhook Meta

Use o mesmo formato do Facebook Messenger:

```text
https://seu-dominio/api/meta/{token-do-canal}
```

Cadastre a URL no aplicativo Meta, utilize o mesmo token como Verify Token e assine os eventos do Instagram necessários para mensagens. A conta deve ser profissional e estar corretamente vinculada ao aplicativo Meta.

---

## 6. Telegram

O tipo **Telegram** usa o bot criado no [@BotFather](https://t.me/BotFather). O Nexora recebe atualizações por sincronização (polling), e não por uma URL de webhook do Telegram.

### Cadastro

- **Base padrão:** obrigatória.
- **Token do bot:** token fornecido pelo BotFather.
- **Enviar respostas automáticas pelo Telegram:** quando ativo, envia a resposta da IA ao chat do usuário.

Após salvar, use **Testar bot**. O teste valida a credencial com a API do Telegram. Use **Sincronizar mensagens** para buscar manualmente as mensagens pendentes.

### Agendamento recomendado

Em produção, configure um cron a cada minuto:

```bash
* * * * * cd /home/USUARIO/public_html/nexora && /usr/bin/php bin/sync-telegram.php >> /home/USUARIO/logs/nexora-telegram.log 2>&1
```

Ajuste o caminho do PHP e da instalação conforme a hospedagem. O processo possui bloqueio para impedir duas sincronizações simultâneas.

---

## 7. E-mail (IMAP e SMTP)

Use o tipo **E-Mail** para ler mensagens não lidas de uma caixa postal e, quando habilitado, responder via SMTP.

### Pré-requisito do servidor

A extensão `imap` do PHP precisa estar habilitada:

```bash
php -r 'var_dump(function_exists("imap_open"));'
```

O resultado esperado é `bool(true)`.

### Cadastro

#### Recebimento IMAP

- **Servidor IMAP:** por exemplo, `imap.exemplo.com`.
- **Porta:** normalmente `993` para SSL.
- **Segurança:** `SSL`, `TLS` ou `NONE`.
- **Usuário IMAP:** normalmente o endereço completo da caixa postal.
- **Senha IMAP:** senha da caixa postal ou senha de aplicativo.

#### Envio SMTP

- **Servidor SMTP:** por exemplo, `smtp.exemplo.com`.
- **Porta:** normalmente `465` para SSL ou `587` para TLS.
- **Segurança:** `SSL`, `TLS` ou `NONE`.
- **Usuário SMTP:** normalmente o endereço completo remetente.
- **Senha SMTP:** senha da caixa postal ou senha de aplicativo.
- **Enviar respostas do Nexora automaticamente pelo SMTP:** habilita a resposta automática.

Use **Testar conexão** depois de salvar. O teste verifica a comunicação IMAP e SMTP usando as credenciais armazenadas.

### Agendamento recomendado

Configure um cron a cada minuto:

```bash
* * * * * cd /home/USUARIO/public_html/nexora && /usr/bin/php bin/sync-email.php >> /home/USUARIO/logs/nexora-email.log 2>&1
```

O sincronizador processa até 100 mensagens não lidas por canal em cada execução e marca as mensagens processadas como lidas.

---

## 7. Webhook genérico

Todo canal possui um endpoint genérico para integrações próprias, ERPs, automações ou outros provedores:

```text
POST https://seu-dominio/api/webhooks/{codigo-do-canal}
```

### Autenticação

Envie a chave do canal no cabeçalho:

```http
X-Nexora-Webhook-Key: {chave-gerada-no-canal}
Content-Type: application/json
```

A chave é exibida uma única vez ao criar ou regenerar. A ação **Gerar nova chave** invalida a chave anterior.

### Corpo JSON esperado

```json
{
  "external_id": "cliente-123",
  "name": "Nome do cliente",
  "conversation_id": "conversa-123",
  "message_id": "id-unico-da-mensagem",
  "base_id": 1,
  "message": "Olá, preciso de ajuda"
}
```

Campos obrigatórios: `external_id`, `message_id`, `base_id` e `message`. Se `conversation_id` não for informado, o Nexora cria um identificador baseado no cliente.

Use um `message_id` único por mensagem para evitar duplicidade. A `base_id` deve pertencer à mesma empresa do canal e estar disponível para o processamento.

---

## 8. Tipo Outro

O tipo **Outro** funciona como cadastro organizacional para provedores não atendidos diretamente. Para receber mensagens por uma integração própria, utilize o **Webhook genérico** e envie a `base_id` no JSON.

---

## Diagnóstico rápido

- **Canal não aparece para a IA:** confirme que o canal está ativo e vinculado a uma base ativa.
- **Erro 401 no webhook genérico:** confira o cabeçalho `X-Nexora-Webhook-Key` e gere nova chave se necessário.
- **Erro 404 no Webchat:** confirme que o token público é o atual e que o canal está ativo.
- **Telegram não recebe mensagens:** teste o bot, confirme o cron `sync-telegram.php` e verifique se há outro processo sincronizando.
- **E-mail não recebe mensagens:** confirme IMAP habilitado no PHP, credenciais, portas e cron `sync-email.php`.
- **Meta retorna não autorizado:** confira App Secret, Access Token, assinatura do webhook e URL HTTPS pública.
- **Z-API não entrega eventos:** confira os três webhooks no painel da instância e o domínio HTTPS usado nas URLs.

## Segurança e operação

- Use sempre HTTPS em produção.
- Restrinja o acesso ao cadastro de canais por permissões.
- Regenerar tokens ou chaves deve ser seguido da atualização imediata no provedor externo.
- Acompanhe falhas em **Menu Sistema → Administração → Integrações** e no histórico de **Auditoria**.
