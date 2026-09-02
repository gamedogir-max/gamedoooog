<?php
/**
 * Plugin Name: GameDog Pedigree Engine
 * Plugin URI:  https://gamedog.ir
 * Description: Domain-driven pedigree engine for dogs with Wright COI calculation (5 generations).
 * Version:     1.0.0
 * Author:      GameDog
 * Text Domain: gamedog-pedigree-engine
 * Requires PHP: 7.4
 *
 * @package GameDog\PedigreeEngine
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('GD_PEDIGREE_VERSION', '1.0.0');
define('GD_PEDIGREE_FILE', __FILE__);
define('GD_PEDIGREE_PATH', plugin_dir_path(__FILE__));
define('GD_PEDIGREE_URL', plugin_dir_url(__FILE__));
define('GD_PEDIGREE_SIRE_RELATION_ID', 6);
define('GD_PEDIGREE_DAM_RELATION_ID', 7);
define('GD_PEDIGREE_COI_META_KEY', '_dog_coi');
define('GD_PEDIGREE_COI_DEPTH', 5);
define('GD_PEDIGREE_POST_TYPE', 'dogs');

require_once GD_PEDIGREE_PATH . 'src/Autoloader.php';

\GameDog\PedigreeEngine\Autoloader::register(GD_PEDIGREE_PATH . 'src/');

add_action('plugins_loaded', static function (): void {
    $plugin = \GameDog\PedigreeEngine\Infrastructure\WordPress\PluginBootstrap::instance();
    $plugin->boot();
});

register_activation_hook(__FILE__, static function (): void {
    \GameDog\PedigreeEngine\Infrastructure\WordPress\PluginBootstrap::activate();
});

register_deactivation_hook(__FILE__, static function (): void {
    \GameDog\PedigreeEngine\Infrastructure\WordPress\PluginBootstrap::deactivate();
});
