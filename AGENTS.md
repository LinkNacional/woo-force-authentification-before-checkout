# AGENTS.md — Diretrizes Absolutas

Regras imutáveis. Qualquer desvio deve ser justificado no código via comentário `// REASON:`.

---

## 1. Arquitetura (SOLID + PSR-4)

### S — Single Responsibility
- 1 classe = 1 motivo para mudar.
- `WC_Force_Auth_Before_Checkout` — orquestra hooks e ciclo de vida apenas.
- Ao crescer, separar em `Admin/`, `Public/` e `Includes/` (padrão Link Nacional).
- Funções com >40 linhas: quebrar ou justificar.

### O — Open/Closed
- Extensão via hooks, nunca via edição de código existente.
- `apply_filters('wc_force_auth_*', $value, $context)` em todo ponto de extensão.
- Filtros públicos já existentes:
  - `wc_force_auth_login_page_url`
  - `wc_force_auth_checkout_page_url`
  - `wc_force_auth_redirect_to_account_page`
  - `wc_force_auth_message`

### L — Liskov Substitution
- Subclasse deve passar nos mesmos contratos da classe pai.

### I — Interface Segregation
- Interfaces com ≤5 métodos.

### D — Dependency Inversion
- Config via `get_option()` / filtros, nunca hardcoded.

### PSR-4 (quando houver refactor para OO)
```
Lkn\WcForceAuth\Includes\  → Includes/
Lkn\WcForceAuth\Admin\     → Admin/
Lkn\WcForceAuth\PublicView\ → Public/
```
- 1 classe por arquivo. Nome do arquivo = nome da classe.
- Namespace deve corresponder ao caminho do diretório.

---

## 2. Segurança

### Superglobais — sanitizar SEMPRE
```php
// Proibido
$id = $_GET['id'];

// Obrigatório
$id = isset($_GET['id']) ? absint($_GET['id']) : 0;
$name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
```

### Nonces — toda requisição state-changing
```php
if (!isset($_POST['wc_force_auth_nonce']) || !wp_verify_nonce($_POST['wc_force_auth_nonce'], 'wcForceAuthNonce')) {
    // fail
}
```

### Output escaping
```php
echo esc_html($value);       // HTML context
echo esc_attr($value);       // Attribute context
echo esc_url($url);          // URL context
echo wp_kses_post($html);    // Late escaping
```

### SQL — prepared statements
```php
// Proibido
$wpdb->query("SELECT * FROM $wpdb->postmeta WHERE meta_key = '$key'");

// Obrigatório
$wpdb->prepare("SELECT * FROM $wpdb->postmeta WHERE meta_key = %s", $key);
```

### Redirects
- `wp_safe_redirect()` SEMPRE. Nunca `wp_redirect()` com URL não validada.
- Após redirect: `die` / `exit`.

---

## 3. Padrões WordPress / WooCommerce

### Naming
- Classes: `WC_Force_Auth_*` (ou `Lkn\WcForceAuth\*` após refactor)
- Funções/hooks: `wc_force_auth_*`
- Options: `wc_force_auth_*`

### Internacionalização
- Toda string visível ao usuário: `__()`, `_e()`, `_n()`
- Text domain: `woo-force-authentification-before-checkout` (idêntico ao slug do plugin no WordPress.org, exigência do PHPCS)
- String com placeholder (`%s`, `%d`, etc.): o comentário `/* translators: ... */` deve ficar na linha IMEDIATAMENTE acima da chamada `__()` (dentro do `printf`/`wp_kses_post`, não acima do `printf`). PHPCS (`WordPress.WP.I18n.MissingTranslatorsComment`) valida a linha acima da própria função de tradução.

### Comportamento (regras de negócio)
- `redirect_to_account_page()`: só redireciona se `is_checkout() && ! is_user_logged_in()`.
- `redirect_to_checkout()`: só aplica quando o query param `redirect_to_checkout` está presente.
- Filtro `wc_force_auth_redirect_to_account_page` controla a condição de redirect — manter default `true`.

### Assets
- `wp_enqueue_script()` / `wp_enqueue_style()` com versionamento.
- `wp_localize_script()` para dados PHP → JS.

---

## 4. Tratamento de Erros
- Fallback adequado quando WooCommerce não está instalado (notice admin).
- Nunca expor stack traces para o frontend.

---

## 5. Build & Qualidade

```bash
composer install   # setup (phan)
vendor/bin/phan    # análise estática
```

- Plugin single-file, sem build de JS/CSS.

---

## 6. Comunicação (Caveman Mode + RTK)

### Caveman Mode — ATIVO
- Zero saudações. Zero "claro!", "ótimo!", "vamos lá!".
- Zero resumos pós-entrega.
- Frases curtas. Sem períodos compostos.
- Código > prosa. Sempre.

### RTK (Rust Token Killer) — ATIVO
- Logs de terminal são comprimidos pelo RTK antes de chegar ao LLM.
- Nunca solicitar output verboso se snippet RTK estruturado já foi fornecido.
- Confiar no pré-parsing do RTK.

### Formato de resposta esperado
```
Tipo: [fix|feat|refactor|security]
Arquivo: path/to/file.php:123
Problema: descrição ≤1 linha
Solução: descrição ≤1 linha
---
[código/diff]
```
