<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../session.php';

Session::start();
$method = $_SERVER['REQUEST_METHOD'];

// Se usa tanto para prestador (CRUD) como para cliente (lectura pública)
$esPrestador = Session::has('user_id') && Session::get('user_rol') === 'prestador';

try {
    $db = Database::getConnection();

    // ============================================================
    // GET público: Obtener horarios de un profesional por su usuario_id
    // ============================================================
    if ($method === 'GET') {
        $profesionalUserId = isset($_GET['usuario_id']) ? intval($_GET['usuario_id']) : 0;

        // Si es prestador y no se especificó otro usuario, usar el suyo
        if ($esPrestador && $profesionalUserId <= 0) {
            $profesionalUserId = Session::get('user_id');
        }

        if ($profesionalUserId <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'ID de profesional requerido.']);
            exit;
        }

        // Obtener el perfil del prestador
        $stmtPerfil = $db->prepare("SELECT id FROM perfiles_prestadores WHERE usuario_id = :uid");
        $stmtPerfil->execute(['uid' => $profesionalUserId]);
        $perfil = $stmtPerfil->fetch();

        if (!$perfil) {
            echo json_encode(['status' => 'error', 'message' => 'Profesional no encontrado.']);
            exit;
        }

        $prestadorId = $perfil['id'];

        // Obtener horarios
        $stmtHor = $db->prepare("SELECT * FROM horarios_trabajo WHERE prestador_id = :pid AND activo = 1 ORDER BY dia_semana, hora_inicio");
        $stmtHor->execute(['pid' => $prestadorId]);
        $horarios = $stmtHor->fetchAll();

        // Obtener excepciones (días bloqueados)
        $stmtExc = $db->prepare("SELECT * FROM excepciones_horario WHERE prestador_id = :pid AND fecha >= CURDATE() ORDER BY fecha");
        $stmtExc->execute(['pid' => $prestadorId]);
        $excepciones = $stmtExc->fetchAll();

        echo json_encode([
            'status' => 'success',
            'horarios' => $horarios,
            'excepciones' => $excepciones
        ]);
        exit;
    }

    // ============================================================
    // POST: Solo prestador puede modificar
    // ============================================================
    if ($method === 'POST') {
        if (!$esPrestador) {
            echo json_encode(['status' => 'error', 'message' => 'No autorizado.']);
            exit;
        }

        $userId = Session::get('user_id');
        $input = json_decode(file_get_contents('php://input'), true);
        $accion = $input['accion'] ?? '';

        // Obtener ID del perfil del prestador
        $stmtPerfil = $db->prepare("SELECT id FROM perfiles_prestadores WHERE usuario_id = :uid");
        $stmtPerfil->execute(['uid' => $userId]);
        $perfil = $stmtPerfil->fetch();
        if (!$perfil) {
            echo json_encode(['status' => 'error', 'message' => 'Perfil de profesional no encontrado.']);
            exit;
        }
        $prestadorId = $perfil['id'];

        // ---- Guardar horarios ----
        if ($accion === 'guardar_horarios') {
            $horarios = $input['horarios'] ?? [];

            $db->beginTransaction();

            // Desactivar todos los horarios actuales
            $stmtDes = $db->prepare("UPDATE horarios_trabajo SET activo = 0 WHERE prestador_id = :pid");
            $stmtDes->execute(['pid' => $prestadorId]);

            // Insertar nuevos horarios
            foreach ($horarios as $h) {
                $dia = intval($h['dia_semana'] ?? -1);
                $horaInicio = $h['hora_inicio'] ?? '';
                $horaFin = $h['hora_fin'] ?? '';

                if ($dia < 0 || $dia > 6 || empty($horaInicio) || empty($horaFin)) continue;

                // Verificar si ya existe
                $stmtExist = $db->prepare("SELECT id FROM horarios_trabajo WHERE prestador_id = :pid AND dia_semana = :dia AND hora_inicio = :hora");
                $stmtExist->execute(['pid' => $prestadorId, 'dia' => $dia, 'hora' => $horaInicio]);
                $exist = $stmtExist->fetch();

                if ($exist) {
                    $stmtUpd = $db->prepare("UPDATE horarios_trabajo SET hora_fin = :hfin, activo = 1 WHERE id = :id");
                    $stmtUpd->execute(['hfin' => $horaFin, 'id' => $exist['id']]);
                } else {
                    $stmtIns = $db->prepare("INSERT INTO horarios_trabajo (prestador_id, dia_semana, hora_inicio, hora_fin, activo) VALUES (:pid, :dia, :hini, :hfin, 1)");
                    $stmtIns->execute(['pid' => $prestadorId, 'dia' => $dia, 'hini' => $horaInicio, 'hfin' => $horaFin]);
                }
            }

            $db->commit();
            echo json_encode(['status' => 'success', 'message' => 'Horarios guardados correctamente.']);
            exit;
        }

        // ---- Bloquear / desbloquear fecha ----
        if ($accion === 'bloquear_fecha' || $accion === 'desbloquear_fecha') {
            $fecha = $input['fecha'] ?? '';
            $motivo = $input['motivo'] ?? '';

            if (empty($fecha)) {
                echo json_encode(['status' => 'error', 'message' => 'Fecha requerida.']);
                exit;
            }

            if ($accion === 'bloquear_fecha') {
                // Upsert: insertar o actualizar
                $stmtExist = $db->prepare("SELECT id FROM excepciones_horario WHERE prestador_id = :pid AND fecha = :fecha");
                $stmtExist->execute(['pid' => $prestadorId, 'fecha' => $fecha]);
                $exist = $stmtExist->fetch();

                if ($exist) {
                    $stmtUpd = $db->prepare("UPDATE excepciones_horario SET bloqueado = 1, motivo = :motivo WHERE id = :id");
                    $stmtUpd->execute(['motivo' => $motivo, 'id' => $exist['id']]);
                } else {
                    $stmtIns = $db->prepare("INSERT INTO excepciones_horario (prestador_id, fecha, motivo, bloqueado) VALUES (:pid, :fecha, :motivo, 1)");
                    $stmtIns->execute(['pid' => $prestadorId, 'fecha' => $fecha, 'motivo' => $motivo]);
                }
                echo json_encode(['status' => 'success', 'message' => 'Fecha bloqueada correctamente.']);
            } else {
                $stmtDel = $db->prepare("DELETE FROM excepciones_horario WHERE prestador_id = :pid AND fecha = :fecha");
                $stmtDel->execute(['pid' => $prestadorId, 'fecha' => $fecha]);
                echo json_encode(['status' => 'success', 'message' => 'Fecha desbloqueada correctamente.']);
            }
            exit;
        }

        echo json_encode(['status' => 'error', 'message' => 'Acción no válida.']);
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'Método no permitido.']);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    error_log(date('[Y-m-d H:i:s] ') . "API Horarios Error: " . $e->getMessage() . "\n", 3, __DIR__ . '/../../logs/errores.log');
    echo json_encode(['status' => 'error', 'message' => 'Error interno del servidor.']);
}
