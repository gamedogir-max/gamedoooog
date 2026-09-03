<?php
/**
 * Integration test for the Pedigree & Genetic Analytics Engine.
 *
 * Exercises the full stack (tree builder, blood contribution, COI, AVK,
 * siblings, matrix renderer) against in-memory fakes and WordPress stubs.
 *
 * Run: php gamedog-pedigree-engine/tests/Unit/PedigreeEngineIntegrationTest.php
 *
 * @package GameDog\PedigreeEngine\Tests
 */

declare(strict_types=1);

namespace GameDog\PedigreeEngine\Tests\Unit;

use GameDog\PedigreeEngine\Application\UseCase\BuildDiversityMetricsUseCase;
use GameDog\PedigreeEngine\Application\UseCase\BuildPedigreeMatrixUseCase;
use GameDog\PedigreeEngine\Application\UseCase\BuildPedigreeStatisticsUseCase;
use GameDog\PedigreeEngine\Application\UseCase\CalculateDogCoiUseCase;
use GameDog\PedigreeEngine\Application\UseCase\GetSiblingsUseCase;
use GameDog\PedigreeEngine\Domain\Contract\RelationTraversalInterface;
use GameDog\PedigreeEngine\Domain\Entity\Dog;
use GameDog\PedigreeEngine\Domain\Repository\DogRepositoryInterface;
use GameDog\PedigreeEngine\Domain\Service\BloodContributionCalculatorService;
use GameDog\PedigreeEngine\Domain\Service\PedigreeTreeBuilderService;
use GameDog\PedigreeEngine\Domain\Service\WrightInbreedingCalculatorService;
use GameDog\PedigreeEngine\Domain\ValueObject\CoiPercentage;
use GameDog\PedigreeEngine\Domain\ValueObject\DogId;
use GameDog\PedigreeEngine\Presentation\Renderer\AnalyticsSuiteRenderer;
use GameDog\PedigreeEngine\Presentation\Renderer\DiversityCardRenderer;
use GameDog\PedigreeEngine\Presentation\Renderer\PedigreeMatrixRenderer;
use GameDog\PedigreeEngine\Presentation\Renderer\PedigreeStatisticsRenderer;
use GameDog\PedigreeEngine\Presentation\Renderer\SiblingsTabsRenderer;
use GameDog\PedigreeEngine\Presentation\Shortcode\PedigreeAnalyticsSuiteShortcode;
use GameDog\PedigreeEngine\Presentation\Shortcode\PedigreeDiversityCardShortcode;
use GameDog\PedigreeEngine\Presentation\Shortcode\PedigreeMatrixShortcode;
use GameDog\PedigreeEngine\Presentation\Shortcode\PedigreeStatisticsShortcode;
use GameDog\PedigreeEngine\Presentation\Shortcode\SiblingsTabsShortcode;

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/src/Autoloader.php';
\GameDog\PedigreeEngine\Autoloader::register(dirname(__DIR__, 2) . '/src/');

final class InMemoryDogRepository implements DogRepositoryInterface
{
    /** @var array<int, Dog> */
    private $dogs = [];

    /** @var array<int, array{coi: CoiPercentage, depth: int}> */
    private $coi = [];

    public function add(int $id, string $name, string $thumb = ''): Dog
    {
        $dog = new Dog(
            new DogId($id),
            $name,
            null,
            null,
            null,
            '',
            $thumb,
            'https://example.test/dog/' . $id
        );

        $this->dogs[$id] = $dog;

        return $dog;
    }

    public function findById(DogId $id): ?Dog
    {
        return $this->dogs[$id->toInt()] ?? null;
    }

    public function findByIds(array $ids): array
    {
        $out = [];

        foreach ($ids as $raw) {
            $dogId = DogId::fromMixed($raw);
            if ($dogId === null) {
                continue;
            }

            $dog = $this->findById($dogId);
            if ($dog !== null) {
                $out[$dogId->toInt()] = $dog;
            }
        }

        return $out;
    }

    public function saveCoi(DogId $id, CoiPercentage $coi, int $depth = 4): void
    {
        $this->coi[$id->toInt()] = ['coi' => $coi, 'depth' => $depth];
    }

    public function getStoredCoi(DogId $id): ?CoiPercentage
    {
        return $this->coi[$id->toInt()]['coi'] ?? null;
    }

    public function getStoredCoiDepth(DogId $id): int
    {
        return $this->coi[$id->toInt()]['depth'] ?? 0;
    }
}

final class InMemoryRelations implements RelationTraversalInterface
{
    /** @var array<int, int> */
    private $sire = [];

    /** @var array<int, int> */
    private $dam = [];

    /** @var array<int, array<int, int>> */
    private $offspring = [];

    public function setParents(int $child, ?int $sire, ?int $dam): void
    {
        if ($sire !== null) {
            $this->sire[$child] = $sire;
            $this->offspring[$sire][] = $child;
        }
        if ($dam !== null) {
            $this->dam[$child] = $dam;
            $this->offspring[$dam][] = $child;
        }
    }

    public function getSireId(DogId $dogId): ?DogId
    {
        return isset($this->sire[$dogId->toInt()]) ? DogId::fromMixed($this->sire[$dogId->toInt()]) : null;
    }

    public function getDamId(DogId $dogId): ?DogId
    {
        return isset($this->dam[$dogId->toInt()]) ? DogId::fromMixed($this->dam[$dogId->toInt()]) : null;
    }

    public function getParentIds(DogId $dogId): array
    {
        return ['sire' => $this->getSireId($dogId), 'dam' => $this->getDamId($dogId)];
    }

    public function getOffspringIds(DogId $dogId): array
    {
        $list = $this->offspring[$dogId->toInt()] ?? [];

        $out = [];
        foreach (array_unique($list) as $child) {
            $vo = DogId::fromMixed($child);
            if ($vo !== null) {
                $out[] = $vo;
            }
        }

        return $out;
    }
}

final class PedigreeEngineIntegrationTest
{
    /** @var int */
    private $passed = 0;

    /** @var int */
    private $failed = 0;

    /** @var InMemoryDogRepository */
    private $dogs;

    /** @var InMemoryRelations */
    private $relations;

    public function run(): int
    {
        $this->testTitleParsing();
        $this->testMatrixStructure();
        $this->testBloodContribution();
        $this->testDiversityMetrics();
        $this->testSiblings();
        $this->testShortcodeRegistration();
        $this->testZeroParents();
        $this->testPartialParents();
        $this->testCircularLineage();

        echo "\nPassed: {$this->passed}, Failed: {$this->failed}\n";

        return $this->failed === 0 ? 0 : 1;
    }

    private function testZeroParents(): void
    {
        $repo = new InMemoryDogRepository();
        $rel  = new InMemoryRelations();
        $repo->add(100, 'Orphan Dog');

        $treeBuilder = new PedigreeTreeBuilderService($repo, $rel);

        $matrix = new BuildPedigreeMatrixUseCase($treeBuilder, $repo);
        $data   = $matrix->execute(100);
        $this->assertSame(62, count($data['cells']), 'orphan matrix still has 62 cells');
        $this->assertSame([], $data['linebreeding'], 'orphan has no linebreeding');

        $html = (new PedigreeMatrixRenderer())->render($data);
        $this->assertSame(62, substr_count($html, 'gd-matrix-unknown'), 'orphan renders 62 unknown cells');

        $coiUseCase = new CalculateDogCoiUseCase($repo, $rel, new WrightInbreedingCalculatorService(), $treeBuilder, null);
        $metrics    = (new BuildDiversityMetricsUseCase($treeBuilder, $coiUseCase))->execute(100, true);
        $this->assertSame(0.0, $metrics['coi_percent'], 'orphan COI 0');
        $this->assertSame(0.0, $metrics['avk_percent'], 'orphan AVK 0');

        $stats = (new BuildPedigreeStatisticsUseCase($treeBuilder, new BloodContributionCalculatorService(), $repo))->execute(100);
        $this->assertSame([], $stats['rows'], 'orphan statistic empty');
    }

    private function testPartialParents(): void
    {
        $repo = new InMemoryDogRepository();
        $rel  = new InMemoryRelations();
        $repo->add(101, 'Partial Dog');
        $repo->add(301, 'Sire Only');
        $repo->add(401, 'GS A');
        $repo->add(402, 'GS B');

        $rel->setParents(101, 301, null);
        $rel->setParents(301, 401, 402);

        $treeBuilder = new PedigreeTreeBuilderService($repo, $rel);

        $coiUseCase = new CalculateDogCoiUseCase($repo, $rel, new WrightInbreedingCalculatorService(), $treeBuilder, null);
        $metrics    = (new BuildDiversityMetricsUseCase($treeBuilder, $coiUseCase))->execute(101, true);
        $this->assertSame(0.0, $metrics['coi_percent'], 'partial parents COI 0 (needs both branches)');
        // Sire side unique ancestors: 301, 401, 402 => 3/30 = 10%.
        $this->assertSame(10.0, $metrics['avk_percent'], 'partial parents AVK 10');

        $stats = (new BuildPedigreeStatisticsUseCase($treeBuilder, new BloodContributionCalculatorService(), $repo))->execute(101);
        $this->assertSame(3, count($stats['rows']), 'partial parents has 3 ancestor rows');
    }

    private function testCircularLineage(): void
    {
        $repo = new InMemoryDogRepository();
        $rel  = new InMemoryRelations();
        $repo->add(200, 'Loop A');
        $repo->add(201, 'Loop B');

        // Corrupt data: A -> B -> A.
        $rel->setParents(200, 201, null);
        $rel->setParents(201, 200, null);

        $treeBuilder = new PedigreeTreeBuilderService($repo, $rel);

        $started = microtime(true);
        $matrix  = (new BuildPedigreeMatrixUseCase($treeBuilder, $repo))->execute(200);
        $elapsed = microtime(true) - $started;

        $this->assertTrue($elapsed < 2.0, 'circular lineage resolves quickly');
        $this->assertSame(62, count($matrix['cells']), 'circular matrix has 62 cells');

        $coiUseCase = new CalculateDogCoiUseCase($repo, $rel, new WrightInbreedingCalculatorService(), $treeBuilder, null);
        $metrics    = (new BuildDiversityMetricsUseCase($treeBuilder, $coiUseCase))->execute(200, true);
        $this->assertSame(0.0, $metrics['coi_percent'], 'circular lineage COI 0');
    }

    private function testTitleParsing(): void
    {
        $dog = new Dog(new DogId(1), 'GR CH Big Boy');
        $this->assertSame('Big Boy', $dog->registeredName(), 'GR CH parsed');
        $this->assertSame(['GR CH'], $dog->titles(), 'GR CH title list');

        $dog = new Dog(new DogId(2), 'CH ROM 2xW Ace');
        $this->assertSame('Ace', $dog->registeredName(), 'CH ROM 2xW parsed');
        $this->assertSame(['CH', 'ROM', '2XW'], $dog->titles(), 'multi title list');

        $dog = new Dog(new DogId(3), 'D.O.Y. BIS Coco');
        $this->assertSame('Coco', $dog->registeredName(), 'D.O.Y. BIS parsed');

        $dog = new Dog(new DogId(4), 'Plain Name');
        $this->assertSame('Plain Name', $dog->registeredName(), 'no titles keeps name');
        $this->assertSame([], $dog->titles(), 'no titles empty list');
    }

    private function testMatrixStructure(): void
    {
        [$repo, $rel, $treeBuilder] = $this->makeWorld();

        $useCase = new BuildPedigreeMatrixUseCase($treeBuilder, $repo);
        $data    = $useCase->execute(1);

        $this->assertSame(62, count($data['cells']), 'matrix has 62 ancestor cells');

        // Linebreeding: repeated ancestors receive dots.
        $repeated = $data['linebreeding'];
        $this->assertTrue(isset($repeated[10]) && isset($repeated[11]), 'grandparents flagged as repeated');
        $this->assertSame(14, count($repeated), '14 repeated ancestors');

        // First two cells are the parents with rowspan 16.
        $this->assertSame(2, $data['cells'][0]['slot'], 'first cell is sire slot');
        $this->assertSame(16, $data['cells'][0]['rowspan'], 'sire rowspan 16');
        $this->assertSame(3, $data['cells'][1]['slot'], 'second cell is dam slot');

        // Last 32 cells are generation 5 leaves with rowspan 1.
        $gen5 = array_slice($data['cells'], 30, 32);
        $allGen5 = true;
        $allRow1 = true;
        foreach ($gen5 as $cell) {
            if ($cell['generation'] !== 5) {
                $allGen5 = false;
            }
            if ($cell['rowspan'] !== 1) {
                $allRow1 = false;
            }
        }
        $this->assertTrue($allGen5, 'last 32 cells are generation 5');
        $this->assertTrue($allRow1, 'generation 5 cells have rowspan 1');

        // Titles + registered name survive into the cell data.
        $sireCell = $data['cells'][0]['dog'];
        $this->assertSame('GR CH', $sireCell['titles'][0], 'sire title parsed');
        $this->assertSame('Sire Alpha', $sireCell['registered_name'], 'sire registered name');

        // Render and check the exact HTML shape.
        $renderer = new PedigreeMatrixRenderer();
        $html     = $renderer->render($data);

        $this->assertSame(32, substr_count($html, '<tr>'), 'renders exactly 32 table rows');
        $this->assertSame(62, substr_count($html, '<td'), 'renders 62 cells');
        $this->assertSame(2, substr_count($html, 'rowspan="16"'), 'two rowspan 16 cells');
        $this->assertSame(4, substr_count($html, 'rowspan="8"'), 'four rowspan 8 cells');
        $this->assertSame(8, substr_count($html, 'rowspan="4"'), 'eight rowspan 4 cells');
        $this->assertSame(16, substr_count($html, 'rowspan="2"'), 'sixteen rowspan 2 cells');
        $this->assertSame(32, substr_count($html, 'rowspan="1"'), 'thirty-two rowspan 1 cells');

        // Repeated dogs get 2 dots each (14 repeated => 28 dots).
        $this->assertSame(28, substr_count($html, 'gd-linebreeding-dot'), 'linebreeding dots rendered');
        $this->assertSame(28, substr_count($html, 'data-dot-color='), 'data-dot-color attributes rendered');
        $this->assertTrue(strpos($html, 'gd-dot-idx-0') !== false, 'palette index class rendered');
        $this->assertSame(0, substr_count($html, 'style='), 'zero inline styles rendered');

        // Photos: dog 2 (gen1) once + dog 10 (gen2) twice = 3 framed photos.
        $this->assertSame(3, substr_count($html, 'gd-matrix-photo'), 'framed photos for gen 1-2');

        // Camera: dog 20 (gen3) appears twice with aria-label instead of screen-reader-text.
        $this->assertSame(2, substr_count($html, 'class="gd-matrix-camera"'), 'camera icons for gen 3+');
        $this->assertSame(2, substr_count($html, 'aria-label="View photo of GG A"'), 'camera icons have aria-label');
        $this->assertSame(0, substr_count($html, 'screen-reader-text'), 'zero screen-reader-text spans');

        // 32 unknown leaves.
        $this->assertSame(32, substr_count($html, 'gd-matrix-unknown'), 'unknown leaves rendered');
    }

    private function testBloodContribution(): void
    {
        [$repo, $rel, $treeBuilder] = $this->makeWorld();

        $useCase = new BuildPedigreeStatisticsUseCase(
            $treeBuilder,
            new BloodContributionCalculatorService(),
            $repo
        );

        $data = $useCase->execute(1);

        $this->assertSame(16, count($data['rows']), '16 unique ancestors in contribution table');

        // Aggregate the percentages back to the expected 400% total.
        $total = 0.0;
        $aceRow = null;
        foreach ($data['rows'] as $row) {
            $total += $row['percent'];
            if ($row['id'] === 10) {
                $aceRow = $row;
            }
        }

        $this->assertTrue(abs($total - 400.0) < 0.001, 'contributions sum to 400%');

        // Grandsire Ace appears twice at 50%.
        $this->assertTrue($aceRow !== null, 'grandsire row present');
        $this->assertSame(2, $aceRow['count'], 'grandsire count 2x');
        $this->assertSame(50.0, $aceRow['percent'], 'grandsire 50%');
        $this->assertSame('Grandsire Ace', $aceRow['name'], 'grandsire registered name');

        // Renderer smoke test.
        $renderer = new PedigreeStatisticsRenderer();
        $html     = $renderer->render($data);
        $this->assertTrue(strpos($html, 'Pedigree statistic') !== false, 'stat header rendered');
        $this->assertTrue(strpos($html, '2x') !== false, 'count marker rendered');
    }

    private function testDiversityMetrics(): void
    {
        [$repo, $rel, $treeBuilder] = $this->makeWorld();

        $coiUseCase = new CalculateDogCoiUseCase(
            $repo,
            $rel,
            new WrightInbreedingCalculatorService(),
            $treeBuilder,
            null
        );

        $useCase = new BuildDiversityMetricsUseCase($treeBuilder, $coiUseCase);
        $data    = $useCase->execute(1, true);

        // COI for parents that are full siblings, grandparents unrelated,
        // 4-generation window => 43.75%.
        $this->assertSame(43.75, $data['coi_percent'], 'COI percent 43.75');
        $this->assertSame('43.75%', $data['coi_formatted'], 'COI formatted');

        // AVK = 16 unique / 30 theoretical = 53.33%.
        $this->assertSame(53.33, $data['avk_percent'], 'AVK percent 53.33');
        $this->assertSame('53.33%', $data['avk_formatted'], 'AVK formatted');

        // The value was persisted to the repository.
        $this->assertSame(4, $repo->getStoredCoiDepth(new DogId(1)), 'COI persisted at depth 4');
        $this->assertSame('43.75%', $repo->getStoredCoi(new DogId(1))->formatted(), 'COI persisted value');

        $renderer = new DiversityCardRenderer();
        $html     = $renderer->render($data);
        $this->assertTrue(strpos($html, 'Coefficient of inbreeding (COI)') !== false, 'COI label rendered');
        $this->assertTrue(strpos($html, 'Ancestor loss (AVK)') !== false, 'AVK label rendered');
        $this->assertTrue(strpos($html, '43.75 %') !== false, 'COI value rendered');
        $this->assertTrue(strpos($html, '53.33 %') !== false, 'AVK value rendered');
        $this->assertTrue(strpos($html, 'View detailed pedigree analysis') !== false, 'CTA rendered');
    }

    private function testSiblings(): void
    {
        [$repo, $rel] = $this->makeWorld();

        $useCase = new GetSiblingsUseCase($repo, $rel);
        $result  = $useCase->execute(1);

        $this->assertSame([4], array_keys($result['full']), 'full sibling found');
        $this->assertSame([5], array_keys($result['sire']), 'paternal half-sibling found');
        $this->assertSame([], array_keys($result['dam']), 'no maternal half-sibling');

        $renderer = new SiblingsTabsRenderer();
        $html     = $renderer->render([
            'subject_id' => 1,
            'full'       => $result['full'],
            'sire'       => $result['sire'],
            'dam'        => $result['dam'],
        ]);

        $this->assertTrue(strpos($html, 'Siblings') !== false, 'Siblings tab rendered');
        $this->assertTrue(strpos($html, 'Same sire') !== false, 'Same sire tab rendered');
        $this->assertTrue(strpos($html, 'Same dam') !== false, 'Same dam tab rendered');
        $this->assertTrue(strpos($html, 'gd-tab-active') !== false, 'active tab marker present');
    }

    private function testShortcodeRegistration(): void
    {
        [$repo, $rel, $treeBuilder] = $this->makeWorld();

        $matrix = new PedigreeMatrixShortcode(
            new BuildPedigreeMatrixUseCase($treeBuilder, $repo),
            new PedigreeMatrixRenderer()
        );

        $siblings = new SiblingsTabsShortcode(
            new GetSiblingsUseCase($repo, $rel),
            new SiblingsTabsRenderer()
        );

        $stats = new PedigreeStatisticsShortcode(
            new BuildPedigreeStatisticsUseCase($treeBuilder, new BloodContributionCalculatorService(), $repo),
            new PedigreeStatisticsRenderer()
        );

        $coiUseCase = new CalculateDogCoiUseCase($repo, $rel, new WrightInbreedingCalculatorService(), $treeBuilder, null);

        $card = new PedigreeDiversityCardShortcode(
            new BuildDiversityMetricsUseCase($treeBuilder, $coiUseCase),
            new DiversityCardRenderer()
        );

        $suite = new PedigreeAnalyticsSuiteShortcode(
            new GetSiblingsUseCase($repo, $rel),
            new BuildPedigreeStatisticsUseCase($treeBuilder, new BloodContributionCalculatorService(), $repo),
            new BuildDiversityMetricsUseCase($treeBuilder, $coiUseCase),
            new AnalyticsSuiteRenderer(
                new SiblingsTabsRenderer(),
                new PedigreeStatisticsRenderer(),
                new DiversityCardRenderer()
            )
        );

        $matrix->register();
        $siblings->register();
        $stats->register();
        $card->register();
        $suite->register();

        $registered = $GLOBALS['GD_SHORTCODES'] ?? [];

        $this->assertTrue(isset($registered['pedigree_tree']), '[pedigree_tree] registered');
        $this->assertTrue(isset($registered['siblings_tabs']), '[siblings_tabs] registered');
        $this->assertTrue(isset($registered['pedigree_statistics']), '[pedigree_statistics] registered');
        $this->assertTrue(isset($registered['pedigree_diversity_card']), '[pedigree_diversity_card] registered');
        $this->assertTrue(isset($registered['pedigree_analytics_suite']), '[pedigree_analytics_suite] registered');

        // Smoke render the analytics suite.
        $GLOBALS['GD_CURRENT_ID'] = 1;
        $html = $suite->render(['id' => 1]);
        $this->assertTrue(strpos($html, 'gd-analytics-suite') !== false, 'analytics suite container rendered');
        $this->assertTrue(strpos($html, 'gd-siblings-tabs') !== false, 'suite contains siblings tabs');
        $this->assertTrue(strpos($html, 'gd-pedigree-stats') !== false, 'suite contains statistic');
        $this->assertTrue(strpos($html, 'gd-diversity-card') !== false, 'suite contains diversity card');
    }

    /**
     * Build the shared inbred world.
     *
     * Dog 1 subject; parents 2 and 3 are full siblings (share 10 and 11).
     * Dogs 4 (full sibling) and 5 (paternal half-sibling) test siblings.
     *
     * @return array{0: InMemoryDogRepository, 1: InMemoryRelations, 2: PedigreeTreeBuilderService}
     */
    private function makeWorld(): array
    {
        $repo = new InMemoryDogRepository();
        $rel  = new InMemoryRelations();

        // Subject + siblings.
        $repo->add(1, 'Subject Dog');
        $repo->add(4, 'Full Sib');
        $repo->add(5, 'Half Sib');
        $repo->add(6, 'Other Dam');

        // Parents (full siblings).
        $repo->add(2, 'GR CH Sire Alpha', 'https://example.test/2.jpg');
        $repo->add(3, 'Dam Beta');

        // Shared grandparents.
        $repo->add(10, 'CH Grandsire Ace', 'https://example.test/10.jpg');
        $repo->add(11, 'Granddam Bella');

        // Great-grandparents.
        $repo->add(20, 'GG A', 'https://example.test/20.jpg');
        $repo->add(21, 'GG B');
        $repo->add(22, 'GG C');
        $repo->add(23, 'GG D');

        // Great-great-grandparents (generation 4).
        foreach ([40, 41, 42, 43, 44, 45, 46, 47] as $id) {
            $repo->add($id, 'GGG ' . $id);
        }

        // Relations.
        $rel->setParents(1, 2, 3);
        $rel->setParents(4, 2, 3);
        $rel->setParents(5, 2, 6);
        $rel->setParents(6, null, null);

        $rel->setParents(2, 10, 11);
        $rel->setParents(3, 10, 11);

        $rel->setParents(10, 20, 21);
        $rel->setParents(11, 22, 23);

        $rel->setParents(20, 40, 41);
        $rel->setParents(21, 42, 43);
        $rel->setParents(22, 44, 45);
        $rel->setParents(23, 46, 47);

        $treeBuilder = new PedigreeTreeBuilderService($repo, $rel);

        return [$repo, $rel, $treeBuilder];
    }

    // --- assertion helpers ---

    private function assertSame($expected, $actual, string $message): void
    {
        if ($expected === $actual) {
            $this->pass($message);
        } else {
            $this->fail($message . ' | expected ' . var_export($expected, true) . ' got ' . var_export($actual, true));
        }
    }

    private function assertTrue(bool $cond, string $message): void
    {
        if ($cond) {
            $this->pass($message);
        } else {
            $this->fail($message);
        }
    }

    private function pass(string $message): void
    {
        $this->passed++;
        echo "[PASS] {$message}\n";
    }

    private function fail(string $message): void
    {
        $this->failed++;
        echo "[FAIL] {$message}\n";
    }
}

exit((new PedigreeEngineIntegrationTest())->run());
