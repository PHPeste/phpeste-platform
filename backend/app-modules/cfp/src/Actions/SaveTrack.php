<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Actions;

use PHPeste\Cfp\Models\CfpTrack;

class SaveTrack
{
    /**
     * @param  array{name?: string, slots?: int, position?: int}  $attributes
     */
    public function handle(?CfpTrack $track, array $attributes): CfpTrack
    {
        if (!$track instanceof CfpTrack) {
            return CfpTrack::create([
                'position' => (int) CfpTrack::query()->max('position') + 1,
                ...$attributes,
            ]);
        }

        $track->update($attributes);

        return $track;
    }
}
