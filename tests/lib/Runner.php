<?php

namespace WcfaTests\Lib;

/**
 * Discovers `*Test.php` cases under a directory and runs their `test_*` methods,
 * printing a TAP-ish report and returning an exit code.
 */
class Runner {

	/** @var string */
	private $cases_dir;

	/** @var string Case-name filter (substring match), or '' for all. */
	private $filter;

	/** @var bool */
	private $verbose;

	/**
	 * @param string $cases_dir Directory holding the `*Test.php` files.
	 * @param string $filter    Only run cases whose label contains this (optional).
	 * @param bool   $verbose   Print stack traces on failures.
	 */
	public function __construct( string $cases_dir, string $filter = '', bool $verbose = false ) {
		$this->cases_dir = rtrim( $cases_dir, '/' );
		$this->filter    = $filter;
		$this->verbose   = $verbose;
	}

	/**
	 * Run every discovered test and return the number of failures.
	 *
	 * @return int
	 */
	public function run(): int {
		$files = glob( $this->cases_dir . '/*Test.php' );
		sort( $files );

		$total = 0;
		$pass  = 0;
		$fail  = 0;
		$skip  = 0;
		$start = microtime( true );

		foreach ( $files as $file ) {
			$before = get_declared_classes();
			require_once $file;
			$new = array_diff( get_declared_classes(), $before );

			foreach ( $new as $class ) {
				if ( ! is_subclass_of( $class, TestCase::class ) ) {
					continue;
				}

				$instance = new $class();
				$label    = $instance->label();

				if ( '' !== $this->filter && false === stripos( $label, $this->filter ) ) {
					continue;
				}

				echo "\n\033[1m" . $label . "\033[0m\n";

				$methods = array_filter(
					get_class_methods( $instance ),
					static function ( $m ) {
						return 0 === strpos( $m, 'test' );
					}
				);

				foreach ( $methods as $method ) {
					$total++;
					$this->runOne( $instance, $method, $pass, $fail, $skip );
				}
			}
		}

		$elapsed = number_format( microtime( true ) - $start, 2 );

		echo "\n" . str_repeat( '─', 60 ) . "\n";
		echo sprintf(
			"%d tests, %d passed, %d failed, %d skipped (%ss)\n",
			$total,
			$pass,
			$fail,
			$skip,
			$elapsed
		);

		return $fail;
	}

	/**
	 * Run a single test method, updating the counters.
	 *
	 * @param TestCase $instance    The case instance.
	 * @param string   $method      Method name.
	 * @param int      $pass        Pass counter (by ref).
	 * @param int      $fail        Fail counter (by ref).
	 * @param int      $skip        Skip counter (by ref).
	 */
	private function runOne( TestCase $instance, string $method, int &$pass, int &$fail, int &$skip ): void {
		$name = preg_replace( '/^test_?/', '', $method );
		$name = str_replace( '_', ' ', $name );

		$instance->assertions = 0;

		try {
			$instance->setUp();
			$instance->$method();
			$instance->tearDown();

			++$pass;
			echo "  \033[32m✓\033[0m " . $name . "\n";
		} catch ( SkipTest $e ) {
			++$skip;
			echo "  \033[33m~\033[0m " . $name . ' (skipped: ' . $e->getMessage() . ")\n";

			try {
				$instance->tearDown();
			} catch ( \Throwable $ignored ) {
				// Teardown failure shouldn't mask the skip.
			}
		} catch ( \Throwable $e ) {
			++$fail;
			echo "  \033[31m✗\033[0m " . $name . "\n";
			echo '      ' . $e->getMessage() . "\n";
			echo '      at ' . str_replace( ABSPATH, '', $e->getFile() ) . ':' . $e->getLine() . "\n";

			if ( $this->verbose ) {
				echo $e->getTraceAsString() . "\n";
			}

			try {
				$instance->tearDown();
			} catch ( \Throwable $ignored ) {
				// Teardown failure shouldn't mask the real error.
			}
		}
	}
}
