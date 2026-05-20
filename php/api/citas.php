<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../session.php';

Session::start();
if (!Session::has('user_id')) {
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$userId = Session::get('user_id');
$userRol = Session::get('user_rol');
$method = $_SERVER['REQUEST_METHOD'];

try {
    $db = Database::getConnection();

    // ============================================================
    // GET: Listar citas (según rol)
    // ============================================================
    if ($method === 'GET') {
        $tipo = $_GET['tipo'] ?? 'proximas'; // proximas, historial, todas

        if ($userRol === 'prestador') {
            // Obtener perfil del prestador
            $stmtPerfil = $db->prepare("SELECT id FROM perfiles_prestadores WHERE usuario_id = :uid");
            $stmtPerfil->execute(['uid' => $userId]);
            $perfil = $stmtPerfil->fetch();
            if (!$perfil) {
                echo json_encode(['status' => 'error', 'message' => 'Perfil no encontrado.']);
                exit;
            }
            $prestadorId = $perfil['id'];

            if ($tipo === 'hoy') {
                $stmt = $db->prepare("
                    SELECT c.*, u.nombre as cliente_nombre, u.telefono as cliente_telefono, u.correo as cliente_correo,
                           s.nombre_servicio, p.nombre_plan
                    FROM citas c
                    JOIN usuarios u ON c.cliente_id = u.id
                    JOIN servicios_precios s ON c.servicio_id = s.id
                    LEFT JOIN planes_servicio p ON c.plan_id = p.id
                    WHERE c.prestador_id = :pid AND DATE(c.fecha_hora_inicio) = CURDATE()
                    ORDER BY c.fecha_hora_inicio ASC
                ");
            } elseif ($tipo === 'historial') {
                $stmt = $db->prepare("
                    SELECT c.*, u.nombre as cliente_nombre, u.telefono as cliente_telefono,
                           s.nombre_servicio, p.nombre_plan
                    FROM citas c
                    JOIN usuarios u ON c.cliente_id = u.id
                    JOIN servicios_precios s ON c.servicio_id = s.id
                    LEFT JOIN planes_servicio p ON c.plan_id = p.id
                    WHERE c.prestador_id = :pid AND c.fecha_hora_inicio < NOW()
                    ORDER BY c.fecha_hora_inicio DESC
                    LIMIT 50
                ");
            } else { // próximas
                $stmt = $db->prepare("
                    SELECT c.*, u.nombre as cliente_nombre, u.telefono as cliente_telefono,
                           s.nombre_servicio, p.nombre_plan
                    FROM citas c
                    JOIN usuarios u ON c.cliente_id = u.id
                    JOIN servicios_precios s ON c.servicio_id = s.id
                    LEFT JOIN planes_servicio p ON c.plan_id = p.id
                    WHERE c.prestador_id = :pid AND c.fecha_hora_inicio >= NOW() AND c.estado != 'cancelada'
                    ORDER BY c.fecha_hora_inicio ASC
                    LIMIT 50
                ");
            }
            $stmt->execute(['pid' => $prestadorId]);

        } elseif ($userRol === 'cliente') {
            if ($tipo === 'historial') {
                $stmt = $db->prepare("
                    SELECT c.*, u.nombre as prestador_nombre, u.telefono as prestador_telefono,
                           s.nombre_servicio, p.nombre_plan, pp.direccion_fisica
                    FROM citas c
                    JOIN usuarios u ON c.prestador_id = (SELECT usuario_id FROM perfiles_prestadores WHERE id = c.prestador_id)
                    JOIN servicios_precios s ON c.servicio_id = s.id
                    LEFT JOIN planes_servicio p ON c.plan_id = p.id
                    LEFT JOIN perfiles_prestadores pp ON pp.id = c.prestador_id
                    WHERE c.cliente_id = :uid AND (c.fecha_hora_inicio < NOW() OR c.estado = 'cancelada')
                    ORDER BY c.fecha_hora_inicio DESC
                    LIMIT 50
                ");
            } else { // próximas
                $stmt = $db->prepare("
                    SELECT c.*, u.nombre as prestador_nombre, u.telefono as prestador_telefono,
                           s.nombre_servicio, p.nombre_plan, pp.direccion_fisica
                    FROM citas c
                    JOIN usuarios u ON c.prestador_id = (SELECT usuario_id FROM perfiles_prestadores WHERE id = c.prestador_id)
                    JOIN servicios_precios s ON c.servicio_id = s.id
                    LEFT JOIN planes_servicio p ON c.plan_id = p.id
                    LEFT JOIN perfiles_prestadores pp ON pp.id = c.prestador_id
                    WHERE c.cliente_id = :uid AND c.fecha_hora_inicio >= NOW() AND c.estado != 'cancelada'
                    ORDER BY c.fecha_hora_inicio ASC
                    LIMIT 50
                ");
            }
            $stmt->execute(['uid' => $userId]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Rol no válido.']);
            exit;
        }

        $citas = $stmt->fetchAll();
        echo json_encode(['status' => 'success', 'data' => $citas]);
        exit;
    }

    // ============================================================
    // POST: Crear o cancelar citas
    // ============================================================
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $accion = $input['accion'] ?? '';

        // ---- Crear cita (solo cliente) ----
        if ($accion === 'crear') {
            if ($userRol !== 'cliente') {
                echo json_encode(['status' => 'error', 'message' => 'Solo los clientes pueden agendar citas.']);
                exit;
            }

            $prestadorId = intval($input['prestador_id'] ?? 0);
            $servicioId = intval($input['servicio_id'] ?? 0);
            $planId = isset($input['plan_id']) ? intval($input['plan_id']) : null;
            $fechaHoraInicio = $input['fecha_hora_inicio'] ?? '';
            $duracionMinutos = intval($input['duracion_minutos'] ?? 60);

            if ($prestadorId <= 0 || $servicioId <= 0 || empty($fechaHoraInicio)) {
                echo json_encode(['status' => 'error', 'message' => 'Datos incompletos.']);
                exit;
            }

            $fechaHoraFin = date('Y-m-d H:i:s', strtotime($fechaHoraInicio) + ($duracionMinutos * 60));

            // Obtener costo del plan o del servicio
            if ($planId) {
                $stmtCosto = $db->prepare("SELECT precio FROM planes_servicio WHERE id = :pid");
                $stmtCosto->execute(['pid' => $planId]);
                $planData = $stmtCosto->fetch();
                $costoFinal = $planData ? $planData['precio'] : 0;
            } else {
                $stmtCosto = $db->prepare("SELECT precio FROM servicios_precios WHERE id = :sid");
                $stmtCosto->execute(['sid' => $servicioId]);
                $servData = $stmtCosto->fetch();
                $costoFinal = $servData ? $servData['precio'] : 0;
            }

            // Obtener dirección del prestador
            $stmtDir = $db->prepare("SELECT direccion_fisica FROM perfiles_prestadores WHERE id = :pid");
            $stmtDir->execute(['pid' => $prestadorId]);
            $prestadorDir = $stmtDir->fetch();
            $direccion = $prestadorDir ? $prestadorDir['direccion_fisica'] : '';

            $stmtIns = $db->prepare("
                INSERT INTO citas (cliente_id, prestador_id, servicio_id, plan_id, fecha_hora_inicio, fecha_hora_fin, estado, direccion_servicio, costo_final)
                VALUES (:cliente_id, :prestador_id, :servicio_id, :plan_id, :inicio, :fin, 'pendiente', :direccion, :costo)
            ");
            $stmtIns->execute([
                'cliente_id' => $userId,
                'prestador_id' => $prestadorId,
                'servicio_id' => $servicioId,
                'plan_id' => $planId,
                'inicio' => $fechaHoraInicio,
                'fin' => $fechaHoraFin,
                'direccion' => $direccion,
                'costo' => $costoFinal
            ]);

            $citaId = $db->lastInsertId();
            echo json_encode(['status' => 'success', 'message' => 'Cita agendada correctamente. Pronto el profesional se comunicará para confirmar.', 'cita_id' => $citaId]);
            exit;
        }

        // ---- Cancelar cita ----
        if ($accion === 'cancelar') {
            $citaId = intval($input['cita_id'] ?? 0);
            if ($citaId <= 0) {
                echo json_encode(['status' => 'error', 'message' => 'ID de cita inválido.']);
                exit;
            }

            // Verificar que la cita pertenece al usuario
            if ($userRol === 'cliente') {
                $stmtCheck = $db->prepare("SELECT id, estado FROM citas WHERE id = :cid AND cliente_id = :uid");
            } elseif ($userRol === 'prestador') {
                $stmtPerfil = $db->prepare("SELECT id FROM perfiles_prestadores WHERE usuario_id = :uid");
                $stmtPerfil->execute(['uid' => $userId]);
                $perfil = $stmtPerfil->fetch();
                $prestadorId = $perfil ? $perfil['id'] : 0;
                $stmtCheck = $db->prepare("SELECT id, estado FROM citas WHERE id = :cid AND prestador_id = :pid");
                $stmtCheck->execute(['cid' => $citaId, 'pid' => $prestadorId]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'No autorizado.']);
                exit;
            }

            if (!isset($prestadorId)) {
                $stmtCheck->execute(['cid' => $citaId, 'uid' => $userId]);
            }

            $cita = $stmtCheck->fetch();
            if (!$cita) {
                echo json_encode(['status' => 'error', 'message' => 'Cita no encontrada.']);
                exit;
            }

            $db->beginTransaction();

            // Actualizar estado
            $stmtUpd = $db->prepare("UPDATE citas SET estado = 'cancelada' WHERE id = :cid");
            $stmtUpd->execute(['cid' => $citaId]);

            // Si es cliente, registrar cancelación y verificar límite
            if ($userRol === 'cliente') {
                $stmtReg = $db->prepare("INSERT INTO cancelaciones_cliente (cliente_id, cita_id) VALUES (:uid, :cid)");
                $stmtReg->execute(['uid' => $userId, 'cid' => $citaId]);

                // Incrementar contador de cancelaciones
                $stmtInc = $db->prepare("UPDATE perfiles_clientes SET cancelaciones = cancelaciones + 1 WHERE usuario_id = :uid");
                $stmtInc->execute(['uid' => $userId]);

                // Verificar si llegó a 3
                $stmtCount = $db->prepare("SELECT cancelaciones FROM perfiles_clientes WHERE usuario_id = :uid");
                $stmtCount->execute(['uid' => $userId]);
                $countData = $stmtCount->fetch();
                $cancelCount = $countData ? $countData['cancelaciones'] : 0;

                $advertencia = '';
                if ($cancelCount >= 3) {
                    $stmtDes = $db->prepare("UPDATE usuarios SET estado = 'desactivado' WHERE id = :uid");
                    $stmtDes->execute(['uid' => $userId]);
                    $advertencia = ' Has alcanzado el límite de cancelaciones. Tu cuenta ha sido suspendida temporalmente.';
                } elseif ($cancelCount >= 2) {
                    $advertencia = ' Advertencia: Te queda 1 cancelación antes de la suspensión temporal de tu cuenta.';
                }

                $db->commit();
                echo json_encode(['status' => 'success', 'message' => 'Cita cancelada correctamente.' . $advertencia]);
                exit;
            }

            $db->commit();
            echo json_encode(['status' => 'success', 'message' => 'Cita cancelada correctamente.']);
            exit;
        }

        // ---- Confirmar cita (prestador) ----
        if ($accion === 'confirmar') {
            if ($userRol !== 'prestador') {
                echo json_encode(['status' => 'error', 'message' => 'No autorizado.']);
                exit;
            }

            $citaId = intval($input['cita_id'] ?? 0);
            $stmtPerfil = $db->prepare("SELECT id FROM perfiles_prestadores WHERE usuario_id = :uid");
            $stmtPerfil->execute(['uid' => $userId]);
            $perfil = $stmtPerfil->fetch();
            $prestadorId = $perfil ? $perfil['id'] : 0;

            $stmtCheck = $db->prepare("SELECT id FROM citas WHERE id = :cid AND prestador_id = :pid");
            $stmtCheck->execute(['cid' => $citaId, 'pid' => $prestadorId]);
            if (!$stmtCheck->fetch()) {
                echo json_encode(['status' => 'error', 'message' => 'Cita no encontrada.']);
                exit;
            }

            $stmtUpd = $db->prepare("UPDATE citas SET estado = 'confirmada' WHERE id = :cid");
            $stmtUpd->execute(['cid' => $citaId]);
            echo json_encode(['status' => 'success', 'message' => 'Cita confirmada correctamente.']);
            exit;
        }

        echo json_encode(['status' => 'error', 'message' => 'Acción no válida.']);
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'Método no permitido.']);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    error_log(date('[Y-m-d H:i:s] ') . "API Citas Error: " . $e->getMessage() . "\n", 3, __DIR__ . '/../../logs/errores.log');
    echo json_encode(['status' => 'error', 'message' => 'Error interno del servidor.']);
}
