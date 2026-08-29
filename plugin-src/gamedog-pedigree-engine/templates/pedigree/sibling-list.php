<?php
/**
 * Sibling list template.
 *
 * @var string $heading
 * @var string $emptyMessage
 * @var array<int, array{
 *     id: int,
 *     title: string,
 *     link: string,
 *     thumb: string,
 *     sex_symbol: string,
 *     sex_class: string
 * }> $siblings
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="gd-siblings-wrapper" dir="ltr">
    <?php if ($heading !== '') : ?>
        <div class="gd-sib-heading"><?php echo esc_html($heading); ?></div>
    <?php endif; ?>

    <?php if ($siblings === []) : ?>
        <div class="gd-sib-empty"><?php echo esc_html($emptyMessage); ?></div>
    <?php else : ?>
        <div class="gd-sib-grid">
            <?php foreach ($siblings as $dog) : ?>
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
