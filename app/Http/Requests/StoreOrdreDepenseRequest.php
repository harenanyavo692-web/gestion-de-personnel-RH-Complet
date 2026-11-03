<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrdreDepenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('depenses.soumettre');
    }

    public function rules(): array
    {
        return [
            'date_depense' => ['required', 'date', 'before_or_equal:today'],
            'motif' => ['required', 'string', 'max:200'],
            'beneficiaire' => ['nullable', 'string', 'max:150'],
            'montant' => ['required', 'numeric', 'gt:0', 'max:999999999999'],
            'categorie_id' => ['required', Rule::exists('categories_financieres', 'id')->where('type', 'depense')],
            'accounting_entity_id' => ['nullable', Rule::exists('accounting_entities', 'id')->where('actif', true)],
            'journal_id' => ['nullable', Rule::exists('accounting_journals', 'id')->where('actif', true)],
            'account_id' => ['nullable', Rule::exists('accounting_accounts', 'id')->where('actif', true)],
            'mode_paiement' => ['nullable', Rule::exists('reference_items', 'code')->where('groupe', 'mode_paiement')->where('actif', true)],
            'justificatif' => ['nullable', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png,xlsx,xls,csv,doc,docx'],
        ];
    }
}
