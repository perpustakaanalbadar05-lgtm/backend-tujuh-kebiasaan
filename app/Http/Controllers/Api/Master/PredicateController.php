<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Models\Predicate;
use Illuminate\Http\Request;
use App\Traits\ApiResponse;

class PredicateController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $schoolId = $request->user()->school_id;
        $predicates = Predicate::where('school_id', $schoolId)->orderBy('min_score')->get();
        return $this->successResponse($predicates, 'Data predikat berhasil diambil');
    }

    public function store(Request $request)
    {
        $schoolId = $request->user()->school_id;

        $request->validate([
            'name' => 'required|string|max:255',
            'min_score' => 'required|integer|min:0|max:100',
            'max_score' => 'required|integer|min:0|max:100|gte:min_score',
        ]);

        $predicate = Predicate::create([
            'school_id' => $schoolId,
            'name' => $request->name,
            'min_score' => $request->min_score,
            'max_score' => $request->max_score,
            'created_by' => $request->user()->id,
        ]);

        return $this->successResponse($predicate, 'Predikat berhasil ditambahkan', 201);
    }

    public function show(Request $request, $id)
    {
        $predicate = Predicate::where('school_id', $request->user()->school_id)->find($id);

        if (!$predicate) {
            return $this->errorResponse('Predikat tidak ditemukan', 404);
        }

        return $this->successResponse($predicate, 'Detail predikat berhasil diambil');
    }

    public function update(Request $request, $id)
    {
        $schoolId = $request->user()->school_id;
        $predicate = Predicate::where('school_id', $schoolId)->find($id);

        if (!$predicate) {
            return $this->errorResponse('Predikat tidak ditemukan', 404);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'min_score' => 'required|integer|min:0|max:100',
            'max_score' => 'required|integer|min:0|max:100|gte:min_score',
        ]);

        $predicate->update([
            'name' => $request->name,
            'min_score' => $request->min_score,
            'max_score' => $request->max_score,
            'updated_by' => $request->user()->id,
        ]);

        return $this->successResponse($predicate, 'Predikat berhasil diperbarui');
    }

    public function destroy(Request $request, $id)
    {
        $predicate = Predicate::where('school_id', $request->user()->school_id)->find($id);

        if (!$predicate) {
            return $this->errorResponse('Predikat tidak ditemukan', 404);
        }

        $predicate->delete();
        return $this->successResponse(null, 'Predikat berhasil dihapus');
    }
}
