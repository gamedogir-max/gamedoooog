<?php

declare(strict_types=1);

namespace GDPE\Presentation\Renderer;

use GDPE\Domain\Entity\Dog;
use GDPE\Infrastructure\Config\JetEngineFieldMap;

final class SiblingsBoxRenderer
{
    private const PLACEHOLDER_IMAGE = 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40"><rect width="40" height="40" fill="%23eee"/></svg>';

    private static bool $scriptIncluded = false;

    public function __construct(
        private readonly string $templateDirectory,
    ) {
    }

    /**
     * @param array<int, Dog> $fullSiblings
     * @param array<int, Dog> $sameSire
     * @param array<int, Dog> $sameDam
     */
    public function render(
        int $currentDogId,
        array $fullSiblings,
        array $sameSire,
        array $sameDam,
    ): string {
        $templatePath = $this->templateDirectory . '/siblings-box.php';

        if (!is_readable($templatePath)) {
            return '';
        }

        $fullSiblingCards = $this->normalizeDogs($fullSiblings, $currentDogId);
        $sameSireCards = $this->normalizeDogs($sameSire, $currentDogId);
        $sameDamCards = $this->normalizeDogs($sameDam, $currentDogId);

        $instanceId = function_exists('wp_unique_id')
            ? wp_unique_id('gdpe-siblings-')
            : uniqid('gdpe-siblings-', false);

        $tabs = [
            [
                'key' => 'full-siblings',
                'label' => esc_html__('Full Siblings', 'gdpe'),
                'count' => count($fullSiblingCards),
                'dogs' => $fullSiblingCards,
                'empty_message' => esc_html__('No full siblings found.', 'gdpe'),
                'is_active' => true,
            ],
            [
                'key' => 'same-sire',
                'label' => esc_html__('Same Sire', 'gdpe'),
                'count' => count($sameSireCards),
                'dogs' => $sameSireCards,
                'empty_message' => esc_html__('No dogs with the same sire found.', 'gdpe'),
                'is_active' => false,
            ],
            [
                'key' => 'same-dam',
                'label' => esc_html__('Same Dam', 'gdpe'),
                'count' => count($sameDamCards),
                'dogs' => $sameDamCards,
                'empty_message' => esc_html__('No dogs with the same dam found.', 'gdpe'),
                'is_active' => false,
            ],
        ];

        ob_start();
        include $templatePath;
        $markup = (string) ob_get_clean();

        if (!self::$scriptIncluded) {
            $markup .= $this->renderScript();
            self::$scriptIncluded = true;
        }

        return $markup;
    }

    public function renderNotice(string $message): string
    {
        return sprintf(
            '<div class="gd-siblings-wrapper" dir="ltr"><div class="gd-sib-empty">%s</div></div>',
            esc_html($message),
        );
    }

    /**
     * @param array<int, Dog> $dogs
     *
     * @return list<array{
     *     id: int,
     *     title: string,
     *     link: string,
     *     thumb: string,
     *     sex_symbol: string,
     *     sex_class: string
     * }>
     */
    private function normalizeDogs(array $dogs, int $excludeDogId): array
    {
        $cards = [];
        $seen = [];

        foreach ($dogs as $dog) {
            if (!$dog instanceof Dog) {
                continue;
            }

            $dogId = $dog->id()->toInt();

            if ($dogId <= 0 || $dogId === $excludeDogId || isset($seen[$dogId])) {
                continue;
            }

            if (get_post_status($dogId) !== 'publish') {
                continue;
            }

            $sexMeta = (string) get_post_meta($dogId, JetEngineFieldMap::META_SEX, true);
            $sex = $this->normalizeSex($sexMeta);

            $cards[] = [
                'id' => $dogId,
                'title' => $dog->name(),
                'link' => $dog->permalink(),
                'thumb' => get_the_post_thumbnail_url($dogId, 'thumbnail') ?: self::PLACEHOLDER_IMAGE,
                'sex_symbol' => $sex['symbol'],
                'sex_class' => $sex['class'],
            ];

            $seen[$dogId] = true;
        }

        return $cards;
    }

    /**
     * @return array{symbol: string, class: string}
     */
    private function normalizeSex(string $sexMeta): array
    {
        $sex = strtolower(trim($sexMeta));

        return match ($sex) {
            'male', 'm' => ['symbol' => '♂', 'class' => 'is-male'],
            'female', 'f' => ['symbol' => '♀', 'class' => 'is-female'],
            default => ['symbol' => '', 'class' => ''],
        };
    }

    private function renderScript(): string
    {
        return <<<'HTML'
<script>
(function () {
    if (window.gdpeSiblingsBoxInitialized) {
        return;
    }

    window.gdpeSiblingsBoxInitialized = true;

    document.addEventListener('click', function (event) {
        var button = event.target.closest('.gd-siblings-wrapper .gd-snav-btn');

        if (!button) {
            return;
        }

        var wrapper = button.closest('.gd-siblings-wrapper');
        var targetId = button.getAttribute('data-target');

        if (!wrapper || !targetId) {
            return;
        }

        wrapper.querySelectorAll('.gd-snav-btn').forEach(function (item) {
            item.classList.remove('gd-snav-active');
            item.setAttribute('aria-selected', 'false');
        });

        wrapper.querySelectorAll('.gd-stab-pane').forEach(function (pane) {
            pane.classList.remove('gd-stab-active');
            pane.setAttribute('hidden', 'hidden');
        });

        button.classList.add('gd-snav-active');
        button.setAttribute('aria-selected', 'true');

        var targetPane = wrapper.querySelector('#' + targetId);

        if (targetPane) {
            targetPane.classList.add('gd-stab-active');
            targetPane.removeAttribute('hidden');
        }
    });
})();
</script>
HTML;
    }
}
