<?php
@ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../session.php';

Session::start();
if (!Session::has('user_id') || Session::get('user_rol') !== 'prestador') {
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$userId = Session::get('user_id');
$method = $_SERVER['REQUEST_METHOD'];

try {
    $db = Database::getConnection();

    // Obtener ID del perfil del prestador
    $stmt = $db->prepare("SELECT id FROM perfiles_prestadores WHERE usuario_id = :uid");
    $stmt->execute(['uid' => $userId]);
    $perfilPrestador = $stmt->fetch();
    if (!$perfilPrestador) {
        echo json_encode(['status' => 'error', 'message' => 'Perfil de profesional no encontrado.']);
        exit;
    }
    $prestadorId = $perfilPrestador['id'];

    // ============================================================
    // GET: Listar servicios del prestador
    // ============================================================
    if ($method === 'GET') {
        $stmtServ = $db->prepare("
            SELECT sp.*, 
                   (SELECT COUNT(*) FROM planes_servicio WHERE servicio_id = sp.id) as planes_count
            FROM servicios_precios sp 
            WHERE sp.prestador_id = :pid 
            ORDER BY sp.id DESC
        ");
        $stmtServ->execute(['pid' => $prestadorId]);
        $servicios = $stmtServ->fetchAll();

        // Agregar planes a cada servicio
        foreach ($servicios as &$serv) {
            $stmtPlanes = $db->prepare("SELECT * FROM planes_servicio WHERE servicio_id = :sid ORDER BY precio ASC");
            $stmtPlanes->execute(['sid' => $serv['id']]);
            $serv['planes'] = $stmtPlanes->fetchAll();
        }

        echo json_encode(['status' => 'success', 'data' => $servicios]);
        exit;
    }

    // ============================================================
    // POST: Crear nuevo servicio
    // ============================================================
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $accion = $input['accion'] ?? 'crear';

        if ($accion === 'crear') {
            $nombreServicio = trim($input['nombre_servicio'] ?? '');
            $precio = floatval($input['precio'] ?? 0);
            $duracion = intval($input['duracion_minutos'] ?? 60);
            $descripcion = trim($input['descripcion_servicio'] ?? '');
            $planes = $input['planes'] ?? [];

            if (empty($nombreServicio)) {
                echo json_encode(['status' => 'error', 'message' => 'El nombre del servicio es obligatorio.']);
                exit;
            }

            $db->beginTransaction();

            $stmtIns = $db->prepare("
                INSERT INTO servicios_precios (prestador_id, nombre_servicio, precio, duracion_minutos, descripcion_servicio) 
                VALUES (:pid, :nombre, :precio, :duracion, :descripcion)
            ");
            $stmtIns->execute([
                'pid' => $prestadorId,
                'nombre' => $nombreServicio,
                'precio' => $precio,
                'duracion' => $duracion,
                'descripcion' => $descripcion
            ]);
            $servicioId = $db->lastInsertId();

            // Insertar planes
            $contadorPlanes = 0;
            foreach ($planes as $plan) {
                if ($contadorPlanes >= 10) break;
                $nombrePlan = trim($plan['nombre_plan'] ?? '');
                $precioPlan = floatval($plan['precio'] ?? 0);
                $duracionPlan = intval($plan['duracion_minutos'] ?? $duracion);
                $descPlan = trim($plan['descripcion_plan'] ?? '');

                if (!empty($nombrePlan) && $precioPlan > 0) {
                    $stmtPlan = $db->prepare("
                        INSERT INTO planes_servicio (servicio_id, nombre_plan, descripcion_plan, precio, duracion_minutos) 
                        VALUES (:sid, :nombre, :descripcion, :precio, :duracion)
                    ");
                    $stmtPlan->execute([
                        'sid' => $servicioId,
                        'nombre' => $nombrePlan,
                        'descripcion' => $descPlan,
                        'precio' => $precioPlan,
                        'duracion' => $duracionPlan
                    ]);
                    $contadorPlanes++;
                }
            }

            // Recalcular activo_etiqueta automáticamente
            $stmtServActivos = $db->prepare("SELECT COUNT(*) FROM servicios_precios WHERE prestador_id = :pid AND activo = 1");
            $stmtServActivos->execute(['pid' => $prestadorId]);
            $tieneServiciosActivos = $stmtServActivos->fetchColumn() > 0;

            $stmtPerfilCheck = $db->prepare("SELECT especialidad, direccion_fisica FROM perfiles_prestadores WHERE id = :pid");
            $stmtPerfilCheck->execute(['pid' => $prestadorId]);
            $perfil = $stmtPerfilCheck->fetch();
            $tienePerfilCompleto = $perfil && !empty($perfil['especialidad']) && !empty($perfil['direccion_fisica']);

            $nuevoEstado = ($tieneServiciosActivos && $tienePerfilCompleto) ? 1 : 0;
            $stmtUpdEtiqueta = $db->prepare("UPDATE perfiles_prestadores SET activo_etiqueta = :activo WHERE id = :pid");
            $stmtUpdEtiqueta->execute(['activo' => $nuevoEstado, 'pid' => $prestadorId]);

            $db->commit();
            echo json_encode(['status' => 'success', 'message' => 'Servicio creado correctamente.', 'servicio_id' => $servicioId]);
            exit;
        }

        // ============================================================
        // Actualizar servicio existente
        // ============================================================
        if ($accion === 'actualizar') {
            $servicioId = intval($input['servicio_id'] ?? 0);
            $nombreServicio = trim($input['nombre_servicio'] ?? '');
            $precio = floatval($input['precio'] ?? 0);
            $duracion = intval($input['duracion_minutos'] ?? 60);
            $descripcion = trim($input['descripcion_servicio'] ?? '');
            $activo = isset($input['activo']) ? intval($input['activo']) : 1;
            $planes = $input['planes'] ?? [];

            if ($servicioId <= 0 || empty($nombreServicio)) {
                echo json_encode(['status' => 'error', 'message' => 'Datos incompletos.']);
                exit;
            }

            // Verificar que el servicio pertenece al prestador
            $stmtCheck = $db->prepare("SELECT id FROM servicios_precios WHERE id = :sid AND prestador_id = :pid");
            $stmtCheck->execute(['sid' => $servicioId, 'pid' => $prestadorId]);
            if (!$stmtCheck->fetch()) {
                echo json_encode(['status' => 'error', 'message' => 'Servicio no encontrado.']);
                exit;
            }

            $db->beginTransaction();

            $stmtUpd = $db->prepare("
                UPDATE servicios_precios SET 
                    nombre_servicio = :nombre, precio = :precio, duracion_minutos = :duracion,
                    descripcion_servicio = :descripcion, activo = :activo
                WHERE id = :sid
            ");
            $stmtUpd->execute([
                'nombre' => $nombreServicio, 'precio' => $precio, 'duracion' => $duracion,
                'descripcion' => $descripcion, 'activo' => $activo, 'sid' => $servicioId
            ]);

            // Eliminar planes antiguos y reinsertar
            $stmtDelPlanes = $db->prepare("DELETE FROM planes_servicio WHERE servicio_id = :sid");
            $stmtDelPlanes->execute(['sid' => $servicioId]);

            $contadorPlanes = 0;
            foreach ($planes as $plan) {
                if ($contadorPlanes >= 10) break;
                $nombrePlan = trim($plan['nombre_plan'] ?? '');
                $precioPlan = floatval($plan['precio'] ?? 0);
                $duracionPlan = intval($plan['duracion_minutos'] ?? $duracion);
                $descPlan = trim($plan['descripcion_plan'] ?? '');

                if (!empty($nombrePlan) && $precioPlan > 0) {
                    $stmtPlan = $db->prepare("
                        INSERT INTO planes_servicio (servicio_id, nombre_plan, descripcion_plan, precio, duracion_minutos) 
                        VALUES (:sid, :nombre, :descripcion, :precio, :duracion)
                    ");
                    $stmtPlan->execute([
                        'sid' => $servicioId, 'nombre' => $nombrePlan,
                        'descripcion' => $descPlan, 'precio' => $precioPlan, 'duracion' => $duracionPlan
                    ]);
                    $contadorPlanes++;
                }
            }

            // Recalcular activo_etiqueta automáticamente
            $stmtServActivos = $db->prepare("SELECT COUNT(*) FROM servicios_precios WHERE prestador_id = :pid AND activo = 1");
            $stmtServActivos->execute(['pid' => $prestadorId]);
            $tieneServiciosActivos = $stmtServActivos->fetchColumn() > 0;

            $stmtPerfilCheck = $db->prepare("SELECT especialidad, direccion_fisica FROM perfiles_prestadores WHERE id = :pid");
            $stmtPerfilCheck->execute(['pid' => $prestadorId]);
            $perfil = $stmtPerfilCheck->fetch();
            $tienePerfilCompleto = $perfil && !empty($perfil['especialidad']) && !empty($perfil['direccion_fisica']);

            $nuevoEstado = ($tieneServiciosActivos && $tienePerfilCompleto) ? 1 : 0;
            $stmtUpdEtiqueta = $db->prepare("UPDATE perfiles_prestadores SET activo_etiqueta = :activo WHERE id = :pid");
            $stmtUpdEtiqueta->execute(['activo' => $nuevoEstado, 'pid' => $prestadorId]);

            $db->commit();
            echo json_encode(['status' => 'success', 'message' => 'Servicio actualizado correctamente.']);
            exit;
        }

        // ============================================================
        // Eliminar servicio
        // ============================================================
        if ($accion === 'eliminar') {
            $servicioId = intval($input['servicio_id'] ?? 0);
            if ($servicioId <= 0) {
                echo json_encode(['status' => 'error', 'message' => 'ID inválido.']);
                exit;
            }

            // Verificar pertenencia
            $stmtCheck = $db->prepare("SELECT id FROM servicios_precios WHERE id = :sid AND prestador_id = :pid");
            $stmtCheck->execute(['sid' => $servicioId, 'pid' => $prestadorId]);
            if (!$stmtCheck->fetch()) {
                echo json_encode(['status' => 'error', 'message' => 'Servicio no encontrado.']);
                exit;
            }

            $stmtDel = $db->prepare("DELETE FROM servicios_precios WHERE id = :sid");
            $stmtDel->execute(['sid' => $servicioId]);

            // Recalcular activo_etiqueta: si ya no hay servicios activos, desactivar
            $stmtServActivos = $db->prepare("SELECT COUNT(*) FROM servicios_precios WHERE prestador_id = :pid AND activo = 1");
            $stmtServActivos->execute(['pid' => $prestadorId]);
            $tieneServiciosActivos = $stmtServActivos->fetchColumn() > 0;

            if (!$tieneServiciosActivos) {
                $stmtUpdEtiqueta = $db->prepare("UPDATE perfiles_prestadores SET activo_etiqueta = 0 WHERE id = :pid");
                $stmtUpdEtiqueta->execute(['pid' => $prestadorId]);
            }

            echo json_encode(['status' => 'success', 'message' => 'Servicio eliminado correctamente.']);
            exit;
        }

        echo json_encode(['status' => 'error', 'message' => 'Acción no válida.']);
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'Método no permitido.']);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    error_log(date('[Y-m-d H:i:s] ') . "API Servicios Error: " . $e->getMessage() . "\n", 3, __DIR__ . '/../../logs/errores.log');
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
