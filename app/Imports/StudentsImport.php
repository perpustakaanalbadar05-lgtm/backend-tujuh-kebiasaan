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
            $nis = trim((string)$row['nis']);
            if (empty($nis)) return null;

            // 1. Cek atau Buat User
            $user = User::where('school_id', $this->schoolId)
                ->where('username', $nis)
                ->first();

            if (!$user) {
                // If username is taken globally, suffix with schoolId to guarantee uniqueness
                $username = User::where('username', $nis)->exists() ? "{$nis}_{$this->schoolId}" : $nis;
                $user = User::create([
                    'name' => $row['nama'],
                    'username' => $username,
                    'email' => !empty($row['email']) ? $row['email'] : ($nis . '_' . $this->schoolId . '@student.g7kaih.id'),
                    'password' => Hash::make($nis),
                    'role' => 'siswa',
                    'school_id' => $this->schoolId,
                ]);
            } else {
                $user->update([
                    'name' => $row['nama'],
                    'email' => !empty($row['email']) ? $row['email'] : $user->email,
                ]);
            }

            // 2. Cari atau Auto-Create Kelas
            $className = trim((string)($row['nama_kelas'] ?? ''));
            $schoolClass = null;

            if (!empty($className)) {
                $schoolClass = \App\Models\SchoolClass::where('school_id', $this->schoolId)
                    ->where(function($q) use ($className) {
                        $q->where('name', $className)
                          ->orWhere('name', 'like', '%' . $className . '%');
                    })
                    ->first();

                if (!$schoolClass) {
                    $activeYear = \App\Models\AcademicYear::where('school_id', $this->schoolId)->where('active', true)->first();
                    if (!$activeYear) {
                        $activeYear = \App\Models\AcademicYear::where('school_id', $this->schoolId)->latest()->first();
                    }
                    if (!$activeYear) {
                        \App\Models\School::initializeDefaults($this->schoolId);
                        $activeYear = \App\Models\AcademicYear::where('school_id', $this->schoolId)->where('active', true)->first();
                    }

                    preg_match('/\d+/', $className, $matches);
                    $grade = !empty($matches[0]) ? $matches[0] : '7';

                    $schoolClass = \App\Models\SchoolClass::create([
                        'school_id' => $this->schoolId,
                        'academic_year_id' => $activeYear ? $activeYear->id : null,
                        'name' => $className,
                        'grade' => $grade,
                        'active' => true,
                    ]);
                }
            }

            if (!$schoolClass) {
                throw new \Exception("Baris dengan NIS {$nis} gagal: Nama kelas tidak diisi.");
            }

            // 3. Cek atau Buat Student
            $student = Student::where('school_id', $this->schoolId)
                ->where('nis', $nis)
                ->first();

            $gender = strtoupper(trim((string)($row['jenis_kelamin'] ?? 'L'))) === 'P' ? 'P' : 'L';

            if ($student) {
                $student->update([
                    'user_id' => $user->id,
                    'class_id' => $schoolClass->id,
                    'nisn' => $row['nisn'] ?? $student->nisn,
                    'name' => $row['nama'],
                    'gender' => $gender,
                    'birth_place' => $row['tempat_lahir'] ?? $student->birth_place,
                    'birth_date' => $row['tanggal_lahir'] ?? $student->birth_date,
                    'address' => $row['alamat'] ?? $student->address,
                    'status' => 'active',
                ]);
                return $student;
            }

            return new Student([
                'school_id' => $this->schoolId,
                'user_id' => $user->id,
                'class_id' => $schoolClass->id,
                'nis' => $nis,
                'nisn' => $row['nisn'] ?? null,
                'name' => $row['nama'],
                'gender' => $gender,
                'birth_place' => $row['tempat_lahir'] ?? null,
                'birth_date' => $row['tanggal_lahir'] ?? null,
                'address' => $row['alamat'] ?? null,
                'status' => 'active',
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
