---
name: open-pr
description: Abre PR da branch dev para main no padrão Link Nacional (título VERSION - repo - resumo; corpo com readme/metadados e resumo copiado do changelog) e adiciona a label release
---

# open-pr

Abre um Pull Request de `dev` → `main` via `gh pr create`, no padrão Link Nacional.

## Parâmetros (via `arguments`)

O usuário pode passar: `version=1.5.0 tested_up=7.1 summary=Ajustes para diretrizes do WordPress`. Qualquer valor ausente é extraído do código.

- **version** — versão da release (Stable tag / cabeçalho PHP)
- **tested_up** — WP testado até
- **summary** — resumo CURTO das mudanças (usado no TÍTULO). Se ausente, derive da entrada mais recente do changelog (NÃO do git log).

## Fluxo de execução

### 1. Extrair metadados (se não vierem nos arguments)

```bash
# Header PHP (fonte da verdade)
grep -m1 -E "^\s*\*\s*Version:" woo-force-authentification-before-checkout.php

# readme.txt (fallback para Tested up to / Stable tag)
grep -m1 -i "^Tested up to:" readme.txt
grep -m1 -i "^Stable tag:" readme.txt

# Nome do repositório
REPO_NAME=$(basename "$PWD")
```

### 2. Ler o changelog da versão atual (fonte do resumo e dos bullets)

⚠️ **Regra anti-redundância.** NÃO use `git log` para gerar o resumo — o range de commits está dessincronizado (tags antigas/ausentes, branches de beta) e traz itens de versões já publicadas. Em vez disso, leia a entrada mais recente do changelog:

```bash
head -n 30 CHANGELOG.md
```

A entrada mais recente tem o formato `# VERSION - DD/MM/AA` seguido de bullets `* ...`.

- **TÍTULO**: resuma esses bullets em uma frase curta (≤ ~12 palavras).
- **CORPO**: copie os bullets tal como estão no changelog (sem hash, sem reescrever).

### 3. Montar TÍTULO

Formato exato (obrigatório):

```
VERSION - REPO_NAME (RESUMO_CURTO)
```

Exemplo:
```
1.5.0 - woo-force-authentification-before-checkout (Ajustes para diretrizes do WordPress)
```

### 4. Montar CORPO

Use como gabarito `.github/release-body-template.md` (o MESMO conteúdo alimenta a release do `.zip`). Preencha os placeholders `{...}`:

```markdown
# Force Authentification Before Checkout for WooCommerce

* Contribuidores: linknacional
* Link: https://www.linknacional.com.br/wordpress/
* Tags: woocommerce, checkout, login, register, cart, cadastro
* Testado até: {TESTED_UP}
* Versão estável: {VERSION}
* Requer PHP: 8.2
* Licença: GPLv3
* URI da Licença: http://www.gnu.org/licenses/gpl-3.0.html
* Traduções: Português(Brasil) / Inglês

Force o cliente a fazer login ou se cadastrar antes de concluir a compra no WooCommerce.

## Descrição

{Descrição REAL do plugin (readme.txt / README.md). Preserve os recursos que o plugin possui.}

## Instalação

1. Baixe o plugin.
2. No painel administrativo do WordPress, vá para **Plugins → Adicionar Novo**.
3. Clique em "Enviar Plugin" e selecione o arquivo ZIP do plugin baixado.
4. Clique em "Instalar Agora" e, em seguida, em "Ativar Plugin".
5. Certifique-se de que o plugin WooCommerce também está ativado.

## CHANGELOG:

{BULLETS copiados da entrada mais recente do CHANGELOG.md. NÃO invente a partir do git log.}
```

O mesmo conteúdo alimenta o corpo da release do `.zip` (`.github/release-body-template.md`), então mantenha os dois alinhados.

### 5. Abrir o PR

Sempre `dev` → `main`. **SEMPRE** adicione a label `release` (o workflow `.github/workflows/main.yml` só gera a release quando o PR mesclado tem essa label):

```bash
gh pr create \
  --base main \
  --head dev \
  --title "VERSION - REPO_NAME (RESUMO_CURTO)" \
  --label release \
  --body "$(cat <<'EOF'
...corpo...
EOF
)"
```

### 6. Confirmar

Mostre a URL retornada pelo `gh` e o comando usado. Se o PR já existir para `dev` → `main`, `gh` vai avisar — não force `--force` sem pedir.

## Regras

- **Nunca** edite arquivos do repo para abrir o PR (é só `gh pr create`).
- Título SEMPRE no formato `VERSION - REPO_NAME (resumo)`.
- Corpo SEMPRE com Testado até, Versão estável e o resumo da versão.
- Sempre incluir `--label release` — **exclusivo deste plugin** (o release automático depende dela).
- Se `version` ou `tested_up` divergirem entre o header PHP e o `readme.txt`, use o **header PHP** e avise.
- Nunca inclua hashes de commit no corpo.
- Resumo e bullets do corpo SEMPRE vindos do changelog da versão atual — nunca do `git log`.
