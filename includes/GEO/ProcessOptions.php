<?php
/**
 * Process Options
 *
 * Configuration options for MarkdownProcessor.
 *
 * @package WPMind\GEO
 * @since 3.1.0
 */

namespace WPMind\GEO;

/**
 * Class ProcessOptions
 *
 * Configurable options for Markdown processing pipeline.
 */
class ProcessOptions {

	/**
	 * Whether to add metadata section.
	 *
	 * @var bool
	 */
	public bool $add_metadata = true;

	/**
	 * Whether to allow content rewriting/optimization.
	 *
	 * @var bool
	 */
	public bool $allow_rewrite = true;

	/**
	 * Whether to skip language-specific optimization (Chinese).
	 *
	 * @var bool
	 */
	public bool $skip_language_opt = false;

	/**
	 * Whether to skip GEO signal injection.
	 *
	 * @var bool
	 */
	public bool $skip_geo_signals = false;

	/**
	 * Create options from array.
	 *
	 * @param array $options Options array.
	 * @return self
	 */
	public static function from_array( array $options ): self {
		$instance = new self();

		if ( isset( $options['add_metadata'] ) ) {
			$instance->add_metadata = (bool) $options['add_metadata'];
		}

		if ( isset( $options['allow_rewrite'] ) ) {
			$instance->allow_rewrite = (bool) $options['allow_rewrite'];
		}

		if ( isset( $options['skip_language_opt'] ) ) {
			$instance->skip_language_opt = (bool) $options['skip_language_opt'];
		}

		if ( isset( $options['skip_geo_signals'] ) ) {
			$instance->skip_geo_signals = (bool) $options['skip_geo_signals'];
		}

		return $instance;
	}

	/**
	 * Create options from WordPress settings.
	 *
	 * @return self
	 */
	public static function from_settings(): self {
		$instance = new self();

		$instance->add_metadata      = true;
		$instance->allow_rewrite     = true;
		$instance->skip_language_opt = ! get_option( 'wpmind_chinese_optimize', true );
		$instance->skip_geo_signals  = ! get_option( 'wpmind_geo_signals', true );

		return $instance;
	}
}
