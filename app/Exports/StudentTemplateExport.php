<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class StudentTemplateExport implements FromArray, WithHeadings
{
    public function array(): array
    {
        return [
            [
                '2026001',
                '0012345678',
                'Budi Santoso',
                'L',
                'Kelas 10-A',
                'budi@siswa.id',
                'Jakarta',
                '2010-05-14',
                'Jl. Sudirman No 10'
            ],
            [
                '2026002',
                '0012345679',
                'Siti Aminah',
                'P',
                'Kelas 10-A',
                'siti@siswa.id',
                'Bandung',
                '2010-08-21',
                'Jl. Merdeka No 2'
            ]
        ];
    }

    public function headings(): array
    {
        return [
            'nis',
            'nisn',
            'nama',
            'jenis_kelamin',
            'nama_kelas',
            'email',
            'tempat_lahir',
            'tanggal_lahir',
            'alamat'
        ];
    }
}
