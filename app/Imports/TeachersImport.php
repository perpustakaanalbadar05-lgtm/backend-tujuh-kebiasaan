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
            $nip = trim((string)($row['nip'] ?? ''));
            $name = trim((string)($row['nama'] ?? ''));
            if (empty($nip) || empty($name)) {
                return null;
            }

            $email = !empty($row['email']) ? trim((string)$row['email']) : null;
            $phone = !empty($row['telepon']) ? trim((string)$row['telepon']) : null;

            // 1. Cek atau Buat User
            $user = User::where('school_id', $this->schoolId)
                ->where('username', $nip)
                ->first();

            if (!$user) {
                // Pastikan username unik secara global jika bentrok dengan sekolah lain
                $username = User::where('username', $nip)->exists() ? "{$nip}_{$this->schoolId}" : $nip;
                
                // Pastikan email unik jika diisi
                $userEmail = $email;
                if ($userEmail && User::where('email', $userEmail)->exists()) {
                    $userEmail = $nip . '_' . $this->schoolId . '@guru.g7kaih.id';
                } elseif (!$userEmail) {
                    $userEmail = $nip . '_' . $this->schoolId . '@guru.g7kaih.id';
                }

                $user = User::create([
                    'name' => $name,
                    'username' => $username,
                    'email' => $userEmail,
                    'password' => Hash::make($nip), // default password NIP
                    'role' => 'guru',
                    'school_id' => $this->schoolId,
                ]);
            } else {
                $updateData = ['name' => $name];
                if ($email && !User::where('email', $email)->where('id', '!=', $user->id)->exists()) {
                    $updateData['email'] = $email;
                }
                $user->update($updateData);
            }

            // 2. Cek atau Buat Teacher (Kolom yang ada di tabel: school_id, user_id, nip, name, email, phone)
            $teacher = Teacher::where('school_id', $this->schoolId)
                ->where('nip', $nip)
                ->first();

            if ($teacher) {
                $teacher->update([
                    'user_id' => $user->id,
                    'name' => $name,
                    'email' => $email ?? $teacher->email,
                    'phone' => $phone ?? $teacher->phone,
                ]);
                return $teacher;
            }

            return new Teacher([
                'school_id' => $this->schoolId,
                'user_id' => $user->id,
                'nip' => $nip,
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
            ]);
        });
    }

    public function rules(): array
    {
        return [
            'nip' => 'required',
            'nama' => 'required|string',
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'nip.required' => 'Kolom NIP tidak boleh kosong.',
            'nama.required' => 'Kolom Nama tidak boleh kosong.',
        ];
    }

    public function customValidationAttributes(): array
    {
        return [
            'nip' => 'NIP',
            'nama' => 'Nama',
        ];
    }
}

