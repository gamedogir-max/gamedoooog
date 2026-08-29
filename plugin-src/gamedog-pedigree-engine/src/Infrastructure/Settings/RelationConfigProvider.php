<?php

declare(strict_types=1);

namespace GDPE\Infrastructure\Settings;

use GDPE\Domain\Service\RelationConfigProviderInterface;
use GDPE\Domain\ValueObject\RelationConfig;
use GDPE\Domain\ValueObject\RelationDirection;
use GDPE\Infrastructure\Config\JetEngineFieldMap;

/**
 * Provides the configured JetEngine Relation IDs for sire and dam.
 *
 * Uses the actual relation IDs from the production site:
 * - Sire Relation ID: 6 (Dogs → Dogs, One to Many)
 * - Dam Relation ID: 7 (Dogs → Dogs, One to Many)
 */
final class RelationConfigProvider implements RelationConfigProviderInterface
{
    public function sireRelationConfig(): RelationConfig
    {
        return new RelationConfig(
            (string) JetEngineFieldMap::SIRE_RELATION_ID,
            RelationDirection::ParentToChild,
        );
    }

    public function damRelationConfig(): RelationConfig
    {
        return new RelationConfig(
            (string) JetEngineFieldMap::DAM_RELATION_ID,
            RelationDirection::ParentToChild,
        );
    }
}
