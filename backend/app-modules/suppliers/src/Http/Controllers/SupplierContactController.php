<?php

declare(strict_types=1);

namespace PHPeste\Suppliers\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PHPeste\Suppliers\Http\Requests\SaveContactRequest;
use PHPeste\Suppliers\Models\SupplierContact;

/**
 * Contatos são do cadastro da comunidade: valem para todas as edições em
 * que o fornecedor está.
 */
class SupplierContactController
{
    use Concerns;

    public function store(SaveContactRequest $request): JsonResponse
    {
        $this->authorizeSuppliers();

        $contact = $this->link($request)->supplier->contacts()->create($request->contact());

        return response()->json(['data' => $contact->only(['id', 'name', 'role', 'email', 'phone'])], 201);
    }

    public function update(SaveContactRequest $request): JsonResponse
    {
        $this->authorizeSuppliers();

        $contact = $this->contact($request);
        $contact->update($request->contact());

        return response()->json(['data' => $contact->only(['id', 'name', 'role', 'email', 'phone'])]);
    }

    public function destroy(Request $request): Response
    {
        $this->authorizeSuppliers();

        $this->contact($request)->delete();

        return response()->noContent();
    }

    private function contact(Request $request): SupplierContact
    {
        $id = $request->route('contact');
        abort_unless(is_string($id), 404);

        return SupplierContact::query()->where('supplier_id', $this->link($request)->supplier_id)->whereKey($id)->firstOrFail();
    }
}
