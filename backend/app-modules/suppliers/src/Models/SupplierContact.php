<?php

declare(strict_types=1);

namespace PHPeste\Suppliers\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Pessoa de contato do fornecedor. Só aparece para quem é da equipe de uma
 * edição em que o fornecedor está.
 *
 * @property string $id
 * @property string $supplier_id
 * @property string $name
 * @property string|null $role
 * @property string|null $email
 * @property string|null $phone
 */
#[Fillable(['supplier_id', 'name', 'role', 'email', 'phone'])]
#[Table(name: 'supplier_contacts')]
class SupplierContact extends Model
{
    use HasUuids;
}
