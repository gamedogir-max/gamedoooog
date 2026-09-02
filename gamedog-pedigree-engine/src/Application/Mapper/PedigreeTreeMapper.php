<?php
/**
 * Maps domain PedigreeTree / PedigreeNode entities to arrays and DTOs.
 *
 * @package GameDog\PedigreeEngine\Application\Mapper
 */

declare(strict_types=1);

namespace GameDog\PedigreeEngine\Application\Mapper;

use GameDog\PedigreeEngine\Application\DTO\PedigreeTreeDto;
use GameDog\PedigreeEngine\Domain\Entity\PedigreeNode;
use GameDog\PedigreeEngine\Domain\Entity\PedigreeTree;
use GameDog\PedigreeEngine\Domain\ValueObject\CoiPercentage;

final class PedigreeTreeMapper
{
    public function toDto(PedigreeTree $tree): PedigreeTreeDto
    {
        $root = $tree->root();
        $coi  = $tree->coi() instanceof CoiPercentage ? $tree->coi() : CoiPercentage::zero();

        return new PedigreeTreeDto(
            $tree->subjectId()->toInt(),
            $root->name(),
            $coi->formatted(),
            $coi->percent(),
            $coi->ratio(),
            $tree->depth()->toInt(),
            $tree->commonAncestorIds(),
            $this->nodeToArray($root, $tree->commonAncestorIds())
        );
    }

    /**
     * @param array<int, int> $commonIds
     *
     * @return array<string, mixed>|null
     */
    public function nodeToArray(?PedigreeNode $node, array $commonIds = []): ?array
    {
        if ($node === null) {
            return null;
        }

        $dogId  = $node->dogId();
        $idInt  = $dogId !== null ? $dogId->toInt() : 0;
        $isCommon = $node->isCommonAncestor() || ($idInt > 0 && isset($commonIds[$idInt]));

        $coiFormatted = '';
        if ($node->coi() instanceof CoiPercentage) {
            $coiFormatted = $node->coi()->formatted();
        }

        return [
            'id'                 => $idInt,
            'name'               => $node->name(),
            'generation'         => $node->generation(),
            'side'               => $node->side(),
            'gender'             => $node->gender(),
            'thumbnail'          => $node->thumbnailUrl(),
            'permalink'          => $node->permalink(),
            'coi'                => $coiFormatted,
            'is_common_ancestor' => $isCommon,
            'is_empty'           => $node->isEmpty(),
            'sire'               => $this->nodeToArray($node->sireNode(), $commonIds),
            'dam'                => $this->nodeToArray($node->damNode(), $commonIds),
        ];
    }
}
