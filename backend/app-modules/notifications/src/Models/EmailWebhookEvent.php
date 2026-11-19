<?php

declare(strict_types=1);

namespace PHPeste\Notifications\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Global (sem escopo de edição): o webhook não diz a edição, ela é
 * descoberta pelo envio.
 *
 * @property string $id
 * @property string $svix_id
 * @property string $type
 * @property string|null $provider_message_id
 * @property string|null $edition_id
 * @property string $outcome
 */
#[Fillable(['svix_id', 'type', 'provider_message_id', 'edition_id', 'outcome'])]
#[Table(name: 'email_webhook_events')]
class EmailWebhookEvent extends Model
{
    use HasUuids;
}
