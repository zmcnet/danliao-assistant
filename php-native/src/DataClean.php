<?php
/**
 * 单聊辅助 - 数据整理
 * 1:1 复刻 Python: data_clean.py
 * 用于将学生原始数据 (反馈/L1-L5/错题) 格式化为标准描述
 *
 * 常用入口: 在设置->数据管理助手 或 import_data 流程之后自动调用
 */

declare(strict_types=1);

namespace DanChat;

final class DataClean
{
    // 反馈标准化映射
    public const FEEDBACK_MAP = [
        // 听懂类
        '能听懂' => '上课接受度尚可，知识点基本能够理解',
        '听得懂' => '课堂知识吸收能力良好',
        '能听懂的' => '上课内容理解程度较好',
        '听懂了' => '课堂知识掌握情况良好',
        '可以听懂的！' => '听课接受度尚可，知识点基本能够跟上',
        '可以' => '课堂表现正常，学习状态稳定',
        '行' => '整体学习状态尚可',
        '好' => '学习态度积极，课堂表现良好',

        // OK/态度类
        'OK' => '上课接受度尚可，知识点基本能够理解',
        '学习态度OK' => '学习态度端正，课堂表现稳定',
        '学习态度可以' => '学习态度较为端正，需要继续保持',
        '还行' => '整体学习状态一般，有提升空间',
        'EMMMM勉强' => '学习状态一般，部分知识点理解存在困难',
        '还算好学' => '学习态度尚可，课堂专注度一般',
        '好学' => '学习态度积极，课堂表现良好',
        '好学？' => '学习态度有待观察，需要进一步关注',
        '认真' => '课堂学习专注度较高，学习态度端正',
        '较好的老师' => '对教师教学认可度较高',
        '好的老师' => '对教师教学认可度较高',
        '谢谢老师' => '礼貌配合，沟通顺畅',
        '开智了' => '学习认知有显著提升',

        // 没回/未联系
        '没回' => '暂未收到学生回复，需进一步跟进沟通',

        // 其他
        '先学一年' => '建议暂缓正式课程，先进行基础学习',
        '偏科' => '学科发展不均衡，需重点关注弱势科目',
        '想选理科' => '倾向于理科方向，后续选科可考虑理科组合',
        '没想好，但是听课OK' => '选科意向不明确，但课堂学习状态良好',
        '美术班，能跟' => '美术班学生，课堂学习进度基本能够跟上',
        '都听得懂，可能物化' => '课堂知识理解良好，选科倾向物化方向',
        '选物化，上课可以听懂' => '选科意向明确（物化），课堂知识吸收能力良好',
        '不错，对理科兴趣更大' => '整体表现良好，对理科方向兴趣浓厚',
        '挺好的，相对理科好' => '整体学习情况较好，理科方面表现突出',
        '课程非常好' => '对课程内容认可度高，学习体验良好',
        '哈哈哈清朝网课' => '课堂氛围轻松活跃，学习态度积极',
        '可以，好学' => '学习积极性较高，课堂表现良好',
        '选理科，听得懂，活泼' => '性格活泼开朗，课堂知识理解良好，选科倾向理科',
        '能听懂不一定选' => '课堂知识理解良好，但选科意向尚不确定',
        '听懂了，可能选理' => '课堂知识掌握情况良好，选科倾向可能为理科',
        '上课没听明白' => '课堂知识理解存在困难，需要课后辅导加强',
        '不知道，上课听懂了' => '课堂知识吸收良好，但对某些细节了解不够深入',
        '老师教的能听得懂' => '对教师教学方法认可度高，课堂知识理解良好',
        '目前还行，跟得上' => '当前学习进度基本能够跟上，状态稳定',
        '讲的挺好的，都能听懂' => '教师授课效果良好，学生课堂知识理解充分',
        '没想好' => '选科意向不明确，需要进一步引导和沟通',
        '孩子听的懂的' => '家长反馈孩子课堂知识理解良好',
        '较为自信，比较简单' => '学习自信心较强，知识点掌握较为扎实',
        '听得懂、马虎' => '课堂知识理解良好，但需注意粗心大意的问题',
    ];

    public const L_STATUS_MAP = [
        '1'         => '1',
        '已联系'    => '1',
        '已联系本人' => '1',
        '找不到'    => '',
    ];

    public const WRONG_Q_MAP = [
        '无'    => '无',
        '印'    => '打印',
        '手'    => '手抄',
        '没'    => '无',
        '打印'  => '打印',
        '手抄'  => '手抄',
        '没选'  => '无',
        '手+印' => '手抄+打印',
        '手，印' => '手抄+打印',
    ];

    public static function formatFeedback(?string $raw): string
    {
        if ($raw === null) return '';
        $raw = trim($raw);
        if ($raw === '' || $raw === 'None') return '';
        // 去除重复前缀
        while (mb_strpos($raw, '学生反馈：学生反馈') !== false) {
            $raw = str_replace('学生反馈：学生反馈', '学生反馈', $raw);
        }
        $clean = str_replace('学生反馈：', '', $raw);
        // 已经是标准描述
        if (preg_match('/^(上课|课堂|学习态度|建议)/u', $raw)) {
            return $raw;
        }
        // 精确匹配清理后的值
        if (isset(self::FEEDBACK_MAP[$clean])) return self::FEEDBACK_MAP[$clean];
        // 精确匹配原值
        if (isset(self::FEEDBACK_MAP[$raw])) return self::FEEDBACK_MAP[$raw];
        // 模糊匹配
        foreach (self::FEEDBACK_MAP as $key => $val) {
            if (mb_strpos($raw, $key) !== false || mb_strpos($key, $raw) !== false) {
                return $val;
            }
        }
        return $clean;
    }

    public static function formatLStatus(?string $raw): string
    {
        if ($raw === null) return '';
        $raw = trim($raw);
        if ($raw === '' || $raw === 'None') return '';
        return self::L_STATUS_MAP[$raw] ?? ($raw === '1' ? '1' : $raw);
    }

    public static function formatWrongQ(?string $raw): string
    {
        if ($raw === null) return '';
        $raw = trim($raw);
        if ($raw === '' || $raw === 'None') return '';
        return self::WRONG_Q_MAP[$raw] ?? $raw;
    }

    /**
     * 对全表进行数据整理，更新差异字段
     * @return int 更新条数
     */
    public static function cleanAll(Student $student): int
    {
        $rows = $student->getAllStudents();
        $updated = 0;
        foreach ($rows as $r) {
            $changes = [];
            $fb = self::formatFeedback((string)($r['feedback'] ?? ''));
            if ($fb !== (string)($r['feedback'] ?? '')) $changes['feedback'] = $fb;
            for ($i = 1; $i <= 5; $i++) {
                $col = "l{$i}_status";
                $old = (string)($r[$col] ?? '');
                $new = self::formatLStatus($old);
                if ($new !== $old) $changes[$col] = $new;
            }
            $wqOld = (string)($r['wrong_questions'] ?? '');
            $wqNew = self::formatWrongQ($wqOld);
            if ($wqNew !== $wqOld) $changes['wrong_questions'] = $wqNew;
            if (!empty($changes)) {
                $student->updateStudent((int)$r['id'], $changes);
                $updated++;
            }
        }
        return $updated;
    }
}
