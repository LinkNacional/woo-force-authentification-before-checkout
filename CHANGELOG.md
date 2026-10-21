# 1.7.1 - 21/10/26
* **Botão "Usar a página 'Minha conta'" sob o campo "Redirect after login" do bloco**: preenche o destino de login daquela página com o endereço da conta da loja, sem ninguém digitar (e errar) a URL. Fica desabilitado quando o campo já aponta para ela ou quando "Return to the previous page" está ligado, com a explicação embaixo. O bloco só declara o lugar (`lknform-redirect-after`): "Minha conta" é uma página do WooCommerce, então quem oferece o endereço é este plugin
* As configurações do código passam a ser **por página** quando o bloco "LKN Forms - Login and Register" cuida do login: o bloco guarda as próprias regras (modo, tamanho e formato do código, validade, intervalo de reenvio, marca/logo e assunto do e-mail), então duas páginas de login podem pedir códigos diferentes. As configurações globais (Authentification > OTP) passam a ser o padrão apenas para as telas do próprio plugin (Minha Conta e a página de login da loja)
* Novo contexto de página no motor do OTP (`use_context()`, `clear_context()`, `context_token()`, `context_from_token()`): `option()` responde pelo contexto quando ele está em uso, e as chaves aceitas são as de `context_keys()`
* O contexto viaja assinado (`wp_hash`) no corpo de `/send-code` e `/verify-code` (`context`) e é validado no servidor; token ausente ou adulterado volta para as configurações da loja. Assim a página não consegue inventar validade maior nem um modo que crie contas
* `WcForceAuthBlockOtp` passou a ler o atributo `otp` do bloco: com o recurso ligado ele desenha as telas com as regras daquela página, e desligado devolve o formulário de senha do bloco mesmo que o modo global esteja ativo. Blocos antigos (sem o atributo) continuam seguindo o modo global
* Novo `WcForceAuthBlockOtp::page_defaults()`: o retrato das configurações da loja usado como ponto de partida quando o recurso é ligado numa página
* Os controles do código saíram da sidebar do plugin e passaram a ser injetados **dentro do bloco** (slot `lknform-otp-panel`), ligados aos atributos da página. O painel mostra só os campos: sem texto introdutório, para o painel ficar compacto
* A chave "usar esta página como página de login da loja" também foi para dentro do bloco, no slot novo `lknform-store-login-page`, logo abaixo do campo "Show": ela é sobre a tela de login que a página mostra. Continua sendo **uma opção só** (uma página por loja) e a resposta do AJAX devolve também o slug, para o aviso de "outra página está com o papel" não ficar ambíguo. Com isso a sidebar própria do plugin deixou de existir (não sobrou nada nela), e o atalho "Abrir as configurações do OTP" foi removido — o painel do bloco já é o lugar de editar as regras
* **O bloco de login deixou de ter o markup substituído**: as telas de código agora são as do próprio bloco (mesmos textos, cores e layout que o editor mostra). Este plugin continua dono do fluxo — gera o código, envia o e-mail, valida e assina o cliente — e o bloco fala com ele pelos endpoints mais o `context` da página, via `WcForceAuthBlockOtp::block_flow()` (payload com endpoints, nonce e token assinado, entregue no `render_block` do bloco). Saíram o filtro `render_block` que trocava o markup, o `enqueue_assets()` e o `block_wants_screens()`
* **A página de login da loja deixou de substituir o formulário dentro de "Minha Conta" e passou a receber os visitantes**: quem abre "Minha Conta" sem estar logado é redirecionado para a página marcada (que desenha as telas com o bloco) e, logado, continua em "Minha Conta" com suas seções e endpoints. O redirecionamento acontece em `template_redirect` (prioridade 5), cobre também os endpoints (`/minha-conta/pedidos/` etc.) e leva junto o marcador `redirect_to_checkout` quando ele está na requisição. Saíram o template `Includes/templates/myaccount/block-login.php` e o filtro `woocommerce_locate_template` do `WcForceAuthLoginPage` — um mecanismo só, em vez de dois fazendo a mesma coisa de jeitos diferentes
* `WcForceAuthRedirectSettings::get_login_page_url()` passa a apontar direto para a página de login escolhida: o redirect do checkout forçado vai para ela em um salto, não dois (checkout → minha conta → login)
* A captura do "de onde o cliente veio" (destino "Página anterior") passou a acontecer também na página de login, que agora é onde o formulário está
* A própria página "Minha Conta" não pode ser escolhida como página de login (o visitante seria enviado para a página em que já está): o AJAX recusa com mensagem e `owns_login()` também ignora essa combinação
* A página "Minha Conta" **não é a única recusada**: "finalizar compra" e "carrinho" também não podem ser a página de login (os visitantes delas são enviados justamente para a página de login, então apontar para si mesmas giraria em loop). A mensagem do AJAX diz isso
* O endpoint `lost-password` fica de fora do redirecionamento: o link do e-mail de redefinição chega em `/minha-conta/lost-password/?key=...&login=...`, e o bloco não lê esses argumentos — redirecionar descartaria a chave e trancaria o cliente para fora
* `owns_login()` passou a exigir o bloco registrado: sem o plugin do bloco ativo a página não assume o papel (senão os visitantes cairiam numa tela de login vazia) e o formulário nativo do WooCommerce continua atendendo
* "Página anterior": a proteção contra guardar a própria tela de login como destino passou a considerar também a página "Minha Conta"
* Os textos do interruptor no painel do bloco acompanharam a mudança de comportamento ("Send guests from \"My account\" to this page"), e o aviso de "outra página está com o papel" agora diz para onde os visitantes vão hoje
* Novo botão **"Usar a página Minha conta"** ao lado do campo "Custom destination URL" (Authentification → Settings → After login): preenche o campo com a URL da página "Minha conta" definida no WooCommerce, sem precisar copiar à mão
* O select do modo de autenticação passou a dizer o que acontece com o cadastro: **"Login only + Register"** (antes só "Login only", que deixava a dúvida se novos clientes ainda podiam se cadastrar) e **"Login and register"**. A descrição do campo acompanhou a mudança, e a do readme também
* Os asteriscos vermelhos saíram dos rótulos das telas do código (e-mail, código e os três campos do cadastro nativo): o `required` do HTML já marca o campo como obrigatório, e o rótulo ficou igual ao do bloco de login. As regras `.wcfa-required` do CSS saíram junto
* As telas passaram a aceitar os textos editados na página: os atributos de texto do bloco (título/subtítulo da etapa 1, rótulo e placeholder do e-mail, botão, e na etapa 2 título, subheading, rótulo e placeholder do código, botão, reenviar, trocar e-mail e relato) são mapeados para as opções correspondentes por `WcForceAuthBlockOtp::block_text_map()` e viajam no contexto da página
* Duas opções novas: `verify_placeholder_code` (placeholder do campo do código; vazio usa as bolinhas geradas pelo tamanho/formato) e `verify_button_report` (rótulo do "não recebi o código", antes fixo no template)
* A linha do "enviamos o código para %s" agora imprime o e-mail logo depois do texto: um `%s` no texto (com a pontuação seguinte) é removido em vez de aparecer na tela
* O wrapper das telas passou a imprimir os valores daquela tela (`data-code-length`, `data-code-format`, `data-expiration`, `data-resend-interval` e `data-context`), para o mesmo script servir telas com regras diferentes
* Correção: a injeção no editor não acontecia (detecção por classe renomeada) e o script do painel não declarava `wp-edit-post` como dependência

# 1.7.0 - 19/10/26
* Suporte ao bloco de login do plugin "LKN Forms - Login and Register" (`lknform/login-register`), que passou a ser atendido por este plugin em vez de implementar o fluxo por conta própria
* Novo método público `get_block_config()` (endpoints, nonce, tamanho do código, expiração e intervalo de reenvio) e a constante `WcForceAuthOtp::NONCE_ACTION`, para integrações que conduzem o fluxo
* Novo filtro `wc_force_auth_after_login_url` (destino após o login, para integrações que renderizam o OTP em outra página)
* Uma tela de login por vez: quando uma página do bloco "LKN Forms" é a página de login da loja, o OTP fica desabilitado (o modo é reportado e salvo como "Disabled" e o campo fica travado, com aviso apontando para as configurações do LKN Forms)

# 1.6.0 - 02/10/26
* Nova arquitetura em classes (PSR-4, `Lkn\WcForceAuth`), com separação `Admin/`, `Public/` e `Includes/`
* Camada de testes (`tests/`) sem dependências: runner próprio que roda contra o WordPress real do Local, com e-mail real capturado pelo Mailpit; cobre os formatos do código (4/6/8, `plain`/`space`/`dash`/`pair`, input único e segmentado), o fluxo REST (envio/verificação/limites) e o Force Authentication
* Correção: as opções de OTP não usam mais o fallback das opções do plugin Invoice Payment (`lkn_wcip_otp_email_*`), que fazia valores como o tempo de expiração vazarem entre os plugins
* Novo menu lateral no admin do WordPress ("Authentification" → "Force", "OTP" e "Settings")
* Reestruturação do menu admin: item de topo "Authentification" abre as configurações do recurso principal; ao passar o mouse aparecem os submenus "Force", "OTP" e "Settings"
* Novo submenu "Settings" que centraliza todo o fluxo de redirect (páginas de login e de checkout + destino após o login). As duas telas (Force Authentication e OTP) passam a ler a mesma configuração, evitando destinos divergentes entre os módulos
* Nova opção de destino após o login: "Previous page" — devolve o cliente à página em que ele estava antes de ir para a conta (por exemplo, o produto que estava vendo), capturada no cookie `wcfa_return_url`
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
* Nova opção "Code length" como seleção (4, 6 ou 8 dígitos)
* Nova opção "Code input style": campo único ou uma caixa por dígito (estilo PIN, com avanço/backspace/colar e agrupamento visual conforme o "Code format")
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
