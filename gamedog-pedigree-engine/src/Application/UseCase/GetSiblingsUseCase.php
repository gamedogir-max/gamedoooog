<?php
/**
 * Application use case: resolve full, paternal and maternal siblings.
 *
 * @package GameDog\PedigreeEngine\Application\UseCase
 */

declare(strict_types=1);

namespace GameDog\PedigreeEngine\Application\UseCase;

use GameDog\PedigreeEngine\Domain\Contract\RelationTraversalInterface;
use GameDog\PedigreeEngine\Domain\Entity\Dog;
use GameDog\PedigreeEngine\Domain\Repository\DogRepositoryInterface;
use GameDog\PedigreeEngine\Domain\ValueObject\DogId;

final class GetSiblingsUseCase
{
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

    /**
     * @param int|DogId $dogId
     *
     * @return array<string, mixed>
     */
    public function execute($dogId): array
    {
        $empty = [
            'subject'  => null,
            'full'     => [],
            'sire'     => [],
            'dam'      => [],
        ];

        $id = DogId::fromMixed($dogId);
        if ($id === null) {
            return $empty;
        }

        $dog = $this->dogs->findById($id);
        if ($dog === null) {
            return $empty;
        }

        $parents = $this->relations->getParentIds($id);
        $sireId  = $parents['sire'] ?? $dog->sireId();
        $damId   = $parents['dam'] ?? $dog->damId();

        $candidateIds = [];
        if ($sireId !== null) {
            foreach ($this->relations->getOffspringIds($sireId) as $offspring) {
                $candidateIds[$offspring->toInt()] = true;
            }
        }
        if ($damId !== null) {
            foreach ($this->relations->getOffspringIds($damId) as $offspring) {
                $candidateIds[$offspring->toInt()] = true;
            }
        }

        unset($candidateIds[$id->toInt()]);

        $full = [];
        $pat  = [];
        $mat  = [];

        foreach (array_keys($candidateIds) as $candidateInt) {
            $candidateId = DogId::fromMixed($candidateInt);
            if ($candidateId === null) {
                continue;
            }

            $sibling = $this->dogs->findById($candidateId);
            if ($sibling === null) {
                continue;
            }

            $siblingParents = $this->relations->getParentIds($candidateId);
            $siblingSire    = $siblingParents['sire'] ?? $sibling->sireId();
            $siblingDam     = $siblingParents['dam'] ?? $sibling->damId();

            $sameSire = $sireId !== null && $siblingSire !== null && $sireId->equals($siblingSire);
            $sameDam  = $damId !== null && $siblingDam !== null && $damId->equals($siblingDam);

            if ($sameSire && $sameDam) {
                $full[$candidateInt] = $sibling;
                continue;
            }

            if ($sameSire) {
                $pat[$candidateInt] = $sibling;
                continue;
            }

            if ($sameDam) {
                $mat[$candidateInt] = $sibling;
            }
        }

        $full = $this->sortDogs($full);
        $pat  = $this->sortDogs($pat);
        $mat  = $this->sortDogs($mat);

        return [
            'subject' => $dog,
            'full'    => $full,
            'sire'    => $pat,
            'dam'     => $mat,
        ];
    }

    /**
     * @param array<int, Dog> $dogs
     *
     * @return array<int, Dog>
     */
    private function sortDogs(array $dogs): array
    {
        uasort($dogs, static function (Dog $a, Dog $b): int {
            return strcasecmp($a->name(), $b->name());
        });

        return $dogs;
    }
}
