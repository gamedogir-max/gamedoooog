<?php
/**
 * Minimal WordPress function stubs so the pedigree engine can run in tests.
 *
 * Defined in the GLOBAL namespace because the plugin calls these functions
 * unqualified from inside its own namespaces.
 *
 * @package GameDog\PedigreeEngine\Tests
 */

declare(strict_types=1);

if (!function_exists('esc_html')) {
    function esc_html($text)
    {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($text)
    {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_url')) {
    function esc_url($url)
    {
        return (string) $url;
    }
}

if (!function_exists('wp_enqueue_script')) {
    function wp_enqueue_script($handle, $src = '', $deps = [], $ver = null, $inFooter = false)
    {
    }
}

if (!function_exists('add_shortcode')) {
    function add_shortcode($tag, $callback)
    {
        $GLOBALS['GD_SHORTCODES'][$tag] = $callback;
    }
}

if (!function_exists('shortcode_atts')) {
    function shortcode_atts($pairs, $atts, $shortcode = '')
    {
        $atts = is_array($atts) ? $atts : [];

        return array_merge($pairs, $atts);
    }
}

if (!function_exists('get_the_ID')) {
    function get_the_ID()
    {
        return isset($GLOBALS['GD_CURRENT_ID']) ? (int) $GLOBALS['GD_CURRENT_ID'] : 0;
    }
}
