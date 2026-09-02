# GameDog Pedigree Engine

Domain-driven WordPress plugin for dog pedigrees with **Wright's Coefficient of Inbreeding (COI)** calculated over **5 generations**.

## Architecture

```
src/
  Domain/          Entities, Value Objects, Domain Services, Repository interfaces
  Application/     Use Cases, DTOs, Mappers, Ports
  Infrastructure/  JetEngine/WordPress adapters, cache, bootstrap
  Presentation/    Shortcodes, Elementor widget, ViewModels, Renderer
templates/pedigree/
  pedigree-tree.php
  pedigree-node.php
```

## Wright COI

\[
F_X = \sum \left(\frac{1}{2}\right)^{n_1 + n_2 + 1} (1 + F_A)
\]

- Common ancestors are detected on both sire and dam branches.
- \(n_1\), \(n_2\): generations from sire / dam to ancestor \(A\).
- \(F_A\): ancestor inbreeding (0 when unknown / depth limit).
- Result stored as percentage string on post meta `_dog_coi` (e.g. `12.50%`).

## Data model

| Item | Value |
|------|-------|
| Post type | `dogs` |
| Sire relation ID | `6` |
| Dam relation ID | `7` |
| COI meta key | `_dog_coi` |
| Depth | 5 generations |

## Usage

```
[pedigree_tree id="123" depth="5"]
[siblings_box id="123"]
```

Elementor widget: **GameDog Pedigree Tree**.

The pedigree header shows:

```html
<div class="gd-pedigree-coi-badge">COI: <strong>6.25%</strong> (5 Generations)</div>
```

Common ancestors in the current tree receive the CSS class `gd-node-inbred`.

## COI recalculation

COI is recalculated and written to `_dog_coi` when:

- A dog post is saved
- JetEngine parent relations change
- Parent-related post meta is updated

Results are also cached via the WordPress object cache / transients.

## Tests

```bash
php tests/Unit/WrightInbreedingCalculatorTest.php
php tests/Unit/CoiPercentageTest.php
```

## Requirements

- PHP 7.4+
- WordPress 5.8+
- JetEngine (for relations; meta fallbacks are supported)
