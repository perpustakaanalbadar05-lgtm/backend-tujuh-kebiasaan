<?php

namespace App\Exports;

use App\Models\Recap;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ReportExport implements FromCollection, WithHeadings, WithMapping
{
    protected $schoolId;
    protected $academicYearId;
    protected $semesterId;

    public function __construct($schoolId, $academicYearId, $semesterId)
    {
        $this->schoolId = $schoolId;
        $this->academicYearId = $academicYearId;
        $this->semesterId = $semesterId;
    }

    public function collection()
    {
        $query = Recap::with(['student.schoolClass', 'predicate'])
            ->where('school_id', $this->schoolId);
            
        if ($this->academicYearId) {
            $query->where('academic_year_id', $this->academicYearId);
        }
        if ($this->semesterId) {
            $query->where('semester_id', $this->semesterId);
        }

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'NIS',
            'Nama Siswa',
            'Kelas',
            'Persentase Capaian (%)',
            'Predikat'
        ];
    }

    public function map($recap): array
    {
        return [
            $recap->student ? $recap->student->nis : '-',
            $recap->student ? $recap->student->name : '-',
            ($recap->student && $recap->student->schoolClass) ? $recap->student->schoolClass->name : '-',
            $recap->percentage,
            $recap->predicate ? $recap->predicate->title : '-',
        ];
    }
}
