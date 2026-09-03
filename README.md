# gamedoooog

WordPress monorepo for the **GameDog Pedigree Engine** plugin.

## Plugin

See [`gamedog-pedigree-engine/`](gamedog-pedigree-engine/) for the full Domain-Driven Design plugin, including the Pedigree & Genetic Analytics Engine (Wright COI, AVK, blood contribution).

### Quick install

1. Copy `gamedog-pedigree-engine/` into `wp-content/plugins/`.
2. Activate **GameDog Pedigree Engine** in wp-admin.
3. Use shortcodes:

```
[pedigree_tree id="123"]
[siblings_tabs id="123"]
[pedigree_statistics id="123"]
[pedigree_diversity_card id="123"]
[pedigree_analytics_suite id="123"]
```

COI is stored on each dog as post meta `_dog_coi` (e.g. `3.13%`).
