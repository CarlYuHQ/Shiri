<?php

namespace Shiri\Domain\Sprints\Permissions;

use Shiri\Core\Auth\Permissions\Permission;
use Shiri\Core\Auth\Permissions\ProvidesPermissions;

/**
 * The Sprints permission vocabulary — the verbs only.
 *
 * Sprints are PROJECT-scoped (a sprint belongs to one project), so every capability is
 * evaluated against the user's role IN that project (projectScoped = true, the default). The
 * standard verbs auto-grant via the central matrix (readonly = view; editor = create/edit/delete;
 * manager+ = all), so no {@see \Shiri\Core\Auth\Permissions\DefaultRolePermissions} change is
 * required.
 */
final class SprintsPermissions implements ProvidesPermissions
{
    public const VIEW = 'sprints.view';

    public const CREATE = 'sprints.create';

    public const EDIT = 'sprints.edit';

    public const DELETE = 'sprints.delete';

    public function domain(): string
    {
        return 'sprints';
    }

    public function permissions(): array
    {
        return [
            new Permission(self::VIEW, 'View sprints'),
            new Permission(self::CREATE, 'Create sprints'),
            new Permission(self::EDIT, 'Edit sprints'),
            new Permission(self::DELETE, 'Delete sprints'),
        ];
    }
}
