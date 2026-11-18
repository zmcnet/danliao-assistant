<?php
/**
 * 单聊辅助 - 数据导入/导出
 * 1:1 复刻 Python: parse_import_file (openpyxl + csv) / export_students (csv/xlsx)
 *
 * 依赖: phpoffice/phpspreadsheet
 */

declare(strict_types=1);

namespace DanChat;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Csv;

final class Importer
{
    /** 中文字段 -> 数据库字段 (与 Student::FIELD_MAP 保持一致) */
    public const CN_HEADERS = [
        'name'                 => '姓名',
        'middle_school'        => '初中学校',
        'high_school'          => '高中学校',
        'zhongkao_score'       => '中考',
        'science_score'        => '科学',
        'middle_school_type'   => '初中文理',
        'subject_preference'   => '选科倾向',
        'wrong_questions'      => '错题',
        'l1_status'            => 'L1',
        'feedback'             => '反馈',
        'l2_status'            => 'L2',
        'l3_status'            => 'L3',
        'l4_status'            => 'L4',
        'l5_status'            => 'L5',
        'continuation_intent'  => '续班意向',
        'continuation_status'  => '续班状态',
        'grade'                => '年级',
    ];

    /**
     * 解析上传文件为 [列名 => 值] 二维数组
     * @return array{columns: array, rows: array, total: int}
     */
    public static function parseFile(string $filepath): array
    {
        $ext = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'xls', 'csv'], true)) {
            throw new \InvalidArgumentException("不支持的文件格式: {$ext}");
        }
        if (!is_file($filepath)) {
            throw new \InvalidArgumentException('文件不存在');
        }

        $spreadsheet = IOFactory::load($filepath);
        $sheet = $spreadsheet->getActiveSheet();
        $allRows = $sheet->toArray(null, true, true, true); // keys: A, B, C...

        if (empty($allRows)) {
            return ['columns' => [], 'rows' => [], 'total' => 0];
        }

        // 第一行是表头 (A,B,C -> 实际列名)
        $headerRow = array_shift($allRows);
        $columns = array_map(fn($v) => trim((string)$v), array_values($headerRow));

        $records = [];
        foreach ($allRows as $row) {
            // row 同样以 A/B/C 为 key, 按下标取
            $values = array_values($row);
            $rec = [];
            $hasData = false;
            foreach ($columns as $idx => $colName) {
                $val = $values[$idx] ?? '';
                $val = is_string($val) ? trim($val) : $val;
                if ($val !== '' && $val !== null) $hasData = true;
                $rec[$colName] = $val;
            }
            if ($hasData) $records[] = $rec;
        }

        return [
            'columns' => $columns,
            'rows'    => $records,
            'total'   => count($records),
        ];
    }

    /**
     * 仅取前 N 行做预览
     */
    public static function preview(string $filepath, int $limit = 10): array
    {
        $parsed = self::parseFile($filepath);
        $parsed['preview'] = array_slice($parsed['rows'], 0, $limit);
        $parsed['total_rows'] = $parsed['total'];
        unset($parsed['rows']);
        return $parsed;
    }
}

final class Exporter
{
    /**
     * 导出学生数据到文件，返回文件路径
     */
    public static function exportStudents(array $rows, string $grade = '', string $format = 'csv'): string
    {
        $exportDir = rtrim((string)config('app.export_dir'), '/');
        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0755, true);
        }
        $timestamp = date('Ymd_His');
        $filename = $grade !== '' ? "学生数据导出_{$grade}_{$timestamp}" : "学生数据导出_{$timestamp}";

        // 准备数据 (字段 -> 中文表头)
        $headers = Importer::CN_HEADERS;
        $data = [];
        foreach ($rows as $row) {
            $rec = [];
            foreach ($headers as $dbKey => $cnName) {
                $rec[$cnName] = $row[$dbKey] ?? '';
            }
            $data[] = $rec;
        }

        if ($format === 'xlsx') {
            $path = $exportDir . '/' . $filename . '.xlsx';
            self::writeXlsx($data, $headers, $path);
        } else {
            $path = $exportDir . '/' . $filename . '.csv';
            self::writeCsv($data, $headers, $path);
        }

        return $path;
    }

    private static function writeXlsx(array $data, array $headers, string $path): void
    {
        $ss = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle('学生数据');

        // 写表头
        $col = 1;
        foreach ($headers as $cnName) {
            $sheet->setCellValueByColumnAndRow($col++, 1, $cnName);
        }
        // 写数据
        $row = 2;
        foreach ($data as $rec) {
            $col = 1;
            foreach ($headers as $cnName) {
                $sheet->setCellValueByColumnAndRow($col++, $row, (string)($rec[$cnName] ?? ''));
            }
            $row++;
        }
        $writer = new Xlsx($ss);
        $writer->save($path);
    }

    private static function writeCsv(array $data, array $headers, string $path): void
    {
        $fp = fopen($path, 'w');
        // 写入 UTF-8 BOM 以便 Excel 正确识别中文
        fwrite($fp, "\xEF\xBB\xBF");
        // 写表头
        fputcsv($fp, array_values($headers));
        foreach ($data as $rec) {
            $line = [];
            foreach ($headers as $cnName) {
                $line[] = (string)($rec[$cnName] ?? '');
            }
            fputcsv($fp, $line);
        }
        fclose($fp);
    }
}
