<?php

declare(strict_types=1);

namespace PHPeste\Suppliers\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use PHPeste\Community\Enums\EditionPermission;
use PHPeste\Suppliers\Models\EditionSupplier;

trait Concerns
{
    private function authorizeSuppliers(): void
    {
        Gate::authorize(EditionPermission::ManageSuppliers->value);
    }

    private function actor(Request $request): ?string
    {
        $id = $request->user()?->getAuthIdentifier();

        return is_string($id) ? $id : null;
    }

    private function link(Request $request): EditionSupplier
    {
        return $this->find($request, 'link', EditionSupplier::class);
    }

    /**
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $model
     * @return TModel
     */
    private function find(Request $request, string $parameter, string $model): Model
    {
        $id = $request->route($parameter);
        abort_unless(is_string($id), 404);

        return $model::query()->whereKey($id)->firstOrFail();
    }
}
