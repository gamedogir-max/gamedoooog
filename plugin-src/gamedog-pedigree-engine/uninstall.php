<?php

/**
 * Uninstall cleanup for GameDog Pedigree Engine.
 *
 * WordPress loads this file (instead of the plugin) when the plugin is
 * deleted from the admin. It must not rely on the plugin being booted,
 * so it uses no autoloading and duplicates the two option keys as
 * literals — they are the plugin's persistence contract, defined in
 * JetEngineFieldMap::OPTION_KEY_SETTINGS and
 * WordPressCacheAdapter::INDEX_OPTION_KEY / TRANSIENT_PREFIX.
 *
 * Dog posts and their meta are content owned by the site (created via
 * JetEngine), not by this plugin, so they are intentionally NOT removed.
 */

declare(strict_types=1);

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Cached pedigree transients: delete each one indexed by the cache
// adapter (transient names are gdpe_ + md5(key), so the index is the
// only reliable way to find them without a LIKE query).
$gdpeCacheIndex = get_option('gdpe_cache_key_index', []);

if (is_array($gdpeCacheIndex)) {
    foreach ($gdpeCacheIndex as $gdpeCacheKey) {
        if (is_string($gdpeCacheKey)) {
            delete_transient('gdpe_' . md5($gdpeCacheKey));
        }
    }
}

delete_option('gdpe_cache_key_index');
delete_option('gdpe_plugin_settings');
