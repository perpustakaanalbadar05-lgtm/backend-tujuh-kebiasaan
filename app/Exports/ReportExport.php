<?php

namespace App\Exports;

use App\Models\Student;
use App\Models\Predicate;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ReportExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    protected $schoolId;
    protected $academicYearId;
    protected $semesterId;
    protected $predicates;

    public function __construct($schoolId = null, $academicYearId = null, $semesterId = null)
    {
        $this->schoolId = $schoolId ?? \Illuminate\Support\Facades\Auth::user()?->school_id ?? \App\Models\School::first()?->id;
        $this->academicYearId = $academicYearId;
        $this->semesterId = $semesterId;
        $this->predicates = Predicate::where('school_id', $this->schoolId)
            ->orderByDesc('min_score')
            ->get();
    }

    public function collection()
    {
        $query = Student::with(['schoolClass', 'journals'])
            ->where('school_id', $this->schoolId);
            
        if ($this->academicYearId) {
            $query->whereHas('schoolClass', function($q) {
                $q->where('academic_year_id', $this->academicYearId);
            });
        }

        return $query->orderBy('name')->get();
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

    public function map($student): array
    {
        $totalJournals = $student->journals ? $student->journals->count() : 0;
        $avgScore = $totalJournals > 0 ? round($student->journals->avg('score')) : 0;

        $predicateName = 'Kurang (D)';
        if ($this->predicates && $this->predicates->isNotEmpty()) {
            foreach ($this->predicates as $pred) {
                if ($avgScore >= $pred->min_score && $avgScore <= $pred->max_score) {
                    $predicateName = $pred->name;
                    break;
                }
            }
        } else {
            if ($avgScore >= 85) {
                $predicateName = 'Sangat Baik (A)';
            } elseif ($avgScore >= 70) {
                $predicateName = 'Baik (B)';
            } elseif ($avgScore >= 50) {
                $predicateName = 'Cukup (C)';
            }
        }

        return [
            $student->nis,
            $student->name,
            $student->schoolClass ? $student->schoolClass->name : '-',
            $avgScore . '%',
            $predicateName,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
