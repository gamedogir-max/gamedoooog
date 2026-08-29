<?php

declare(strict_types=1);

namespace GDPE\Domain\ValueObject;

/**
 * Distinguishes which side of a pedigree tree an ancestor belongs to.
 *
 * Used by {@see PedigreeNode} so the widget can render paternal ancestors
 * on one side of the tree and maternal ancestors on the other, matching
 * conventional pedigree chart layout.
 */
enum LineageSide: string
{
    /** Descends from the root dog's father. */
    case PATERNAL = 'paternal';

    /** Descends from the root dog's mother. */
    case MATERNAL = 'maternal';
}