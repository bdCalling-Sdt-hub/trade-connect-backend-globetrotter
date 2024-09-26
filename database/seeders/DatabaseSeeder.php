<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {

       User::create([
        'id'=>1,
        'full_name'=>"Admin",
        'email'=>"admin@gmail.com",
        'password'=>Hash::make(12345678),
        'verify_email'=>1,
        'role'=>'ADMIN',
        'status'=>'active'
       ]);
    }
}
