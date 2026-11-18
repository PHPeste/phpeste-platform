<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use PHPeste\Community\Tenancy\CurrentEdition;

class SaveTrackRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $creating = $this->isMethod('post');
        $trackId = $this->route('track');

        return [
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:120', Rule::unique('cfp_tracks', 'name')
                ->where('edition_id', resolve(CurrentEdition::class)->id())
                ->ignore(is_string($trackId) ? $trackId : null)],
            'slots' => [$creating ? 'required' : 'sometimes', 'integer', 'between:0,500'],
            'position' => ['sometimes', 'integer', 'between:0,1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'Já existe uma trilha com esse nome nesta edição.',
            'slots.between' => 'As vagas na grade vão de 0 a 500.',
        ];
    }
}
