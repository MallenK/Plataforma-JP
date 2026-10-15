<?php

namespace App\Libraries\Push;

use CodeIgniter\Database\BaseConnection;
use Config\Database;

/**
 * Acceso a `push_subscriptions`. Una fila por navegador, única por endpoint_hash.
 * Solo persistencia: las reglas (qué endpoints valen, cuándo se borra) están en
 * PushService y DeliveryPolicy.
 */
final class SubscriptionStore
{
    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    /**
     * Alta o reasignación (mismo navegador, otro usuario). Atómica ante dos peticiones
     * simultáneas: si el INSERT choca con el índice único, se actualiza la fila ganadora.
     *
     * @param array{user_id:int,endpoint:string,p256dh:string,auth:string,user_agent:?string} $row
     */
    public function upsert(string $hash, array $row): void
    {
        $now  = date('Y-m-d H:i:s');
        $data = $row + ['failures' => 0, 'last_used_at' => $now];

        if ($this->updateByHash($hash, $data)) {
            return;
        }

        try {
            $inserted = $this->db->table('push_subscriptions')->insert($data + ['endpoint_hash' => $hash, 'created_at' => $now]);
        } catch (\Throwable $e) {
            $inserted = false; // choque con el índice único: otra petición la creó entre medias
        }

        if (!$inserted && !$this->updateByHash($hash, $data)) {
            throw new \RuntimeException('No se pudo guardar la suscripción push.');
        }
    }

    private function updateByHash(string $hash, array $data): bool
    {
        $exists = $this->db->table('push_subscriptions')->select('id')->where('endpoint_hash', $hash)->get()->getRowArray();
        if (!$exists) {
            return false;
        }
        $this->db->table('push_subscriptions')->where('id', $exists['id'])->update($data);
        return true;
    }

    public function deleteByHash(string $hash, ?int $userId = null): int
    {
        $b = $this->db->table('push_subscriptions')->where('endpoint_hash', $hash);
        if ($userId !== null) {
            $b->where('user_id', $userId);
        }
        $b->delete();

        return $this->db->affectedRows();
    }

    public function countForUser(int $userId): int
    {
        return (int) $this->db->table('push_subscriptions')->where('user_id', $userId)->countAllResults();
    }

    /**
     * @param list<int> $userIds
     * @return list<array<string,mixed>>
     */
    public function forUsers(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }
        return $this->db->table('push_subscriptions')->whereIn('user_id', $userIds)->get()->getResultArray();
    }

    public function markDelivered(int $id): void
    {
        $this->db->table('push_subscriptions')->where('id', $id)->update(['failures' => 0, 'last_used_at' => date('Y-m-d H:i:s')]);
    }

    public function setFailures(int $id, int $failures): void
    {
        $this->db->table('push_subscriptions')->where('id', $id)->update(['failures' => $failures]);
    }

    public function delete(int $id): void
    {
        $this->db->table('push_subscriptions')->where('id', $id)->delete();
    }
}
