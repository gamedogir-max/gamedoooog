<?php

declare(strict_types=1);

namespace GDPE\Infrastructure\Guard;

/**
 * Verifies at plugin bootstrap time that the runtime environment
 * meets GDPE's hard requirements: PHP 8.3+, Elementor, Elementor
 * Pro, and JetEngine all active.
 *
 * This class only inspects the environment — it performs no
 * business logic and belongs entirely to the Infrastructure layer.
 */
final class DependencyGuard
{
    private const MINIMUM_PHP_VERSION = '8.3.0';

    /**
     * Returns the list of unmet requirements, or an empty array if
     * every requirement is satisfied.
     *
     * @return array<int, string>
     */
    public function check(): array
    {
        $missing = [];

        if (version_compare(PHP_VERSION, self::MINIMUM_PHP_VERSION, '<')) {
            $missing[] = sprintf(
                'PHP %s or higher is required (running %s)',
                self::MINIMUM_PHP_VERSION,
                PHP_VERSION,
            );
        }

        if (!$this->isPluginActive('elementor/elementor.php')) {
            $missing[] = 'Elementor must be installed and active';
        }

        if (!$this->isPluginActive('elementor-pro/elementor-pro.php')) {
            $missing[] = 'Elementor Pro must be installed and active';
        }

        if (!$this->isPluginActive('jet-engine/jet-engine.php')) {
            $missing[] = 'JetEngine must be installed and active';
        }

        return $missing;
    }

    /**
     * Throws MissingDependencyException if any requirement is unmet.
     *
     * @throws MissingDependencyException
     */
    public function guard(): void
    {
        $missing = $this->check();

        if ($missing !== []) {
            throw new MissingDependencyException($missing);
        }
    }

    /**
     * Checks plugin activation via WordPress's is_plugin_active(),
     * loading the plugin.php admin include on demand since it is not
     * always autoloaded outside wp-admin.
     *
     * @param string $pluginBasename e.g. "jet-engine/jet-engine.php".
     */
    private function isPluginActive(string $pluginBasename): bool
    {
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return is_plugin_active($pluginBasename);
    }
}
