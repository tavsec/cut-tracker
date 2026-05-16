<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class HashPassword extends Command
{
    protected $signature = 'app:hash-password';

    protected $description = 'Hash a password for use in APP_PASSWORD_HASH env var';

    public function handle(): void
    {
        $password = $this->secret('Enter password');
        $this->line(Hash::make($password));
    }
}
