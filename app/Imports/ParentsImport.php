<?php

namespace App\Imports;

use App\Models\StudentParent;
use App\Models\User;
use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Illuminate\Support\Facades\Log;

class ParentsImport implements ToCollection, WithHeadingRow, WithValidation
{
    public function collection(Collection $rows)
    {
        $schoolId = Auth::user()->school_id;
        $adminId = Auth::id();

        foreach ($rows as $row) {
            // Kolom Excel: Nama Lengkap, Username, No. Telepon / WhatsApp, Email, NIS Anak (Pisahkan Koma), Relasi (Ayah/Ibu/Wali)
            $name = $row['nama_lengkap'] ?? null;
            $phone = $row['no_telepon_whatsapp'] ?? null;
            $email = $row['email'] ?? null;
            $nisAnak = $row['nis_anak_pisahkan_koma'] ?? $row['nis_anak'] ?? $row['nis'] ?? null;
            $relasi = $row['relasi_ayahibuwali'] ?? $row['relasi'] ?? 'Wali';

            if (!$name) {
                continue; // Skip if name is empty
            }

            // Generate Username Otomatis
            $username = $row['username'] ?? null;
            if (!$username) {
                if ($phone) {
                    // Gunakan nomor telepon jika ada (buang spasi)
                    $username = preg_replace('/\s+/', '', $phone);
                } elseif ($nisAnak) {
                    // Ambil NIS anak pertama
                    $nisList = array_map('trim', explode(',', $nisAnak));
                    if (!empty($nisList[0])) {
                        $username = 'ortu_' . $nisList[0];
                    } else {
                        $username = 'ortu_' . rand(1000, 9999);
                    }
                } else {
                    $username = 'ortu_' . rand(1000, 9999);
                }
            }

            // 1. Cek User berdasarkan username atau phone
            $user = User::where('school_id', $schoolId)
                ->where(function($q) use ($username, $phone) {
                    $q->where('username', $username);
                    if ($phone) {
                        $q->orWhere('username', $phone); // Sometimes phone is used as username
                    }
                })
                ->first();

            if (!$user) {
                // Create new user
                $user = User::create([
                    'name' => $name,
                    'username' => $username,
                    'email' => $email,
                    'password' => Hash::make($phone ?? $username), // Default password
                    'role' => 'orangtua',
                    'school_id' => $schoolId,
                ]);
            } else {
                // Update existing user (nama & email)
                $user->update([
                    'name' => $name,
                    'email' => $email ?? $user->email,
                ]);
            }

            // 2. Cek Parent berdasarkan user_id
            $parent = StudentParent::where('user_id', $user->id)->first();
            
            if (!$parent) {
                $parent = StudentParent::create([
                    'school_id' => $schoolId,
                    'user_id' => $user->id,
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                    'created_by' => $adminId,
                ]);
            } else {
                $parent->update([
                    'name' => $name,
                    'email' => $email ?? $parent->email,
                    'phone' => $phone ?? $parent->phone,
                    'updated_by' => $adminId,
                ]);
            }

            // 3. Mapping ke Anak (Siblings)
            if ($nisAnak) {
                // Bersihkan spasi dan pisahkan dengan koma
                $nisList = array_map('trim', explode(',', $nisAnak));
                
                // Cari ID anak berdasarkan NIS di sekolah ini
                $students = Student::where('school_id', $schoolId)
                    ->whereIn('nis', $nisList)
                    ->get();
                
                $syncData = [];
                foreach ($students as $student) {
                    $syncData[$student->id] = ['relationship' => $relasi];
                }
                
                // Gunakan syncWithoutDetaching agar relasi lama tidak hilang jika tidak ada di Excel
                if (!empty($syncData)) {
                    $parent->students()->syncWithoutDetaching($syncData);
                }
            }
        }
    }

    public function rules(): array
    {
        return [
            'nama_lengkap' => 'required|string',
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'nama_lengkap.required' => 'Kolom Nama Lengkap tidak boleh kosong.',
        ];
    }

    public function customValidationAttributes(): array
    {
        return [
            'nama_lengkap' => 'Nama Lengkap',
            'username' => 'Username',
        ];
    }
}
