<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Http\Requests;

class OrganizationLogisticsRequest extends SpeakerLogisticsRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'travel_paid_by_organization' => ['sometimes', 'boolean'],
            'lodging_paid_by_organization' => ['sometimes', 'boolean'],
            'organization_notes' => ['sometimes', 'nullable', 'string', 'max:3000'],
        ];
    }
}
