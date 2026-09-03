<?php
/**
 * Simple lazy service container wiring all layers together.
 *
 * @package GameDog\PedigreeEngine\Infrastructure\WordPress
 */

declare(strict_types=1);

namespace GameDog\PedigreeEngine\Infrastructure\WordPress;

use GameDog\PedigreeEngine\Application\Mapper\PedigreeTreeMapper;
use GameDog\PedigreeEngine\Application\Port\ContainerInterface;
use GameDog\PedigreeEngine\Application\UseCase\BuildDiversityMetricsUseCase;
use GameDog\PedigreeEngine\Application\UseCase\BuildPedigreeMatrixUseCase;
use GameDog\PedigreeEngine\Application\UseCase\BuildPedigreeStatisticsUseCase;
use GameDog\PedigreeEngine\Application\UseCase\BuildPedigreeTreeUseCase;
use GameDog\PedigreeEngine\Application\UseCase\CalculateDogCoiUseCase;
use GameDog\PedigreeEngine\Application\UseCase\GetSiblingsUseCase;
use GameDog\PedigreeEngine\Domain\Contract\CacheInterface;
use GameDog\PedigreeEngine\Domain\Contract\RelationTraversalInterface;
use GameDog\PedigreeEngine\Domain\Repository\DogRepositoryInterface;
use GameDog\PedigreeEngine\Domain\Service\AncestorPathCollector;
use GameDog\PedigreeEngine\Domain\Service\BloodContributionCalculatorService;
use GameDog\PedigreeEngine\Domain\Service\InbreedingCalculatorInterface;
use GameDog\PedigreeEngine\Domain\Service\PedigreeTreeBuilderService;
use GameDog\PedigreeEngine\Domain\Service\WrightInbreedingCalculatorService;
use GameDog\PedigreeEngine\Infrastructure\Cache\WordPressCacheAdapter;
use GameDog\PedigreeEngine\Infrastructure\JetEngine\JetEngineRelationTraversal;
use GameDog\PedigreeEngine\Infrastructure\Repository\JetEngineDogRepository;
use GameDog\PedigreeEngine\Presentation\Renderer\AnalyticsSuiteRenderer;
use GameDog\PedigreeEngine\Presentation\Renderer\DiversityCardRenderer;
use GameDog\PedigreeEngine\Presentation\Renderer\PedigreeMatrixRenderer;
use GameDog\PedigreeEngine\Presentation\Renderer\PedigreeStatisticsRenderer;
use GameDog\PedigreeEngine\Presentation\Renderer\PedigreeTreeRenderer;
use GameDog\PedigreeEngine\Presentation\Renderer\SiblingsTabsRenderer;
use GameDog\PedigreeEngine\Presentation\Shortcode\PedigreeAnalyticsSuiteShortcode;
use GameDog\PedigreeEngine\Presentation\Shortcode\PedigreeDiversityCardShortcode;
use GameDog\PedigreeEngine\Presentation\Shortcode\PedigreeMatrixShortcode;
use GameDog\PedigreeEngine\Presentation\Shortcode\PedigreeStatisticsShortcode;
use GameDog\PedigreeEngine\Presentation\Shortcode\PedigreeTreeShortcode;
use GameDog\PedigreeEngine\Presentation\Shortcode\SiblingsBoxShortcode;
use GameDog\PedigreeEngine\Presentation\Shortcode\SiblingsTabsShortcode;
use GameDog\PedigreeEngine\Presentation\ViewModel\PedigreeTreeViewModelBuilder;

final class ServiceContainer implements ContainerInterface
{
    /** @var array<string, mixed> */
    private $services = [];

    /** @var array<string, callable> */
    private $factories = [];

    public function __construct()
    {
        $this->registerFactories();
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $id)
    {
        if (array_key_exists($id, $this->services)) {
            return $this->services[$id];
        }

        if (!isset($this->factories[$id])) {
            throw new \RuntimeException('Service not found: ' . $id);
        }

        $this->services[$id] = ($this->factories[$id])($this);

        return $this->services[$id];
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $id): bool
    {
        return isset($this->factories[$id]) || array_key_exists($id, $this->services);
    }

    private function registerFactories(): void
    {
        $this->factories[CacheInterface::class] = static function (): CacheInterface {
            return new WordPressCacheAdapter('gd_pe_');
        };

        $this->factories[RelationTraversalInterface::class] = static function (): RelationTraversalInterface {
            return new JetEngineRelationTraversal(
                GD_PEDIGREE_SIRE_RELATION_ID,
                GD_PEDIGREE_DAM_RELATION_ID
            );
        };

        $this->factories[DogRepositoryInterface::class] = function (self $c): DogRepositoryInterface {
            return new JetEngineDogRepository(
                $c->get(RelationTraversalInterface::class),
                GD_PEDIGREE_POST_TYPE,
                GD_PEDIGREE_COI_META_KEY
            );
        };

        $this->factories[AncestorPathCollector::class] = static function (): AncestorPathCollector {
            return new AncestorPathCollector();
        };

        $this->factories[InbreedingCalculatorInterface::class] = function (self $c): InbreedingCalculatorInterface {
            return new WrightInbreedingCalculatorService(
                $c->get(AncestorPathCollector::class)
            );
        };

        $this->factories[BloodContributionCalculatorService::class] = static function (): BloodContributionCalculatorService {
            return new BloodContributionCalculatorService();
        };

        $this->factories[PedigreeTreeBuilderService::class] = function (self $c): PedigreeTreeBuilderService {
            return new PedigreeTreeBuilderService(
                $c->get(DogRepositoryInterface::class),
                $c->get(RelationTraversalInterface::class)
            );
        };

        $this->factories[PedigreeTreeMapper::class] = static function (): PedigreeTreeMapper {
            return new PedigreeTreeMapper();
        };

        $this->factories[CalculateDogCoiUseCase::class] = function (self $c): CalculateDogCoiUseCase {
            return new CalculateDogCoiUseCase(
                $c->get(DogRepositoryInterface::class),
                $c->get(RelationTraversalInterface::class),
                $c->get(InbreedingCalculatorInterface::class),
                $c->get(PedigreeTreeBuilderService::class),
                $c->get(CacheInterface::class)
            );
        };

        $this->factories[BuildPedigreeTreeUseCase::class] = function (self $c): BuildPedigreeTreeUseCase {
            return new BuildPedigreeTreeUseCase(
                $c->get(PedigreeTreeBuilderService::class),
                $c->get(InbreedingCalculatorInterface::class),
                $c->get(PedigreeTreeMapper::class),
                $c->get(DogRepositoryInterface::class),
                $c->get(CalculateDogCoiUseCase::class),
                $c->get(CacheInterface::class)
            );
        };

        $this->factories[PedigreeTreeViewModelBuilder::class] = static function (): PedigreeTreeViewModelBuilder {
            return new PedigreeTreeViewModelBuilder();
        };

        $this->factories[PedigreeTreeRenderer::class] = function (self $c): PedigreeTreeRenderer {
            return new PedigreeTreeRenderer(
                $c->get(PedigreeTreeViewModelBuilder::class)
            );
        };

        $this->factories[PedigreeTreeShortcode::class] = function (self $c): PedigreeTreeShortcode {
            return new PedigreeTreeShortcode(
                $c->get(BuildPedigreeTreeUseCase::class),
                $c->get(PedigreeTreeRenderer::class)
            );
        };

        $this->factories[SiblingsBoxShortcode::class] = function (self $c): SiblingsBoxShortcode {
            return new SiblingsBoxShortcode(
                $c->get(DogRepositoryInterface::class),
                $c->get(RelationTraversalInterface::class)
            );
        };

        // --- Pedigree & Genetic Analytics Engine components ---

        $this->factories[BuildPedigreeMatrixUseCase::class] = function (self $c): BuildPedigreeMatrixUseCase {
            return new BuildPedigreeMatrixUseCase(
                $c->get(PedigreeTreeBuilderService::class),
                $c->get(DogRepositoryInterface::class)
            );
        };

        $this->factories[GetSiblingsUseCase::class] = function (self $c): GetSiblingsUseCase {
            return new GetSiblingsUseCase(
                $c->get(DogRepositoryInterface::class),
                $c->get(RelationTraversalInterface::class)
            );
        };

        $this->factories[BuildPedigreeStatisticsUseCase::class] = function (self $c): BuildPedigreeStatisticsUseCase {
            return new BuildPedigreeStatisticsUseCase(
                $c->get(PedigreeTreeBuilderService::class),
                $c->get(BloodContributionCalculatorService::class),
                $c->get(DogRepositoryInterface::class)
            );
        };

        $this->factories[BuildDiversityMetricsUseCase::class] = function (self $c): BuildDiversityMetricsUseCase {
            return new BuildDiversityMetricsUseCase(
                $c->get(PedigreeTreeBuilderService::class),
                $c->get(CalculateDogCoiUseCase::class)
            );
        };

        $this->factories[PedigreeMatrixRenderer::class] = static function (): PedigreeMatrixRenderer {
            return new PedigreeMatrixRenderer();
        };

        $this->factories[SiblingsTabsRenderer::class] = static function (): SiblingsTabsRenderer {
            return new SiblingsTabsRenderer();
        };

        $this->factories[PedigreeStatisticsRenderer::class] = static function (): PedigreeStatisticsRenderer {
            return new PedigreeStatisticsRenderer();
        };

        $this->factories[DiversityCardRenderer::class] = static function (): DiversityCardRenderer {
            return new DiversityCardRenderer();
        };

        $this->factories[AnalyticsSuiteRenderer::class] = function (self $c): AnalyticsSuiteRenderer {
            return new AnalyticsSuiteRenderer(
                $c->get(SiblingsTabsRenderer::class),
                $c->get(PedigreeStatisticsRenderer::class),
                $c->get(DiversityCardRenderer::class)
            );
        };

        $this->factories[PedigreeMatrixShortcode::class] = function (self $c): PedigreeMatrixShortcode {
            return new PedigreeMatrixShortcode(
                $c->get(BuildPedigreeMatrixUseCase::class),
                $c->get(PedigreeMatrixRenderer::class)
            );
        };

        $this->factories[SiblingsTabsShortcode::class] = function (self $c): SiblingsTabsShortcode {
            return new SiblingsTabsShortcode(
                $c->get(GetSiblingsUseCase::class),
                $c->get(SiblingsTabsRenderer::class)
            );
        };

        $this->factories[PedigreeStatisticsShortcode::class] = function (self $c): PedigreeStatisticsShortcode {
            return new PedigreeStatisticsShortcode(
                $c->get(BuildPedigreeStatisticsUseCase::class),
                $c->get(PedigreeStatisticsRenderer::class)
            );
        };

        $this->factories[PedigreeDiversityCardShortcode::class] = function (self $c): PedigreeDiversityCardShortcode {
            return new PedigreeDiversityCardShortcode(
                $c->get(BuildDiversityMetricsUseCase::class),
                $c->get(DiversityCardRenderer::class)
            );
        };

        $this->factories[PedigreeAnalyticsSuiteShortcode::class] = function (self $c): PedigreeAnalyticsSuiteShortcode {
            return new PedigreeAnalyticsSuiteShortcode(
                $c->get(GetSiblingsUseCase::class),
                $c->get(BuildPedigreeStatisticsUseCase::class),
                $c->get(BuildDiversityMetricsUseCase::class),
                $c->get(AnalyticsSuiteRenderer::class)
            );
        };

        $this->factories[ParentConnectionRegistrar::class] = function (self $c): ParentConnectionRegistrar {
            return new ParentConnectionRegistrar(
                $c->get(CalculateDogCoiUseCase::class),
                $c->get(BuildPedigreeTreeUseCase::class)
            );
        };
    }
}
