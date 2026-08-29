<?php

declare(strict_types=1);

namespace GDPE\Presentation\Support;

use Elementor\Plugin;
use Throwable;

/**
 * Resolves the dog post currently being rendered.
 *
 * Resolution order intentionally mirrors real WordPress rendering flows:
 *  1. The current loop item (covers singular templates, listings, and loops)
 *  2. The main queried object (covers singular pages outside the loop)
 *  3. Elementor editor preview document context
 */
final class CurrentDogContextResolver
{
    public function resolve(): int
    {
        $loopPostId = get_the_ID();

        if (is_int($loopPostId) && $loopPostId > 0) {
            return $loopPostId;
        }

        $queriedObjectId = get_queried_object_id();

        if ($queriedObjectId > 0) {
            return $queriedObjectId;
        }

        return $this->resolveElementorPreviewPostId();
    }

    private function resolveElementorPreviewPostId(): int
    {
        if (!did_action('elementor/loaded') || !class_exists(Plugin::class)) {
            return 0;
        }

        try {
            $plugin = Plugin::$instance;

            if (!isset($plugin->documents)) {
                return 0;
            }

            $document = $plugin->documents->get_current();

            if ($document === null) {
                return 0;
            }

            $mainId = $document->get_main_id();

            return is_numeric($mainId) && (int) $mainId > 0
                ? (int) $mainId
                : 0;
        } catch (Throwable) {
            return 0;
        }
    }
}
