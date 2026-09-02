<?php
/**
 * Single pedigree node template (recursive via PedigreeTreeRenderer).
 *
 * Available vars:
 *   $node     array  Node data
 *   $vm       array  Full tree ViewModel
 *   $renderer \GameDog\PedigreeEngine\Presentation\Renderer\PedigreeTreeRenderer
 *
 * @package GameDog\PedigreeEngine
 */

if (!defined('ABSPATH')) {
    exit;
}

$node = isset($node) && is_array($node) ? $node : [];
$vm   = isset($vm) && is_array($vm) ? $vm : [];

$isEmpty   = !empty($node['is_empty']);
$isCommon  = !empty($node['is_common_ancestor']);
$cssClass  = isset($node['css_class']) ? (string) $node['css_class'] : 'gd-pedigree-node';
$name      = isset($node['name']) ? (string) $node['name'] : '';
$permalink = isset($node['permalink']) ? (string) $node['permalink'] : '';
$thumb     = isset($node['thumbnail']) ? (string) $node['thumbnail'] : '';
$coi       = isset($node['coi']) ? (string) $node['coi'] : '';
$gen       = isset($node['generation']) ? (int) $node['generation'] : 0;
$id        = isset($node['id']) ? (int) $node['id'] : 0;

// Ensure common-ancestor class is present even if ViewModel missed it.
if ($isCommon && strpos($cssClass, 'gd-node-inbred') === false) {
    $cssClass .= ' gd-node-inbred gd-node-common-ancestor';
}

$displayName = $name !== '' ? $name : ($id > 0 ? ('#' . $id) : 'Unknown');
?>
<div class="<?php echo esc_attr(trim($cssClass)); ?>"
     data-dog-id="<?php echo esc_attr((string) $id); ?>"
     data-generation="<?php echo esc_attr((string) $gen); ?>"
     <?php echo $isCommon ? ' data-common-ancestor="1"' : ''; ?>>

    <div class="gd-node-card">
        <?php if (!$isEmpty) : ?>
            <?php if ($thumb !== '') : ?>
                <div class="gd-node-thumb">
                    <img src="<?php echo esc_url($thumb); ?>" alt="<?php echo esc_attr($displayName); ?>" loading="lazy" width="36" height="36" />
                </div>
            <?php endif; ?>

            <div class="gd-node-info">
                <?php if ($permalink !== '' && $permalink !== '#') : ?>
                    <a class="gd-node-name" href="<?php echo esc_url($permalink); ?>"><?php echo esc_html($displayName); ?></a>
                <?php else : ?>
                    <span class="gd-node-name"><?php echo esc_html($displayName); ?></span>
                <?php endif; ?>

                <?php if ($isCommon) : ?>
                    <span class="gd-node-common-label" title="Common ancestor">CA</span>
                <?php endif; ?>

                <?php if ($coi !== '' && $gen === 0) : ?>
                    <span class="gd-node-coi"><?php echo esc_html($coi); ?></span>
                <?php endif; ?>
            </div>
        <?php else : ?>
            <div class="gd-node-info gd-node-unknown">
                <span class="gd-node-name">Unknown</span>
            </div>
        <?php endif; ?>
    </div>

    <?php
    $hasSire = isset($node['sire']) && is_array($node['sire']);
    $hasDam  = isset($node['dam']) && is_array($node['dam']);
    ?>

    <?php if ($hasSire || $hasDam) : ?>
        <div class="gd-node-parents">
            <?php if ($hasSire) : ?>
                <div class="gd-node-parent gd-node-sire">
                    <?php
                    if (isset($renderer) && is_object($renderer) && method_exists($renderer, 'renderNode')) {
                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        echo $renderer->renderNode($node['sire'], $vm);
                    }
                    ?>
                </div>
            <?php endif; ?>

            <?php if ($hasDam) : ?>
                <div class="gd-node-parent gd-node-dam">
                    <?php
                    if (isset($renderer) && is_object($renderer) && method_exists($renderer, 'renderNode')) {
                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        echo $renderer->renderNode($node['dam'], $vm);
                    }
                    ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
