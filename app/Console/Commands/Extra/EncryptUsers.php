<?php

namespace App\Console\Commands\Extra;

use Illuminate\Console\Command;

class EncryptUsers extends Command
{
    protected $signature = 'encrypt:users';

    public function handle()
    {
        $users = ['kishor', 'kaji', 'dharani', 'dilip', 'narmathan', 'lakshika'];

        $data = [];

        foreach ($users as $key => $user) {
            $data[$key] = [
                'user' => $user,
                'key' => encrypt('AUTO_GENERATED_' . $user)
            ];
        }
        dd($data);
    }
}
