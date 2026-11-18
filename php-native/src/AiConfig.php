<?php
/**
 * 单聊辅助 - AI 配置管理
 * 1:1 复刻 Python: database.py -> get_ai_config / save_ai_config
 */

declare(strict_types=1);

namespace DanChat;

final class AiConfig
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function get(): ?array
    {
        $row = $this->db->fetchOne('SELECT * FROM ai_config WHERE id = 1');
        if (!$row) return null;
        $row['id'] = (int)$row['id'];
        $row['temperature'] = (float)$row['temperature'];
        $row['max_tokens']  = (int)$row['max_tokens'];
        return $row;
    }

    public function save(array $data): void
    {
        $allowed = ['api_url', 'api_key', 'model', 'system_prompt', 'temperature', 'max_tokens'];
        $payload = [];
        foreach ($allowed as $k) {
            if (array_key_exists($k, $data)) {
                $payload[$k] = $data[$k];
            }
        }
        if (empty($payload)) return;

        $exists = $this->db->fetchValue('SELECT id FROM ai_config WHERE id = 1');
        if ($exists !== null) {
            $this->db->update('ai_config', $payload, 'id = ?', [1]);
        } else {
            $payload['id'] = 1;
            $this->db->insert('ai_config', $payload);
        }
    }
}
