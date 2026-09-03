<?php
/**
 * Pedigree tree template.
 *
 * Available vars:
 *   $vm       array  ViewModel (subject, coi, root, depth, ...)
 *   $tree     array  Alias of $vm
 *   $renderer \GameDog\PedigreeEngine\Presentation\Renderer\PedigreeTreeRenderer
 *
 * @package GameDog\PedigreeEngine
 */

if (!defined('ABSPATH')) {
    exit;
}

$vm       = isset($vm) && is_array($vm) ? $vm : (isset($tree) && is_array($tree) ? $tree : []);
$coiValue = isset($vm['coi_value']) ? (string) $vm['coi_value'] : (isset($vm['coi']) ? (string) $vm['coi'] : '0.00%');
$depth    = isset($vm['depth']) ? (int) $vm['depth'] : 5;
$subject  = isset($vm['subject_name']) ? (string) $vm['subject_name'] : '';
$root     = isset($vm['root']) && is_array($vm['root']) ? $vm['root'] : null;
?>
<div class="gd-pedigree-tree" data-depth="<?php echo esc_attr((string) $depth); ?>" data-coi="<?php echo esc_attr($coiValue); ?>">
    <div class="gd-pedigree-header">
        <?php if ($subject !== '') : ?>
            <h3 class="gd-pedigree-title"><?php echo esc_html($subject); ?></h3>
        <?php endif; ?>

        <div class="gd-pedigree-coi-badge">
            COI: <strong><?php echo esc_html($coiValue); ?></strong>
            (<?php echo esc_html((string) $depth); ?> Generations)
        </div>
    </div>

    <div class="gd-pedigree-body">
        <?php if ($root !== null && isset($renderer) && is_object($renderer)) : ?>
            <?php
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- node template escapes its own output.
            echo $renderer->renderNode($root, $vm);
            ?>
        <?php elseif ($root !== null) : ?>
            <?php
            // Fallback inline include when renderer is unavailable.
            $node = $root;
            $template = __DIR__ . '/pedigree-node.php';
            if (is_file($template)) {
                include $template;
            }
            ?>
        <?php else : ?>
            <p class="gd-pedigree-empty">Pedigree data is not available.</p>
        <?php endif; ?>
    </div>
</div>
