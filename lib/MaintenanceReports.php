<?php
/**
 * Lógica de negocio de la Bitácora de Mantenimiento, separada de los
 * endpoints HTTP para poder probarla directamente con PHPUnit.
 */

const MR_PRIORITIES = ['baja', 'media', 'alta'];
const MR_STATUSES = ['abierto', 'en_proceso', 'cerrado'];

/**
 * Mapa de permisos por rol. 'admin' puede todo; 'usuario' solo puede
 * crear y ver reportes, no cerrarlos ni eliminarlos.
 */
const MR_PERMISSIONS = [
    'usuario' => ['create', 'list'],
    'admin'   => ['create', 'list', 'update_status', 'delete'],
];

/**
 * Revisa si un rol tiene permiso para una acción. No lanza excepción,
 * solo responde true/false -- quien la llame decide qué hacer con eso.
 */
function mr_can(string $role, string $action): bool
{
    return in_array($action, MR_PERMISSIONS[$role] ?? [], true);
}

/**
 * Crea un reporte de mantenimiento. Lanza InvalidArgumentException si
 * faltan datos o son inválidos.
 */
function mr_create(PDO $pdo, array $data): int
{
    $roomId = trim($data['room_id'] ?? '');
    $title = trim($data['title'] ?? '');
    $description = trim($data['description'] ?? '');
    $priority = $data['priority'] ?? 'media';
    $reportedBy = (int)($data['reported_by'] ?? 0);

    if (!$roomId || !$title || !$reportedBy) {
        throw new InvalidArgumentException('room_id, title y reported_by son obligatorios');
    }
    if (!in_array($priority, MR_PRIORITIES, true)) {
        throw new InvalidArgumentException('Prioridad inválida, debe ser: ' . implode(', ', MR_PRIORITIES));
    }

    $stmt = $pdo->prepare(
        "INSERT INTO maintenance_reports (room_id, title, description, priority, reported_by)
         VALUES (:room_id, :title, :description, :priority, :reported_by)"
    );
    $stmt->execute([
        'room_id'     => $roomId,
        'title'       => $title,
        'description' => $description ?: null,
        'priority'    => $priority,
        'reported_by' => $reportedBy,
    ]);

    return (int)$pdo->lastInsertId();
}

/** Lista reportes, opcionalmente filtrados por estado. */
function mr_list(PDO $pdo, ?string $status = null): array
{
    if ($status !== null && !in_array($status, MR_STATUSES, true)) {
        throw new InvalidArgumentException('Estado inválido, debe ser: ' . implode(', ', MR_STATUSES));
    }

    $sql = "SELECT mr.*, r.name AS room_name, u.full_name AS reported_by_name
            FROM maintenance_reports mr
            JOIN rooms r ON r.id = mr.room_id
            JOIN staff_users u ON u.id = mr.reported_by";
    if ($status !== null) {
        $sql .= " WHERE mr.status = :status";
    }
    $sql .= " ORDER BY FIELD(mr.priority,'alta','media','baja'), mr.created_at DESC";

    $stmt = $pdo->prepare($sql);
    if ($status !== null) {
        $stmt->execute(['status' => $status]);
    } else {
        $stmt->execute();
    }
    return $stmt->fetchAll();
}

/**
 * Cambia el estado de un reporte. Si el nuevo estado es 'cerrado', registra
 * quién lo cerró y cuándo. Devuelve false si el reporte no existe.
 */
function mr_update_status(PDO $pdo, int $id, string $newStatus, int $closedByUserId): bool
{
    if (!in_array($newStatus, MR_STATUSES, true)) {
        throw new InvalidArgumentException('Estado inválido, debe ser: ' . implode(', ', MR_STATUSES));
    }

    if ($newStatus === 'cerrado') {
        $stmt = $pdo->prepare(
            "UPDATE maintenance_reports SET status = :status, closed_by = :closed_by, closed_at = NOW() WHERE id = :id"
        );
        $stmt->execute(['status' => $newStatus, 'closed_by' => $closedByUserId, 'id' => $id]);
    } else {
        $stmt = $pdo->prepare(
            "UPDATE maintenance_reports SET status = :status, closed_by = NULL, closed_at = NULL WHERE id = :id"
        );
        $stmt->execute(['status' => $newStatus, 'id' => $id]);
    }

    return $stmt->rowCount() > 0;
}

/** Elimina un reporte. Devuelve false si no existía. */
function mr_delete(PDO $pdo, int $id): bool
{
    $stmt = $pdo->prepare("DELETE FROM maintenance_reports WHERE id = :id");
    $stmt->execute(['id' => $id]);
    return $stmt->rowCount() > 0;
}
