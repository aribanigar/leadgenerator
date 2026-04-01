<?php
namespace ViaKashmir;

use PDO;
use PDOException;

/**
 * Database singleton — wraps PDO for MySQL.
 */
class Database
{
    private static ?PDO $instance = null;

    public static function get(): PDO
    {
        if (self::$instance === null) {
            $cfg = require __DIR__ . '/../config.php';
            $db  = $cfg['db'];
            $dsn = "mysql:host={$db['host']};port={$db['port']};dbname={$db['name']};charset={$db['charset']}";
            try {
                self::$instance = new PDO($dsn, $db['user'], $db['pass'], [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            } catch (PDOException $e) {
                http_response_code(500);
                die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
            }
        }
        return self::$instance;
    }

    // ── Ad Accounts ──────────────────────────────────────────────────────────

    public static function getAccounts(?string $platform = null): array
    {
        $db = self::get();
        if ($platform) {
            $st = $db->prepare('SELECT * FROM ad_accounts WHERE active=1 AND platform=?');
            $st->execute([$platform]);
        } else {
            $st = $db->query('SELECT * FROM ad_accounts WHERE active=1');
        }
        return $st->fetchAll();
    }

    public static function addAccount(array $data): int
    {
        $db = self::get();
        $st = $db->prepare('INSERT INTO ad_accounts (platform,label,account_id,access_token,extra_config) VALUES (?,?,?,?,?)');
        $st->execute([
            $data['platform'],
            $data['label'],
            $data['account_id'],
            $data['access_token'] ?? null,
            json_encode($data['extra_config'] ?? []),
        ]);
        return (int)$db->lastInsertId();
    }

    public static function deleteAccount(int $id): void
    {
        self::get()->prepare('UPDATE ad_accounts SET active=0 WHERE id=?')->execute([$id]);
    }

    // ── Leads ────────────────────────────────────────────────────────────────

    public static function upsertLead(array $data): ?int
    {
        $db = self::get();

        if (!empty($data['external_id'])) {
            $st = $db->prepare('SELECT id FROM leads WHERE external_id=?');
            $st->execute([$data['external_id']]);
            if ($st->fetchColumn()) return null; // already exists
        }

        $st = $db->prepare('
            INSERT INTO leads
              (external_id,source_platform,source_account,ad_name,campaign_name,form_name,
               client_name,email,phone,
               destination,travel_duration,travel_from_date,travel_to_date,
               adults,children,budget,special_requests,raw_data,created_at)
            VALUES
              (?,?,?,?,?,?,  ?,?,?,  ?,?,?,?,  ?,?,?,?,?,?)
        ');
        $st->execute([
            $data['external_id']      ?? null,
            $data['source_platform'],
            $data['source_account']   ?? null,
            $data['ad_name']          ?? null,
            $data['campaign_name']    ?? null,
            $data['form_name']        ?? null,
            $data['client_name']      ?? null,
            $data['email']            ?? null,
            $data['phone']            ?? null,
            $data['destination']      ?? null,
            $data['travel_duration']  ?? null,
            $data['travel_from_date'] ?? null,
            $data['travel_to_date']   ?? null,
            $data['adults']           ?? 0,
            $data['children']         ?? 0,
            $data['budget']           ?? null,
            $data['special_requests'] ?? null,
            json_encode($data['raw_data'] ?? []),
            $data['created_at']       ?? date('Y-m-d H:i:s'),
        ]);
        return (int)$db->lastInsertId();
    }

    public static function getLeads(array $filters = []): array
    {
        $db     = self::get();
        $where  = [];
        $params = [];

        if (!empty($filters['status']))      { $where[] = 'l.status=?';           $params[] = $filters['status']; }
        if (!empty($filters['platform']))    { $where[] = 'l.source_platform=?';  $params[] = $filters['platform']; }
        if (!empty($filters['assigned_to'])) { $where[] = 'l.assigned_to=?';      $params[] = $filters['assigned_to']; }
        if (!empty($filters['search'])) {
            $where[]  = '(l.client_name LIKE ? OR l.email LIKE ? OR l.phone LIKE ?)';
            $q        = '%' . $filters['search'] . '%';
            $params   = array_merge($params, [$q, $q, $q]);
        }

        $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $page     = max(1, (int)($filters['page']  ?? 1));
        $limit    = min(100, max(1, (int)($filters['limit'] ?? 50)));
        $offset   = ($page - 1) * $limit;

        $rows = $db->prepare("
            SELECT l.*, t.name AS assignee_name, t.email AS assignee_email
            FROM leads l
            LEFT JOIN team_members t ON t.id = l.assigned_to
            {$whereSQL}
            ORDER BY l.created_at DESC
            LIMIT {$limit} OFFSET {$offset}
        ");
        $rows->execute($params);

        $total = $db->prepare("SELECT COUNT(*) FROM leads l {$whereSQL}");
        $total->execute($params);

        return [
            'leads' => $rows->fetchAll(),
            'total' => (int)$total->fetchColumn(),
            'page'  => $page,
            'limit' => $limit,
        ];
    }

    public static function getLead(int $id): ?array
    {
        $db = self::get();
        $st = $db->prepare('
            SELECT l.*, t.name AS assignee_name
            FROM leads l
            LEFT JOIN team_members t ON t.id=l.assigned_to
            WHERE l.id=?
        ');
        $st->execute([$id]);
        $lead = $st->fetch();
        if (!$lead) return null;

        $ns = $db->prepare('
            SELECT n.*, m.name AS author_name
            FROM lead_notes n
            LEFT JOIN team_members m ON m.id=n.author_id
            WHERE n.lead_id=?
            ORDER BY n.created_at DESC
        ');
        $ns->execute([$id]);
        $lead['notes'] = $ns->fetchAll();
        return $lead;
    }

    public static function updateLeadStatus(int $id, string $status): void
    {
        $db = self::get();
        $extra = '';
        $params = [$status];

        if ($status === 'contacted') { $extra = ', contacted_at=NOW()'; }
        if ($status === 'converted') { $extra = ', converted_at=NOW()'; }

        $db->prepare("UPDATE leads SET status=?, updated_at=NOW(){$extra} WHERE id=?")->execute(array_merge($params, [$id]));
        self::addNote($id, "Status changed to \"{$status}\"");
    }

    public static function assignLead(int $leadId, int $memberId): void
    {
        $db = self::get();
        $db->prepare('UPDATE leads SET assigned_to=?, updated_at=NOW() WHERE id=?')->execute([$memberId, $leadId]);
        $m = $db->prepare('SELECT name FROM team_members WHERE id=?');
        $m->execute([$memberId]);
        $name = $m->fetchColumn() ?: 'team member';
        self::addNote($leadId, "Assigned to {$name}");
    }

    public static function addNote(int $leadId, string $note, ?int $authorId = null): void
    {
        self::get()->prepare('INSERT INTO lead_notes (lead_id,author_id,note) VALUES (?,?,?)')->execute([$leadId, $authorId, $note]);
    }

    // ── Stats ────────────────────────────────────────────────────────────────

    public static function getStats(): array
    {
        $db = self::get();
        $byStatus   = $db->query('SELECT status, COUNT(*) AS n FROM leads GROUP BY status')->fetchAll();
        $byPlatform = $db->query('SELECT source_platform, COUNT(*) AS n FROM leads GROUP BY source_platform')->fetchAll();
        $total      = (int)$db->query('SELECT COUNT(*) FROM leads')->fetchColumn();
        return compact('total', 'byStatus', 'byPlatform');
    }

    // ── Team ─────────────────────────────────────────────────────────────────

    public static function getTeam(): array
    {
        return self::get()->query('SELECT * FROM team_members WHERE active=1 ORDER BY name')->fetchAll();
    }

    public static function addTeamMember(array $data): int
    {
        $db = self::get();
        $db->prepare('INSERT INTO team_members (name,email,phone,role) VALUES (?,?,?,?)')->execute([
            $data['name'], $data['email'], $data['phone'] ?? null, $data['role'] ?? 'agent',
        ]);
        return (int)$db->lastInsertId();
    }

    public static function removeTeamMember(int $id): void
    {
        self::get()->prepare('UPDATE team_members SET active=0 WHERE id=?')->execute([$id]);
    }
}
