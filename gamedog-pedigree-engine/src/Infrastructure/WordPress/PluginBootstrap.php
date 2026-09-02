<?php
/**
 * WordPress plugin bootstrap — wires hooks, shortcodes, assets, Elementor.
 *
 * @package GameDog\PedigreeEngine\Infrastructure\WordPress
 */

declare(strict_types=1);

namespace GameDog\PedigreeEngine\Infrastructure\WordPress;

use GameDog\PedigreeEngine\Presentation\Shortcode\PedigreeTreeShortcode;
use GameDog\PedigreeEngine\Presentation\Shortcode\SiblingsBoxShortcode;

final class PluginBootstrap
{
    /** @var self|null */
    private static $instance = null;

    /** @var ServiceContainer */
    private $container;

    private function __construct()
    {
        $this->container = new ServiceContainer();
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function container(): ServiceContainer
    {
        return $this->container;
    }

    public function boot(): void
    {
        add_action('init', [$this, 'registerShortcodes']);
        add_action('wp_enqueue_scripts', [$this, 'registerAssets']);
        add_action('elementor/widgets/register', [$this, 'registerElementorWidgets']);
        add_action('elementor/widgets/widgets_registered', [$this, 'registerElementorWidgetsLegacy']);

        /** @var ParentConnectionRegistrar $registrar */
        $registrar = $this->container->get(ParentConnectionRegistrar::class);
        $registrar->register();
    }

    public function registerShortcodes(): void
    {
        /** @var PedigreeTreeShortcode $pedigree */
        $pedigree = $this->container->get(PedigreeTreeShortcode::class);
        $pedigree->register();

        /** @var SiblingsBoxShortcode $siblings */
        $siblings = $this->container->get(SiblingsBoxShortcode::class);
        $siblings->register();
    }

    public function registerAssets(): void
    {
        $css = GD_PEDIGREE_URL . 'assets/css/pedigree.css';
        $js  = GD_PEDIGREE_URL . 'assets/js/pedigree.js';

        wp_register_style(
            'gamedog-pedigree',
            $css,
            [],
            GD_PEDIGREE_VERSION
        );

        wp_register_script(
            'gamedog-pedigree',
            $js,
            [],
            GD_PEDIGREE_VERSION,
            true
        );
    }

    /**
     * Elementor 3.5+ widgets registration.
     *
     * @param mixed $widgets_manager
     */
    public function registerElementorWidgets($widgets_manager = null): void
    {
        if (!class_exists('\\Elementor\\Widget_Base')) {
            return;
        }

        $widget = $this->makeElementorWidget();
        if ($widget === null) {
            return;
        }

        if ($widgets_manager && method_exists($widgets_manager, 'register')) {
            $widgets_manager->register($widget);
        }
    }

    /**
     * Legacy Elementor widgets_registered hook.
     */
    public function registerElementorWidgetsLegacy(): void
    {
        if (!class_exists('\\Elementor\\Plugin') || !class_exists('\\Elementor\\Widget_Base')) {
            return;
        }

        $widget = $this->makeElementorWidget();
        if ($widget === null) {
            return;
        }

        \Elementor\Plugin::instance()->widgets_manager->register_widget_type($widget);
    }

    /**
     * @return object|null
     */
    private function makeElementorWidget()
    {
        if (!class_exists('\\Elementor\\Widget_Base')) {
            return null;
        }

        $class = 'GameDog\\PedigreeEngine\\Presentation\\Elementor\\PedigreeTreeWidget';

        // Load only after Elementor is available so the class can extend Widget_Base.
        $widgetFile = GD_PEDIGREE_PATH . 'src/Presentation/Elementor/PedigreeTreeWidget.php';
        if (!class_exists($class, false) && is_file($widgetFile)) {
            require_once $widgetFile;
        }

        if (!class_exists($class, false)) {
            return null;
        }

        return new $class($this->container);
    }

    public static function activate(): void
    {
        // Placeholder for future DB / capability setup.
        if (function_exists('flush_rewrite_rules')) {
            flush_rewrite_rules(false);
        }
    }

    public static function deactivate(): void
    {
        if (function_exists('flush_rewrite_rules')) {
            flush_rewrite_rules(false);
        }
    }
}
