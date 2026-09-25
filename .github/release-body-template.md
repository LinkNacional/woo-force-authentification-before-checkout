# Force Authentification Before Checkout for WooCommerce

* Contribuidores: linknacional
* Link: https://www.linknacional.com.br/wordpress/
* Tags: woocommerce, checkout, login, register, cart, cadastro
* Testado até: ${TESTED_UP}
* Versão estável: ${STABLE_TAG}
* Requer PHP: 8.2
* Licença: GPLv3
* URI da Licença: http://www.gnu.org/licenses/gpl-3.0.html
* Traduções: Português(Brasil) / Inglês

Force o cliente a fazer login ou se cadastrar antes de concluir a compra no WooCommerce.

## Descrição

O Force Authentification Before Checkout for WooCommerce garante que o cliente esteja autenticado antes de finalizar o pedido. Quando um visitante não logado tenta acessar o checkout, o plugin o redireciona para a página "Minha conta" para que ele faça login ou crie uma conta — e, ao concluir, retorna automaticamente para o checkout.

**Recursos Principais**

- Redirecionamento automático do checkout para a página "Minha conta" (apenas para visitantes não logados)
- Retorno automático ao checkout após login ou cadastro
- Aviso na página "Minha conta" informando que é preciso entrar/cadastrar para concluir a compra
- Compatível com plugins de login social
- Filtros (hooks) para personalizar URLs de login, checkout, mensagem e condição de redirecionamento:
  - `wc_force_auth_redirect_to_account_page`
  - `wc_force_auth_login_page_url`
  - `wc_force_auth_checkout_page_url`
  - `wc_force_auth_message`
- Aviso no painel administrativo quando o cadastro na "Minha conta" está desativado

**Dependências**

Este plugin depende do WooCommerce. Certifique-se de que o WooCommerce está instalado e ativado antes de usar o Force Authentification Before Checkout for WooCommerce.

**Instruções de uso**

1. Instale e ative o plugin (o WooCommerce deve estar ativo).
2. Acesse **WooCommerce → Configurações → Contas e privacidade**.
3. Em "Criação de conta", marque a opção **Na página "Minha conta"** para permitir que os clientes criem uma conta.
4. Pronto! Quando um cliente não logado tentar acessar o checkout, será redirecionado para a "Minha conta" para fazer login/cadastro e, em seguida, retornará ao checkout.

## Instalação

1. Baixe o plugin.
2. No painel administrativo do WordPress, vá para **Plugins → Adicionar Novo**.
3. Clique em "Enviar Plugin" e selecione o arquivo ZIP do plugin baixado.
4. Clique em "Instalar Agora" e, em seguida, em "Ativar Plugin".
5. Certifique-se de que o plugin WooCommerce também está ativado.

## CHANGELOG:

${VERSION_SUMMARY}
