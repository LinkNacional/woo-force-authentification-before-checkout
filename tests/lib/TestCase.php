<?php

namespace WcfaTests\Lib;

/**
 * Thrown when an assertion fails.
 */
class AssertionError extends \Exception {
}

/**
 * Minimal test-case base class with the assertions the suite needs.
 *
 * Cases extend this and expose `test_*` methods; the Runner invokes them.
 */
abstract class TestCase {

	/** @var int Number of assertions executed in the current test. */
	public $assertions = 0;

	/**
	 * Human-readable label for the case (defaults to the class short name).
	 *
	 * @return string
	 */
	public function label(): string {
		$class = static::class;
		$pos   = strrpos( $class, '\\' );

		return false === $pos ? $class : substr( $class, $pos + 1 );
	}

	/**
	 * Optional setup run before each test method.
	 */
	public function setUp(): void {
	}

	/**
	 * Optional teardown run after each test method.
	 */
	public function tearDown(): void {
	}

	/**
	 * Mark the current test as skipped.
	 *
	 * @param string $reason Why it was skipped.
	 * @throws SkipTest
	 */
	public function skip( string $reason ): void {
		throw new SkipTest( $reason );
	}

	public function assertTrue( $condition, string $message = '' ): void {
		$this->assertions++;
		if ( true !== $condition ) {
			$this->fail( $message ?: 'Expected condition to be true, got ' . $this->export( $condition ) );
		}
	}

	public function assertFalse( $condition, string $message = '' ): void {
		$this->assertions++;
		if ( false !== $condition ) {
			$this->fail( $message ?: 'Expected condition to be false, got ' . $this->export( $condition ) );
		}
	}

	public function assertSame( $expected, $actual, string $message = '' ): void {
		$this->assertions++;
		if ( $expected !== $actual ) {
			$this->fail( $message ?: 'Expected (same) ' . $this->export( $expected ) . ' but got ' . $this->export( $actual ) );
		}
	}

	public function assertEquals( $expected, $actual, string $message = '' ): void {
		$this->assertions++;
		if ( $expected != $actual ) {
			$this->fail( $message ?: 'Expected ' . $this->export( $expected ) . ' but got ' . $this->export( $actual ) );
		}
	}

	public function assertSameSet( array $expected, array $actual, string $message = '' ): void {
		$e = $expected;
		$a = $actual;
		sort( $e );
		sort( $a );
		$this->assertSame( $e, $a, $message );
	}

	public function assertContains( string $needle, string $haystack, string $message = '' ): void {
		$this->assertions++;
		if ( '' === $needle || false === strpos( $haystack, $needle ) ) {
			$this->fail( $message ?: 'Expected to find ' . $this->export( $needle ) . ' in the haystack' );
		}
	}

	public function assertNotContains( string $needle, string $haystack, string $message = '' ): void {
		$this->assertions++;
		if ( '' !== $needle && false !== strpos( $haystack, $needle ) ) {
			$this->fail( $message ?: 'Did not expect to find ' . $this->export( $needle ) . ' in the haystack' );
		}
	}

	public function assertMatches( string $pattern, string $subject, string $message = '' ): void {
		$this->assertions++;
		if ( ! preg_match( $pattern, $subject ) ) {
			$this->fail( $message ?: 'Expected ' . $this->export( $subject ) . ' to match pattern ' . $pattern );
		}
	}

	public function assertGreaterThan( $expected, $actual, string $message = '' ): void {
		$this->assertions++;
		if ( ! ( $actual > $expected ) ) {
			$this->fail( $message ?: 'Expected ' . $this->export( $actual ) . ' to be greater than ' . $this->export( $expected ) );
		}
	}

	public function assertLessThan( $expected, $actual, string $message = '' ): void {
		$this->assertions++;
		if ( ! ( $actual < $expected ) ) {
			$this->fail( $message ?: 'Expected ' . $this->export( $actual ) . ' to be less than ' . $this->export( $expected ) );
		}
	}

	public function assertNotEmpty( $value, string $message = '' ): void {
		$this->assertions++;
		if ( empty( $value ) ) {
			$this->fail( $message ?: 'Expected a non-empty value' );
		}
	}

	public function assertArrayHasKey( $key, $array, string $message = '' ): void {
		$this->assertions++;
		if ( ! is_array( $array ) || ! array_key_exists( $key, $array ) ) {
			$this->fail( $message ?: 'Expected array to contain key ' . $this->export( $key ) );
		}
	}

	public function fail( string $message ): void {
		throw new AssertionError( $message );
	}

	/**
	 * Compact, single-line representation of a value for failure messages.
	 *
	 * @param mixed $value Value to export.
	 * @return string
	 */
	protected function export( $value ): string {
		if ( is_string( $value ) ) {
			$short = strlen( $value ) > 200 ? substr( $value, 0, 200 ) . '…' : $value;

			return '"' . $short . '"';
		}

		if ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		}

		if ( is_null( $value ) ) {
			return 'null';
		}

		if ( is_array( $value ) ) {
			return 'array(' . count( $value ) . ') ' . json_encode( $value );
		}

		return (string) $value;
	}
}

/**
 * Thrown to mark a test as skipped.
 */
class SkipTest extends \Exception {
}
