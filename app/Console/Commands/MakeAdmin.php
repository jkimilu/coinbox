<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class MakeAdmin extends Command
{
    protected $signature = 'coinbox:make-admin {email} {--name=Administrator} {--password=}';

    protected $description = 'Create or promote a platform administrator';

    public function handle(): int
    {
        $password = $this->option('password') ?: $this->secret('Password');

        $user = User::query()->firstOrNew(['email' => $this->argument('email')]);
        $user->name = $user->name ?: $this->option('name');
        if ($password) {
            $user->password = $password;
        }
        $user->is_admin = true;
        $user->email_verified_at ??= now();
        $user->save();

        $this->info("Admin ready: {$user->email} → /admin");

        return self::SUCCESS;
    }
}
