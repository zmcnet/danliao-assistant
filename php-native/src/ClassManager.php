<?php
/**
 * 单聊辅助 - 班级管理
 * 1:1 复刻 Python: database.py -> get_all_classes / get_class / add_class / update_class / delete_class / get_students_by_class
 */

declare(strict_types=1);

namespace DanChat;

final class ClassManager
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getAllClasses(): array
    {
        $sql = <<<SQL
            SELECT c.*, COUNT(s.id) AS student_count
            FROM classes c
            LEFT JOIN students s ON s.class_id = c.id
            GROUP BY c.id
            ORDER BY c.grade, c.id
        SQL;
        $rows = $this->db->fetchAll($sql);
        return array_map([$this, 'normalizeClass'], $rows);
    }

    public function getClass(int $classId): ?array
    {
        $sql = <<<SQL
            SELECT c.*, COUNT(s.id) AS student_count
            FROM classes c
            LEFT JOIN students s ON s.class_id = c.id
            WHERE c.id = ?
            GROUP BY c.id
        SQL;
        $row = $this->db->fetchOne($sql, [$classId]);
        return $row ? $this->normalizeClass($row) : null;
    }

    public function addClass(
        string $name,
        string $classNumber = '',
        string $classType = 'A1',
        int $lessonCount = 5,
        string $grade = ''
    ): int {
        if ($name === '') {
            throw new \InvalidArgumentException('班级名称不能为空');
        }
        if ($grade === '') {
            $grade = $classNumber !== '' ? $classNumber : $name;
        }
        if (!in_array($classType, ['A1', 'A2', 'B1', 'B2', 'S'], true)) {
            $classType = 'A1';
        }
        $lessonCount = max(1, min(20, $lessonCount));
        return $this->db->insert('classes', [
            'name'         => $name,
            'class_number' => $classNumber,
            'class_type'   => $classType,
            'lesson_count' => $lessonCount,
            'grade'        => $grade,
        ]);
    }

    public function updateClass(int $classId, array $data): void
    {
        // 限制可更新字段
        $allowed = ['name', 'class_number', 'class_type', 'lesson_count', 'grade'];
        $update = [];
        foreach ($allowed as $k) {
            if (array_key_exists($k, $data)) {
                $update[$k] = $data[$k];
            }
        }
        if (empty($update)) return;
        $this->db->update('classes', $update, 'id = ?', [$classId]);
    }

    public function deleteClass(int $classId): void
    {
        $this->db->begin();
        try {
            // 把学生 class_id 置 0, grade 清空 (与Python一致)
            $this->db->update(
                'students',
                ['class_id' => 0, 'grade' => ''],
                'class_id = ?',
                [$classId]
            );
            $this->db->delete('classes', 'id = ?', [$classId]);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * 班级迁移: 从学生 grade 字段中提取所有不重复的 grade 创建班级
     */
    public function migrateFromStudentGrades(): void
    {
        $rows = $this->db->fetchAll(
            'SELECT DISTINCT grade FROM students WHERE grade <> ""'
        );
        foreach ($rows as $r) {
            $g = $r['grade'];
            if ($g === '' || $g === null) continue;
            $exists = $this->db->fetchValue('SELECT id FROM classes WHERE grade = ?', [$g]);
            if ($exists !== null) continue;
            $cid = $this->addClass($g . '班', $g, 'A1', 5, $g);
            $this->db->update('students', ['class_id' => $cid], 'grade = ?', [$g]);
        }
    }

    private function normalizeClass(array $row): array
    {
        $row['id'] = (int)$row['id'];
        $row['lesson_count'] = (int)($row['lesson_count'] ?? 5);
        $row['student_count'] = (int)($row['student_count'] ?? 0);
        return $row;
    }
}
