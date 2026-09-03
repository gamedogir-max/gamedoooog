<?php
/**
 * Shortcode: [siblings_box id="123"]
 *
 * Lists full siblings / half-siblings of a dog without breaking existing usage.
 *
 * @package GameDog\PedigreeEngine\Presentation\Shortcode
 */

declare(strict_types=1);

namespace GameDog\PedigreeEngine\Presentation\Shortcode;

use GameDog\PedigreeEngine\Domain\Contract\RelationTraversalInterface;
use GameDog\PedigreeEngine\Domain\Repository\DogRepositoryInterface;
use GameDog\PedigreeEngine\Domain\ValueObject\DogId;

final class SiblingsBoxShortcode
{
    public const TAG = 'siblings_box';

    /** @var DogRepositoryInterface */
    private $dogs;

    /** @var RelationTraversalInterface */
    private $relations;

    public function __construct(
        DogRepositoryInterface $dogs,
        RelationTraversalInterface $relations
    ) {
        $this->dogs      = $dogs;
        $this->relations = $relations;
    }

    public function register(): void
    {
        add_shortcode(self::TAG, [$this, 'render']);
    }

    /**
     * @param array<string, mixed>|string $atts
     */
    public function render($atts = []): string
    {
        $atts = shortcode_atts(
            [
                'id'    => 0,
                'limit' => 20,
            ],
            is_array($atts) ? $atts : [],
            self::TAG
        );

        $dogId = (int) $atts['id'];
        if ($dogId <= 0 && function_exists('get_the_ID')) {
            $dogId = (int) get_the_ID();
        }

        $id = DogId::fromMixed($dogId);
        if ($id === null) {
            return '<!-- siblings_box: missing dog id -->';
        }

        $limit = max(1, (int) $atts['limit']);

        try {
            $dog = $this->dogs->findById($id);
            if ($dog === null) {
                return '';
            }

            $parents = $this->relations->getParentIds($id);
            $sireId  = $parents['sire'] ?? $dog->sireId();
            $damId   = $parents['dam'] ?? $dog->damId();

            $candidates = [];

            if ($sireId !== null) {
                foreach ($this->relations->getOffspringIds($sireId) as $offspring) {
                    $candidates[$offspring->toInt()] = $offspring;
                }
            }
            if ($damId !== null) {
                foreach ($this->relations->getOffspringIds($damId) as $offspring) {
                    $candidates[$offspring->toInt()] = $offspring;
                }
            }

            unset($candidates[$id->toInt()]);

            if ($candidates === []) {
                return '<div class="gd-siblings-box gd-siblings-empty"><p>No siblings found.</p></div>';
            }

            $html   = '<div class="gd-siblings-box"><ul class="gd-siblings-list">';
            $count  = 0;

            foreach ($candidates as $siblingId) {
                if ($count >= $limit) {
                    break;
                }

                $sibling = $this->dogs->findById($siblingId);
                if ($sibling === null) {
                    continue;
                }

                $name = esc_html($sibling->name());
                $url  = $sibling->permalink() !== '' ? esc_url($sibling->permalink()) : '#';
                $coi  = $sibling->coi() ? esc_html($sibling->coi()->formatted()) : '';

                $html .= '<li class="gd-sibling-item">';
                $html .= '<a href="' . $url . '">' . $name . '</a>';
                if ($coi !== '') {
                    $html .= ' <span class="gd-sibling-coi">COI: ' . $coi . '</span>';
                }
                $html .= '</li>';

                $count++;
            }

            $html .= '</ul></div>';

            return $html;
        } catch (\Throwable $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                return '<!-- siblings_box error: ' . esc_html($e->getMessage()) . ' -->';
            }

            return '<!-- siblings_box error -->';
        }
    }
}
