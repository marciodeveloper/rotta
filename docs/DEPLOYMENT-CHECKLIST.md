# Checklist de publicação

Nenhuma etapa abaixo foi executada nesta entrega.

## Gate de homologação privada

- [ ] Aprovar aviso de privacidade, base legal, retenção, controlador e contato.
- [ ] Criar PostgreSQL e credenciais exclusivos; confirmar que nenhum banco compartilhado é usado.
- [ ] Injetar `APP_KEY`, pepper e senhas por secret store.
- [ ] Configurar SMTP real e validar SPF, DKIM, DMARC, entrega e bounce.
- [ ] Configurar Turnstile para o hostname e testar sucesso, falha e indisponibilidade.
- [ ] Restringir Google Maps/Places New por hostname, API e quota; confirmar fallback manual.
- [ ] Configurar Nginx → PHP-FPM sem `artisan serve`, HTTPS moderno e limite de body/timeouts.
- [ ] Não registrar o token da rota de confirmação em access logs.
- [ ] Executar migrations com backup/rollback, criar admin nominal e confirmar TOTP.
- [ ] Executar testes PostgreSQL, build, auditorias de dependências e smoke test no artefato exato.
- [ ] Validar backups, restauração, monitoramento, alertas e runbook de incidente.
- [ ] Executar QA de acessibilidade, navegadores, segurança e abuso.

## Gate de produção

- [ ] Homologação formal de produto, segurança, operações e jurídico.
- [ ] DNS/TLS e janela de mudança aprovados.
- [ ] Health checks sem dados sensíveis; logs centralizados e retenção definida.
- [ ] Plano de rollback testado e responsável de plantão identificado.
- [ ] Monitorar erros, latência, e-mail, Turnstile, tentativas de MFA e exportações após liberação.

## Nginx mínimo esperado

Servir apenas `public/`, negar arquivos ocultos e caminhos internos, encaminhar `index.php` ao socket PHP-FPM, impor HTTPS e cabeçalhos coerentes. A configuração deve ser criada para o host efetivo e revisada junto aos demais projetos da VPS; não há exemplo pronto para copiar cegamente porque sockets, domínio e política de logs ainda não foram definidos.

