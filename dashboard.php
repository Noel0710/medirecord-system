<?php
include 'config.php';
requireAuth();

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_type = $_SESSION['user_type'];

// Obtener información del usuario
$user = getCurrentUser();

// Obtener medicamentos del usuario (usando la función corregida)
$medications = getUserMedications($user_id, $user_type);

// Obtener próxima medicación (usando la función corregida)
$next_medication = getNextMedication($user_id, $user_type);

// Obtener estadísticas del usuario (usando la función corregida)
$stats = getUserStats($user_id, $user_type);

// Obtener historial reciente de tomas (últimas 5)
if ($user_type === 'paciente') {
    $stmt = $pdo->prepare("
        SELECT m.nombre_medicamento, ht.fecha_hora_toma, ht.estado 
        FROM historial_tomas ht
        JOIN horarios h ON ht.id_horario = h.id_horario
        JOIN medicamentos m ON h.id_medicamento = m.id_medicamento
        WHERE m.id_usuario = ?
        ORDER BY ht.fecha_hora_toma DESC
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
} else {
    // Para cuidadores, mostrar historial de todos sus pacientes
    $stmt = $pdo->prepare("
        SELECT m.nombre_medicamento, ht.fecha_hora_toma, ht.estado, p.nombre as paciente_nombre
        FROM historial_tomas ht
        JOIN horarios h ON ht.id_horario = h.id_horario
        JOIN medicamentos m ON h.id_medicamento = m.id_medicamento
        JOIN usuarios p ON m.id_usuario = p.id_usuario
        JOIN vinculaciones v ON m.id_usuario = v.id_paciente AND v.id_cuidador = ?
        WHERE v.confirmado = 1
        ORDER BY ht.fecha_hora_toma DESC
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
}
$recent_history = $stmt->fetchAll();

// Verificar configuración de WhatsApp
$stmt = $pdo->prepare("SELECT telefono FROM usuarios WHERE id_usuario = ?");
$stmt->execute([$user_id]);
$usuario = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediRecord - Panel Principal</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>MediRecord</h1>
            <div class="user-info">
                Hola, <strong><?php echo $user_name; ?></strong> (<?php echo $user_type; ?>) | 
                <a href="profile.php">Perfil</a> | 
                <a href="logout.php">Cerrar Sesión</a>
            </div>
        </header>

        <?php displayFlashMessage(); ?>

        <div class="dashboard">
            <!-- Sección de bienvenida y acciones rápidas -->
            <div class="welcome-section">
                <h2>Bienvenido/a a tu panel de control</h2>
                <div class="quick-actions">
                    <a href="add_medication.php" class="btn btn-large">➕ Añadir Medicamento</a>
                    <a href="medications.php" class="btn btn-large">📋 Ver Medicamentos</a>
                    <?php if ($user_type === 'paciente'): ?>
                        <a href="caregivers.php" class="btn btn-large">👥 Gestionar Cuidadores</a>
                    <?php else: ?>
                        <a href="patients.php" class="btn btn-large">👥 Mis Pacientes</a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sección de próxima medicación -->
            <div class="dashboard-section">
                <h2>Próxima Medicación</h2>
                <?php if ($next_medication): ?>
                    <div class="next-medication-card">
                        <h3><?php echo htmlspecialchars($next_medication['nombre_medicamento']); ?></h3>
                        <p><strong>Dosis:</strong> <?php echo htmlspecialchars($next_medication['dosis']); ?></p>
                        <p><strong>Hora:</strong> <?php echo date('h:i A', strtotime($next_medication['hora'])); ?></p>
                        <?php if (isset($next_medication['paciente_nombre'])): ?>
                            <p><strong>Paciente:</strong> <?php echo htmlspecialchars($next_medication['paciente_nombre']); ?></p>
                        <?php endif; ?>
                        
                        <!-- Formulario para confirmar toma -->
                        <form method="POST" action="confirm_medication.php" class="confirm-form">
                            <input type="hidden" name="horario_id" value="<?php echo $next_medication['id_horario']; ?>">
                            <input type="hidden" name="estado" value="tomado">
                            <button type="submit" class="btn btn-success" onclick="return confirm('¿Confirmas que has tomado este medicamento?')">
                                ✅ Confirmar Toma
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <p>No hay medicamentos programados para el resto del día.</p>
                <?php endif; ?>
            </div>

            <!-- Sección de estadísticas -->
            <div class="dashboard-section">
                <h2>Mis Estadísticas</h2>
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3>Medicamentos</h3>
                        <p class="stat-number"><?php echo $stats['total_medicamentos']; ?></p>
                        <p>
                            <?php 
                            if ($user_type === 'cuidador') {
                                echo 'Total pacientes';
                            } else {
                                echo 'Total registrados';
                            }
                            ?>
                        </p>
                    </div>
                    <div class="stat-card">
                        <h3>Tomas Hoy</h3>
                        <p class="stat-number"><?php echo $stats['tomas_hoy']; ?></p>
                        <p>Confirmadas hoy</p>
                    </div>
                    <div class="stat-card">
                        <h3>Tomas Totales</h3>
                        <p class="stat-number"><?php echo $stats['total_tomas']; ?></p>
                        <p>Historial total</p>
                    </div>
                </div>
            </div>

            <!-- Sección de medicamentos de hoy -->
            <div class="dashboard-section">
                <h2>Medicamentos de Hoy</h2>
                <?php 
                // Obtener medicamentos para hoy
                if ($user_type === 'paciente') {
                    $stmt = $pdo->prepare("
                        SELECT m.nombre_medicamento, m.dosis, h.hora, h.id_horario 
                        FROM medicamentos m 
                        JOIN horarios h ON m.id_medicamento = h.id_medicamento 
                        WHERE m.id_usuario = ? AND h.activo = 1
                        ORDER BY h.hora ASC
                    ");
                    $stmt->execute([$user_id]);
                } else {
                    $stmt = $pdo->prepare("
                        SELECT m.nombre_medicamento, m.dosis, h.hora, h.id_horario, p.nombre as paciente_nombre
                        FROM medicamentos m 
                        JOIN horarios h ON m.id_medicamento = h.id_medicamento 
                        JOIN usuarios p ON m.id_usuario = p.id_usuario
                        JOIN vinculaciones v ON m.id_usuario = v.id_paciente AND v.id_cuidador = ?
                        WHERE h.activo = 1 AND v.confirmado = 1
                        ORDER BY p.nombre, h.hora ASC
                    ");
                    $stmt->execute([$user_id]);
                }
                $today_medications = $stmt->fetchAll();
                ?>
                
                <?php if ($today_medications): ?>
                    <div class="medication-list">
                        <?php foreach ($today_medications as $med): ?>
                            <div class="medication-item">
                                <div class="med-info">
                                    <h4><?php echo htmlspecialchars($med['nombre_medicamento']); ?></h4>
                                    <p>
                                        <?php echo htmlspecialchars($med['dosis']); ?> - 
                                        <?php echo date('h:i A', strtotime($med['hora'])); ?>
                                        <?php if (isset($med['paciente_nombre'])): ?>
                                            <br><small><strong>Paciente:</strong> <?php echo htmlspecialchars($med['paciente_nombre']); ?></small>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <div class="med-actions">
                                    <form method="POST" action="confirm_medication.php" class="confirm-form">
                                        <input type="hidden" name="horario_id" value="<?php echo $med['id_horario']; ?>">
                                        <input type="hidden" name="estado" value="tomado">
                                        <button type="submit" class="btn-small" onclick="return confirm('¿Confirmas que has tomado <?php echo htmlspecialchars($med['nombre_medicamento']); ?>?')">
                                            ✅ Tomado
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p>No hay medicamentos programados para hoy.</p>
                <?php endif; ?>
            </div>

            <!-- Sección de historial reciente -->
            <div class="dashboard-section">
                <h2>Historial Reciente</h2>
                <?php if ($recent_history): ?>
                    <div class="history-list">
                        <?php foreach ($recent_history as $record): ?>
                            <div class="history-item">
                                <span class="med-name"><?php echo htmlspecialchars($record['nombre_medicamento']); ?></span>
                                <?php if (isset($record['paciente_nombre'])): ?>
                                    <span class="med-patient">(<?php echo htmlspecialchars($record['paciente_nombre']); ?>)</span>
                                <?php endif; ?>
                                <span class="med-time"><?php echo date('d/m H:i', strtotime($record['fecha_hora_toma'])); ?></span>
                                <span class="med-status <?php echo $record['estado']; ?>">
                                    <?php 
                                    $estados = [
                                        'tomado' => '✅ Tomado',
                                        'omitido' => '❌ Omitido', 
                                        'pospuesto' => '⏰ Pospuesto'
                                    ];
                                    echo $estados[$record['estado']] ?? $record['estado'];
                                    ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <a href="history.php" class="btn">Ver Historial Completo</a>
                <?php else: ?>
                    <p>No hay historial de tomas registrado.</p>
                <?php endif; ?>
            </div>

            <!-- Sección de estado de WhatsApp -->
            <div class="dashboard-section">
                <h2>💬 Estado de WhatsApp</h2>
                <?php if (empty($usuario['telefono'])): ?>
                    <div class="alert alert-warning">
                        <p>⚠️ <strong>WhatsApp no configurado</strong></p>
                        <p>Para recibir recordatorios por WhatsApp, agrega tu número de teléfono en tu perfil.</p>
                        <a href="profile.php" class="btn">Configurar WhatsApp</a>
                    </div>
                <?php else: ?>
                    <div class="alert alert-success">
                        <p>✅ <strong>WhatsApp activado</strong></p>
                        <p>Recibirás recordatorios en: <strong><?php echo htmlspecialchars($usuario['telefono']); ?></strong></p>
                        <p><small>Los recordatorios se envían 5 minutos antes de cada medicamento.</small></p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Sección de notificaciones -->
            <div class="dashboard-section">
                <h2>Notificaciones</h2>
                <p>Activa las notificaciones para recibir recordatorios en tu dispositivo.</p>
                <button class="btn" onclick="requestNotificationPermission()">🔔 Activar Notificaciones</button>
            </div>
        </div>
    </div>

    <script>
        function requestNotificationPermission() {
            if ('Notification' in window) {
                Notification.requestPermission().then(permission => {
                    if (permission === 'granted') {
                        alert('Notificaciones activadas correctamente.');
                        
                        // Mostrar notificación de prueba
                        if (Notification.permission === 'granted') {
                            new Notification('MediRecord - Notificaciones', {
                                body: 'Las notificaciones han sido activadas correctamente.',
                                icon: '/icon.png'
                            });
                        }
                    } else {
                        alert('Permiso de notificaciones denegado.');
                    }
                });
            } else {
                alert('Tu navegador no soporta notificaciones.');
            }
        }

        // Verificar cada minuto si hay medicamentos próximos
        setInterval(() => {
            const now = new Date();
            const currentTime = now.getHours() + ':' + (now.getMinutes() < 10 ? '0' : '') + now.getMinutes();
            
            // En una implementación real, aquí compararíamos con los horarios de la BD
            // Por ahora es un ejemplo básico
            console.log('Verificando medicamentos a las:', currentTime);
            
        }, 60000);
    </script>
</body>
</html>