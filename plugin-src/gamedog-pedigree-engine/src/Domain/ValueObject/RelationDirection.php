<?php

declare(strict_types=1);

namespace GDPE\Domain\ValueObject;

enum RelationDirection: string
{
    case ParentToChild = 'parent_to_child';

    case ChildToParent = 'child_to_parent';

    public function isParentToChild(): bool
    {
        return $this === self::ParentToChild;
    }

    public function isChildToParent(): bool
    {
        return $this === self::ChildToParent;
    }
}