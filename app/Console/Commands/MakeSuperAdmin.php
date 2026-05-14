<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class MakeSuperAdmin extends Command
{
    protected $signature   = 'admin:make-super-admin
                             {--email= : Email of existing user to promote}
                             {--create : Create a new super admin user}';

    protected $description = 'Promote an existing user to super admin, or create a new one';

    public function handle(): int
    {
        if ($this->option('create')) {
            return $this->createSuperAdmin();
        }

        if ($email = $this->option('email')) {
            return $this->promoteUser($email);
        }

        // Interactive mode
        $choice = $this->choice('What would you like to do?', [
            'create'  => 'Create a new super admin',
            'promote' => 'Promote an existing user',
        ]);

        return $choice === 'create' ? $this->createSuperAdmin() : $this->promoteUser();
    }

    private function createSuperAdmin(): int
    {
        $name  = $this->ask('Name');
        $email = $this->ask('Email');
        $pass  = $this->secret('Password');

        $validator = Validator::make(
            ['email' => $email, 'password' => $pass],
            ['email' => 'required|email|unique:users,email', 'password' => 'required|min:8'],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }
            return self::FAILURE;
        }

        $user = User::create([
            'name'           => $name,
            'email'          => $email,
            'password'       => Hash::make($pass),
            'is_super_admin' => true,
            'is_active'      => true,
            'role'           => 'owner',
        ]);

        $this->info("✓ Super admin created: {$user->email}");
        $this->line("  Access panel at: /admin");

        return self::SUCCESS;
    }

    private function promoteUser(?string $email = null): int
    {
        $email ??= $this->ask('Enter the user email to promote');

        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("No user found with email: {$email}");
            return self::FAILURE;
        }

        if ($user->is_super_admin) {
            $this->warn("{$email} is already a super admin.");
            return self::SUCCESS;
        }

        $user->update(['is_super_admin' => true]);
        $this->info("✓ {$user->name} ({$email}) is now a super admin.");
        $this->line("  Access panel at: /admin");

        return self::SUCCESS;
    }
}
