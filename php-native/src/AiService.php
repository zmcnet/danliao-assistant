<?php
/**
 * 单聊辅助 - AI 服务 (OpenAI 兼容 API)
 * 1:1 复刻 Python: ai_service.py
 *
 * 支持: 学生学情分析、沟通建议、统计数据分析、自由对话
 */

declare(strict_types=1);

namespace DanChat;

final class AiService
{
    private string $apiUrl;
    private string $apiKey;
    private string $model;
    private float $temperature;
    private int $maxTokens;

    public function __construct(string $apiUrl, string $apiKey, string $model, float $temperature = 0.7, int $maxTokens = 2000)
    {
        $this->apiUrl      = $apiUrl;
        $this->apiKey      = $apiKey;
        $this->model       = $model;
        $this->temperature = $temperature;
        $this->maxTokens   = $maxTokens;
    }

    /**
     * 调用 AI API (兼容 OpenAI 格式)
     * @return array{success: bool, content?: string, error?: string}
     */
    public function call(array $messages, ?int $maxTokens = null): array
    {
        $url = rtrim($this->apiUrl, '/');
        if (!str_ends_with($url, '/chat/completions')) {
            if (!str_ends_with($url, '/v1')) {
                $url .= '/v1';
            }
            $url .= '/chat/completions';
        }

        $payload = [
            'model'       => $this->model,
            'messages'    => $messages,
            'temperature' => $this->temperature,
            'max_tokens'  => $maxTokens ?? $this->maxTokens,
            'stream'      => false,
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
                'Accept: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $errno    = curl_errno($ch);
        $error    = curl_error($ch);
        $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            $msg = $errno === CURLE_OPERATION_TIMEDOUT
                ? '请求超时，请检查网络连接'
                : '连接失败: ' . $error;
            return ['success' => false, 'error' => $msg];
        }

        if ($status !== 200) {
            $detail = '';
            $decoded = json_decode((string)$response, true);
            if (is_array($decoded) && isset($decoded['error']['message'])) {
                $detail = $decoded['error']['message'];
            } else {
                $detail = (string)$response;
            }
            return ['success' => false, 'error' => "API错误 ({$status}): {$detail}"];
        }

        $data = json_decode((string)$response, true);
        if (!is_array($data) || empty($data['choices'][0]['message']['content'])) {
            return ['success' => false, 'error' => 'API 返回数据格式异常'];
        }
        return ['success' => true, 'content' => (string)$data['choices'][0]['message']['content']];
    }

    /**
     * 测试连接
     */
    public function testConnection(): array
    {
        return $this->call(
            [['role' => 'user', 'content' => '你好，请回复"连接成功"（仅回复这四个字）']],
            20
        );
    }

    // ============================================================
    // 提示词构建
    // ============================================================

    public static function buildStudentAnalysisPrompt(array $student, ?string $systemPrompt = null): array
    {
        $systemPrompt ??= '你是一位经验丰富的教育辅导老师，擅长分析学生学情并给出针对性建议。';

        $fields = [
            '姓名'             => 'name',
            '初中学校'         => 'middle_school',
            '高中学校'         => 'high_school',
            '中考成绩'         => 'zhongkao_score',
            '科学成绩'         => 'science_score',
            '初中文理倾向'     => 'middle_school_type',
            '选科倾向'         => 'subject_preference',
            '错题类型'         => 'wrong_questions',
            'L1(第1次课作业)'  => 'l1_status',
            '学生反馈'         => 'feedback',
            'L2(第2次课作业)'  => 'l2_status',
            'L3(第3次课作业)'  => 'l3_status',
            'L4(第4次课作业)'  => 'l4_status',
            'L5(第5次课作业)'  => 'l5_status',
            '续班意向'         => 'continuation_intent',
            '续班状态'         => 'continuation_status',
            '年级'             => 'grade',
        ];
        $info = [];
        foreach ($fields as $label => $key) {
            $val = $student[$key] ?? '';
            if ($val !== null && trim((string)$val) !== '') {
                $info[] = "{$label}: {$val}";
            }
        }
        $studentInfo = implode("\n", $info);

        $userPrompt = <<<TXT
请根据以下学生信息进行全面分析，给出你的专业建议：

## 学生信息
{$studentInfo}

## 请分析以下内容
1. **学情概况**: 该学生的整体学习情况如何？
2. **优势与不足**: 有哪些优势和需要改进的地方？
3. **选科建议**: 根据成绩和倾向，适合什么选科方向？
4. **沟通策略**: 如何与该学生/家长进行有效沟通？
5. **续班建议**: 是否有续班潜力？建议采取什么策略？
6. **重点关注**: 有什么需要特别关注的地方？

请用专业、友善的语气输出分析报告。
TXT;

        return [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user',   'content' => $userPrompt],
        ];
    }

    public static function buildChatSuggestionPrompt(array $student, ?string $systemPrompt = null): array
    {
        $systemPrompt ??= '你是一位经验丰富的教育辅导老师，擅长与学生和家长沟通。';

        $fields = [
            '姓名'         => 'name',
            '高中学校'     => 'high_school',
            '中考成绩'     => 'zhongkao_score',
            '科学成绩'     => 'science_score',
            '选科倾向'     => 'subject_preference',
            '学生反馈'     => 'feedback',
            '续班状态'     => 'continuation_status',
        ];
        $info = [];
        foreach ($fields as $label => $key) {
            $val = $student[$key] ?? '';
            if ($val !== null && trim((string)$val) !== '') {
                $info[] = "{$label}: {$val}";
            }
        }
        $studentInfo = implode("\n", $info);

        $userPrompt = <<<TXT
我正在准备与以下学生进行一对一沟通，请帮我准备沟通要点：

## 学生信息
{$studentInfo}

## 请提供
1. **开场白建议**: 如何自然开启对话
2. **沟通要点**: 需要重点沟通的内容
3. **话术建议**: 针对该学生的具体话术建议
4. **注意事项**: 沟通中需要注意什么

请提供简洁实用的沟通建议。
TXT;

        return [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user',   'content' => $userPrompt],
        ];
    }

    public static function buildStatsAnalysisPrompt(array $stats, ?string $systemPrompt = null): array
    {
        $systemPrompt ??= '你是一位经验丰富的教育数据分析师，擅长从数据中发现问题和机会。';
        $statsText = json_encode($stats, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $userPrompt = <<<TXT
请根据以下班级统计数据进行分析：

## 统计数据
{$statsText}

## 请分析
1. **整体概况**: 班级整体情况如何？
2. **数据发现**: 有哪些值得关注的数据趋势或问题？
3. **行动建议**: 建议采取哪些行动来提升续班率和教学质量？

请输出简洁的分析报告。
TXT;

        return [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user',   'content' => $userPrompt],
        ];
    }
}
