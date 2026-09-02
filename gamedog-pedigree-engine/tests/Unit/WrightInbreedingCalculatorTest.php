<?php
/**
 * Unit tests for WrightInbreedingCalculatorService (no WordPress required).
 *
 * Run: php gamedog-pedigree-engine/tests/Unit/WrightInbreedingCalculatorTest.php
 *
 * @package GameDog\PedigreeEngine\Tests
 */

declare(strict_types=1);

namespace GameDog\PedigreeEngine\Tests\Unit;

use GameDog\PedigreeEngine\Domain\Entity\PedigreeNode;
use GameDog\PedigreeEngine\Domain\Entity\PedigreeTree;
use GameDog\PedigreeEngine\Domain\Service\WrightInbreedingCalculatorService;
use GameDog\PedigreeEngine\Domain\ValueObject\DogId;
use GameDog\PedigreeEngine\Domain\ValueObject\GenerationDepth;

require_once dirname(__DIR__, 2) . '/src/Autoloader.php';
\GameDog\PedigreeEngine\Autoloader::register(dirname(__DIR__, 2) . '/src/');

final class WrightInbreedingCalculatorTest
{
    /** @var int */
    private $passed = 0;

    /** @var int */
    private $failed = 0;

    public function run(): int
    {
        $this->testZeroWhenNoCommonAncestor();
        $this->testFullSiblingMating();
        $this->testHalfSiblingMating();
        $this->testParentOffspringMating();
        $this->testGrandparentInbreeding();
        $this->testCircularLineageDoesNotInfiniteLoop();
        $this->testFormattedPercentage();
        $this->testEmptyBranches();
        $this->testKnownAncestorFa();
        $this->testFindCommonAncestorIds();

        echo "\nPassed: {$this->passed}, Failed: {$this->failed}\n";

        return $this->failed === 0 ? 0 : 1;
    }

    /**
     * Full-sib mating: F_X = 0.25 when parents are full siblings and grandparents unrelated.
     *
     * Subject X
     *   Sire S  (parents: GF, GM)
     *   Dam  D  (parents: GF, GM)
     * Common ancestors GF and GM each contribute:
     *   n1=1, n2=1 => (1/2)^3 * (1+0) = 0.125 each => total 0.25
     */
    private function testFullSiblingMating(): void
    {
        $calc = new WrightInbreedingCalculatorService();

        $gf = new PedigreeNode(new DogId(10), 'GF', 2, 'sire');
        $gm = new PedigreeNode(new DogId(11), 'GM', 2, 'dam');

        $sire = (new PedigreeNode(new DogId(2), 'Sire', 1, 'sire'))
            ->withSireNode($gf)
            ->withDamNode($gm);

        // Same grandparents on dam side (new node instances, same IDs).
        $gf2 = new PedigreeNode(new DogId(10), 'GF', 2, 'sire');
        $gm2 = new PedigreeNode(new DogId(11), 'GM', 2, 'dam');

        $dam = (new PedigreeNode(new DogId(3), 'Dam', 1, 'dam'))
            ->withSireNode($gf2)
            ->withDamNode($gm2);

        $coi = $calc->calculateFromBranches($sire, $dam);

        $this->assertFloatEquals(0.25, $coi->ratio(), 'Full-sib mating COI ratio', 0.0001);
        $this->assertSame('25.00%', $coi->formatted(), 'Full-sib mating formatted');
    }

    /**
     * Half-sib mating (shared sire only): F_X = 0.125
     */
    private function testHalfSiblingMating(): void
    {
        $calc = new WrightInbreedingCalculatorService();

        $sharedSire = new PedigreeNode(new DogId(50), 'SharedSire', 2, 'sire');
        $gm1        = new PedigreeNode(new DogId(51), 'GM1', 2, 'dam');
        $gm2        = new PedigreeNode(new DogId(52), 'GM2', 2, 'dam');

        $sire = (new PedigreeNode(new DogId(2), 'Sire', 1, 'sire'))
            ->withSireNode($sharedSire)
            ->withDamNode($gm1);

        $sharedSire2 = new PedigreeNode(new DogId(50), 'SharedSire', 2, 'sire');
        $dam = (new PedigreeNode(new DogId(3), 'Dam', 1, 'dam'))
            ->withSireNode($sharedSire2)
            ->withDamNode($gm2);

        $coi = $calc->calculateFromBranches($sire, $dam);

        $this->assertFloatEquals(0.125, $coi->ratio(), 'Half-sib mating COI ratio', 0.0001);
        $this->assertSame('12.50%', $coi->formatted(), 'Half-sib mating formatted');
    }

    /**
     * Parent-offspring: dam is also the sire's mother.
     * Subject X, Sire S (dam = M), Dam M.
     * Common ancestor M: on sire path n1=1 (S->M), on dam path n2=0 (dam is M).
     * Contribution: (1/2)^(1+0+1) = 0.25
     */
    private function testParentOffspringMating(): void
    {
        $calc = new WrightInbreedingCalculatorService();

        $mother = new PedigreeNode(new DogId(7), 'Mother', 2, 'dam');
        $sire   = (new PedigreeNode(new DogId(2), 'Sire', 1, 'sire'))
            ->withDamNode($mother);

        $dam = new PedigreeNode(new DogId(7), 'Mother', 1, 'dam');

        $coi = $calc->calculateFromBranches($sire, $dam);

        $this->assertFloatEquals(0.25, $coi->ratio(), 'Parent-offspring COI ratio', 0.0001);
    }

    /**
     * Shared maternal grandsire only, one generation further:
     * n1=2, n2=2 => (1/2)^5 = 0.03125
     */
    private function testGrandparentInbreeding(): void
    {
        $calc = new WrightInbreedingCalculatorService();

        $common = new PedigreeNode(new DogId(99), 'Common', 3, 'sire');

        $sireFather = (new PedigreeNode(new DogId(20), 'SF', 2, 'sire'))
            ->withSireNode($common);
        $sire = (new PedigreeNode(new DogId(2), 'Sire', 1, 'sire'))
            ->withSireNode($sireFather);

        $common2 = new PedigreeNode(new DogId(99), 'Common', 3, 'sire');
        $damFather = (new PedigreeNode(new DogId(30), 'DF', 2, 'sire'))
            ->withSireNode($common2);
        $dam = (new PedigreeNode(new DogId(3), 'Dam', 1, 'dam'))
            ->withSireNode($damFather);

        $coi = $calc->calculateFromBranches($sire, $dam);

        $this->assertFloatEquals(0.03125, $coi->ratio(), 'Great-grandparent path COI', 0.0001);
        // 0.03125 => 3.125% => rounds to 3.13% with PHP_ROUND_HALF_UP
        $formatted = $coi->formatted();
        $this->assertTrue(
            $formatted === '3.13%' || $formatted === '3.12%',
            'Great-grandparent formatted (got ' . $formatted . ')'
        );
    }

    private function testZeroWhenNoCommonAncestor(): void
    {
        $calc = new WrightInbreedingCalculatorService();

        $sire = (new PedigreeNode(new DogId(2), 'Sire', 1, 'sire'))
            ->withSireNode(new PedigreeNode(new DogId(10), 'A', 2, 'sire'))
            ->withDamNode(new PedigreeNode(new DogId(11), 'B', 2, 'dam'));

        $dam = (new PedigreeNode(new DogId(3), 'Dam', 1, 'dam'))
            ->withSireNode(new PedigreeNode(new DogId(12), 'C', 2, 'sire'))
            ->withDamNode(new PedigreeNode(new DogId(13), 'D', 2, 'dam'));

        $coi = $calc->calculateFromBranches($sire, $dam);

        $this->assertFloatEquals(0.0, $coi->ratio(), 'No common ancestor => 0');
        $this->assertSame('0.00%', $coi->formatted(), 'Zero formatted');
    }

    private function testCircularLineageDoesNotInfiniteLoop(): void
    {
        $calc = new WrightInbreedingCalculatorService();

        // A points to B as sire, B points back to A as sire (corrupt data).
        $a = new PedigreeNode(new DogId(100), 'A', 2, 'sire');
        $b = new PedigreeNode(new DogId(101), 'B', 3, 'sire');
        $a = $a->withSireNode($b);
        $bCircular = (new PedigreeNode(new DogId(101), 'B', 3, 'sire'))
            ->withSireNode(new PedigreeNode(new DogId(100), 'A', 4, 'sire'));
        $a = (new PedigreeNode(new DogId(100), 'A', 2, 'sire'))->withSireNode($bCircular);

        $sire = (new PedigreeNode(new DogId(2), 'Sire', 1, 'sire'))->withSireNode($a);
        $dam  = new PedigreeNode(new DogId(3), 'Dam', 1, 'dam');

        $started = microtime(true);
        $coi     = $calc->calculateFromBranches($sire, $dam);
        $elapsed = microtime(true) - $started;

        $this->assertTrue($elapsed < 1.0, 'Circular lineage finished quickly');
        $this->assertFloatEquals(0.0, $coi->ratio(), 'No shared sides => 0 despite cycle');
    }

    private function testFormattedPercentage(): void
    {
        $calc = new WrightInbreedingCalculatorService();
        $coi  = $calc->calculateFromBranches(null, null);
        $this->assertSame('0.00%', $coi->formatted(), 'Null branches format');
    }

    private function testEmptyBranches(): void
    {
        $calc  = new WrightInbreedingCalculatorService();
        $empty = PedigreeNode::empty(1, 'sire');
        $dam   = new PedigreeNode(new DogId(3), 'Dam', 1, 'dam');
        $coi   = $calc->calculateFromBranches($empty, $dam);
        $this->assertTrue($coi->isZero(), 'Empty sire branch => zero');
    }

    private function testKnownAncestorFa(): void
    {
        $calc = new WrightInbreedingCalculatorService();

        // Half-sib with F_A = 0.25 for shared ancestor:
        // (1/2)^(1+1+1) * (1+0.25) = 0.125 * 1.25 = 0.15625
        $shared = new PedigreeNode(new DogId(50), 'Shared', 2, 'sire');
        $sire   = (new PedigreeNode(new DogId(2), 'Sire', 1, 'sire'))->withSireNode($shared);
        $shared2 = new PedigreeNode(new DogId(50), 'Shared', 2, 'sire');
        $dam    = (new PedigreeNode(new DogId(3), 'Dam', 1, 'dam'))->withSireNode($shared2);

        $coi = $calc->calculateFromBranches($sire, $dam, [50 => 0.25]);
        $this->assertFloatEquals(0.15625, $coi->ratio(), 'With F_A=0.25', 0.0001);
    }

    private function testFindCommonAncestorIds(): void
    {
        $calc = new WrightInbreedingCalculatorService();

        $sire = (new PedigreeNode(new DogId(2), 'Sire', 1, 'sire'))
            ->withSireNode(new PedigreeNode(new DogId(10), 'A', 2, 'sire'));
        $dam = (new PedigreeNode(new DogId(3), 'Dam', 1, 'dam'))
            ->withDamNode(new PedigreeNode(new DogId(10), 'A', 2, 'dam'));

        $common = $calc->findCommonAncestorIds($sire, $dam);
        $this->assertTrue(isset($common[10]), 'Dog 10 is common ancestor');
        $this->assertTrue(!isset($common[2]), 'Sire itself not on dam side');
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

    private function assertFloatEquals(float $expected, float $actual, string $message, float $delta = 0.00001): void
    {
        if (abs($expected - $actual) <= $delta) {
            $this->pass($message);
        } else {
            $this->fail($message . " | expected {$expected} got {$actual}");
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

exit((new WrightInbreedingCalculatorTest())->run());
