<?php

namespace App\Exports;

use App\Models\Student;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class StudentsExport implements FromCollection, WithHeadings, WithMapping
{
    protected $schoolId;

    public function __construct($schoolId)
    {
        $this->schoolId = $schoolId;
    }

    public function collection()
    {
        return Student::with('schoolClass')
            ->where('school_id', $this->schoolId)
            ->get();
    }

    public function headings(): array
    {
        return [
            'NIS',
            'Nama Lengkap',
            'Jenis Kelamin',
            'Kelas',
            'Status'
        ];
    }

    public function map($student): array
    {
        return [
            $student->nis,
            $student->name,
            $student->gender == 'L' ? 'Laki-laki' : 'Perempuan',
            $student->schoolClass ? $student->schoolClass->name : '-',
            $student->status,
        ];
    }
}
