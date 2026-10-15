<?php

namespace WcfaTests\Cases;

use WcfaTests\Lib\Wp;
use WcfaTests\Lib\WpTestCase;

/**
 * Rendering of the OTP field: a single input, or one box per digit
 * ("fragmented" / segmented) grouped per the chosen format.
 */
class OtpSegmentedMarkupTest extends WpTestCase {

	public function test_single_input_renders_one_field(): void {
		Wp::setOtpConfig(
			array(
				'enable_type' => 'register_and_login',
				'code_input'  => 'single',
				'code_length' => '6',
				'code_format' => 'plain',
			)
		);

		$html = Wp::renderOtpForm();

		$this->assertContains( 'id="wcfa_code"', $html, 'single input present' );
		$this->assertContains( 'name="code"', $html, 'single input named code' );
		$this->assertNotContains( 'wcfa-otp-box', $html, 'no segmented boxes' );
		$this->assertNotContains( 'id="wcfa-otp-boxes"', $html, 'no boxes wrapper' );
		$this->assertContains( 'maxlength="6"', $html, 'maxlength for 6 digits' );
	}

	public function test_segmented_renders_one_box_per_digit(): void {
		$expectations = array(
			array( '4', 'plain', 4, 0 ),
			array( '6', 'plain', 6, 0 ),
			array( '8', 'plain', 8, 0 ),
			array( '6', 'space', 6, 1 ),
			array( '6', 'dash', 6, 1 ),
			array( '6', 'pair', 6, 2 ),
			array( '8', 'pair', 8, 3 ),
			array( '8', 'dash', 8, 1 ),
		);

		foreach ( $expectations as $case ) {
			list( $length, $format, $boxes, $seps ) = $case;

			Wp::setOtpConfig(
				array(
					'enable_type' => 'register_and_login',
					'code_input'  => 'segmented',
					'code_length' => $length,
					'code_format' => $format,
				)
			);

			$html = Wp::renderOtpForm();
			$label = "length={$length} format={$format}";

			$this->assertContains( 'id="wcfa-otp-boxes"', $html, "boxes wrapper ($label)" );
			$this->assertNotContains( 'id="wcfa_code"', $html, "no single input ($label)" );
			$this->assertSame( $boxes, substr_count( $html, 'class="wcfa-otp-box"' ), "box count ($label)" );
			$this->assertSame( $seps, substr_count( $html, 'class="wcfa-otp-sep"' ), "separator count ($label)" );

			// Exactly one name="code" (the first box carries the POST name).
			$this->assertSame( 1, substr_count( $html, 'name="code"' ), "single name=code ($label)" );

			// Every box behaves like a single digit field.
			$this->assertSame(
				$boxes,
				substr_count( $html, 'maxlength="1"' ),
				"each box maxlength=1 ($label)"
			);

			// The label points at the first box.
			$this->assertContains( 'for="wcfa_code_0"', $html, "label targets first box ($label)" );
		}
	}

	public function test_segmented_boxes_have_unique_ids(): void {
		Wp::setOtpConfig(
			array(
				'enable_type' => 'register_and_login',
				'code_input'  => 'segmented',
				'code_length' => '8',
				'code_format' => 'pair',
			)
		);

		$html = Wp::renderOtpForm();

		for ( $i = 0; $i < 8; $i++ ) {
			$this->assertContains( 'id="wcfa_code_' . $i . '"', $html, "box id wcfa_code_{$i}" );
		}

		$this->assertNotContains( 'id="wcfa_code_8"', $html, 'no extra box id beyond length' );
	}

	public function test_segmented_first_box_is_one_time_code_autocomplete(): void {
		Wp::setOtpConfig(
			array(
				'enable_type' => 'register_and_login',
				'code_input'  => 'segmented',
				'code_length' => '6',
				'code_format' => 'space',
			)
		);

		$html = Wp::renderOtpForm();

		// one-time-code autocomplete on the first box only.
		$this->assertSame( 1, substr_count( $html, 'autocomplete="one-time-code"' ), 'one-time-code once' );
	}
}
