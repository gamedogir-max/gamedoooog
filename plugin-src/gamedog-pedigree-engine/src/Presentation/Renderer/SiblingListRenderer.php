<?php

declare(strict_types=1);

namespace GDPE\Presentation\Renderer;

use GDPE\Domain\Entity\Dog;
use GDPE\Infrastructure\Config\JetEngineFieldMap;

final class SiblingListRenderer
{
    private const PLACEHOLDER_IMAGE = 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40"><rect width="40" height="40" fill="%23eee"/></svg>';

    public function __construct(
        private readonly string $templateDirectory,
    ) {
    }

    /**
     * @param array<int, Dog> $dogs
     */
    public function render(
        array $dogs,
        string $heading,
        string $emptyMessage,
        int $excludeDogId = 0,
    ): string {
        $templatePath = $this->templateDirectory . '/sibling-list.php';

        if (!is_readable($templatePath)) {
            return '';
        }

        $siblings = $this->normalizeDogs($dogs, $excludeDogId);
        $heading = trim($heading);

        ob_start();
        include $templatePath;

        return (string) ob_get_clean();
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
        $siblings = [];
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

            $siblings[] = [
                'id' => $dogId,
                'title' => $dog->name(),
                'link' => $dog->permalink(),
                'thumb' => get_the_post_thumbnail_url($dogId, 'thumbnail') ?: self::PLACEHOLDER_IMAGE,
                'sex_symbol' => $sex['symbol'],
                'sex_class' => $sex['class'],
            ];

            $seen[$dogId] = true;
        }

        return $siblings;
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
}
