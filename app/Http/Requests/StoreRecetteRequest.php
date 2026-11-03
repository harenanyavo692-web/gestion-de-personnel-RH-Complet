<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRecetteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('recettes.gerer');
    }

    public function rules(): array
    {
        return [
            'date_recette' => ['required', 'date', 'before_or_equal:today'],
            'montant' => ['required', 'numeric', 'gt:0', 'max:999999999999'],
            'categorie_id' => ['required', Rule::exists('categories_financieres', 'id')->where('type', 'recette')],
            'origine' => ['required', 'string', 'max:150'],
            'note' => ['nullable', 'string', 'max:1000'],
            'numero_bon' => ['nullable', 'string', 'max:40'],
            'client' => ['nullable', 'string', 'max:150'],
            'espece' => ['nullable', 'string', 'max:80'],
            'poids_kg' => ['nullable', 'numeric', 'gt:0', 'max:99999999'],
            'montant_facture' => ['nullable', 'numeric', 'gte:montant', 'max:999999999999'],
            'destination' => ['nullable', 'string', 'max:150'],
            'accounting_entity_id' => ['nullable', Rule::exists('accounting_entities', 'id')->where('actif', true)],
            'journal_id' => ['nullable', Rule::exists('accounting_journals', 'id')->where('actif', true)],
            'account_id' => ['nullable', Rule::exists('accounting_accounts', 'id')->where('actif', true)],
            'mode_paiement' => ['nullable', Rule::exists('reference_items', 'code')->where('groupe', 'mode_paiement')->where('actif', true)],
            'justificatif' => ['nullable', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png,xlsx,xls,csv,doc,docx'],
        ];
    }
}
