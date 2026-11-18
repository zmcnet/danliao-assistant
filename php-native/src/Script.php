<?php
/**
 * 单聊辅助 - 话术管理
 * 1:1 复刻 Python: database.py -> get_all_scripts / get_scripts_by_category / get_script_categories
 *      add_script / update_script / delete_script / move_script
 */

declare(strict_types=1);

namespace DanChat;

final class Script
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getAllScripts(): array
    {
        $rows = $this->db->fetchAll('SELECT * FROM scripts ORDER BY sort_order, id');
        return $this->normalizeList($rows);
    }

    public function getScriptsByCategory(string $category = ''): array
    {
        if ($category !== '') {
            $rows = $this->db->fetchAll(
                'SELECT * FROM scripts WHERE category = ? ORDER BY sort_order, id',
                [$category]
            );
        } else {
            $rows = $this->db->fetchAll(
                'SELECT * FROM scripts ORDER BY category, sort_order, id'
            );
        }
        return $this->normalizeList($rows);
    }

    public function getCategories(): array
    {
        $rows = $this->db->fetchAll(
            'SELECT DISTINCT category FROM scripts ORDER BY category'
        );
        return array_map(fn($r) => $r['category'], $rows);
    }

    public function addScript(string $title, string $content, string $category = '通用'): int
    {
        $maxOrder = (int)($this->db->fetchValue('SELECT MAX(sort_order) FROM scripts') ?: 0);
        return $this->db->insert('scripts', [
            'title'      => $title,
            'content'    => $content,
            'category'   => $category !== '' ? $category : '通用',
            'sort_order' => $maxOrder + 1,
        ]);
    }

    public function updateScript(int $scriptId, array $data): void
    {
        $data['updated_at'] = now();
        $this->db->update('scripts', $data, 'id = ?', [$scriptId]);
    }

    public function deleteScript(int $scriptId): void
    {
        $this->db->delete('scripts', 'id = ?', [$scriptId]);
    }

    /**
     * 移动话术位置 (上下)
     */
    public function moveScript(int $scriptId, string $direction): bool
    {
        $current = $this->db->fetchOne('SELECT id, sort_order FROM scripts WHERE id = ?', [$scriptId]);
        if (!$current) return false;
        $curOrder = (int)$current['sort_order'];

        if ($direction === 'up') {
            $neighbor = $this->db->fetchOne(
                'SELECT id, sort_order FROM scripts WHERE sort_order < ? ORDER BY sort_order DESC LIMIT 1',
                [$curOrder]
            );
        } else {
            $neighbor = $this->db->fetchOne(
                'SELECT id, sort_order FROM scripts WHERE sort_order > ? ORDER BY sort_order ASC LIMIT 1',
                [$curOrder]
            );
        }

        if (!$neighbor) return false;

        $this->db->begin();
        try {
            $this->db->update('scripts', ['sort_order' => (int)$neighbor['sort_order']], 'id = ?', [$current['id']]);
            $this->db->update('scripts', ['sort_order' => $curOrder], 'id = ?', [$neighbor['id']]);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
        return true;
    }

    private function normalizeList(array $rows): array
    {
        return array_map(function (array $r): array {
            $r['id'] = (int)$r['id'];
            $r['sort_order'] = (int)$r['sort_order'];
            return $r;
        }, $rows);
    }
}
