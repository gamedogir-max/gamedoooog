<?php

declare(strict_types=1);

namespace GDPE\Infrastructure\WordPress;

use GDPE\Application\DTO\PluginSettingsDTO;
use GDPE\Application\Mapper\PluginSettingsMapper;
use GDPE\Application\UseCase\GetPluginSettingsUseCase;
use GDPE\Application\UseCase\UpdatePluginSettingsUseCase;
use InvalidArgumentException;

final class AdminSettingsRegistrar
{
    private const PAGE_SLUG = 'gdpe-settings';
    private const NONCE_ACTION = 'gdpe_save_settings';
    private const NONCE_FIELD = 'gdpe_settings_nonce';

    public function __construct(
        private readonly GetPluginSettingsUseCase $getPluginSettingsUseCase,
        private readonly UpdatePluginSettingsUseCase $updatePluginSettingsUseCase,
        private readonly PluginSettingsMapper $pluginSettingsMapper,
    ) {
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'registerMenuPage']);
        add_action('admin_init', [$this, 'handleFormSubmission']);
    }

    public function registerMenuPage(): void
    {
        add_options_page(
            esc_html__('GameDog Pedigree Engine', 'gdpe'),
            esc_html__('GameDog Pedigree', 'gdpe'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'renderPage'],
        );
    }

    public function handleFormSubmission(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (!isset($_POST[self::NONCE_FIELD])) {
            return;
        }

        if (!wp_verify_nonce((string) $_POST[self::NONCE_FIELD], self::NONCE_ACTION)) {
            return;
        }

        $dto = new PluginSettingsDTO(
            filter_input(INPUT_POST, 'default_generation', FILTER_VALIDATE_INT) ?: 4,
            isset($_POST['full_siblings_enabled']),
            isset($_POST['same_sire_enabled']),
            isset($_POST['same_dam_enabled']),
            filter_input(INPUT_POST, 'cache_ttl_seconds', FILTER_VALIDATE_INT) ?: 3600,
        );

        try {
            $this->updatePluginSettingsUseCase->execute($dto);

            add_settings_error(
                'gdpe_settings',
                'gdpe_settings_saved',
                esc_html__('Settings saved.', 'gdpe'),
                'success'
            );
        } catch (InvalidArgumentException $exception) {
            add_settings_error(
                'gdpe_settings',
                'gdpe_settings_invalid',
                esc_html($exception->getMessage()),
                'error'
            );
        }
    }

    public function renderPage(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $dto = $this->pluginSettingsMapper->toDto(
            $this->getPluginSettingsUseCase->execute()
        );

        settings_errors('gdpe_settings');

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('GameDog Pedigree Engine Settings', 'gdpe') . '</h1>';

        echo '<form method="post" action="">';

        wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);

        echo '<table class="form-table"><tbody>';

        echo '<tr><th><label for="default_generation">'
            . esc_html__('Default Generations', 'gdpe')
            . '</label></th><td>';

        echo '<select name="default_generation" id="default_generation">';

        foreach ([4, 5, 6, 8] as $option) {
            printf(
                '<option value="%d"%s>%d</option>',
                $option,
                selected($dto->defaultGeneration, $option, false),
                $option
            );
        }

        echo '</select></td></tr>';

        echo '<tr><th>'
            . esc_html__('Full Siblings', 'gdpe')
            . '</th><td><label><input type="checkbox" name="full_siblings_enabled"'
            . checked($dto->fullSiblingsEnabled, true, false)
            . '> '
            . esc_html__('Enabled', 'gdpe')
            . '</label></td></tr>';

        echo '<tr><th>'
            . esc_html__('Same Sire', 'gdpe')
            . '</th><td><label><input type="checkbox" name="same_sire_enabled"'
            . checked($dto->sameSireEnabled, true, false)
            . '> '
            . esc_html__('Enabled', 'gdpe')
            . '</label></td></tr>';

        echo '<tr><th>'
            . esc_html__('Same Dam', 'gdpe')
            . '</th><td><label><input type="checkbox" name="same_dam_enabled"'
            . checked($dto->sameDamEnabled, true, false)
            . '> '
            . esc_html__('Enabled', 'gdpe')
            . '</label></td></tr>';

        echo '<tr><th><label for="cache_ttl_seconds">'
            . esc_html__('Cache TTL (seconds)', 'gdpe')
            . '</label></th><td>';

        printf(
            '<input type="number" min="0" name="cache_ttl_seconds" id="cache_ttl_seconds" value="%d">',
            $dto->cacheTtlSeconds
        );

        echo '</td></tr>';

        echo '</tbody></table>';

        submit_button(esc_html__('Save Settings', 'gdpe'));

        echo '</form>';
        echo '</div>';
    }
}