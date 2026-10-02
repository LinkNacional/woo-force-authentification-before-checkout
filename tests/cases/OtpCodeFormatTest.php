<?php

namespace WcfaTests\Cases;

use WcfaTests\Lib\Wp;
use WcfaTests\Lib\WpTestCase;

/**
 * Code generation + the numeric layout logic that drives the input (length,
 * grouping, placeholder and maxlength) across every supported combination.
 */
class OtpCodeFormatTest extends WpTestCase {

	/**
	 * Expected group sizes for a given length + format.
	 *
	 * @return array<string,array<int,int>>
	 */
	private function expectedGroups(): array {
		return array(
			'4|plain' => array( 4 ),
			'4|space' => array( 2, 2 ),
			'4|dash'  => array( 2, 2 ),
			'4|pair'  => array( 2, 2 ),
			'6|plain' => array( 6 ),
			'6|space' => array( 3, 3 ),
			'6|dash'  => array( 3, 3 ),
			'6|pair'  => array( 2, 2, 2 ),
			'8|plain' => array( 8 ),
			'8|space' => array( 4, 4 ),
			'8|dash'  => array( 4, 4 ),
			'8|pair'  => array( 2, 2, 2, 2 ),
		);
	}

	public function test_defaults_are_sane(): void {
		$defaults = \Lkn\WcForceAuth\Includes\WcForceAuthOtp::defaults();
		$this->assertSame( '6', $defaults['code_length'], 'default length' );
		$this->assertSame( 'plain', $defaults['code_format'], 'default format' );
		$this->assertSame( 'single', $defaults['code_input'], 'default input' );

		// With the options removed, the class falls back to those defaults.
		delete_option( \Lkn\WcForceAuth\Includes\WcForceAuthOtp::OPTION_PREFIX . 'code_length' );
		delete_option( \Lkn\WcForceAuth\Includes\WcForceAuthOtp::OPTION_PREFIX . 'code_format' );
		delete_option( \Lkn\WcForceAuth\Includes\WcForceAuthOtp::OPTION_PREFIX . 'code_input' );

		$otp = new \Lkn\WcForceAuth\Includes\WcForceAuthOtp( 'x', '2' );
		$this->assertSame( 6, $otp->get_code_length(), 'fallback length is 6' );
		$this->assertSame( 'plain', $otp->get_code_format(), 'fallback format is plain' );
		$this->assertSame( 'single', $otp->get_code_input(), 'fallback input is single' );
	}

	public function test_group_sizes_for_every_combination(): void {
		foreach ( $this->expectedGroups() as $combo => $expected ) {
			list( $length, $format ) = explode( '|', $combo );

			Wp::setOtpConfig(
				array(
					'enable_type' => 'register_and_login',
					'code_length' => $length,
					'code_format' => $format,
				)
			);

			$otp = new \Lkn\WcForceAuth\Includes\WcForceAuthOtp( 'x', '2' );
			$this->assertSame(
				$expected,
				$otp->get_code_group_sizes(),
				"groups for length={$length} format={$format}"
			);
		}
	}

	public function test_placeholder_matches_grouping(): void {
		$cases = array(
			array( '4', 'plain', '••••' ),
			array( '4', 'space', '•• ••' ),
			array( '4', 'dash', '••-••' ),
			array( '6', 'plain', '••••••' ),
			array( '6', 'space', '••• •••' ),
			array( '6', 'dash', '•••-•••' ),
			array( '6', 'pair', '•• •• ••' ),
			array( '8', 'pair', '•• •• •• ••' ),
			array( '8', 'dash', '••••-••••' ),
		);

		foreach ( $cases as $case ) {
			list( $length, $format, $expected ) = $case;

			Wp::setOtpConfig(
				array(
					'code_length' => $length,
					'code_format' => $format,
				)
			);

			$otp = new \Lkn\WcForceAuth\Includes\WcForceAuthOtp( 'x', '2' );
			$this->assertSame( $expected, $otp->get_code_placeholder(), "placeholder {$length}/{$format}" );
		}
	}

	public function test_maxlength_allows_separators(): void {
		$cases = array(
			array( '4', 'plain', 4 ),
			array( '6', 'plain', 6 ),
			array( '8', 'plain', 8 ),
			array( '6', 'space', 7 ),
			array( '6', 'dash', 7 ),
			array( '6', 'pair', 8 ),
			array( '8', 'pair', 11 ),
			array( '4', 'dash', 5 ),
		);

		foreach ( $cases as $case ) {
			list( $length, $format, $expected ) = $case;

			Wp::setOtpConfig(
				array(
					'code_length' => $length,
					'code_format' => $format,
				)
			);

			$otp = new \Lkn\WcForceAuth\Includes\WcForceAuthOtp( 'x', '2' );
			$this->assertSame( $expected, $otp->get_code_maxlength(), "maxlength {$length}/{$format}" );
			$this->assertSame(
				$expected,
				mb_strlen( $otp->get_code_placeholder() ),
				"placeholder length equals maxlength for {$length}/{$format}"
			);
		}
	}

	public function test_generated_code_length_and_digits(): void {
		foreach ( array( '4', '6', '8' ) as $length ) {
			Wp::setOtpConfig(
				array(
					'enable_type' => 'register_and_login',
					'code_length' => $length,
				)
			);

			$otp = new \Lkn\WcForceAuth\Includes\WcForceAuthOtp( 'x', '2' );

			// Run a few times to catch flakiness in the random generator.
			for ( $i = 0; $i < 25; $i++ ) {
				$code = $otp->generate_code();
				$this->assertSame( (int) $length, strlen( $code ), "generated code length={$length}" );
				$this->assertMatches( '/^\d+$/', $code, 'generated code is numeric' );
			}
		}
	}

	public function test_invalid_options_fall_back_to_defaults(): void {
		Wp::setOtpConfig(
			array(
				'code_length' => '5',
				'code_format' => 'weird',
				'code_input'  => 'nope',
			)
		);

		$otp = new \Lkn\WcForceAuth\Includes\WcForceAuthOtp( 'x', '2' );
		$this->assertSame( 6, $otp->get_code_length(), 'invalid length -> 6' );
		$this->assertSame( 'plain', $otp->get_code_format(), 'invalid format -> plain' );
		$this->assertSame( 'single', $otp->get_code_input(), 'invalid input -> single' );
	}

	public function test_generated_code_zero_pads(): void {
		// A 4-digit code must never come out shorter (e.g. "0007", not "7").
		Wp::setOtpConfig(
			array(
				'enable_type' => 'register_and_login',
				'code_length' => '4',
			)
		);

		$otp = new \Lkn\WcForceAuth\Includes\WcForceAuthOtp( 'x', '2' );

		for ( $i = 0; $i < 50; $i++ ) {
			$this->assertSame( 4, strlen( $otp->generate_code() ), 'code is always 4 digits' );
		}
	}
}
