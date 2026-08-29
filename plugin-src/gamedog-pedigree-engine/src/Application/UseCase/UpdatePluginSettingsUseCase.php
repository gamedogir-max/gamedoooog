<?php

declare(strict_types=1);

namespace GDPE\Application\UseCase;

use GDPE\Application\DTO\PluginSettingsDTO;
use GDPE\Application\Event\PluginSettingsUpdatedEvent;
use GDPE\Application\EventDispatcher\EventDispatcherInterface;
use GDPE\Application\Mapper\PluginSettingsMapper;
use GDPE\Application\Validator\PluginSettingsValidator;
use InvalidArgumentException;

/**
 * Safely updates plugin settings from untrusted primitive input:
 * validates the incoming DTO, maps it to the Domain PluginSettings
 * value object, persists it (via the existing SavePluginSettingsUseCase,
 * which also clears the pedigree cache), and dispatches an event so
 * other parts of the plugin can react to the change.
 */
final class UpdatePluginSettingsUseCase
{
    public function __construct(
        private readonly PluginSettingsValidator $validator,
        private readonly PluginSettingsMapper $mapper,
        private readonly SavePluginSettingsUseCase $savePluginSettingsUseCase,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * Validates, persists, and announces the given settings update.
     *
     * @param PluginSettingsDTO $dto Incoming settings data.
     *
     * @throws InvalidArgumentException If the DTO fails validation.
     */
    public function execute(PluginSettingsDTO $dto): void
    {
        $this->validator->validate($dto);

        $settings = $this->mapper->toDomain($dto);

        $this->savePluginSettingsUseCase->execute($settings);

        $this->eventDispatcher->dispatch(new PluginSettingsUpdatedEvent($settings));
    }
}