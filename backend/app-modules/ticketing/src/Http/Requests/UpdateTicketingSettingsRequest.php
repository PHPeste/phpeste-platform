<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTicketingSettingsRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'capacity' => ['sometimes', 'nullable', 'integer', 'between:1,1000000'],
            'reservation_minutes' => ['sometimes', 'integer', 'between:5,1440'],
            'max_tickets_per_buyer' => ['sometimes', 'nullable', 'integer', 'between:1,100'],
            'gateway_fee_basis_points' => ['sometimes', 'integer', 'between:0,2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['reservation_minutes.between' => 'A reserva dura de 5 minutos a 24 horas.'];
    }
}
