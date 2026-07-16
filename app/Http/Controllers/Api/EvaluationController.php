<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Evaluation;
use App\Models\EvaluationAnswer;
use Illuminate\Http\Request;
use App\Traits\ApiResponse;

class EvaluationController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $schoolId = $request->user()->school_id;
        $userId = $request->user()->id;

        // Cari evaluasi aktif untuk sekolah ini, jika belum ada, buatkan template otomatis
        $evaluation = Evaluation::firstOrCreate(
            [
                'school_id' => $schoolId,
                'type' => 'guru',
                'title' => 'Evaluasi Program 7 Kebiasaan (Akhir Tahun)'
            ],
            [
                'description' => 'Mohon isi laporan evaluasi ini dengan menceritakan praktik baik, kendala, solusi, dan analisis implementasi program di kelas Anda.'
            ]
        );

        // Cari apakah user ini sudah pernah mengisi
        $answer = EvaluationAnswer::where('evaluation_id', $evaluation->id)
                                  ->where('user_id', $userId)
                                  ->first();

        return $this->successResponse([
            'evaluation' => $evaluation,
            'my_answer' => $answer ? $answer->answers : null
        ], 'Data evaluasi berhasil diambil');
    }

    public function submit(Request $request, $id)
    {
        $request->validate([
            'answers' => 'required|array',
            'answers.praktik_baik' => 'required|string',
            'answers.kendala' => 'required|string',
            'answers.solusi' => 'required|string',
            'answers.analisis' => 'required|string',
        ]);

        $evaluation = Evaluation::where('school_id', $request->user()->school_id)->findOrFail($id);

        $answer = EvaluationAnswer::updateOrCreate(
            [
                'evaluation_id' => $evaluation->id,
                'user_id' => $request->user()->id
            ],
            [
                'answers' => $request->answers
            ]
        );

        return $this->successResponse($answer, 'Terima kasih! Hasil evaluasi Anda telah tersimpan.');
    }
}
