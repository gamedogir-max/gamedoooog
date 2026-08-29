<?php

declare(strict_types=1);

namespace GDPE\Presentation\Elementor;

use Elementor\Controls_Manager;
use Elementor\Plugin;
use Elementor\Widget_Base;
use GDPE\Domain\Entity\Dog;
use GDPE\Domain\Exception\DogNotFoundException;
use GDPE\Domain\ValueObject\DogId;
use GDPE\Presentation\Renderer\SiblingListRenderer;
use InvalidArgumentException;
use Throwable;

/**
 * Shared base for the three related-dogs widgets (Full Siblings, Same
 * Sire, Same Dam).
 *
 * All three share identical controls (a single dynamic-capable Dog ID),
 * identical error handling, and identical rendering via the
 * {@see SiblingListRenderer}; only the use case they invoke and their
 * labels differ.
 */
abstract class AbstractSiblingWidget extends Widget_Base
{
    /**
     * @return string[]
     */
    public function get_categories(): array
    {
        return [PedigreeCategoryRegistrar::CATEGORY_SLUG];
    }

    /**
     * @return string[]
     */
    public function get_style_depends(): array
    {
        return [PedigreeAssetRegistrar::STYLE_HANDLE];
    }

    public function get_icon(): string
    {
        return 'eicon-post-list';
    }

    protected function register_controls(): void
    {
        $this->start_controls_section(
            'gdpe_content_section',
            [
                'label' => esc_html__('Relation', 'gdpe'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'dog_id',
            [
                'label' => esc_html__('Dog ID', 'gdpe'),
                'type' => Controls_Manager::NUMBER,
                'min' => 1,
                'dynamic' => ['active' => true],
                'description' => esc_html__(
                    'The post ID of the reference dog. Leave empty to auto-detect current dog.',
                    'gdpe'
                ),
            ]
        );

        $this->add_control(
            'heading',
            [
                'label' => esc_html__('Heading', 'gdpe'),
                'type' => Controls_Manager::TEXT,
                'default' => $this->defaultHeading(),
                'dynamic' => ['active' => true],
            ]
        );

        $this->end_controls_section();
    }

    protected function render(): void
    {
        $renderer = $this->renderer();

        if ($renderer === null) {
            return;
        }

        $settings = $this->get_settings_for_display();

        $rawDogId = (int) ($settings['dog_id'] ?? 0);

        if ($rawDogId <= 0) {
            $rawDogId = (int) get_the_ID();
        }

        if ($rawDogId <= 0) {
            $rawDogId = get_queried_object_id();
        }

        if ($rawDogId <= 0 && Plugin::$instance->documents->get_current()) {
            $rawDogId = (int) Plugin::$instance->documents->get_current()->get_main_id();
        }

        $heading = (string) ($settings['heading'] ?? $this->defaultHeading());

        if ($rawDogId <= 0) {
            echo $renderer->renderNotice(
                esc_html__('Please select a dog to display related dogs.', 'gdpe')
            );

            return;
        }

        try {
            $dogId = new DogId($rawDogId);
        } catch (InvalidArgumentException) {
            echo $renderer->renderNotice(
                esc_html__('This widget is not configured correctly.', 'gdpe')
            );

            return;
        }

        try {
            $dogs = $this->findRelatedDogs($dogId);
        } catch (DogNotFoundException) {
            echo $renderer->renderNotice(
                esc_html__('The selected dog could not be found.', 'gdpe')
            );

            return;
        } catch (Throwable) {
            echo $renderer->renderNotice(
                esc_html__('The related dogs could not be loaded.', 'gdpe')
            );

            return;
        }

        echo $renderer->render($dogs, $heading, $this->emptyMessage(), $rawDogId);
    }

    /**
     * Resolves the related dogs for the given reference dog by invoking
     * the concrete widget's use case.
     *
     * @return array<int, Dog>
     *
     * @throws DogNotFoundException
     */
    abstract protected function findRelatedDogs(DogId $dogId): array;

    /**
     * Returns the shared list renderer, or null if the widget has not
     * been bootstrapped (which should never happen on a booted plugin).
     */
    abstract protected function renderer(): ?SiblingListRenderer;

    /**
     * Default heading shown in the control and used when the editor
     * leaves the heading field empty.
     */
    abstract protected function defaultHeading(): string;

    /**
     * Message rendered when the reference dog has no related dogs.
     */
    abstract protected function emptyMessage(): string;
}
