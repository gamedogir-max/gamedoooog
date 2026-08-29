<?php

declare(strict_types=1);

namespace GDPE\Presentation\Renderer;

use GDPE\Domain\Model\PedigreeTree;
use GDPE\Presentation\ViewModel\PedigreeTreeViewModelBuilder;

final class PedigreeTreeRenderer
{
    public function __construct(
        private readonly string $templateDirectory,
        private readonly PedigreeTreeViewModelBuilder $viewModelBuilder,
    ) {
    }

    public function render(PedigreeTree $tree): string
    {
        $templatePath = $this->templateDirectory . '/pedigree-tree.php';

        if (!is_readable($templatePath)) {
            return '';
        }

        $pedigree = $this->viewModelBuilder->build($tree);

        ob_start();
        include $templatePath;

        return (string) ob_get_clean();
    }

    public function renderNotice(string $message): string
    {
        return sprintf(
            '<div class="gd-pedigree-wrapper gd-pedigree-wrapper--notice" dir="ltr"><div class="gd-ped-empty">%s</div></div>',
            esc_html($message),
        );
    }
}
