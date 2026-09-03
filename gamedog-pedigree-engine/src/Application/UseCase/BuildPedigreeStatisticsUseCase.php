<?php
/**
 * Application use case: build the 4-generation blood contribution statistic.
 *
 * @package GameDog\PedigreeEngine\Application\UseCase
 */

declare(strict_types=1);

namespace GameDog\PedigreeEngine\Application\UseCase;

use GameDog\PedigreeEngine\Domain\Repository\DogRepositoryInterface;
use GameDog\PedigreeEngine\Domain\Service\BloodContributionCalculatorService;
use GameDog\PedigreeEngine\Domain\Service\PedigreeTreeBuilderService;
use GameDog\PedigreeEngine\Domain\ValueObject\DogId;
use GameDog\PedigreeEngine\Domain\ValueObject\GenerationDepth;

final class BuildPedigreeStatisticsUseCase
{
    /** @var PedigreeTreeBuilderService */
    private $treeBuilder;

    /** @var BloodContributionCalculatorService */
    private $bloodCalculator;

    /** @var DogRepositoryInterface */
    private $dogs;

    public function __construct(
        PedigreeTreeBuilderService $treeBuilder,
        BloodContributionCalculatorService $bloodCalculator,
        DogRepositoryInterface $dogs
    ) {
        $this->treeBuilder     = $treeBuilder;
        $this->bloodCalculator = $bloodCalculator;
        $this->dogs            = $dogs;
    }

    /**
     * @param int|DogId $dogId
     *
     * @return array<string, mixed>
     */
    public function execute($dogId): array
    {
        $empty = [
            'subject_id'   => 0,
            'subject_name' => '',
            'rows'         => [],
        ];

        $id = DogId::fromMixed($dogId);
        if ($id === null) {
            return $empty;
        }

        $tree = $this->treeBuilder->build($id, new GenerationDepth(BloodContributionCalculatorService::MAX_DEPTH));

        $root = $tree->root();

        $empty['subject_id']   = $id->toInt();
        $empty['subject_name'] = $root->name();

        if ($root->isEmpty()) {
            return $empty;
        }

        $contributions = $this->bloodCalculator->calculate($tree);

        $rows = [];

        foreach ($contributions as $row) {
            $dog = $this->dogs->findById(DogId::fromMixed($row['id']));
            if ($dog === null) {
                continue;
            }

            $rows[] = [
                'id'        => $row['id'],
                'name'      => $dog->registeredName(),
                'permalink' => $dog->permalink(),
                'count'     => $row['count'],
                'percent'   => $row['percent'],
            ];
        }

        return [
            'subject_id'   => $id->toInt(),
            'subject_name' => $root->name(),
            'rows'         => $rows,
        ];
    }
}
