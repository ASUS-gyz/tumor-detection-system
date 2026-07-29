<?php

namespace App\Http\Controllers\Api\ZZT;

use App\Enums\ResponseCode;
use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\AIDiagnosis;
use App\Services\AIDiagnosisService;
use App\Support\Result;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AIDiagnosisController extends Controller
{
    public function __construct(protected AIDiagnosisService $aiService) {}

    public function store(Request $request): JsonResponse
    {
        $request->validate(['symptom_description' => ['required', 'string', 'min:2', 'max:2000'], 'appointment_id' => ['nullable', 'integer']]);
        $patientId = $request->user()->id;
        $result = $this->aiService->textDiagnosis($request->input('symptom_description'), $patientId);
        $diagnosis = AIDiagnosis::create([
            'type' => 'text', 'patient_id' => $patientId,
            'appointment_id' => $request->input('appointment_id'),
            'symptom_description' => $request->input('symptom_description'),
            'analysis' => $result['analysis'] ?? '', 'risk_level' => $result['risk_level'] ?? '低风险',
            'risk_warning' => $result['risk_warning'] ?? null, 'advice' => $result['advice'] ?? '',
            'possible_conditions' => $result['possible_conditions'] ?? [],
        ]);
        return Result::success('诊断完成', $this->formatDetail($diagnosis));
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 10), 50);
        $diagnoses = AIDiagnosis::where('patient_id', $request->user()->id)->where('type', 'text')
            ->select(['id', 'type', 'symptom_description', 'risk_level', 'created_at'])
            ->orderBy('created_at', 'desc')->paginate($perPage);
        $list = $diagnoses->getCollection()->map(fn ($d) => [
            'id' => $d->id, 'type' => $d->type,
            'symptom_description' => mb_substr($d->symptom_description, 0, 50),
            'risk_level' => $d->risk_level, 'created_at' => $d->created_at,
        ]);
        return Result::success('成功', ['list' => $list->values(), 'page' => $diagnoses->currentPage(), 'size' => $diagnoses->perPage(), 'total' => $diagnoses->total(), 'total_pages' => $diagnoses->lastPage()]);
    }

    public function show(int $id, Request $request): JsonResponse
    {
        $diagnosis = AIDiagnosis::where('patient_id', $request->user()->id)->where('type', 'text')->find($id);
        if (! $diagnosis) { throw new BusinessException('诊断记录不存在', ResponseCode::DATA_NOT_FOUND); }
        return Result::success('成功', $this->formatDetail($diagnosis));
    }

    public function continue(Request $request): JsonResponse
    {
        $request->validate(['diagnosis_id' => ['required', 'integer', 'exists:ai_diagnoses,id'], 'question' => ['required', 'string', 'min:2', 'max:1000']]);
        $patientId = $request->user()->id;
        $previous = AIDiagnosis::where('patient_id', $patientId)->where('type', 'text')->find($request->input('diagnosis_id'));
        if (! $previous) { throw new BusinessException('诊断记录不存在', ResponseCode::DATA_NOT_FOUND); }
        $context = '原始症状：' . ($previous->symptom_description ?? '') . "\n上次分析：" . ($previous->analysis ?? '') . "\n患者追问：" . $request->input('question');
        $result = $this->aiService->textDiagnosis($context, $patientId);
        $diagnosis = AIDiagnosis::create([
            'type' => 'text', 'patient_id' => $patientId,
            'symptom_description' => $request->input('question'),
            'analysis' => $result['analysis'] ?? '', 'risk_level' => $result['risk_level'] ?? '低风险',
            'risk_warning' => $result['risk_warning'] ?? null, 'advice' => $result['advice'] ?? '',
            'possible_conditions' => $result['possible_conditions'] ?? [],
        ]);
        return Result::success('追问回复', ['previous_id' => $previous->id, 'diagnosis' => $this->formatDetail($diagnosis)]);
    }

    public function exportPdf(int $id, Request $request): JsonResponse
    {
        $diagnosis = AIDiagnosis::where('patient_id', $request->user()->id)->where('type', 'text')->find($id);
        if (! $diagnosis) { throw new BusinessException('诊断记录不存在', ResponseCode::DATA_NOT_FOUND); }
        return Result::success('报告导出数据', ['report' => [
            'title' => '肿瘤科 AI 智能诊断报告',
            'report_no' => 'AI-' . str_pad($diagnosis->id, 6, '0', STR_PAD_LEFT),
            'patient_name' => $request->user()->name, 'created_at' => $diagnosis->created_at,
            'risk_level' => $diagnosis->risk_level, 'analysis' => $diagnosis->analysis,
            'advice' => $diagnosis->advice, 'risk_warning' => $diagnosis->risk_warning,
            'possible_conditions' => $diagnosis->possible_conditions,
            'disclaimer' => '本报告由 AI 生成，仅供参考，不构成医疗建议。',
        ]]);
    }

    private function formatDetail(AIDiagnosis $diagnosis): array
    {
        return [
            'id' => $diagnosis->id, 'type' => $diagnosis->type,
            'symptom_description' => $diagnosis->symptom_description,
            'analysis' => $diagnosis->analysis, 'risk_level' => $diagnosis->risk_level,
            'risk_warning' => $diagnosis->risk_warning, 'advice' => $diagnosis->advice,
            'possible_conditions' => $diagnosis->possible_conditions, 'created_at' => $diagnosis->created_at,
        ];
    }
}
