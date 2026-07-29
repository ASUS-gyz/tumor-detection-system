<?php

namespace App\Http\Controllers\Api\ZZT;

use App\Http\Controllers\Controller;
use App\Support\Result;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class TemplateController extends Controller
{
    private function key(string $t, int $did): string { return "doctor_{$did}_{$t}_templates"; }

    public function storeMedicalRecord(Request $request): JsonResponse
    {
        $request->validate(['name' => 'required|string|max:50', 'symptoms' => 'required|string', 'imaging_findings' => 'nullable|string', 'preliminary_diagnosis' => 'required|string', 'treatment_plan' => 'required|string']);
        $k = $this->key('mr', $request->user()->id); $ts = Cache::get($k, []);
        $t = ['id' => count($ts) + 1, 'name' => $request->input('name'), 'symptoms' => $request->input('symptoms'), 'imaging_findings' => $request->input('imaging_findings'), 'preliminary_diagnosis' => $request->input('preliminary_diagnosis'), 'treatment_plan' => $request->input('treatment_plan'), 'created_at' => now()->toDateTimeString()];
        $ts[] = $t; Cache::forever($k, $ts);
        return Result::success('模板保存成功', $t);
    }

    public function indexMedicalRecord(Request $request): JsonResponse
    { return Result::success('成功', ['list' => Cache::get($this->key('mr', $request->user()->id), []), 'total' => count(Cache::get($this->key('mr', $request->user()->id), []))]); }

    public function storePrescription(Request $request): JsonResponse
    {
        $request->validate(['name' => 'required|string|max:50', 'items' => 'required|array|min:1', 'items.*.drug_id' => 'required|integer', 'items.*.quantity' => 'required|integer|min:1', 'items.*.dosage' => 'required|string', 'items.*.instructions' => 'nullable|string']);
        $k = $this->key('rx', $request->user()->id); $ts = Cache::get($k, []);
        $t = ['id' => count($ts) + 1, 'name' => $request->input('name'), 'items' => $request->input('items'), 'created_at' => now()->toDateTimeString()];
        $ts[] = $t; Cache::forever($k, $ts);
        return Result::success('模板保存成功', $t);
    }

    public function indexPrescription(Request $request): JsonResponse
    { return Result::success('成功', ['list' => Cache::get($this->key('rx', $request->user()->id), []), 'total' => count(Cache::get($this->key('rx', $request->user()->id), []))]); }
}
