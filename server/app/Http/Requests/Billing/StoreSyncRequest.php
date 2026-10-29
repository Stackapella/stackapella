<?php

declare(strict_types=1);

namespace App\Http\Requests\Billing;

use App\Models\StoreSubscription;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * billing.store.sync: what the store gave the app, after a purchase or on restore (pending
 * purchases are never sent). iOS and macOS send Apple's signed transactions; Android sends
 * Google's purchase tokens.
 */
final class StoreSyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'store' => ['required', Rule::in(StoreSubscription::STORES)],
            'transactions' => ['required_if:store,apple', 'prohibited_unless:store,apple', 'array', 'min:1', 'max:50'],
            'transactions.*' => ['string', 'max:20000'],
            'purchases' => ['required_if:store,google', 'prohibited_unless:store,google', 'array', 'min:1', 'max:50'],
            'purchases.*.productId' => ['required', 'string', 'max:120'],
            'purchases.*.purchaseToken' => ['required', 'string', 'max:1000'],
        ];
    }
}
