<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class TeacherTemplateExport implements FromArray, WithHeadings
{
    public function array(): array
    {
        return [
            [
                '198001012005011001',
                'Ahmad Dahlan, S.Pd',
                'L',
                'ahmad.dahlan@sekolah.id',
                '081234567890',
                'Jl. Pendidikan No 1',
                'Guru Kelas'
            ],
            [
                '198502022010022002',
                'Siti Aminah, M.Pd',
                'P',
                'siti.aminah@sekolah.id',
                '081987654321',
                'Jl. Pahlawan No 2',
                'Guru Agama'
            ]
        ];
    }

    public function headings(): array
    {
        return [
            'nip',
            'nama',
            'jenis_kelamin',
            'email',
            'telepon',
            'alamat',
            'jabatan'
        ];
    }
}
