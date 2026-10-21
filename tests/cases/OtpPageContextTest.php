<?php

namespace WcfaTests\Cases;

use Lkn\WcForceAuth\Includes\WcForceAuthBlockOtp;
use Lkn\WcForceAuth\Includes\WcForceAuthOtp;
use WcfaTests\Lib\Wp;
use WcfaTests\Lib\WpTestCase;

/**
 * The per-page settings a "Login and Register" block can carry.
 *
 * The block stores its own copy of the code rules with the page, and those
 * values have to reach three places: the screens it renders, the code that is
 * generated for it, and the verification — the last two in requests of their
 * own, which is what the signed token is for.
 */
class OtpPageContextTest extends WpTestCase {

	private function otp(): WcForceAuthOtp {
		return new WcForceAuthOtp( 'x', '2' );
	}

	/**
	 * Runs a callback with a block's own settings in place, as `block_flow()` does.
	 *
	 * @param array    $settings Page values (without the option prefix).
	 * @param callable $callback Assertions to run.
	 */
	private function withContext( array $settings, callable $callback ): void {
		$otp = $this->otp();

		$otp->use_context( $settings );

		try {
			$callback( $otp );
		} finally {
			$otp->clear_context();
		}
	}

	public function test_page_settings_win_over_the_store_ones(): void {
		Wp::setOtpConfig(
			array(
				'enable_type'     => 'register_and_login',
				'code_length'     => '6',
				'expiration_time' => '10',
				'brand_name'      => 'Store',
			)
		);

		$otp = $this->otp();
		$this->assertSame( 6, $otp->get_code_length(), 'store code length' );
		$this->assertSame( 'register_and_login', $otp->get_mode(), 'store mode' );

		$this->withContext(
			array(
				'enable_type'     => 'login_only',
				'code_length'     => '4',
				'expiration_time' => '3',
				'brand_name'      => 'Page A',
			),
			function ( $page ) {
				$this->assertSame( 4, $page->get_code_length(), 'page code length' );
				$this->assertSame( 'login_only', $page->get_mode(), 'page mode' );
				$this->assertSame( 3, $page->get_expiration_minutes(), 'page lifetime' );
				$this->assertSame( 'Page A', $page->option( 'brand_name' ), 'page brand' );
			}
		);

		// Back to the store values: the context is request-scoped only.
		$this->assertSame( 6, $otp->get_code_length(), 'store length restored' );
		$this->assertSame( 'register_and_login', $otp->get_mode(), 'store mode restored' );
	}

	public function test_missing_page_values_fall_back_to_the_store(): void {
		Wp::setOtpConfig(
			array(
				'code_length'     => '8',
				'resend_interval' => '30',
			)
		);

		$this->withContext(
			array( 'code_length' => '4' ),
			function ( $page ) {
				$this->assertSame( 4, $page->get_code_length(), 'page value applies' );
				$this->assertSame( 30, $page->get_resend_interval(), 'unset value comes from the store' );
			}
		);
	}

	public function test_only_known_keys_and_valid_modes_are_taken(): void {
		$otp = $this->otp();

		$otp->use_context(
			array(
				'enable_type'  => 'not-a-mode',
				'code_length'  => '4',
				'unknown_key'  => 'value',
				'brand_name'   => array( 'not', 'a', 'string' ),
			)
		);

		$context = $otp->context();

		$this->assertFalse( isset( $context['enable_type'] ), 'invalid mode is dropped' );
		$this->assertFalse( isset( $context['unknown_key'] ), 'unknown key is dropped' );
		$this->assertFalse( isset( $context['brand_name'] ), 'non-string value is dropped' );
		$this->assertSame( '4', $context['code_length'], 'valid key is kept' );

		$otp->clear_context();
	}

	public function test_token_round_trip_and_tampering(): void {
		$otp = $this->otp();

		$this->assertSame( '', $otp->context_token(), 'no context -> no token' );

		$otp->use_context(
			array(
				'code_length' => '8',
				'enable_type' => 'login_only',
			)
		);

		$token = $otp->context_token();
		$otp->clear_context();

		$this->assertTrue( '' !== $token, 'token generated' );

		$values = WcForceAuthOtp::context_from_token( $token );
		$this->assertSame( '8', $values['code_length'], 'token keeps the code length' );
		$this->assertSame( 'login_only', $values['enable_type'], 'token keeps the mode' );

		$this->assertSame( array(), WcForceAuthOtp::context_from_token( 'x' . $token ), 'tampered token is rejected' );
		$this->assertSame( array(), WcForceAuthOtp::context_from_token( 'garbage' ), 'malformed token is rejected' );
		$this->assertSame( array(), WcForceAuthOtp::context_from_token( '' ), 'empty token is rejected' );
	}

	public function test_block_own_settings_decide_the_render(): void {
		$block_otp = new WcForceAuthBlockOtp( $this->otp() );

		$block = array(
			'blockName' => WcForceAuthBlockOtp::BLOCK_NAME,
			'attrs'     => array(
				'otp' => array(
					'enabled'  => true,
					'mode'     => 'login_only',
					'settings' => array( 'code_length' => '4' ),
				),
			),
		);

		$own = $block_otp->settings_of( $block );

		$this->assertSame( true, $own['enabled'], 'block asks for the screens' );
		$this->assertSame( 'login_only', $own['mode'], 'page mode' );
		$this->assertSame( '4', $own['settings']['code_length'], 'page code length' );

		// An invalid mode never reaches the flow.
		$block['attrs']['otp']['mode'] = 'whatever';
		$this->assertSame( 'register_and_login', $block_otp->settings_of( $block )['mode'], 'invalid mode falls back' );

		// A block saved before this existed has no settings of its own.
		$this->assertSame(
			null,
			$block_otp->settings_of( array( 'blockName' => WcForceAuthBlockOtp::BLOCK_NAME, 'attrs' => array( 'mode' => 'both' ) ) ),
			'legacy block follows the store settings'
		);
	}

	/**
	 * The payload the block's own script runs with: endpoints, nonce, and the
	 * page's signed context (its rules), so the block draws the screens and this
	 * plugin applies that page's code rules when sending/validating.
	 */
	public function test_block_flow_hands_over_the_page_context(): void {
		Wp::setOtpConfig(
			array(
				'enable_type'     => 'register_and_login',
				'code_length'     => '6',
				'expiration_time' => '10',
			)
		);

		$block = array(
			'blockName' => WcForceAuthBlockOtp::BLOCK_NAME,
			'attrs'     => array(
				'otp' => array(
					'enabled'  => true,
					'mode'     => 'login_only',
					'settings' => array( 'code_length' => '8', 'expiration_time' => '3' ),
				),
			),
		);

		$flow = WcForceAuthBlockOtp::block_flow( $block );

		$this->assertTrue( isset( $flow['sendCode'], $flow['verifyCode'], $flow['nonce'] ), 'endpoints and nonce' );
		$this->assertSame( 'login_only', $flow['mode'], 'the page shape travels' );
		$this->assertSame( 8, $flow['codeLength'], 'the page code length wins' );
		$this->assertSame( 3 * 60, $flow['expiration'], 'the page lifetime wins' );

		$values = WcForceAuthOtp::context_from_token( $flow['context'] );
		$this->assertSame( '8', $values['code_length'], 'the token carries the page length' );
		$this->assertSame( 'login_only', $values['enable_type'], 'the token carries the page mode' );

		// A page that does not want the code gets no payload at all.
		$block['attrs']['otp']['enabled'] = false;
		$this->assertSame( array(), WcForceAuthBlockOtp::block_flow( $block ), 'disabled page: nothing to hand over' );
	}

	public function test_block_texts_become_the_screen_settings(): void {
		$block_otp = new WcForceAuthBlockOtp( $this->otp() );

		$block = array(
			'blockName' => WcForceAuthBlockOtp::BLOCK_NAME,
			'attrs'     => array(
				'otp'             => array( 'enabled' => true, 'mode' => 'login_only', 'settings' => array( 'code_length' => '4' ) ),
				'loginHeading'    => 'Sign in here',
				'otpVerifyHeading' => 'Type the code',
				'otpCodeLabel'    => 'Your code',
				'otpReportText'   => 'Nothing arrived?',
				// Not a screen text: never mapped.
				'loginPassLabel'  => 'Password',
			),
		);

		$method = new \ReflectionMethod( $block_otp, 'settings_of_block' );
		$method->setAccessible( true );
		$settings = $method->invoke( $block_otp, $block );

		$this->assertSame( '4', $settings['code_length'], 'the page settings are kept' );
		$this->assertSame( 'Sign in here', $settings['login_heading'], 'step 1 heading' );
		$this->assertSame( 'Type the code', $settings['verify_heading'], 'step 2 heading' );
		$this->assertSame( 'Your code', $settings['verify_label_code'], 'code label' );
		$this->assertSame( 'Nothing arrived?', $settings['verify_button_report'], 'report link' );
		$this->assertFalse( isset( $settings['login_pass_label'] ), 'unrelated attributes are not mapped' );

		// A block without its own settings maps nothing.
		$this->assertSame(
			array(),
			$method->invoke( $block_otp, array( 'blockName' => WcForceAuthBlockOtp::BLOCK_NAME, 'attrs' => array( 'loginHeading' => 'x' ) ) ),
			'legacy block has no page settings'
		);
	}

	public function test_page_defaults_snapshot_the_store_values(): void {
		Wp::setOtpConfig(
			array(
				'enable_type' => 'login_only',
				'code_length' => '8',
				'brand_name'  => 'Store brand',
			)
		);

		$defaults = WcForceAuthBlockOtp::page_defaults();

		$this->assertSame( '8', $defaults['settings']['code_length'], 'snapshot keeps the store code length' );
		$this->assertSame( 'Store brand', $defaults['settings']['brand_name'], 'snapshot keeps the store brand' );
		$this->assertFalse( isset( $defaults['settings']['enable_type'] ), 'the mode is the page choice, not part of the snapshot' );
		$this->assertFalse( isset( $defaults['settings']['verify_heading'] ), 'screen texts are block attributes, not snapshot copies' );
	}
}
