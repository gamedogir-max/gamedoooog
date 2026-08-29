<?php
/**
 * Unified siblings box template.
 *
 * @var string $instanceId
 * @var array<int, array{
 *     key: string,
 *     label: string,
 *     count: int,
 *     dogs: array<int, array{
 *         id: int,
 *         title: string,
 *         link: string,
 *         thumb: string,
 *         sex_symbol: string,
 *         sex_class: string
 *     }>,
 *     empty_message: string,
 *     is_active: bool
 * }> $tabs
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="gd-siblings-wrapper" dir="ltr" data-gdpe-siblings-box="<?php echo esc_attr($instanceId); ?>">
    <div class="gd-sib-tabs-nav" role="tablist" aria-label="<?php echo esc_attr__('Sibling relationships', 'gdpe'); ?>">
        <?php foreach ($tabs as $tab) :
            $tabId = $instanceId . '-tab-' . $tab['key'];
            $panelId = $instanceId . '-panel-' . $tab['key'];
            $buttonClass = $tab['is_active'] ? 'gd-snav-btn gd-snav-active' : 'gd-snav-btn';
            $countLabel = sprintf('%s (%d)', $tab['label'], $tab['count']);
            ?>
            <button
                type="button"
                id="<?php echo esc_attr($tabId); ?>"
                class="<?php echo esc_attr($buttonClass); ?>"
                data-target="<?php echo esc_attr($panelId); ?>"
                role="tab"
                aria-controls="<?php echo esc_attr($panelId); ?>"
                aria-selected="<?php echo $tab['is_active'] ? 'true' : 'false'; ?>"
            >
                <?php echo esc_html($countLabel); ?>
            </button>
        <?php endforeach; ?>
    </div>

    <div class="gd-sib-tabs-content">
        <?php foreach ($tabs as $tab) :
            $tabId = $instanceId . '-tab-' . $tab['key'];
            $panelId = $instanceId . '-panel-' . $tab['key'];
            $panelClass = $tab['is_active'] ? 'gd-stab-pane gd-stab-active' : 'gd-stab-pane';
            ?>
            <div
                id="<?php echo esc_attr($panelId); ?>"
                class="<?php echo esc_attr($panelClass); ?>"
                role="tabpanel"
                aria-labelledby="<?php echo esc_attr($tabId); ?>"
                <?php if (!$tab['is_active']) : ?>hidden<?php endif; ?>
            >
                <?php if ($tab['dogs'] === []) : ?>
                    <div class="gd-sib-empty"><?php echo esc_html($tab['empty_message']); ?></div>
                <?php else : ?>
                    <div class="gd-sib-grid">
                        <?php foreach ($tab['dogs'] as $dog) : ?>
                            <a href="<?php echo esc_url($dog['link']); ?>" class="gd-sib-item">
                                <img src="<?php echo esc_url($dog['thumb']); ?>" alt="<?php echo esc_attr($dog['title']); ?>">
                                <div class="gd-sib-meta">
                                    <span class="gd-sib-title">
                                        <?php echo esc_html($dog['title']); ?>
                                        <?php if ($dog['sex_symbol'] !== '') : ?>
                                            <span class="gd-sib-sex <?php echo esc_attr($dog['sex_class']); ?>"><?php echo esc_html($dog['sex_symbol']); ?></span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>
