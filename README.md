# gamedoooog

WordPress monorepo for the **GameDog Pedigree Engine** plugin.

## Plugin

See [`gamedog-pedigree-engine/`](gamedog-pedigree-engine/) for the full Domain-Driven Design plugin, including Wright's Coefficient of Inbreeding (COI) over 5 generations.

### Quick install

1. Copy `gamedog-pedigree-engine/` into `wp-content/plugins/`.
2. Activate **GameDog Pedigree Engine** in wp-admin.
3. Use shortcodes:

```
[pedigree_tree id="123" depth="5"]
[siblings_box id="123"]
```

COI is stored on each dog as post meta `_dog_coi` (e.g. `12.50%`).
