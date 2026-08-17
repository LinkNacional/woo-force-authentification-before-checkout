---
name: prepare-release
description: Prepara release do woo-force-authentification-before-checkout: atualiza readme.txt, CHANGELOG.md, cabeçalho PHP e DEPLOY_TAG dos workflows baseado no git log
---

# prepare-release

Atualiza **todos** os arquivos que contêm o número de versão para uma nova release do plugin.

## Parâmetros (via `arguments`)

O usuário pode passar os valores diretamente: `"version=1.5.0 tested_up=6.7 php=7.3 highlights=Correção de bug X"`. Se algum valor faltar, pergunte.

- **version** — nova versão (Stable tag)
- **tested_up** — versão do WP testada (Tested up to)
- **php** — versão mínima do PHP (Requires PHP)
- **highlights** — resumo da versão (opcional, usa git log se vazio)

## Fluxo de execução

### 1. Coletar valores
Se não recebidos via arguments, pergunte ao usuário um por um. Detecte a versão atual via grep no `.php` raiz:
```
grep -E "Version:|Requires PHP:" *.php
```

### 2. Capturar git log
```bash
LAST_TAG=$(git describe --tags --abbrev=0 2>/dev/null)
if [ -z "$LAST_TAG" ]; then
    git log -n 10 --oneline
else
    git log ${LAST_TAG}..HEAD --oneline
fi
```

### 3. Atualizar TODOS os arquivos com versão

A versão aparece em **6 locais** espalhados por **6 arquivos**. Atualize todos:

#### 3a. `readme.txt`
- `Stable tag:` → nova versão
- `Tested up to:` e `Requires PHP:` se alterados
- Adicionar entrada no topo da seção `== Changelog ==`, **em inglês**, preservando o formato atual do arquivo (`= VERSION =` + bullets + linha em branco antes da versão anterior):
  ```
  = 1.5.0 =

  * Item baseado nos commits

  = 1.4.6 =
  ```
- Se `highlights` foi fornecido, avalie adicionar na `== Description ==` (NUNCA apague conteúdo existente)

#### 3b. `CHANGELOG.md`
- Adicionar entrada no topo do arquivo, **em português**, no formato atual (`# VERSION` + bullets + linha em branco). Usar a **data de hoje** (`date +%d/%m/%y`):
  ```
  # 1.5.0 - DD/MM/AA
  * Item baseado nos commits

  # 1.4.6
  ```

#### 3c. `woo-force-authentification-before-checkout.php`
- `* Version: NOVA_VERSION` (cabeçalho do plugin)
- `* Requires PHP:` se alterado

#### 3d. `.github/workflows/main.yml`
- `DEPLOY_TAG: "NOVA_VERSION"`

#### 3e. `.github/workflows/dev-release.yml`
- `DEPLOY_TAG: "NOVA_VERSION"`

#### 3f. `.github/workflows/wordpressRelease.yml`
- `DEPLOY_TAG: "NOVA_VERSION"`

### 4. Validação final
Rodar grep com a versão **antiga** para confirmar que não restou nenhuma ocorrência fora do esperado:
```
grep -r "VERSAO_ANTIGA" --include="*.php" --include="*.md" --include="*.txt" --include="*.yml" .
```
O esperado: `readme.txt` e `CHANGELOG.md` ainda contêm a versão antiga **apenas** nas entradas antigas do Changelog (isso é correto). Qualquer outro arquivo retornando a versão antiga é **erro** e deve ser corrigido.

Depois, grep com a versão **nova** para confirmar que aparece em todos os **6 locais**:
```
grep -rn "NOVA_VERSAO" --include="*.php" --include="*.md" --include="*.txt" --include="*.yml" .
```
Deve retornar 6+ matches (múltiplas entradas no changelog do `readme.txt` são normais).

## Observações específicas deste plugin
- Plugin single-file: **não** há constante `VERSION`, nem fallback em `Includes/`, nem `tests/`, nem array `$old_versions`.
- `README.md` **não** tem campo de versão explícito (as badges são dinâmicas via shields.io) — não precisa editar.
- `composer.json` **não** tem campo `version` — não precisa editar.
- Não há `release-candidate.yml`; o fluxo de pré-release usa `dev-release.yml`.
- O changelog do `readme.txt` usa formato `= VERSION =` (WordPress clássico), **diferente** do `CHANGELOG.md` que usa `# VERSION`.
