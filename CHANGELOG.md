# 2.0.0 - 02/10/26
* Nova arquitetura em classes (PSR-4, `Lkn\WcForceAuth`), com separação `Admin/`, `Public/` e `Includes/`
* Camada de testes (`tests/`) sem dependências: runner próprio que roda contra o WordPress real do Local, com e-mail real capturado pelo Mailpit; cobre os formatos do código (4/6/8, `plain`/`space`/`dash`/`pair`, input único e segmentado), o fluxo REST (envio/verificação/limites) e o Force Authentication
* Correção: as opções de OTP não usam mais o fallback das opções do plugin Invoice Payment (`lkn_wcip_otp_email_*`), que fazia valores como o tempo de expiração vazarem entre os plugins
* Novo menu lateral no admin do WordPress ("Force Authentification" → "OTP")
* Reestruturação do menu admin: item de topo "Force Authentification" abre as configurações do recurso principal; ao passar o mouse aparecem os submenus "Force Authentification" e "OTP"
* Nova tela de configuração do recurso principal (Force Authentication): ativar/desativar, mensagem do aviso, URLs de login/checkout e destino após o login; as opções agora controlam o comportamento (antes fixo)
* Arquitetura de settings com classe base compartilhada (`WcForceAuthSettingsPage`) e uma página por recurso
* Ajuste de espaçamento no topo das telas de configuração (as páginas próprias não têm a nav de abas do WooCommerce que dava esse respiro no plugin fraud)
* Card do "Invoice Payment Link for WooCommerce": detecção de instalação alinhada ao antifraud (checa apenas a pasta do slug do wp.org, `invoice-payment-for-woocommerce/wc-invoice-payment.php`)
* Novo recurso: autenticação OTP por e-mail (login e/ou cadastro sem senha)
* Telas de login e verificação com o layout novo e identidade visual da loja (logo, cor primária e textos configuráveis)
* Substitui os formulários nativos de login/cadastro do WooCommerce via override de template (`woocommerce_locate_template`), então a tela de OTP vence qualquer tema (inclusive temas com `form-login.php` próprio)
* Envio/verificação de código via REST, código armazenado com hash, limite de tentativas e intervalo de reenvio
* Template de e-mail de OTP próprio
* Salvamento das configurações via AJAX com SweetAlert2
* Notificações do frontend (envio, verificação, erros) com SweetAlert2 e detecção automática do código na área de transferência (oferece usar quando o cliente volta com o código copiado)
* Placeholder no campo do código
* Padronização da altura de inputs e botões; botão de login/cadastro e ícone ganham destaque na cor primária quando o e-mail é válido; ícones de e-mail/cadeado em SVG
* Removido o fundo do autofill do navegador nos campos (e-mail, código e cadastro)
* Botão de verificar com o mesmo estado/animação do botão de login; contador e botão "Reenviar" sobrepostos (não empurram mais o campo do código) e "Reenviar" na cor primária
* Alinhamento do botão de fechar (×) e "Use code" na cor primária no aviso de código detectado
* Fontes maiores, cartão mais largo e controles com altura fixa e `box-sizing` explícito (inputs e botões com a mesma altura, sem o tema "estourar" o input)
* Botão "Use another email" com altura e fonte padronizadas; placeholder dos campos em cinza; aviso lateral de código detectado com mais tempo de exibição
* Contador e botão "Reenviar" abaixo do botão "Verificar": o contador aparece dentro do botão, em estilo cinza discreto, e ao liberar o botão vira a cor primária clara (tom secundário); o código fica centralizado com o ícone à esquerda; o botão "Use another email" usa a cor primária em tom claro
* Texto legal exibido apenas na tela de login; na tela de verificação há o link "Didn't receive the code?" que abre um popup (SweetAlert2) com e-mail, contato e mensagem (pré-preenchida, editável) e envia um report por e-mail para todos os administradores do WordPress
* Horário de expiração do código exibido abaixo do campo (formatado com a data/hora do WordPress) e animação de carregamento (spinner) nos botões de ação
* Nova opção "Code length" como seleção (4, 6 ou 8 dígitos)* Nova opção "Code input style": campo único ou uma caixa por dígito (estilo PIN, com avanço/backspace/colar e agrupamento visual conforme o "Code format")
* Correção: as caixas do modo segmentado não eram mais esticadas pelo tema (largura/altura fixas com especificidade maior); correção também do valor padrão exibido nos selects (a comparação estrita fazia o select mostrar a primeira opção em vez do valor salvo)
* Correção crítica: a limpeza de códigos expirados apagava configurações do plugin (o `LIKE` casava `code_length`/`code_format`/`code_input`), fazendo as opções voltarem ao padrão após cada login e o e-mail gerar o código no tamanho padrão — os códigos temporários agora usam um prefixo isolado (`auth_`) que não colide com nenhuma opção
* Nova opção "Code format" para o campo do código: simples (123456), grupos com espaço (123 456), grupos com hífen (123-456) ou pares (12 34 56) — o separador é apenas visual e é removido antes do envio; a detecção de código copiado aceita tanto o valor puro quanto o formatado
* Nova opção "Estilo de notificação" (flutuante ou inline): escolhe se os avisos (código enviado, erros, confirmações) aparecem como pop-ups flutuantes (SweetAlert2) ou dentro do componente; no modo inline cada mensagem fica logo abaixo do campo do passo ativo; o alerta de código copiado permanece sempre flutuante

# 1.5.0 - 17/08/26
* Adição dos banners do plugin
* Ajuste na notificação de opção da página "minha conta"
* Atualização dos links da documentação para Link Nacional
* Ajustes de conformidade para o WordPress.org

# 1.4.6
* Testado até WordPress 6.9 e WooCommerce 10.6

# 1.4.5
* Testado até WordPress 6.6

# 1.4.4
* Testado até WordPress 6.4

# 1.4.3
* Correção no aviso de doação

# 1.4.2
* Atualização da versão testada

# 1.4.1
* Correção de chamada a método indefinido

# 1.4.0
* Correção: redirecionamento não funcionava com página de login personalizada
* Ajuste: agora usa um cookie para dispensar o aviso de doação no painel administrativo, em vez do banco de dados

# 1.3.2
* Correção de um erro de sintaxe em versões antigas do PHP

# 1.3.1 - 2020/4/19
* Pequena correção.

# 1.3.0 - 2020/4/19
* Novo filtro: wc_force_auth_redirect_to_account_page
* Novo filtro: wc_force_auth_login_page_url
* Novo filtro: wc_force_auth_checkout_page_url

# 1.2.3 - 2018/10/28
* Correção menor

# 1.2.2 - 2018/09/17
* Correção menor

# 1.2.1 - 2018/07/16
* Primeiro lançamento público.
