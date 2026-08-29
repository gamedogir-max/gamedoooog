<?php

/**
 * Single pedigree ancestor card.
 *
 * @var \GDPE\Domain\ValueObject\PedigreeNode $node The ancestor to render.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/** @var \GDPE\Domain\ValueObject\PedigreeNode $node */

$sideClass = 'gdpe-pedigree__node--' . $node->lineageSide()->value;
$stateClass = $node->isUnknown()
    ? 'gdpe-pedigree__node--unknown'
    : 'gdpe-pedigree__node--known';
?>
<div class="gdpe-pedigree__node <?php echo esc_attr($sideClass . ' ' . $stateClass); ?>">
    <?php if ($node->isKnown() && $node->permalink() !== null) : ?>
        <a class="gdpe-pedigree__node-link" href="<?php echo esc_url($node->permalink()); ?>">
            <?php echo esc_html($node->name()); ?>
        </a>
    <?php else : ?>
        <span class="gdpe-pedigree__node-name">
            <?php echo esc_html($node->name()); ?>
        </span>
    <?php endif; ?>
</div>
