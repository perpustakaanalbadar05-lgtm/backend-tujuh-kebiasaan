<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('school:create-admin {school=mustofa} {username=admin_ziyad} {email=mohziyad@gmail.com} {password=rahasia321}', function ($school, $username, $email, $password) {
    $schoolModel = \App\Models\School::where('name', 'like', "%{$school}%")
        ->orWhere('email', $school)
        ->orWhere('id', $school)
        ->first();

    if (!$schoolModel) {
        $this->error("Sekolah '{$school}' tidak ditemukan!");
        return 1;
    }

    $user = \App\Models\User::updateOrCreate(
        ['email' => $email],
        [
            'name' => 'Admin ' . $schoolModel->name,
            'username' => $username,
            'password' => \Illuminate\Support\Facades\Hash::make($password),
            'role' => 'admin',
            'school_id' => $schoolModel->id,
        ]
    );

    $this->info("BERHASIL: Akun Admin Sekolah untuk '{$schoolModel->name}' berhasil dibuat/diperbarui!");
    $this->line("Nama     : {$user->name}");
    $this->line("Username : {$user->username}");
    $this->line("Email    : {$user->email}");
    $this->line("Password : {$password}");
    return 0;
})->purpose('Buat atau perbarui akun Admin Sekolah secara instan');
