<?php
/**
 * Data transfer object for a built pedigree tree (presentation-friendly).
 *
 * @package GameDog\PedigreeEngine\Application\DTO
 */

declare(strict_types=1);

namespace GameDog\PedigreeEngine\Application\DTO;

final class PedigreeTreeDto
{
    /** @var int */
    public $subjectId;

    /** @var string */
    public $subjectName;

    /** @var string COI formatted percentage, e.g. "6.25%". */
    public $coi;

    /** @var float COI as percentage points (e.g. 6.25). */
    public $coiPercent;

    /** @var float COI ratio (0..1). */
    public $coiRatio;

    /** @var int */
    public $depth;

    /** @var array<int, int> */
    public $commonAncestorIds;

    /** @var array<string, mixed>|null Nested node structure. */
    public $root;

    /**
     * @param int                      $subjectId
     * @param string                   $subjectName
     * @param string                   $coi
     * @param float                    $coiPercent
     * @param float                    $coiRatio
     * @param int                      $depth
     * @param array<int, int>          $commonAncestorIds
     * @param array<string, mixed>|null $root
     */
    public function __construct(
        int $subjectId,
        string $subjectName = '',
        string $coi = '0.00%',
        float $coiPercent = 0.0,
        float $coiRatio = 0.0,
        int $depth = 5,
        array $commonAncestorIds = [],
        ?array $root = null
    ) {
        $this->subjectId          = $subjectId;
        $this->subjectName        = $subjectName;
        $this->coi                = $coi;
        $this->coiPercent         = $coiPercent;
        $this->coiRatio           = $coiRatio;
        $this->depth              = $depth;
        $this->commonAncestorIds  = $commonAncestorIds;
        $this->root               = $root;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'subject_id'           => $this->subjectId,
            'subject_name'         => $this->subjectName,
            'coi'                  => $this->coi,
            'coi_percent'          => $this->coiPercent,
            'coi_ratio'            => $this->coiRatio,
            'depth'                => $this->depth,
            'common_ancestor_ids'  => array_values($this->commonAncestorIds),
            'root'                 => $this->root,
        ];
    }
}
