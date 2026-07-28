<?php

namespace App\Http\Controllers\Patient;

use App\Enums\ResponseCode;
use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Patient\AIDiagnosis\CreateDiagnosisRequest;
use App\Models\AIDiagnosis;
use App\Services\AIDiagnosisService;
use App\Support\Result;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 患者端 AI 诊断控制器
 *
 * 提供 AI 文字智能诊断的创建、列表查询和详情查看。
 * 所有接口需 patient 角色认证，仅可操作本人数据。
 */
class AIDiagnosisController extends Controller
{
    public function __construct(
        protected AIDiagnosisService $aiService,
    ) {}

    // ───────────────────── 1. AI 文字智能诊断 ─────────────────────

    /**
     * AI 文字智能诊断
     *
     * POST /api/patient/ai-diagnosis
     * 功能：患者输入症状描述，调用 AI 接口进行分析，结果入库返回。
     * 参数：symptom_description（必填，2-2000字），appointment_id（可选）
     */
    public function store(CreateDiagnosisRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $patientId = $request->user()->id;

        // 调用 AI 诊断服务
        $result = $this->aiService->textDiagnosis(
            $validated['symptom_description'],
            $patientId,
        );

        // 保存诊断记录
        $diagnosis = AIDiagnosis::create([
            'type' => 'text',
            'patient_id' => $patientId,
            'appointment_id' => $validated['appointment_id'] ?? null,
            'symptom_description' => $validated['symptom_description'],
            'analysis' => $result['analysis'] ?? '',
            'risk_level' => $result['risk_level'] ?? '低风险',
            'risk_warning' => $result['risk_warning'] ?? null,
            'advice' => $result['advice'] ?? '',
            'possible_conditions' => $result['possible_conditions'] ?? [],
        ]);

        return Result::success('诊断完成', $this->formatDetail($diagnosis));
    }

    // ───────────────────── 2. AI 诊断记录列表 ─────────────────────

    /**
     * AI 诊断记录列表
     *
     * GET /api/patient/ai-diagnosis
     * 功能：分页返回当前患者的 AI 文字诊断记录（含简要信息）。
     * 参数：page, per_page（默认10，最大50）
     */
    public function index(Request $request): JsonResponse
    {
        $patientId = $request->user()->id;
        $perPage = min((int) $request->input('per_page', 10), 50);

        $diagnoses = AIDiagnosis::where('patient_id', $patientId)
            ->where('type', 'text')
            ->select(['id', 'type', 'symptom_description', 'risk_level', 'created_at'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        $list = $diagnoses->getCollection()->map(function ($d) {
            return [
                'id' => $d->id,
                'type' => $d->type,
                'symptom_description' => mb_substr($d->symptom_description, 0, 50),
                'risk_level' => $d->risk_level,
                'created_at' => $d->created_at,
            ];
        });

        return Result::success('成功', [
            'list' => $list->values(),
            'page' => $diagnoses->currentPage(),
            'size' => $diagnoses->perPage(),
            'total' => $diagnoses->total(),
            'total_pages' => $diagnoses->lastPage(),
        ]);
    }

    // ───────────────────── 3. AI 诊断报告详情 ─────────────────────

    /**
     * AI 诊断报告详情
     *
     * GET /api/patient/ai-diagnosis/{id}
     * 功能：返回指定诊断报告的完整信息，仅限本人。
     */
    public function show(int $id, Request $request): JsonResponse
    {
        $patientId = $request->user()->id;

        $diagnosis = AIDiagnosis::where('patient_id', $patientId)
            ->where('type', 'text')
            ->find($id);

        if (! $diagnosis) {
            throw new BusinessException('诊断记录不存在', ResponseCode::DATA_NOT_FOUND);
        }

        return Result::success('成功', $this->formatDetail($diagnosis));
    }

    // ───────────────────── 响应格式化 ─────────────────────

    /**
     * 格式化诊断详情响应
     */
    private function formatDetail(AIDiagnosis $diagnosis): array
    {
        return [
            'id' => $diagnosis->id,
            'type' => $diagnosis->type,
            'symptom_description' => $diagnosis->symptom_description,
            'analysis' => $diagnosis->analysis,
            'risk_level' => $diagnosis->risk_level,
            'risk_warning' => $diagnosis->risk_warning,
            'advice' => $diagnosis->advice,
            'possible_conditions' => $diagnosis->possible_conditions,
            'created_at' => $diagnosis->created_at,
        ];
    }

    // ───────────────────── 4. AI 诊断追问 ─────────────────────

    /**
     * AI 诊断追问（多轮对话）
     *
     * POST /api/patient/ai-diagnosis/continue
     * 功能：在已有诊断基础上追问更多问题。
     * 参数：diagnosis_id（上次诊断ID）, question（追问内容）
     */
    public function continue(Request $request): JsonResponse
    {
        $request->validate([
            'diagnosis_id' => ['required', 'integer', 'exists:ai_diagnoses,id'],
            'question' => ['required', 'string', 'min:2', 'max:1000'],
        ]);

        $patientId = $request->user()->id;

        $previous = AIDiagnosis::where('patient_id', $patientId)
            ->where('type', 'text')
            ->find($request->input('diagnosis_id'));

        if (! $previous) {
            throw new BusinessException('诊断记录不存在', ResponseCode::DATA_NOT_FOUND);
        }

        // 拼接上下文再次调用 AI
        $context = '原始症状：' . ($previous->symptom_description ?? '')
            . "\n上次分析：" . ($previous->analysis ?? '')
            . "\n患者追问：" . $request->input('question');

        $result = $this->aiService->textDiagnosis($context, $patientId);

        $diagnosis = AIDiagnosis::create([
            'type' => 'text',
            'patient_id' => $patientId,
            'symptom_description' => $request->input('question'),
            'analysis' => $result['analysis'] ?? '',
            'risk_level' => $result['risk_level'] ?? '低风险',
            'risk_warning' => $result['risk_warning'] ?? null,
            'advice' => $result['advice'] ?? '',
            'possible_conditions' => $result['possible_conditions'] ?? [],
        ]);

        return Result::success('追问回复', [
            'previous_id' => $previous->id,
            'diagnosis' => $this->formatDetail($diagnosis),
        ]);
    }

    // ───────────────────── 5. 导出 PDF 报告 ─────────────────────

    /**
     * 导出 PDF 诊断报告
     *
     * GET /api/patient/ai-diagnosis/{id}/export
     * 功能：生成诊断报告的 JSON 数据（前端可用此调用 PDF 生成服务）。
     */
    public function exportPdf(int $id, Request $request): JsonResponse
    {
        $patientId = $request->user()->id;

        $diagnosis = AIDiagnosis::where('patient_id', $patientId)
            ->where('type', 'text')
            ->find($id);

        if (! $diagnosis) {
            throw new BusinessException('诊断记录不存在', ResponseCode::DATA_NOT_FOUND);
        }

        return Result::success('报告导出数据', [
            'report' => [
                'title' => '肿瘤科 AI 智能诊断报告',
                'report_no' => 'AI-' . str_pad($diagnosis->id, 6, '0', STR_PAD_LEFT),
                'patient_name' => $request->user()->name,
                'created_at' => $diagnosis->created_at,
                'risk_level' => $diagnosis->risk_level,
                'analysis' => $diagnosis->analysis,
                'advice' => $diagnosis->advice,
                'risk_warning' => $diagnosis->risk_warning,
                'possible_conditions' => $diagnosis->possible_conditions,
                'disclaimer' => '本报告由 AI 生成，仅供参考，不构成医疗建议。请以执业医师诊断为准。',
            ],
        ]);
    }
}
