<?php
/**
 * Unit tests for CoiPercentage value object.
 *
 * Run: php gamedog-pedigree-engine/tests/Unit/CoiPercentageTest.php
 *
 * @package GameDog\PedigreeEngine\Tests
 */

declare(strict_types=1);

namespace GameDog\PedigreeEngine\Tests\Unit;

use GameDog\PedigreeEngine\Domain\ValueObject\CoiPercentage;

require_once dirname(__DIR__, 2) . '/src/Autoloader.php';
\GameDog\PedigreeEngine\Autoloader::register(dirname(__DIR__, 2) . '/src/');

$passed = 0;
$failed = 0;

function expect_true(bool $cond, string $msg) {
    global $passed, $failed;
    if ($cond) {
        $passed++;
        echo "[PASS] {$msg}\n";
    } else {
        $failed++;
        echo "[FAIL] {$msg}\n";
    }
}

$z = CoiPercentage::zero();
expect_true($z->formatted() === '0.00%', 'zero formats as 0.00%');
expect_true($z->isZero(), 'zero isZero');

$p = CoiPercentage::fromPercent(12.5);
expect_true($p->formatted() === '12.50%', '12.5% formats');
expect_true(abs($p->ratio() - 0.125) < 0.00001, '12.5% ratio');

$fromMeta = CoiPercentage::fromStored('6.25%');
expect_true($fromMeta->formatted() === '6.25%', 'parse 6.25%');

$fromFloat = CoiPercentage::fromStored(0.0625);
expect_true($fromFloat->formatted() === '6.25%', 'parse ratio 0.0625');

$fromPts = CoiPercentage::fromStored(6.25);
expect_true($fromPts->formatted() === '6.25%', 'parse percent points 6.25');

$empty = CoiPercentage::fromStored(null);
expect_true($empty->formatted() === '0.00%', 'null stored => 0');

echo "\nPassed: {$passed}, Failed: {$failed}\n";
exit($failed === 0 ? 0 : 1);
