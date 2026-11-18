<?php
/**
 * 单聊辅助 - 学生管理
 * 1:1 复刻 Python: database.py -> import_students_from_df / get_all_students / search_students
 *      get_student / update_student / delete_student / get_student_stats / export_students / get_students_by_class
 */

declare(strict_types=1);

namespace DanChat;

final class Student
{
    /** 中文列名 -> 数据库字段 (与Python版一致) */
    public const FIELD_MAP = [
        '姓名'     => 'name',
        '初中学校' => 'middle_school',
        '高中学校' => 'high_school',
        '中考'     => 'zhongkao_score',
        '科学'     => 'science_score',
        '初中文理' => 'middle_school_type',
        '选科倾向' => 'subject_preference',
        '错题'     => 'wrong_questions',
        'L1'       => 'l1_status',
        '反馈'     => 'feedback',
        'L2'       => 'l2_status',
        'L3'       => 'l3_status',
        'L4'       => 'l4_status',
        'L5'       => 'l5_status',
        '续班意向' => 'continuation_intent',
    ];

    public const STUDENT_COLUMNS = [
        'name', 'middle_school', 'high_school',
        'zhongkao_score', 'science_score', 'middle_school_type',
        'subject_preference', 'wrong_questions',
        'l1_status', 'feedback', 'l2_status', 'l3_status',
        'l4_status', 'l5_status', 'continuation_intent',
        'continuation_status', 'grade', 'note', 'class_id',
    ];

    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ============================================================
    // 导入
    // ============================================================

    /**
     * 从二维数据 (rows) 导入学生
     * @param array $rows 每一项为 [col => val]
     * @param string $grade
     * @return int 新增数量
     */
    public function importFromRows(array $rows, string $grade = ''): int
    {
        $count = 0;
        $this->db->begin();
        try {
            foreach ($rows as $row) {
                if (!is_array($row)) continue;

                // 计算列映射
                $actualMap = [];
                foreach ($row as $col => $_) {
                    $colClean = trim((string)$col);
                    if (isset(self::FIELD_MAP[$colClean])) {
                        $actualMap[$col] = self::FIELD_MAP[$colClean];
                    }
                }

                $nameVal = trim((string)($row['姓名'] ?? ''));
                if ($nameVal === '' || $nameVal === 'None') continue;

                // 检查是否已存在 (姓名+年级去重)
                $existingId = $this->db->fetchValue(
                    'SELECT id FROM students WHERE name = ? AND grade = ?',
                    [$nameVal, $grade]
                );

                $data = ['grade' => $grade, 'updated_at' => now()];
                foreach ($actualMap as $srcCol => $dbCol) {
                    $val = $row[$srcCol] ?? null;
                    if ($val !== null && trim((string)$val) !== '' && strtolower((string)$val) !== 'nan') {
                        $data[$dbCol] = trim((string)$val);
                    }
                }

                if ($existingId) {
                    $this->db->update('students', $data, 'id = ?', [$existingId]);
                } else {
                    $data['name'] = $nameVal;
                    $data['continuation_status'] = 'none';
                    $data['created_at'] = now();
                    $this->db->insert('students', $data);
                    $count++;
                }
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
        return $count;
    }

    // ============================================================
    // 查询
    // ============================================================

    public function getAllStudents(string $grade = '', string $sortBy = 'name', string $sortOrder = 'asc'): array
    {
        $order = $this->buildOrderBy($sortBy, $sortOrder);
        $base = 'SELECT s.*, c.name AS class_name, c.class_number, c.class_type, c.lesson_count '
              . 'FROM students s LEFT JOIN classes c ON s.class_id = c.id';

        if ($grade !== '') {
            $rows = $this->db->fetchAll($base . ' WHERE s.grade = ? ' . $order, [$grade]);
        } else {
            $rows = $this->db->fetchAll($base . ' ' . $order);
        }
        return array_map([$this, 'normalize'], $rows);
    }

    public function searchStudents(
        string $keyword = '',
        string $grade = '',
        string $sortBy = 'name',
        string $sortOrder = 'asc'
    ): array {
        $order = $this->buildOrderBy($sortBy, $sortOrder);
        $params = [];
        $where = ' WHERE 1=1 ';

        if ($keyword !== '') {
            $where .= ' AND (s.name LIKE ? OR s.middle_school LIKE ? OR s.high_school LIKE ?) ';
            $like = '%' . $keyword . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        if ($grade !== '') {
            $where .= ' AND s.grade = ? ';
            $params[] = $grade;
        }

        $sql = 'SELECT s.*, c.name AS class_name, c.class_number, c.class_type, c.lesson_count '
             . 'FROM students s LEFT JOIN classes c ON s.class_id = c.id '
             . $where . ' ' . $order;

        $rows = $this->db->fetchAll($sql, $params);
        // 与 Python 一致: 姓名完全匹配优先, 模糊匹配次之, 学校匹配最后
        if ($keyword !== '') {
            usort($rows, function ($a, $b) use ($keyword) {
                $aMatch = $this->matchScore($a['name'], $a['middle_school'] ?? '', $a['high_school'] ?? '', $keyword);
                $bMatch = $this->matchScore($b['name'], $b['middle_school'] ?? '', $b['high_school'] ?? '', $keyword);
                return $aMatch <=> $bMatch;
            });
        }
        return array_map([$this, 'normalize'], $rows);
    }

    private function matchScore(string $name, string $mid, string $high, string $kw): int
    {
        if ($name === $kw) return 0;
        if (str_starts_with($name, $kw)) return 1;
        if (mb_strpos($name, $kw) !== false) return 2;
        if (mb_strpos($mid, $kw) !== false || mb_strpos($high, $kw) !== false) return 3;
        return 4;
    }

    public function getStudentsByClass(
        int $classId,
        string $sortBy = 'name',
        string $sortOrder = 'asc'
    ): array {
        $order = $this->buildOrderBy($sortBy, $sortOrder, 's');
        $sql = 'SELECT s.*, c.name AS class_name, c.class_number, c.class_type, c.lesson_count '
             . 'FROM students s LEFT JOIN classes c ON s.class_id = c.id '
             . 'WHERE s.class_id = ? ' . $order;
        $rows = $this->db->fetchAll($sql, [$classId]);
        return array_map([$this, 'normalize'], $rows);
    }

    public function getStudent(int $studentId): ?array
    {
        $sql = 'SELECT s.*, c.name AS class_name, c.class_number, c.class_type, c.lesson_count '
             . 'FROM students s LEFT JOIN classes c ON s.class_id = c.id '
             . 'WHERE s.id = ?';
        $row = $this->db->fetchOne($sql, [$studentId]);
        return $row ? $this->normalize($row) : null;
    }

    public function updateStudent(int $studentId, array $data): void
    {
        $data['updated_at'] = now();
        $this->db->update('students', $data, 'id = ?', [$studentId]);
    }

    public function deleteStudent(int $studentId): void
    {
        $this->db->delete('students', 'id = ?', [$studentId]);
    }

    // ============================================================
    // 统计
    // ============================================================

    public function getStats(): array
    {
        $stats = [];
        $stats['total'] = (int)$this->db->fetchValue('SELECT COUNT(*) FROM students');

        // 续班状态
        $rows = $this->db->fetchAll(
            'SELECT continuation_status, COUNT(*) AS cnt FROM students GROUP BY continuation_status'
        );
        $stats['continuation'] = ['continued' => 0, 'no_condition' => 0, 'none' => 0];
        foreach ($rows as $r) {
            $stats['continuation'][$r['continuation_status']] = (int)$r['cnt'];
        }

        // 班级分布
        $stats['classes'] = array_map(
            fn(array $r) => [
                'id'            => (int)$r['id'],
                'name'          => $r['name'],
                'class_number'  => $r['class_number'],
                'class_type'    => $r['class_type'],
                'lesson_count'  => (int)$r['lesson_count'],
                'grade'         => $r['grade'],
                'student_count' => (int)$r['student_count'],
            ],
            $this->db->fetchAll(
                'SELECT c.id, c.name, c.class_number, c.class_type, c.lesson_count, c.grade, '
              . 'COUNT(s.id) AS student_count FROM classes c '
              . 'LEFT JOIN students s ON s.class_id = c.id '
              . 'GROUP BY c.id ORDER BY c.grade'
            )
        );

        // 选科倾向
        $prefs = $this->db->fetchAll('SELECT subject_preference FROM students WHERE subject_preference <> ""');
        $stats['subject_preferences'] = array_map(fn($r) => $r['subject_preference'], $prefs);

        // 作业提交率 (动态课次)
        $maxLessons = (int)($this->db->fetchValue('SELECT MAX(lesson_count) FROM classes') ?: 5);
        $maxLessons = max(1, $maxLessons);
        for ($i = 1; $i <= $maxLessons; $i++) {
            $col = "l{$i}_status";
            $submitted = (int)$this->db->fetchValue("SELECT COUNT(*) FROM students WHERE `{$col}` = '1'");
            $total = (int)$this->db->fetchValue("SELECT COUNT(*) FROM students WHERE `{$col}` <> ''");
            $stats["{$col}_submitted"] = $submitted;
            $stats["{$col}_total"] = $total;
        }

        return $stats;
    }

    // ============================================================
    // 私有
    // ============================================================

    private function buildOrderBy(string $sortBy, string $sortOrder, string $alias = 's'): string
    {
        $dir = strtolower($sortOrder) === 'desc' ? 'DESC' : 'ASC';
        return match ($sortBy) {
            'continuation' => "ORDER BY CASE {$alias}.continuation_status "
                            . "WHEN 'none' THEN 0 WHEN 'continued' THEN 1 WHEN 'no_condition' THEN 2 ELSE 0 END {$dir}, "
                            . "{$alias}.name ASC",
            default        => "ORDER BY {$alias}.name {$dir}",
        };
    }

    private function normalize(array $row): array
    {
        $row['id'] = (int)$row['id'];
        if (isset($row['class_id'])) $row['class_id'] = (int)$row['class_id'];
        if (isset($row['lesson_count'])) $row['lesson_count'] = (int)$row['lesson_count'];
        return $row;
    }
}
