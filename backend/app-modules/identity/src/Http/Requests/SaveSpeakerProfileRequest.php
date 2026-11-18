<?php

declare(strict_types=1);

namespace PHPeste\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use PHPeste\Community\Enums\BrazilianState;
use PHPeste\Identity\Models\SpeakerProfile;

class SaveSpeakerProfileRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = [
            'display_name' => ['required', 'string', 'max:120'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'company' => ['nullable', 'string', 'max:120'],
            'bio' => ['nullable', 'string', 'max:400'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', Rule::enum(BrazilianState::class)],
            'links' => ['sometimes', 'array:'.implode(',', SpeakerProfile::LINKS)],
            'accessibility_needs' => ['nullable', 'string', 'max:2000'],
            'dietary_restrictions' => ['nullable', 'string', 'max:2000'],
        ];

        foreach (SpeakerProfile::LINKS as $link) {
            $rules["links.{$link}"] = ['nullable', 'string', 'max:255', 'url:https'];
        }

        return $rules;
    }

    /**
     * @return array{display_name: string, job_title: string|null, company: string|null, bio: string|null, city: string|null, state: string|null, links: array<string, string|null>}
     */
    public function profile(): array
    {
        $links = [];

        foreach (SpeakerProfile::LINKS as $link) {
            $links[$link] = $this->optional("links.{$link}");
        }

        return [
            'display_name' => $this->string('display_name')->trim()->toString(),
            'job_title' => $this->optional('job_title'),
            'company' => $this->optional('company'),
            'bio' => $this->optional('bio'),
            'city' => $this->optional('city'),
            'state' => $this->optional('state'),
            'links' => $links,
        ];
    }

    /**
     * @return array{accessibility_needs: string|null, dietary_restrictions: string|null}
     */
    public function privateDetails(): array
    {
        return [
            'accessibility_needs' => $this->optional('accessibility_needs'),
            'dietary_restrictions' => $this->optional('dietary_restrictions'),
        ];
    }

    private function optional(string $key): ?string
    {
        $value = $this->string($key)->trim()->toString();

        return $value === '' ? null : $value;
    }
}
