<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use PHPeste\Community\Tenancy\CurrentEdition;
use PHPeste\Ticketing\Enums\TicketVisibility;

class SaveTicketTypeRequest extends FormRequest
{
    public const array QUESTION_TYPES = ['text', 'select', 'boolean'];

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $creating = $this->isMethod('post');
        $typeId = $this->route('ticketType');

        return [
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:120', Rule::unique('ticket_types', 'name')
                ->where('edition_id', resolve(CurrentEdition::class)->id())
                ->ignore(is_string($typeId) ? $typeId : null)],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'visibility' => ['sometimes', Rule::enum(TicketVisibility::class)],
            'access_code' => ['nullable', 'required_if:visibility,code', 'string', 'alpha_dash:ascii', 'min:4', 'max:40'],
            'min_per_order' => ['sometimes', 'integer', 'between:1,50'],
            'max_per_order' => ['sometimes', 'integer', 'between:1,50', ...($this->has('min_per_order') ? ['gte:min_per_order'] : [])],
            'show_remaining' => ['sometimes', 'boolean'],
            'pass_fee_to_buyer' => ['sometimes', 'boolean'],
            'questions' => ['sometimes', 'nullable', 'array', 'max:10'],
            'questions.*.label' => ['required', 'string', 'max:120'],
            'questions.*.type' => ['required', Rule::in(self::QUESTION_TYPES)],
            'questions.*.required' => ['sometimes', 'boolean'],
            'questions.*.options' => ['required_if:questions.*.type,select', 'array', 'max:30'],
            'questions.*.options.*' => ['string', 'max:60'],
            'position' => ['sometimes', 'integer', 'between:0,1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'access_code.required_if' => 'Informe o código que libera este ingresso.',
            'max_per_order.gte' => 'O máximo por pedido precisa ser maior ou igual ao mínimo.',
            'questions.*.options.required_if' => 'Pergunta de escolha precisa de opções.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function ticketType(): array
    {
        $values = $this->validated();

        if (array_key_exists('access_code', $values)) {
            $values['access_code'] = is_string($values['access_code']) ? Str::upper($values['access_code']) : null;
        }

        if (array_key_exists('questions', $values)) {
            $values['questions'] = $this->questions(is_array($values['questions']) ? $values['questions'] : []);
        }

        return $values;
    }

    /**
     * @param  array<mixed>  $questions
     * @return list<array{id: string, label: string, type: string, required: bool, options: list<string>}>
     */
    private function questions(array $questions): array
    {
        $normalized = [];
        $seen = [];

        foreach ($questions as $question) {
            if (!is_array($question)) {
                continue;
            }

            $label = is_string($question['label'] ?? null) ? $question['label'] : '';
            $type = is_string($question['type'] ?? null) ? $question['type'] : 'text';
            $base = Str::slug($label, '_') ?: 'pergunta';
            $id = $base;
            $suffix = 2;

            while (in_array($id, $seen, true)) {
                $id = "{$base}_{$suffix}";
                $suffix++;
            }

            $seen[] = $id;
            $options = is_array($question['options'] ?? null) ? $question['options'] : [];

            $normalized[] = [
                'id' => $id,
                'label' => $label,
                'type' => $type,
                'required' => (bool) ($question['required'] ?? false),
                'options' => $type === 'select' ? array_values(array_filter($options, is_string(...))) : [],
            ];
        }

        return $normalized;
    }
}
