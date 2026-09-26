<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdmin extends Command
{
    protected $signature = 'courier:create-admin {email} {name}';

    protected $description = 'Create the first administrator account (prompts for a password)';

    public function handle(): int
    {
        $password = $this->secret('Password (min 12 characters, letters and numbers)');
        $v = Validator::make(
            ['email' => $this->argument('email'), 'password' => $password],
            ['email' => 'required|email|unique:users,email', 'password' => ['required', Password::min(12)->letters()->numbers()]]
        );
        if ($v->fails()) {
            foreach ($v->errors()->all() as $e) {
                $this->error($e);
            }

            return self::FAILURE;
        }
        $user = User::create([
            'name' => $this->argument('name'), 'email' => strtolower($this->argument('email')),
            'password' => $password, 'role' => 'admin', 'email_verified_at' => now(),
        ]);
        Audit::log('admin.created_via_cli', $user, [], null);
        $this->info("Administrator {$user->email} created.");

        return self::SUCCESS;
    }
}
