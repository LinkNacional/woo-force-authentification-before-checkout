<?php

namespace WcfaTests\Cases;

use Lkn\WcForceAuth\Includes\WcForceAuthLoginPage;
use WcfaTests\Lib\Wp;
use WcfaTests\Lib\WpTestCase;

/**
 * "Send the guests of My account to this page": which page holds the role, and
 * the cases where it must not.
 *
 * The redirect itself is a `wp_safe_redirect()` + `exit` pair, so what is tested
 * here is the decision around it: whether the page really can serve the login
 * screen, which is what gates the redirect.
 */
class LoginPageTest extends WpTestCase {

	/**
	 * Create a published page holding the login block.
	 *
	 * @return int Page id.
	 */
	private function login_page(): int {
		return (int) wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Login screen under test',
				'post_name'    => 'login-screen-under-test',
				'post_content' => '<!-- wp:lknform/login-register /-->',
			)
		);
	}

	public function test_no_page_means_no_redirect(): void {
		Wp::setLoginPage( 0 );

		$this->assertFalse( WcForceAuthLoginPage::is_enabled(), 'nothing chosen' );
		$this->assertFalse( WcForceAuthLoginPage::owns_login(), 'nobody owns the screen' );
		$this->assertFalse( WcForceAuthLoginPage::is_page_unusable(), 'and that is not a problem' );
	}

	public function test_published_page_with_the_block_owns_the_screen(): void {
		$page_id = $this->login_page();

		Wp::setLoginPage( $page_id );

		$this->assertTrue( WcForceAuthLoginPage::is_enabled(), 'a page is chosen' );
		$this->assertTrue( WcForceAuthLoginPage::owns_login(), 'it holds the block' );
		$this->assertFalse( WcForceAuthLoginPage::is_page_unusable(), 'so nothing is broken' );

		// Outside that page there is no login screen to speak of.
		$this->assertFalse( WcForceAuthLoginPage::is_login_screen(), 'not rendering that page here' );

		wp_delete_post( $page_id, true );
	}

	public function test_draft_page_does_not_own_the_screen(): void {
		$page_id = $this->login_page();

		wp_update_post( array( 'ID' => $page_id, 'post_status' => 'draft' ) );
		Wp::setLoginPage( $page_id );

		$this->assertTrue( WcForceAuthLoginPage::is_enabled(), 'still chosen' );
		$this->assertFalse( WcForceAuthLoginPage::owns_login(), 'a draft cannot serve the login screen' );
		$this->assertTrue( WcForceAuthLoginPage::is_page_unusable(), 'and the editor says so' );

		wp_delete_post( $page_id, true );
	}

	/**
	 * The account page cannot receive its own guests: that would be a redirect
	 * loop. Older versions allowed the choice, so the guard lives here too.
	 */
	public function test_the_account_page_cannot_own_the_screen(): void {
		Wp::setLoginPage( WcForceAuthLoginPage::account_page_id() );

		$this->assertTrue( WcForceAuthLoginPage::is_enabled(), 'the option accepts it' );
		$this->assertFalse( WcForceAuthLoginPage::owns_login(), 'but it never owns the screen' );
	}

	public function test_page_without_the_block_is_unusable(): void {
		$page_id = (int) wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'No block here',
				'post_name'    => 'no-block-here',
				'post_content' => '<!-- wp:paragraph --><p>Nothing to log in with.</p><!-- /wp:paragraph -->',
			)
		);

		Wp::setLoginPage( $page_id );

		$this->assertFalse( WcForceAuthLoginPage::owns_login(), 'the login screen would be empty' );
		$this->assertTrue( WcForceAuthLoginPage::is_page_unusable(), 'reported as unusable' );

		wp_delete_post( $page_id, true );
	}
}
