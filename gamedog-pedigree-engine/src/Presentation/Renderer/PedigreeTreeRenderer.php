<?php
/**
 * Renders pedigree tree templates with a ViewModel.
 *
 * @package GameDog\PedigreeEngine\Presentation\Renderer
 */

declare(strict_types=1);

namespace GameDog\PedigreeEngine\Presentation\Renderer;

use GameDog\PedigreeEngine\Application\DTO\PedigreeTreeDto;
use GameDog\PedigreeEngine\Presentation\ViewModel\PedigreeTreeViewModelBuilder;

final class PedigreeTreeRenderer
{
    /** @var PedigreeTreeViewModelBuilder */
    private $viewModelBuilder;

    /** @var string */
    private $templateDir;

    public function __construct(
        PedigreeTreeViewModelBuilder $viewModelBuilder,
        ?string $templateDir = null
    ) {
        $this->viewModelBuilder = $viewModelBuilder;
        $this->templateDir      = $templateDir !== null
            ? rtrim($templateDir, '/\\')
            : GD_PEDIGREE_PATH . 'templates/pedigree';
    }

    /**
     * @param PedigreeTreeDto|array<string, mixed> $source
     */
    public function render($source): string
    {
        $vm = $this->viewModelBuilder->build($source);

        if (function_exists('wp_enqueue_style')) {
            wp_enqueue_style('gamedog-pedigree');
            wp_enqueue_script('gamedog-pedigree');
        }

        return $this->renderTemplate('pedigree-tree.php', [
            'vm'   => $vm,
            'tree' => $vm,
        ]);
    }

    /**
     * Render a single node (used recursively from the tree template).
     *
     * @param array<string, mixed>|null $node
     * @param array<string, mixed>      $vm
     */
    public function renderNode(?array $node, array $vm = []): string
    {
        if ($node === null) {
            return '';
        }

        return $this->renderTemplate('pedigree-node.php', [
            'node' => $node,
            'vm'   => $vm,
        ]);
    }

    /**
     * @param array<string, mixed> $vars
     */
    private function renderTemplate(string $file, array $vars): string
    {
        $path = $this->resolveTemplate($file);
        if ($path === null) {
            return '<!-- GameDog pedigree template missing: ' . htmlspecialchars($file, ENT_QUOTES, 'UTF-8') . ' -->';
        }

        // Expose renderer to templates for recursive node includes.
        $vars['renderer'] = $this;

        extract($vars, EXTR_SKIP);

        ob_start();
        include $path;

        return (string) ob_get_clean();
    }

    private function resolveTemplate(string $file): ?string
    {
        $file = ltrim($file, '/\\');

        // Theme override: theme/gamedog-pedigree/pedigree-tree.php
        if (function_exists('get_stylesheet_directory')) {
            $theme = get_stylesheet_directory() . '/gamedog-pedigree/' . $file;
            if (is_file($theme)) {
                return $theme;
            }
        }

        if (function_exists('get_template_directory')) {
            $parent = get_template_directory() . '/gamedog-pedigree/' . $file;
            if (is_file($parent)) {
                return $parent;
            }
        }

        $plugin = $this->templateDir . DIRECTORY_SEPARATOR . $file;
        if (is_file($plugin)) {
            return $plugin;
        }

        return null;
    }
}
