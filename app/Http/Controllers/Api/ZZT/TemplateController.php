<?php

namespace App\Http\Controllers\Api\ZZT;

use App\Http\Controllers\Controller;
use App\Support\Result;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class TemplateController extends Controller
{
    private function cacheKey(string $type, int $doctorId): string
    {
        return "doctor_{$doctorId}_{$type}_templates";
    }

    public function storeMedicalRecord(Request $request): JsonResponse
    {
        $request->validate(['name' => ['required', 'string', 'max:50'], 'symptoms' => ['required', 'string'], 'imaging_findings' => ['nullable', 'string'], 'preliminary_diagnosis' => ['required', 'string'], 'treatment_plan' => ['required', 'string']]);
        $key = $this->cacheKey('mr', $request->user()->id);
        $templates = Cache::get($key, []);
        $t = ['id' => count($templates) + 1, 'name' => $request->input('name'), 'symptoms' => $request->input('symptoms'), 'imaging_findings' => $request->input('imaging_findings'), 'preliminary_diagnosis' => $request->input('preliminary_diagnosis'), 'treatment_plan' => $request->input('treatment_plan'), 'created_at' => now()->toDateTimeString()];
        $templates[] = $t;
        Cache::forever($key, $templates);
        return Result::success('模板保存成功', $t);
    }

    public function indexMedicalRecord(Request $request): JsonResponse
    {
        $templates = Cache::get($this->cacheKey('mr', $request->user()->id), []);
        return Result::success('成功', ['list' => $templates, 'total' => count($templates)]);
    }

    public function storePrescription(Request $request): JsonResponse
    {
        $request->validate(['name' => ['required', 'string', 'max:50'], 'items' => ['required', 'array', 'min:1'], 'items.*.drug_id' => ['required', 'integer'], 'items.*.quantity' => ['required', 'integer', 'min:1'], 'items.*.dosage' => ['required', 'string'], 'items.*.instructions' => ['nullable', 'string']]);
        $key = $this->cacheKey('rx', $request->user()->id);
        $templates = Cache::get($key, []);
        $t = ['id' => count($templates) + 1, 'name' => $request->input('name'), 'items' => $request->input('items'), 'created_at' => now()->toDateTimeString()];
        $templates[] = $t;
        Cache::forever($key, $templates);
        return Result::success('模板保存成功', $t);
    }

    public function indexPrescription(Request $request): JsonResponse
    {
        $templates = Cache::get($this->cacheKey('rx', $request->user()->id), []);
        return Result::success('成功', ['list' => $templates, 'total' => count($templates)]);
    }
}
