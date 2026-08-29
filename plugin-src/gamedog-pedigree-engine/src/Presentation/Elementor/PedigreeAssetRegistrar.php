<?php

declare(strict_types=1);

namespace GDPE\Presentation\Elementor;

/**
 * Registers the Pedigree Tree stylesheet with WordPress.
 *
 * The style is only *registered* here, not force-enqueued globally: the
 * widget declares this handle via get_style_depends(), so Elementor loads
 * the CSS only on pages where the widget actually appears.
 */
final class PedigreeAssetRegistrar
{
    public const STYLE_HANDLE = 'gdpe-pedigree-tree';

    public function __construct(
        private readonly string $cssUrl,
        private readonly string $version,
    ) {
    }

    public function register(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'registerStyle']);
        add_action('elementor/editor/after_enqueue_styles', [$this, 'registerStyle']);
    }

    public function registerStyle(): void
    {
        if (wp_style_is(self::STYLE_HANDLE, 'registered')) {
            return;
        }

        wp_register_style(
            self::STYLE_HANDLE,
            $this->cssUrl,
            [],
            $this->version,
        );
    }
}
