<?php

declare(strict_types=1);

namespace PHPeste\Notifications\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use PHPeste\Community\Tenancy\BelongsToEdition;
use PHPeste\Notifications\Enums\DeliveryStatus;
use PHPeste\Notifications\Enums\EmailTemplate;

/**
 * @property string $id
 * @property string $edition_id
 * @property EmailTemplate $template
 * @property string $to_email
 * @property string $to_name
 * @property array<string, string> $variables
 * @property array{subject: string, body: string}|null $content
 * @property string|null $action_url
 * @property DeliveryStatus $status
 * @property int $attempts
 * @property string|null $last_error
 * @property string|null $provider_message_id
 * @property bool $is_test
 * @property Carbon|null $sent_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['edition_id', 'template', 'to_email', 'to_name', 'variables', 'content', 'action_url', 'status', 'attempts', 'last_error', 'provider_message_id', 'is_test', 'sent_at'])]
#[Table(name: 'email_deliveries')]
class EmailDelivery extends Model
{
    use BelongsToEdition;
    use HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'template' => EmailTemplate::class,
            'status' => DeliveryStatus::class,
            'variables' => 'array',
            'content' => 'array',
            'attempts' => 'integer',
            'is_test' => 'boolean',
            'sent_at' => 'datetime',
        ];
    }
}
