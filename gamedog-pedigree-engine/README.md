# GameDog Pedigree Engine

Domain-driven WordPress plugin for dog pedigrees with a full **Pedigree & Genetic Analytics Engine**:

- 5-generation authentic pedigree matrix (binary rowspan layout)
- Siblings tabbed module (full / same sire / same dam)
- Blood contribution statistic (4 generations)
- Genetic diversity metrics (Wright's COI + AVK)

## Architecture

```
src/
  Domain/          Entities, Value Objects, Domain Services, Repository interfaces
  Application/     Use Cases, DTOs, Mappers, Ports
  Infrastructure/  JetEngine/WordPress adapters, cache, bootstrap
  Presentation/    Shortcodes, Elementor widget, Renderers, ViewModels
templates/pedigree/
  pedigree-tree.php
  pedigree-node.php
```

## Data model

| Item | Value |
|------|-------|
| Post type | `dogs` |
| Sire relation ID | `6` |
| Dam relation ID | `7` |
| COI meta key | `_dog_coi` |
| Metrics depth | 4 generations |
| Matrix depth | 5 generations |

Parent links are resolved exclusively through the JetEngine Relation API
(`jet_engine()->relations`), never raw post meta.

## Shortcodes

```
[pedigree_tree id="123"]
[siblings_tabs id="123"]
[pedigree_statistics id="123"]
[pedigree_diversity_card id="123"]
[pedigree_analytics_suite id="123"]
```

`id` is optional and falls back to the current post.

Legacy shortcode `[siblings_box]` remains available.

## Wright COI

F_X = sum (1/2)^(n1 + n2 + 1)

- Common ancestors are detected on both sire and dam branches within 4 generations.
- n1, n2: generations from sire / dam to the common ancestor A.
- Result stored as a percentage string on post meta `_dog_coi` (e.g. `3.13%`),
  together with `_dog_coi_raw` and `_dog_coi_depth`.

## AVK (Ancestor Loss)

AVK = (unique ancestors / 30) * 100 over a 4-generation window (30 slots).

## Blood contribution

Each occurrence of an ancestor contributes its generation weight:

| Generation | Weight |
|------------|--------|
| Parents | 50% |
| Grandparents | 25% |
| Great-grandparents | 12.5% |
| Great-great-grandparents | 6.25% |

Rows are sorted by percentage (desc), then occurrence count (desc).

## Requirements

- PHP 7.4+
- WordPress 5.8+
- JetEngine (relations; meta fallbacks are supported but relations are authoritative)
