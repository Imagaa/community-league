<?php

namespace App\Http\Requests;

use App\Actions\Booking\CreateBooking;
use App\Rules\Turnstile;
use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'quantity' => ['required', 'integer', 'min:1', 'max:'.CreateBooking::MAX_QUANTITY],
            'terms' => ['accepted'],
        ];

        if (! $this->user()) {
            $rules['guest_name'] = ['required', 'string', 'max:255'];
            $rules['guest_email'] = ['required', 'email', 'max:255'];
            $rules['guest_phone'] = ['required', 'string', 'max:20', 'regex:/^08[0-9]{8,12}$/'];
            $rules['cf-turnstile-response'] = [new Turnstile];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'guest_phone.regex' => 'Nomor HP harus diawali 08 dan terdiri dari 10-14 angka.',
            'terms.accepted' => 'Anda harus menyetujui Syarat & Ketentuan.',
        ];
    }
}
