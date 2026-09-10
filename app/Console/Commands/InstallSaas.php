<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Rules\SafePassword;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class InstallSaas extends Command
{
    protected $signature = 'saas:install {--email= : Root administrator email} {--name= : Administrator name} {--password= : Prefer the hidden prompt on production}';

    protected $description = 'Create a new platform super administrator without modifying existing accounts';

    public function handle(): int
    {
        $data = ['email' => $this->option('email') ?: $this->ask('Administrator email'), 'name' => $this->option('name') ?: $this->ask('Administrator name', 'Super Admin'), 'password' => $this->option('password') ?: $this->secret('Password (12+ characters, upper/lowercase and number)')];
        $validator = Validator::make($data, ['email' => 'required|email|max:255|unique:users,email', 'name' => 'required|string|max:120', 'password' => ['required', new SafePassword, Password::min(12)->mixedCase()->numbers()]]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }
        $user = new User($data + ['status' => 'active', 'locale' => 'ar', 'theme' => 'light']);
        $user->is_super_admin = true;
        $user->save();
        $this->info('Super administrator created: '.$user->email);

        return self::SUCCESS;
    }
}
