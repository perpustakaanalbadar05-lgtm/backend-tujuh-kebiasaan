<?php

namespace App\Imports;

use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

class StudentsImport implements ToModel, WithHeadingRow, WithValidation, SkipsEmptyRows
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
                'username' => $row['nis'], // Set username dari NIS
                'email' => $row['email'] ?? ($row['nis'] . '@siswa.sekolah.id'), // fallback email
                'password' => Hash::make($row['nis']), // default password is NIS
                'role' => 'siswa',
                'school_id' => $this->schoolId,
            ]);

            // Cari kelas berdasarkan nama (lebih fleksibel dengan LIKE)
            $className = trim($row['nama_kelas'] ?? '');
            $schoolClass = \App\Models\SchoolClass::where('school_id', $this->schoolId)
                ->where('name', 'like', '%' . $className . '%')
                ->first();

            if (!$schoolClass) {
                throw new \Exception("Baris dengan NIS {$row['nis']} gagal: Kelas '{$className}' tidak ditemukan di sistem sekolah ini. Pastikan nama kelas sudah dibuat di menu Kelas.");
            }

            // 2. Buat Student
            return new Student([
                'school_id' => $this->schoolId,
                'user_id' => $user->id,
                'class_id' => $schoolClass->id,
                'nis' => $row['nis'],
                'nisn' => $row['nisn'] ?? null,
                'name' => $row['nama'],
                'gender' => strtolower($row['jenis_kelamin']) == 'l' ? 'l' : 'p',
                'birth_place' => $row['tempat_lahir'] ?? null,
                'birth_date' => $row['tanggal_lahir'] ?? null,
                'address' => $row['alamat'] ?? null,
            ]);
        });
    }

    public function rules(): array
    {
        return [
            'nis' => 'required',
            'nama' => 'required|string',
            'jenis_kelamin' => 'required|in:l,p,L,P',
            'nama_kelas' => 'required|string',
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'nis.required' => 'Kolom NIS tidak boleh kosong.',
            'nama.required' => 'Kolom Nama tidak boleh kosong.',
            'jenis_kelamin.required' => 'Kolom Jenis Kelamin tidak boleh kosong.',
            'jenis_kelamin.in' => 'Format Jenis Kelamin harus L atau P.',
            'nama_kelas.required' => 'Kolom Nama Kelas tidak boleh kosong.',
        ];
    }

    public function customValidationAttributes(): array
    {
        return [
            'nis' => 'NIS',
            'nama' => 'Nama',
            'jenis_kelamin' => 'Jenis Kelamin',
            'nama_kelas' => 'Nama Kelas',
        ];
    }
}
