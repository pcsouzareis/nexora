-- WhatsApp Cloud API reutiliza os campos Meta existentes em n003:
-- pag003 = Phone Number ID, met003 = token criptografado e sec003 = App Secret criptografado.
-- O tipo do canal continua em tip003 para separar Cloud API da Z-API.

COMMENT ON COLUMN n003.pag003 IS 'ID da Página, conta profissional Instagram ou Phone Number ID do WhatsApp Cloud.';
COMMENT ON COLUMN n003.met003 IS 'Token de acesso Meta/WhatsApp Cloud criptografado.';
COMMENT ON COLUMN n003.sec003 IS 'App Secret Meta criptografado para validar webhooks.';