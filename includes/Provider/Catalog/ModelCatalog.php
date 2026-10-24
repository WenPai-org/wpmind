<?php
/**
 * Bundled model capability catalog (single load point).
 *
 * Model ids, capabilities, status (active/deprecated/retired), replacements and
 * optional pricing come from models.json shipped with the plugin. Call sites must
 * not hardcode retired models as defaults.
 *
 * Retired handling (D9=a):
 * 1. retired + replaced_by → remap, fire wpmind_model_remapped
 * 2. retired without replacement → provider default_model
 * 3. unknown id → passthrough as text/chat
 *
 * @package WPMind\Provider\Catalog
 * @since 0.13.0
 */

declare(strict_types=1);

namespace WPMind\Provider\Catalog;

/**
 * Loads and queries the bundled model catalog.
 *
 * @since 0.13.0
 */
final class ModelCatalog {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Decoded catalog payload.
	 *
	 * @var array<string, mixed>
	 */
	private array $data = [];

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->load();
	}

	/**
	 * Shared instance.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Reset singleton (tests only).
	 *
	 * @internal
	 */
	public static function reset_instance(): void {
		self::$instance = null;
	}

	/**
	 * Catalog version string.
	 */
	public function version(): string {
		return (string) ( $this->data['catalog_version'] ?? '' );
	}

	/**
	 * Raw provider block or null.
	 *
	 * @param string $provider_id Provider id.
	 * @return array<string, mixed>|null
	 */
	public function provider( string $provider_id ): ?array {
		$providers = $this->data['providers'] ?? [];
		if ( ! is_array( $providers ) || ! isset( $providers[ $provider_id ] ) || ! is_array( $providers[ $provider_id ] ) ) {
			return null;
		}
		return $providers[ $provider_id ];
	}

	/**
	 * Recommended default model for a provider (never retired).
	 *
	 * @param string $provider_id Provider id.
	 * @return string
	 */
	public function default_model( string $provider_id ): string {
		$block = $this->provider( $provider_id );
		if ( null === $block ) {
			return '';
		}
		$default = (string) ( $block['default_model'] ?? '' );
		if ( '' === $default ) {
			$ids = $this->recommended_ids( $provider_id );
			return $ids[0] ?? '';
		}
		$meta = $this->model_meta( $provider_id, $default );
		if ( null !== $meta && 'retired' === ( $meta['status'] ?? '' ) ) {
			$ids = $this->recommended_ids( $provider_id );
			return $ids[0] ?? $default;
		}
		return $default;
	}

	/**
	 * Model ids suitable for UI / defaults (active; if none, deprecated).
	 *
	 * Retired ids are never included.
	 *
	 * @param string $provider_id Provider id.
	 * @return list<string>
	 */
	public function recommended_ids( string $provider_id ): array {
		$block = $this->provider( $provider_id );
		if ( null === $block ) {
			return [];
		}
		$models     = is_array( $block['models'] ?? null ) ? $block['models'] : [];
		$active     = [];
		$deprecated = [];
		foreach ( $models as $id => $meta ) {
			if ( ! is_string( $id ) || ! is_array( $meta ) ) {
				continue;
			}
			$status = (string) ( $meta['status'] ?? 'active' );
			if ( 'retired' === $status ) {
				continue;
			}
			if ( 'deprecated' === $status ) {
				$deprecated[] = $id;
			} else {
				$active[] = $id;
			}
		}
		$ids     = [] !== $active ? $active : $deprecated;
		$default = (string) ( $block['default_model'] ?? '' );
		if ( '' !== $default && in_array( $default, $ids, true ) ) {
			$ids = array_values( array_unique( array_merge( [ $default ], $ids ) ) );
		}
		return $ids;
	}

	/**
	 * Id to display-name map for SDK ModelMetadataDirectory (skips retired).
	 *
	 * @param string $provider_id        Provider id.
	 * @param bool   $include_deprecated Whether to include deprecated models.
	 * @return array<string, string>
	 */
	public function for_provider_map( string $provider_id, bool $include_deprecated = true ): array {
		$block = $this->provider( $provider_id );
		if ( null === $block ) {
			return [];
		}
		$models = is_array( $block['models'] ?? null ) ? $block['models'] : [];
		$map    = [];
		foreach ( $models as $id => $meta ) {
			if ( ! is_string( $id ) || ! is_array( $meta ) ) {
				continue;
			}
			$status = (string) ( $meta['status'] ?? 'active' );
			if ( 'retired' === $status ) {
				continue;
			}
			if ( ! $include_deprecated && 'deprecated' === $status ) {
				continue;
			}
			$map[ $id ] = (string) ( $meta['name'] ?? $id );
		}
		return $map;
	}

	/**
	 * Filtered model entries.
	 *
	 * @param string      $provider_id        Provider id.
	 * @param string|null $capability         Optional capability filter.
	 * @param bool        $include_deprecated Whether to include deprecated models.
	 * @return array<string, array<string, mixed>>
	 */
	public function for_provider( string $provider_id, ?string $capability = null, bool $include_deprecated = false ): array {
		$block = $this->provider( $provider_id );
		if ( null === $block ) {
			return [];
		}
		$models = is_array( $block['models'] ?? null ) ? $block['models'] : [];
		$out    = [];
		foreach ( $models as $id => $meta ) {
			if ( ! is_string( $id ) || ! is_array( $meta ) ) {
				continue;
			}
			$status = (string) ( $meta['status'] ?? 'active' );
			if ( 'retired' === $status ) {
				continue;
			}
			if ( ! $include_deprecated && 'deprecated' === $status ) {
				continue;
			}
			if ( null !== $capability ) {
				$caps = $meta['capabilities'] ?? [];
				if ( ! is_array( $caps ) || ! in_array( $capability, $caps, true ) ) {
					continue;
				}
			}
			$out[ $id ] = $meta;
		}
		return $out;
	}

	/**
	 * Model metadata entry.
	 *
	 * @param string $provider_id Provider id.
	 * @param string $model_id    Model id.
	 * @return array<string, mixed>|null
	 */
	public function model_meta( string $provider_id, string $model_id ): ?array {
		$block = $this->provider( $provider_id );
		if ( null === $block ) {
			return null;
		}
		$models = is_array( $block['models'] ?? null ) ? $block['models'] : [];
		if ( ! isset( $models[ $model_id ] ) || ! is_array( $models[ $model_id ] ) ) {
			return null;
		}
		return $models[ $model_id ];
	}

	/**
	 * Resolve a requested model (aliases + retired remap).
	 *
	 * @param string $provider_id Provider id.
	 * @param string $model_id    Requested model id.
	 * @return ModelResolution
	 */
	public function resolve( string $provider_id, string $model_id ): ModelResolution {
		$model_id = trim( $model_id );
		if ( '' === $model_id || 'auto' === $model_id || 'default' === $model_id ) {
			$default = $this->default_model( $provider_id );
			return new ModelResolution( $default, null, 'user_default' );
		}

		$block = $this->provider( $provider_id );
		if ( null === $block ) {
			return new ModelResolution( $model_id, null, 'unknown_passthrough' );
		}

		$aliases = is_array( $block['aliases'] ?? null ) ? $block['aliases'] : [];
		if ( isset( $aliases[ $model_id ] ) && is_string( $aliases[ $model_id ] ) && '' !== $aliases[ $model_id ] ) {
			$mapped = $aliases[ $model_id ];
			$res    = $this->resolve_status( $provider_id, $mapped, $model_id, 'alias' );
			$this->maybe_fire_remap( $provider_id, $res );
			return $res;
		}

		$res = $this->resolve_status( $provider_id, $model_id, null, '' );
		$this->maybe_fire_remap( $provider_id, $res );
		return $res;
	}

	/**
	 * Pricing from catalog, or null to fall back to Pricing::DATA.
	 *
	 * @param string $provider_id Provider id.
	 * @param string $model_id    Model id.
	 * @return array{currency?: string, input: float, output: float}|null
	 */
	public function pricing( string $provider_id, string $model_id ): ?array {
		$meta = $this->model_meta( $provider_id, $model_id );
		if ( null === $meta ) {
			// Try prefix-free alias target.
			$resolved = $this->resolve( $provider_id, $model_id );
			if ( $resolved->model !== $model_id ) {
				$meta = $this->model_meta( $provider_id, $resolved->model );
			}
		}
		if ( null === $meta ) {
			return null;
		}
		$pricing = $meta['pricing'] ?? null;
		if ( ! is_array( $pricing ) ) {
			return null;
		}
		if ( ! array_key_exists( 'input', $pricing ) || ! array_key_exists( 'output', $pricing ) ) {
			return null;
		}
		if ( null === $pricing['input'] || null === $pricing['output'] ) {
			return null;
		}
		return [
			'currency' => isset( $pricing['currency'] ) ? (string) $pricing['currency'] : '',
			'input'    => (float) $pricing['input'],
			'output'   => (float) $pricing['output'],
		];
	}

	/**
	 * Gateway-facing aliases: every catalog model id + provider aliases + legacy external names.
	 *
	 * @return array<string, array{provider: string, model: string}>
	 */
	public function gateway_alias_map(): array {
		$map       = [];
		$providers = is_array( $this->data['providers'] ?? null ) ? $this->data['providers'] : [];
		foreach ( $providers as $provider_id => $block ) {
			if ( ! is_string( $provider_id ) || ! is_array( $block ) ) {
				continue;
			}
			$models = is_array( $block['models'] ?? null ) ? $block['models'] : [];
			foreach ( array_keys( $models ) as $model_id ) {
				if ( ! is_string( $model_id ) ) {
					continue;
				}
				$map[ $model_id ] = [
					'provider' => $provider_id,
					'model'    => $model_id,
				];
			}
			$aliases = is_array( $block['aliases'] ?? null ) ? $block['aliases'] : [];
			foreach ( $aliases as $from => $to ) {
				if ( ! is_string( $from ) || ! is_string( $to ) || '' === $to ) {
					continue;
				}
				$map[ $from ] = [
					'provider' => $provider_id,
					'model'    => $to,
				];
			}
		}
		return $map;
	}

	/**
	 * Raw decoded catalog (schema tests).
	 *
	 * @return array<string, mixed>
	 */
	public function raw(): array {
		return $this->data;
	}

	/**
	 * Path to bundled JSON.
	 */
	public static function catalog_path(): string {
		return __DIR__ . '/models.json';
	}

	/**
	 * Load models.json from disk.
	 *
	 * @return void
	 */
	private function load(): void {
		$path = self::catalog_path();
		if ( ! is_readable( $path ) ) {
			$this->data = [
				'catalog_version' => '',
				'providers'       => [],
			];
			return;
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local bundled JSON.
		$json = file_get_contents( $path );
		if ( false === $json ) {
			$this->data = [
				'catalog_version' => '',
				'providers'       => [],
			];
			return;
		}
		$decoded    = json_decode( $json, true );
		$this->data = is_array( $decoded ) ? $decoded : [
			'catalog_version' => '',
			'providers'       => [],
		];
	}

	/**
	 * Apply status and replacement rules for a model id.
	 *
	 * @param string      $provider_id Provider id.
	 * @param string      $model_id    Candidate model id.
	 * @param string|null $from        Original requested id when already aliased.
	 * @param string      $base_reason Base reason when status is not retired.
	 * @return ModelResolution
	 */
	private function resolve_status( string $provider_id, string $model_id, ?string $from, string $base_reason ): ModelResolution {
		$meta = $this->model_meta( $provider_id, $model_id );
		if ( null === $meta ) {
			return new ModelResolution( $model_id, $from, '' !== $base_reason ? $base_reason : 'unknown_passthrough' );
		}
		$status = (string) ( $meta['status'] ?? 'active' );
		if ( 'retired' !== $status ) {
			return new ModelResolution( $model_id, $from, $base_reason );
		}
		$replaced = $meta['replaced_by'] ?? null;
		if ( is_string( $replaced ) && '' !== $replaced ) {
			$origin = $from ?? $model_id;
			return new ModelResolution( $replaced, $origin, 'retired_replaced' );
		}
		$default = $this->default_model( $provider_id );
		$origin  = $from ?? $model_id;
		return new ModelResolution( $default, $origin, 'retired_default' );
	}

	/**
	 * Fire remap action when applicable.
	 *
	 * @param string          $provider_id Provider id.
	 * @param ModelResolution $res         Resolution result.
	 * @return void
	 */
	private function maybe_fire_remap( string $provider_id, ModelResolution $res ): void {
		if ( ! $res->was_remapped() || ! function_exists( 'do_action' ) ) {
			return;
		}
		/**
		 * Fires when a retired/aliased model is remapped.
		 *
		 * @param string $provider_id Provider id.
		 * @param string $from        Original model id.
		 * @param string $to          Remapped model id.
		 */
		do_action( 'wpmind_model_remapped', $provider_id, (string) $res->remapped_from, $res->model );
	}
}
