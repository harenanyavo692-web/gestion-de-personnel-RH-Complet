<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRapprochementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('rapprochement.gerer');
    }

    public function rules(): array
    {
        return [
            'periode' => ['required', 'string', 'max:60'],
            'date_arrete' => ['required', 'date', 'before_or_equal:today'],
            'solde_tresor' => ['required', 'numeric', 'between:-999999999999,999999999999'],
            'ecarts' => ['nullable', 'array', 'max:30'],
            'ecarts.*.libelle' => ['required_with:ecarts.*.montant', 'nullable', 'string', 'max:200'],
            'ecarts.*.montant' => ['required_with:ecarts.*.libelle', 'nullable', 'numeric', 'between:-999999999999,999999999999'],
        ];
    }
}
