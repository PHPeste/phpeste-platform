<?php

declare(strict_types=1);

namespace PHPeste\Community\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use PHPeste\Community\Cloning\EditionCloners;
use PHPeste\Community\Enums\EditionRole;
use PHPeste\Community\Enums\EditionStatus;
use PHPeste\Community\Events\TeamChanged;
use PHPeste\Community\Models\Edition;
use PHPeste\Community\Models\EditionClone;
use PHPeste\Community\Models\EditionMember;
use Throwable;

class CloneEdition implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(
        public readonly string $editionId,
        public readonly string $cloneId,
    ) {
        $this->afterCommit();
    }

    public function handle(EditionCloners $cloners): void
    {
        $clone = EditionClone::query()->where('source_edition_id', $this->editionId)->findOrFail($this->cloneId);

        if ($clone->status !== EditionClone::QUEUED) {
            return;
        }

        try {
            $targetId = DB::transaction(function () use ($clone, $cloners): string {
                $source = Edition::query()->findOrFail($clone->source_edition_id);

                $target = Edition::create([
                    'community_id' => $source->community_id,
                    'name' => $clone->name,
                    'slug' => $clone->slug,
                    'city' => $source->city,
                    'state' => $source->state,
                    'timezone' => $source->timezone,
                    'status' => EditionStatus::Draft,
                ]);

                $available = $cloners->all();

                foreach ($clone->parts as $part) {
                    $available[$part]->clone($source->id, $target->id);
                }

                $organizer = EditionMember::query()->firstOrCreate(
                    ['edition_id' => $target->id, 'user_id' => $clone->requested_by],
                    ['role' => EditionRole::Organizer],
                );

                if ($organizer->role !== EditionRole::Organizer) {
                    $organizer->update(['role' => EditionRole::Organizer]);
                }

                event(new TeamChanged($target->id, TeamChanged::JOINED, $clone->requested_by, 'member', $organizer->id, [
                    'user_id' => $clone->requested_by,
                    'role' => EditionRole::Organizer->value,
                    'cloned_from' => $source->id,
                ]));

                return $target->id;
            });
        } catch (Throwable $throwable) {
            $clone->update(['status' => EditionClone::FAILED, 'error' => mb_substr($throwable->getMessage(), 0, 2_000), 'finished_at' => now()]);

            report($throwable);

            return;
        }

        $clone->update(['status' => EditionClone::DONE, 'target_edition_id' => $targetId, 'finished_at' => now()]);
    }
}
