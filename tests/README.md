# Testes — woo-force-authentification-before-checkout

Camada de testes **sem dependências** (runner próprio em PHP) que roda contra o
**WordPress real** do Local (`ambiente-dev`): mesmo banco, WooCommerce, opções e
— principalmente — o **envio de e-mail real**, capturado pelo **Mailpit**.

## Como rodar

```bash
tests/run.sh                 # roda tudo
tests/run.sh Otp             # só casos cujo nome contém "Otp"
tests/run.sh --verbose       # mostra stack trace das falhas
```

Pré-requisitos: o site do Local estar **em execução** (MySQL + Mailpit no ar).

O `run.sh` auto-detecta:

- o binário PHP do Local (prefere `8.2`);
- o socket do MySQL (`~/.config/Local/run/<site>/mysql/mysqld.sock`);
- o Mailpit (SMTP + API HTTP) a partir do `sites.json` do Local.

Ele gera um `php.ini` temporário (memória, socket e `sendmail_path` → Mailpit) e
executa `run.php`. Sobrescreva o root do WP com `WC_FA_WP_ROOT` se precisar.

## Como funciona

- `run.php` — entrada: faz snapshot das opções do plugin, roda os casos e
  **restaura tudo ao final** (mesmo em erro), para nunca deixar o site alterado.
- `bootstrap.php` — carrega o `wp-load.php` real e a biblioteca de testes.
- `lib/Runner.php` — descobre `cases/*Test.php`, roda os métodos `test_*` e
  imprime o relatório (exit code = nº de falhas).
- `lib/TestCase.php` — asserções (`assertSame`, `assertContains`, …).
- `lib/WpTestCase.php` — snapshot/restore por teste, helpers de REST (`sendCode`,
  `verifyCode`), assunto único por e-mail e limpeza dos usuários criados.
- `lib/Mailpit.php` — cliente da API do Mailpit (lê o e-mail enviado e extrai o código).
- `lib/Wp.php` — helpers de opções do WP e de estado OTP.

## Casos

| Arquivo | Cobre |
|---|---|
| `OtpCodeFormatTest` | tamanho 4/6/8, agrupamento em todos os formatos (`plain`/`space`/`dash`/`pair`), placeholder, `maxlength`, geração do código (tamanho + zero-pad) e fallback de valores inválidos |
| `OtpSegmentedMarkupTest` | input **único** vs. **fragmentado** (1 caixa por dígito): contagem de caixas, separadores por formato, `name="code"` único, ids únicos, `maxlength=1`, `autocomplete="one-time-code"` |
| `OtpEmailTest` | o e-mail **real** chega no Mailpit, no destinatário certo, com o assunto configurado e o **código do tamanho configurado**; e o código do e-mail **verifica e loga** |
| `OtpRestFlowTest` | modo desabilitado (403), nonce inválido (403), `login_only` sem conta (400), metadados (`expires_in`/`resend_in`/`expires_label`), rate limit (429), código errado (400) vs. certo (200) e uso único do código |
| `ForceAuthSettingsTest` | defaults, allowlists de `sanitize_option`, save AJAX (persiste só as chaves do recurso) e detecção do plugin Invoice (só a pasta do slug) |
| `ForceCheckoutTest` | gate de ativação, URLs de login/checkout (default + override), destino pós-login, mensagem do aviso e filtro de redirect |

## Notas

- O e-mail de teste padrão é o `admin_email` do site (`dev-email@wpengine.local`),
  mas o Mailpit captura **todo** envio.
- O Mailpit fica em `http://127.0.0.1:<porta>` (a `run.sh` descobre a porta);
  abra no navegador para ver os e-mails capturados.
- Refactor mínimo para testabilidade: `WcForceAuthOtp::generate_code()` e
  `get_code_group_sizes()` ficaram públicos (eram privados) — sem mudança de
  comportamento.
