<?php

namespace App\Imports;

use App\Models\Teacher;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

class TeachersImport implements ToModel, WithHeadingRow, WithValidation, SkipsEmptyRows
{
    protected $schoolId;

    public function __construct($schoolId)
    {
        $this->schoolId = $schoolId;
    }

    public function model(array $row)
    {
        return DB::transaction(function () use ($row) {
            // 1. Buat User
            $user = User::create([
                'name' => $row['nama'],
                'email' => $row['email'] ?? ($row['nip'] . '@guru.sekolah.id'), // fallback email
                'password' => Hash::make($row['nip']), // default password is NIP
                'role' => 'guru',
                'school_id' => $this->schoolId,
            ]);

            // 2. Buat Teacher
            return new Teacher([
                'user_id' => $user->id,
                'nip' => $row['nip'],
                'name' => $row['nama'],
                'gender' => strtolower($row['jenis_kelamin']) == 'l' ? 'l' : 'p',
                'phone' => $row['telepon'] ?? null,
                'address' => $row['alamat'] ?? null,
                'position' => $row['jabatan'] ?? 'Guru',
                'status' => 'active',
            ]);
        });
    }

    public function rules(): array
    {
        return [
            'nip' => 'required',
            'nama' => 'required|string',
            'jenis_kelamin' => 'required|in:l,p,L,P',
        ];
    }
}
