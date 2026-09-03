<?php
/**
 * Result of a COI calculation use case.
 *
 * @package GameDog\PedigreeEngine\Application\DTO
 */

declare(strict_types=1);

namespace GameDog\PedigreeEngine\Application\DTO;

final class CoiResultDto
{
    /** @var int */
    public $dogId;

    /** @var string Formatted percentage e.g. "12.50%". */
    public $formatted;

    /** @var float Percentage points. */
    public $percent;

    /** @var float Ratio 0..1. */
    public $ratio;

    /** @var array<int, int> */
    public $commonAncestorIds;

    /** @var bool Whether the value was freshly calculated (vs read from cache/meta). */
    public $fresh;

    /**
     * @param int             $dogId
     * @param string          $formatted
     * @param float           $percent
     * @param float           $ratio
     * @param array<int, int> $commonAncestorIds
     * @param bool            $fresh
     */
    public function __construct(
        int $dogId,
        string $formatted = '0.00%',
        float $percent = 0.0,
        float $ratio = 0.0,
        array $commonAncestorIds = [],
        bool $fresh = true
    ) {
        $this->dogId              = $dogId;
        $this->formatted          = $formatted;
        $this->percent            = $percent;
        $this->ratio              = $ratio;
        $this->commonAncestorIds  = $commonAncestorIds;
        $this->fresh              = $fresh;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'dog_id'               => $this->dogId,
            'coi'                  => $this->formatted,
            'coi_percent'          => $this->percent,
            'coi_ratio'            => $this->ratio,
            'common_ancestor_ids'  => array_values($this->commonAncestorIds),
            'fresh'                => $this->fresh,
        ];
    }
}
