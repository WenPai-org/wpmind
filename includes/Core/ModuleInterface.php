<?php
/**
 * Module Interface
 *
 * Interface for WPMind modules.
 *
 * @package WPMind\Core
 * @since 3.2.0
 */

declare(strict_types=1);

namespace WPMind\Core;

/**
 * Interface ModuleInterface
 *
 * All WPMind modules must implement this interface.
 */
interface ModuleInterface {

	/**
	 * Get module ID.
	 *
	 * @return string Unique module identifier.
	 */
	public function get_id(): string;

	/**
	 * Get module name.
	 *
	 * @return string Human-readable module name.
	 */
	public function get_name(): string;

	/**
	 * Get module description.
	 *
	 * @return string Module description.
	 */
	public function get_description(): string;

	/**
	 * Get module version.
	 *
	 * @return string Module version.
	 */
	public function get_version(): string;

	/**
	 * Initialize the module.
	 *
	 * Called when the module is loaded and enabled.
	 */
	public function init(): void;

	/**
	 * Check if module dependencies are met.
	 *
	 * @return bool True if dependencies are satisfied.
	 */
	public function check_dependencies(): bool;

	/**
	 * Get module settings tab slug.
	 *
	 * @return string|null Settings tab slug or null if no settings.
	 */
	public function get_settings_tab(): ?string;
}
