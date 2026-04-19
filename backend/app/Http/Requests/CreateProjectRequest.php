<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTOs\ProjectInput;
use Illuminate\Foundation\Http\FormRequest;

class CreateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        $hasCoords = $this->filled('lat') || $this->filled('lng');
        $addressRule = $hasCoords ? ['nullable', 'string', 'max:200'] : ['required', 'string', 'min:2', 'max:200'];

        return [
            'street'  => $addressRule,
            'city'    => $hasCoords ? ['nullable', 'string', 'max:120'] : ['required', 'string', 'min:2', 'max:120'],
            'country' => $hasCoords ? ['nullable', 'string', 'max:80']  : ['required', 'string', 'min:2', 'max:80'],
            'lat'     => ['nullable', 'required_with:lng', 'numeric', 'between:-90,90'],
            'lng'     => ['nullable', 'required_with:lat', 'numeric', 'between:-180,180'],
        ];
    }

    public function toDto(): ProjectInput
    {
        $lat = $this->filled('lat') ? (float) $this->input('lat') : null;
        $lng = $this->filled('lng') ? (float) $this->input('lng') : null;

        return new ProjectInput(
            street: trim((string) $this->string('street')),
            city: trim((string) $this->string('city')),
            country: trim((string) $this->string('country')),
            lat: $lat,
            lng: $lng,
        );
    }
}
