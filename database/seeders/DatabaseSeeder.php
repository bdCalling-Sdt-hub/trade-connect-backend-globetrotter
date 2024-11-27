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
        'full_name'=>"Sam",
        'user_name'=>"sam",
        'email'=>"sam@gmail.com",
        'password'=>Hash::make(12345678),
        'verify_email'=>1,
        'role'=>'ADMIN',
        'status'=>'active'
       ]);
       User::create([
        'id'=>2,
        'full_name'=>"User",
        'user_name'=>"user",
        'email'=>"user@gmail.com",
        'password'=>Hash::make(12345678),
        'verify_email'=>1,
        'role'=>'MEMBER',
        'status'=>'active'
       ]);
       User::create([
        'id'=>3,
        'full_name'=>"User2",
        'user_name'=>"user2",
        'email'=>"user2@gmail.com",
        'password'=>Hash::make(12345678),
        'verify_email'=>1,
        'role'=>'MEMBER',
        'status'=>'active'
       ]);
       User::create([
        'id'=>4,
        'full_name'=>"User3",
        'user_name'=>"user3",
        'email'=>"user3@gmail.com",
        'password'=>Hash::make(12345678),
        'verify_email'=>1,
        'role'=>'MEMBER',
        'status'=>'active'
       ]);
       User::create([
        'id'=>5,
        'full_name'=>"Arif Biswas",
        'user_name'=>"arifbiswas",
        'email'=>"mailto:arifbiswas1020@gmail.com",
        'password'=>Hash::make(12345678),
        'verify_email'=>1,
        'role'=>'MEMBER',
        'status'=>'active'
       ]);

    }
}
