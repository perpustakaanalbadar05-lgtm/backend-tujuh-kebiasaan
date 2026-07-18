<?php

namespace App\Exports;

use App\Models\StudentParent;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Support\Facades\Auth;

class ParentsExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    public function collection()
    {
        $schoolId = Auth::user()->school_id;
        // Ambil data orang tua beserta relasi siswa dan user (untuk username)
        return StudentParent::with(['user', 'students'])
            ->where('school_id', $schoolId)
            ->get();
    }

    public function headings(): array
    {
        return [
            'Nama Lengkap',
            'Username',
            'No. Telepon / WhatsApp',
            'Email',
            'NIS Anak (Pisahkan Koma)',
            'Relasi (Ayah/Ibu/Wali)'
        ];
    }

    public function map($parent): array
    {
        // Ambil daftar NIS anak, gabungkan dengan koma
        $nisAnak = $parent->students->pluck('nis')->filter()->implode(', ');
        
        // Ambil relasi dari pivot pertama jika ada
        $relasi = 'Wali';
        if ($parent->students->count() > 0) {
            $relasi = $parent->students->first()->pivot->relationship ?? 'Wali';
        }

        return [
            $parent->name,
            $parent->user ? $parent->user->username : '',
            $parent->phone,
            $parent->email,
            $nisAnak,
            $relasi
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
