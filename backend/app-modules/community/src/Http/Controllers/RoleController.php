<?php

declare(strict_types=1);

namespace PHPeste\Community\Http\Controllers;

use Illuminate\Http\JsonResponse;
use PHPeste\Community\Enums\EditionPermission;
use PHPeste\Community\Enums\EditionRole;

class RoleController
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'data' => [
                'roles' => array_map(fn (EditionRole $role): array => [
                    'id' => $role->value,
                    'label' => $role->label(),
                    'access' => $role->access(),
                    'permissions' => array_map(fn (EditionPermission $permission): string => $permission->value, $role->permissions()),
                ], EditionRole::cases()),
                'permissions' => array_map(fn (EditionPermission $permission): array => [
                    'id' => $permission->value,
                    'label' => $permission->label(),
                ], EditionPermission::cases()),
            ],
        ]);
    }
}
