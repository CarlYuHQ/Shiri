<?php

namespace Shiri\Domain\Ideas\Permissions;

use Shiri\Core\Auth\Permissions\Permission;
use Shiri\Core\Auth\Permissions\ProvidesPermissions;

/**
 * The Ideas permission vocabulary — the verbs only.
 *
 * Idea boards and items are PROJECT-scoped (each board belongs to one project; items belong to a
 * board), so every capability is evaluated against the user's role IN that project (projectScoped =
 * true, the default). The standard verbs auto-grant via the central matrix (readonly = view; editor
 * = create/edit/delete; manager+ = all), so no
 * {@see \Shiri\Core\Auth\Permissions\DefaultRolePermissions} change is required.
 */
final class IdeasPermissions implements ProvidesPermissions
{
    public const VIEW = 'ideas.view';

    public const CREATE = 'ideas.create';

    public const EDIT = 'ideas.edit';

    public const DELETE = 'ideas.delete';

    public function domain(): string
    {
        return 'ideas';
    }

    public function permissions(): array
    {
        return [
            new Permission(self::VIEW, 'View idea boards'),
            new Permission(self::CREATE, 'Create idea boards and ideas'),
            new Permission(self::EDIT, 'Edit ideas'),
            new Permission(self::DELETE, 'Delete idea boards and ideas'),
        ];
    }
}
