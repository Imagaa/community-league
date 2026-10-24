<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\Rules\Turnstile;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'phone' => $this->phoneRules(),
            'password' => $this->passwordRules(),
            'terms' => ['accepted'],
            'cf-turnstile-response' => [new Turnstile],
        ], [
            'phone.regex' => 'Nomor HP harus diawali 08 dan terdiri dari 10-14 angka.',
            'terms.accepted' => 'Anda harus menyetujui Syarat & Ketentuan.',
        ])->validate();

        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'phone' => $input['phone'],
            'password' => $input['password'],
            'terms_version' => User::TERMS_VERSION,
            'terms_accepted_at' => now(),
        ]);
    }
}
