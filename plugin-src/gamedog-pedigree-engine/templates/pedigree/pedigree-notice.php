<?php

/**
 * Standalone notice (empty state / dog not found / invalid config).
 *
 * @var string $message Human-readable, already-translated message.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/** @var string $message */
?>
<div class="gdpe-pedigree gdpe-pedigree--notice">
    <p class="gdpe-pedigree__notice"><?php echo esc_html($message); ?></p>
</div>
