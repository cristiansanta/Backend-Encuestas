<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;

class CreateSuperAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:create-super-admin';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a super admin user';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = 'superadmin@test.com';
        $password = 'password123';

        // Check if user already exists
        $existingUser = User::where('email', $email)->first();
        if ($existingUser) {
            $this->error('Super admin user already exists!');
            return;
        }

        // Create the user
        $user = User::create([
            'name' => 'Super Administrator',
            'email' => $email,
            'password' => Hash::make($password),
            'email_verified_at' => now(),
        ]);

        // Create Super Admin role if it doesn't exist
        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin']);
        
        // Assign role to user
        $user->assignRole($superAdminRole);

        $this->info('Super admin user created successfully!');
        $this->info("Email: {$email}");
        $this->info("Password: {$password}");
    }
}