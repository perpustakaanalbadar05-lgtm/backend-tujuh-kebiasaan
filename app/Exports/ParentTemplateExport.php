<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ParentTemplateExport implements FromArray, WithHeadings, WithStyles
{
    public function array(): array
    {
        return [
            ['Bapak Budi', 'budi123', '081234567890', 'budi@example.com', '1001, 1002', 'Ayah'],
        ];
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

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
