<?php

namespace App\Http\Controllers\Api\ZZT;

use App\Enums\ResponseCode;
use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\AIDiagnosis;
use App\Http\Services\ZZT\AIDiagnosisService;
use App\Support\Result;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AIDiagnosisController extends Controller
{
    public function __construct(protected AIDiagnosisService $ai) {}

    public function store(Request $request): JsonResponse
    {
        $request->validate(['symptom_description' => 'required|string|min:2|max:2000', 'appointment_id' => 'nullable|integer']);
        $pid = $request->user()->id;
        $r = $this->ai->textDiagnosis($request->input('symptom_description'), $pid);
        $d = AIDiagnosis::create(['type' => 'text', 'patient_id' => $pid, 'appointment_id' => $request->input('appointment_id'), 'symptom_description' => $request->input('symptom_description'), 'analysis' => $r['analysis'] ?? '', 'risk_level' => $r['risk_level'] ?? '低风险', 'risk_warning' => $r['risk_warning'] ?? null, 'advice' => $r['advice'] ?? '', 'possible_conditions' => $r['possible_conditions'] ?? []]);
        return Result::success('诊断完成', $this->fmt($d));
    }

    public function index(Request $request): JsonResponse
    {
        $pp = min((int) $request->input('per_page', 10), 50);
        $p = AIDiagnosis::where('patient_id', $request->user()->id)->where('type', 'text')->select('id', 'type', 'symptom_description', 'risk_level', 'created_at')->orderByDesc('created_at')->paginate($pp);
        $list = $p->getCollection()->map(fn($d) => ['id' => $d->id, 'type' => $d->type, 'symptom_description' => mb_substr($d->symptom_description, 0, 50), 'risk_level' => $d->risk_level, 'created_at' => $d->created_at]);
        return Result::success('成功', ['list' => $list->values(), 'page' => $p->currentPage(), 'size' => $p->perPage(), 'total' => $p->total(), 'total_pages' => $p->lastPage()]);
    }

    public function show(int $id, Request $request): JsonResponse
    {
        $d = AIDiagnosis::where('patient_id', $request->user()->id)->where('type', 'text')->find($id);
        if (! $d) throw new BusinessException('诊断记录不存在', ResponseCode::DATA_NOT_FOUND);
        return Result::success('成功', $this->fmt($d));
    }

    public function continue(Request $request): JsonResponse
    {
        $request->validate(['diagnosis_id' => 'required|integer|exists:ai_diagnoses,id', 'question' => 'required|string|min:2|max:1000']);
        $pid = $request->user()->id;
        $prev = AIDiagnosis::where('patient_id', $pid)->where('type', 'text')->find($request->input('diagnosis_id'));
        if (! $prev) throw new BusinessException('诊断记录不存在', ResponseCode::DATA_NOT_FOUND);
        $ctx = '原始症状：' . ($prev->symptom_description ?? '') . "\n上次分析：" . ($prev->analysis ?? '') . "\n患者追问：" . $request->input('question');
        $r = $this->ai->textDiagnosis($ctx, $pid);
        $d = AIDiagnosis::create(['type' => 'text', 'patient_id' => $pid, 'symptom_description' => $request->input('question'), 'analysis' => $r['analysis'] ?? '', 'risk_level' => $r['risk_level'] ?? '低风险', 'risk_warning' => $r['risk_warning'] ?? null, 'advice' => $r['advice'] ?? '', 'possible_conditions' => $r['possible_conditions'] ?? []]);
        return Result::success('追问回复', ['previous_id' => $prev->id, 'diagnosis' => $this->fmt($d)]);
    }

    public function exportPdf(int $id, Request $request): JsonResponse
    {
        $d = AIDiagnosis::where('patient_id', $request->user()->id)->where('type', 'text')->find($id);
        if (! $d) throw new BusinessException('诊断记录不存在', ResponseCode::DATA_NOT_FOUND);
        return Result::success('报告导出数据', ['report' => ['title' => '肿瘤科 AI 智能诊断报告', 'report_no' => 'AI-' . str_pad($d->id, 6, '0', STR_PAD_LEFT), 'patient_name' => $request->user()->name, 'created_at' => $d->created_at, 'risk_level' => $d->risk_level, 'analysis' => $d->analysis, 'advice' => $d->advice, 'risk_warning' => $d->risk_warning, 'possible_conditions' => $d->possible_conditions, 'disclaimer' => '本报告由 AI 生成，仅供参考']]);
    }

    private function fmt(AIDiagnosis $d): array { return ['id' => $d->id, 'type' => $d->type, 'symptom_description' => $d->symptom_description, 'analysis' => $d->analysis, 'risk_level' => $d->risk_level, 'risk_warning' => $d->risk_warning, 'advice' => $d->advice, 'possible_conditions' => $d->possible_conditions, 'created_at' => $d->created_at]; }
}
