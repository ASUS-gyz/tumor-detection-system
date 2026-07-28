<?php

namespace App\Http\Controllers\Doctor;

use App\Enums\ResponseCode;
use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Support\Result;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * 医生端模板控制器
 *
 * 提供病历模板和处方模板的 CRUD 功能。
 * 使用 Cache 存储模板数据（简化实现，生产环境建议用数据库）。
 * 所有接口需 doctor 角色认证。
 */
class TemplateController extends Controller
{
    private function cacheKey(string $type, int $doctorId): string
    {
        return "doctor_{$doctorId}_{$type}_templates";
    }

    // ───────────────────── 1. 保存病历模板 ─────────────────────

    /**
     * 保存病历模板
     *
     * POST /api/doctor/medical-record-templates
     * 参数：name（模板名称）, symptoms, imaging_findings, preliminary_diagnosis, treatment_plan
     */
    public function storeMedicalRecord(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'symptoms' => ['required', 'string'],
            'imaging_findings' => ['nullable', 'string'],
            'preliminary_diagnosis' => ['required', 'string'],
            'treatment_plan' => ['required', 'string'],
        ]);
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


        $doctorId = $request->user()->id;
        $key = $this->cacheKey('mr', $doctorId);

        $templates = Cache::get($key, []);
        $template = [
            'id' => count($templates) + 1,
            'name' => $request->input('name'),
            'symptoms' => $request->input('symptoms'),
            'imaging_findings' => $request->input('imaging_findings'),
            'preliminary_diagnosis' => $request->input('preliminary_diagnosis'),
            'treatment_plan' => $request->input('treatment_plan'),
            'created_at' => now()->toDateTimeString(),
        ];
        $templates[] = $template;
        Cache::forever($key, $templates);

        return Result::success('模板保存成功', $template);
    }

    // ───────────────────── 2. 病历模板列表 ─────────────────────

    /**
     * 病历模板列表
     *
     * GET /api/doctor/medical-record-templates
     */
    public function indexMedicalRecord(Request $request): JsonResponse
    {
        $doctorId = $request->user()->id;
        $key = $this->cacheKey('mr', $doctorId);
        $templates = Cache::get($key, []);

        return Result::success('成功', [
            'list' => $templates,
            'total' => count($templates),
        ]);
    }

    // ───────────────────── 3. 保存处方模板 ─────────────────────

    /**
     * 保存处方模板
     *
     * POST /api/doctor/prescription-templates
     * 参数：name（模板名称）, items（药品列表）
     */
    public function storePrescription(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.drug_id' => ['required', 'integer'],
            'items.*.drug_id' => ['required', 'integer', 'exists:drugs,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.dosage' => ['required', 'string'],
            'items.*.instructions' => ['nullable', 'string'],
        ]);
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

        $doctorId = $request->user()->id;
        $key = $this->cacheKey('rx', $doctorId);

        $templates = Cache::get($key, []);
        $template = [
            'id' => count($templates) + 1,
            'name' => $request->input('name'),
            'items' => $request->input('items'),
            'created_at' => now()->toDateTimeString(),
        ];
        $templates[] = $template;
        Cache::forever($key, $templates);

        return Result::success('模板保存成功', $template);
    }

    // ───────────────────── 4. 处方模板列表 ─────────────────────

    /**
     * 处方模板列表
     *
     * GET /api/doctor/prescription-templates
     */
    public function indexPrescription(Request $request): JsonResponse
    {
        $doctorId = $request->user()->id;
        $key = $this->cacheKey('rx', $doctorId);
        $templates = Cache::get($key, []);

        return Result::success('成功', [
            'list' => $templates,
            'total' => count($templates),
        ]);
    }
}
