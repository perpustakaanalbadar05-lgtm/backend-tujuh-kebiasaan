<?php

namespace App\Exports;

use App\Models\Teacher;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class TeachersExport implements FromCollection, WithHeadings, WithMapping
{
    protected $schoolId;

    public function __construct($schoolId = null)
    {
        $this->schoolId = $schoolId ?? \Illuminate\Support\Facades\Auth::user()?->school_id;
    }

    public function collection()
    {
        return Teacher::where('school_id', $this->schoolId)->get();
    }

    public function headings(): array
    {
        return [
            'NIP',
            'Nama Lengkap',
            'Email',
            'No. HP'
        ];
    }

    public function map($teacher): array
    {
        return [
            $teacher->nip,
            $teacher->name,
            $teacher->email,
            $teacher->phone,
        ];
    }
}
