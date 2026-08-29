<?php

/**
 * Plugin Name:       GameDog Pedigree Engine
 * Plugin URI:        https://github.com/RayanCo/gamedog-pedigree-engine
 * Description:       Pedigree tree and relation lookups (Full Siblings, Same Sire, Same Dam) for dogs managed as a JetEngine CPT, rendered through Elementor.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.3
 * Author:            RayanCo
 * License:           Proprietary
 * Text Domain:       gdpe
 * Domain Path:       /languages
 */

declare(strict_types=1);

use GDPE\Application\UseCase\ClearPedigreeCacheUseCase;
use GDPE\Application\UseCase\SavePluginSettingsUseCase;
use GDPE\Infrastructure\Bootstrap\PluginBootstrap;
use GDPE\Infrastructure\Config\JetEngineFieldMap;
use GDPE\Infrastructure\Guard\MissingDependencyException;
use GDPE\Infrastructure\Settings\DefaultPluginSettingsFactory;

if (!defined('ABSPATH')) {
    exit;
}

if (!is_readable(__DIR__ . '/vendor/autoload.php')) {
    add_action('admin_notices', static function (): void {
        echo '<div class="notice notice-error"><p>';
        echo esc_html__(
            'GameDog Pedigree Engine: vendor/autoload.php is missing. Run composer install.',
            'gdpe'
        );
        echo '</p></div>';
    });

    return;
}

require_once __DIR__ . '/vendor/autoload.php';

define('GDPE_VERSION', '1.0.0');
define('GDPE_PLUGIN_FILE', __FILE__);
define('GDPE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GDPE_PLUGIN_URL', plugin_dir_url(__FILE__));

function gdpe_bootstrap(): PluginBootstrap
{
    static $bootstrap = null;

    if ($bootstrap === null) {
        $bootstrap = new PluginBootstrap(
            GDPE_PLUGIN_DIR . 'templates/pedigree',
            GDPE_PLUGIN_URL . 'assets/css/pedigree-tree.css',
            GDPE_VERSION,
        );
    }

    return $bootstrap;
}

function gdpe_render_dependency_notice(MissingDependencyException $exception): void
{
    add_action('admin_notices', static function () use ($exception): void {

        echo '<div class="notice notice-error"><p><strong>';
        echo esc_html__('GameDog Pedigree Engine is inactive.', 'gdpe');
        echo '</strong></p>';

        echo '<ul style="list-style:disc;padding-left:20px;">';

        foreach ($exception->getMissingDependencies() as $requirement) {
            echo '<li>' . esc_html($requirement) . '</li>';
        }

        echo '</ul></div>';
    });
}

add_action('plugins_loaded', static function (): void {

    load_plugin_textdomain(
        'gdpe',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );

    error_log('==============================');
    error_log('GDPE -> plugins_loaded');

    try {

        gdpe_bootstrap()->boot();

    } catch (Throwable $e) {

        error_log('==============================');
        error_log('GDPE BOOT FAILED');
        error_log($e->getMessage());
        error_log($e->getFile() . ':' . $e->getLine());

        if ($e instanceof MissingDependencyException) {
            gdpe_render_dependency_notice($e);
        }

    }

});

register_activation_hook(__FILE__, static function (): void {

    if (get_option(JetEngineFieldMap::OPTION_KEY_SETTINGS, false) !== false) {
        return;
    }

    $container = gdpe_bootstrap()->getContainer();

    $container->get(SavePluginSettingsUseCase::class)->execute(
        $container->get(DefaultPluginSettingsFactory::class)->create()
    );
});

register_deactivation_hook(__FILE__, static function (): void {

    gdpe_bootstrap()
        ->getContainer()
        ->get(ClearPedigreeCacheUseCase::class)
        ->execute();

});