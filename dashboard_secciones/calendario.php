<?php
require_once 'conexion.php';

// --- centros del usuario ---
if ($_SESSION['rol'] === 'empleador') {
    $stmt = $pdo->prepare('
        SELECT centro_id, nombre_centro
        FROM centros
        WHERE empleador_id = ? AND activo = 1
        ORDER BY nombre_centro
    ');
} else {
    $stmt = $pdo->prepare('
        SELECT c.centro_id, c.nombre_centro
        FROM centros c
        INNER JOIN usuario_centro uc ON uc.centro_id = c.centro_id
        WHERE uc.usuario_id = ? AND c.activo = 1
        ORDER BY c.nombre_centro
    ');
}
$stmt->execute([$_SESSION['usuario_id']]);
$mis_centros = $stmt->fetchAll();
$ids_centros = array_column($mis_centros, 'centro_id');


// --- clientes, perros y empleados de esos centros (para el modal de cita) ---
$mis_clientes = [];
$mis_perros = [];
$mis_empleados = []; // solo se usa si rol = empleador

if (!empty($ids_centros)) {
    $ph = implode(',', array_fill(0, count($ids_centros), '?'));

    $stmt = $pdo->prepare("
        SELECT cliente_id, nombre_cliente, centro_id
        FROM clientes
        WHERE centro_id IN ($ph) AND activo = 1
        ORDER BY nombre_cliente
    ");
    $stmt->execute($ids_centros);
    $mis_clientes = $stmt->fetchAll();

    // perros de esos clientes
    $ids_clientes = array_column($mis_clientes, 'cliente_id');
    if (!empty($ids_clientes)) {
        $ph_c = implode(',', array_fill(0, count($ids_clientes), '?'));
        $stmt = $pdo->prepare("
            SELECT perro_id, nombre_perro, cliente_id
            FROM perros
            WHERE cliente_id IN ($ph_c) AND activo = 1
            ORDER BY nombre_perro
        ");
        $stmt->execute($ids_clientes);
        $mis_perros = $stmt->fetchAll();
    }

    // empleados que trabajan en los centros del empleador
    if ($_SESSION['rol'] === 'empleador') {
        $stmt = $pdo->prepare("
            SELECT DISTINCT u.usuario_id, u.nombre_usuario, uc.centro_id
            FROM usuarios u
            INNER JOIN usuario_centro uc ON uc.usuario_id = u.usuario_id
            WHERE uc.centro_id IN ($ph) AND u.activo = 1
            ORDER BY u.nombre_usuario
        ");
        $stmt->execute($ids_centros);
        $mis_empleados = $stmt->fetchAll();

        // el empleador no está en usuario_centro (es dueño), pero queremos que pueda
        // asignarse citas a sí mismo. Lo añadimos a mano, una entrada por cada centro suyo.
        foreach ($mis_centros as $c) {
            $mis_empleados[] = [
                'usuario_id' => $_SESSION['usuario_id'],
                'nombre_usuario' => $_SESSION['nombre'] . ' (tú)',
                'centro_id' => $c['centro_id'],
            ];
        }
    }
}

// catálogo de servicios (5 fijos, ENUM en BD)
$servicios = $pdo->query('
    SELECT servicio_id, nombre_servicio
    FROM servicios
    WHERE activo = 1
    ORDER BY servicio_id
')->fetchAll();

// lista plana de empleados para el filtro del calendario (sin duplicar por centro)
$empleados_filtro = [];
foreach ($mis_empleados as $e) {
    $empleados_filtro[$e['usuario_id']] = $e['nombre_usuario'];
}
?>

<!-- FullCalendar por CDN, edición Standard  -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>

<div class="seccion-header">
    <h2>Calendario</h2>
    <?php if ($_SESSION['rol'] === 'empleador' && !empty($empleados_filtro)): ?>
        <select id="filtro-empleado" class="select-filtro">
            <option value="">Todos los empleados</option>
            <?php foreach ($empleados_filtro as $uid => $nombre): ?>
                <option value="<?= $uid ?>"><?= htmlspecialchars($nombre) ?></option>
            <?php endforeach; ?>
        </select>
    <?php endif; ?>
</div>

<div id="cal-mensaje" class="seccion-ok" style="display:none"></div>

<div id="calendar"></div>

<?php // --- modal único, sirve para crear y para editar (cambia el título y el comportamiento) --- ?>
<?php if (!empty($mis_centros)): ?>
<div id="modal-cita" class="modal-overlay" style="display:none">
    <div class="modal-caja modal-form modal-form-amplio">
        <h3 id="modal-cita-titulo">Nueva cita</h3>

        <form id="form-cita">
            <input type="hidden" name="accion" value="crear">
            <input type="hidden" name="cita_id" id="cita-id" value="">

            <label class="campo-label">Centro</label>
            <select name="centro_id" id="cita-centro" required>
                <?php foreach ($mis_centros as $c): ?>
                    <option value="<?= $c['centro_id'] ?>"><?= htmlspecialchars($c['nombre_centro']) ?></option>
                <?php endforeach; ?>
            </select>

            <label class="campo-label">Cliente</label>
            <select name="cliente_id" id="cita-cliente" required>
                <option value="">Elige cliente...</option>
            </select>

            <label class="campo-label">Perro</label>
            <select name="perro_id" id="cita-perro" required>
                <option value="">Elige perro...</option>
            </select>

            <label class="campo-label">Servicio</label>
            <select name="servicio_id" id="cita-servicio" required>
                <option value="">Elige servicio...</option>
                <?php
                $nombresServicio = [
                    'banio' => 'Baño', 'rapar' => 'Rapar', 'corte' => 'Corte',
                    'stripping' => 'Stripping', 'deslanado' => 'Deslanado',
                ];
                foreach ($servicios as $s):
                    $etiqueta = $nombresServicio[$s['nombre_servicio']] ?? ucfirst($s['nombre_servicio']);
                ?>
                    <option value="<?= $s['servicio_id'] ?>"><?= htmlspecialchars($etiqueta) ?></option>
                <?php endforeach; ?>
            </select>

            <?php if ($_SESSION['rol'] === 'empleador'): ?>
                <label class="campo-label">Empleado asignado</label>
                <select name="usuario_id" id="cita-empleado" required>
                    <option value="">Elige empleado...</option>
                </select>
            <?php else: ?>
                <input type="hidden" name="usuario_id" value="<?= $_SESSION['usuario_id'] ?>">
            <?php endif; ?>

            <label class="campo-label">Fecha y hora</label>
            <input type="datetime-local" name="fecha_hora" id="cita-fechahora" required>

            <label class="campo-label">Duración (minutos, opcional)</label>
            <input type="number" name="duracion_real" id="cita-duracion" min="1" max="600" placeholder="Vacío = se calcula automáticamente">

            <label class="campo-label">Notas</label>
            <textarea name="notas" id="cita-notas" rows="2" placeholder="Opcional"></textarea>

            <div id="cita-error" class="seccion-error" style="display:none; margin-top:8px"></div>

            <div class="modal-botones modal-botones-tres">
                <button type="button" id="cancelar-cita-existente" class="btn-peligro" style="display:none">Cancelar cita</button>
                <button type="button" id="cerrar-modal-cita" class="btn-secundario">Cerrar</button>
                <button type="submit" id="guardar-cita" class="btn-primario">Guardar cita</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
// catálogos pasados de PHP a JS para los selectores en cascada del modal
const CLIENTES = <?= json_encode($mis_clientes) ?>;
const PERROS = <?= json_encode($mis_perros) ?>;
const EMPLEADOS = <?= json_encode($mis_empleados) ?>; // [{usuario_id, nombre_usuario, centro_id}, ...]

document.addEventListener('DOMContentLoaded', function() {
    const calendarEl = document.getElementById('calendar');
    const filtroEmp = document.getElementById('filtro-empleado');
    const modalCita = document.getElementById('modal-cita');

    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'timeGridWeek',
        locale: 'es',
        firstDay: 1,
        slotMinTime: '08:00:00',
        slotMaxTime: '21:00:00',
        slotDuration: '00:30:00',  // celdas visibles de 30 min
        snapDuration: '00:15:00',  // ajuste fino: mover/redimensionar en saltos de 15 min
        allDaySlot: false,
        nowIndicator: true,
        height: 'calc(100vh - 200px)',  // altura fija → scroll interno, no del body
        selectable: !!modalCita,
        selectMirror: true,
        editable: !!modalCita,
        eventDurationEditable: true,
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'timeGridWeek,timeGridDay'
        },
        
        buttonText: { today: 'Hoy', week: 'Semana', day: 'Día' },

        // pintamos cada cita con dos líneas: "perro — raza" y debajo el servicio
        eventContent: function(arg) {
            const ext = arg.event.extendedProps;
            const cuerpo = document.createElement('div');
            cuerpo.className = 'cita-cuerpo';

            const l1 = document.createElement('div');
            l1.className = 'cita-linea1';
            l1.textContent = ext.nombre_perro + ' — ' + ext.nombre_raza;

            const l2 = document.createElement('div');
            l2.className = 'cita-linea2';
            l2.textContent = ext.nombre_servicio_label || ext.nombre_servicio;

            cuerpo.append(l1, l2);
            return { domNodes: [cuerpo] };
        },

        // FullCalendar nos pasa el rango visible y le devolvemos las citas vía successCallback
        events: function(info, successCallback, failureCallback) {
            const params = new URLSearchParams({ start: info.startStr, end: info.endStr });
            if (filtroEmp && filtroEmp.value) {
                params.append('empleado_id', filtroEmp.value);
            }
            fetch('api/eventos_citas.php?' + params.toString())
                .then(r => r.json())
                .then(successCallback)
                .catch(failureCallback);
        },

        select: function(info) {       // arrastre sobre franja libre → modal de nueva cita
            if (!modalCita) {
                return;
            }
            abrirModalNuevaCita(info.startStr);
            calendar.unselect();
        },
        eventClick: function(info) {   // clic sobre cita → modal en modo edición
            if (!modalCita) {
                return;
            }
            abrirModalEditarCita(info.event);
        },
        eventDrop: function(info) {    // arrastrar a otra hora/día
            guardarCambioRapido(info);
        },
        eventResize: function(info) {  // redimensionar borde inferior (cambia duración)
            guardarCambioRapido(info);
        }
    });

    calendar.render();

    if (filtroEmp) {
        filtroEmp.addEventListener('change', () => calendar.refetchEvents());
    }

    // si el empleado no tiene ningún centro asignado el modal no existe; salimos sin engancharlo
    if (!modalCita) {
        return;
    }


    // --- modal de nueva/editar cita ---
    const selCentro = document.getElementById('cita-centro');
    const selCliente = document.getElementById('cita-cliente');
    const selPerro = document.getElementById('cita-perro');
    const selServicio = document.getElementById('cita-servicio');
    const selEmpleado = document.getElementById('cita-empleado'); // null si rol = empleado
    const inputFecha = document.getElementById('cita-fechahora');
    const inputDur = document.getElementById('cita-duracion');
    const inputNotas = document.getElementById('cita-notas');
    const inputCitaId = document.getElementById('cita-id');
    const errorBox = document.getElementById('cita-error');
    const titulo = document.getElementById('modal-cita-titulo');
    const formCita = document.getElementById('form-cita');
    const btnCancelarCita = document.getElementById('cancelar-cita-existente');

    function formatearFechaParaInput(d) {
        // d es un objeto Date; el input datetime-local quiere "YYYY-MM-DDTHH:mm"
        const pad = n => String(n).padStart(2, '0');
        return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
    }

    function abrirModalNuevaCita(fechaHoraISO) {
        titulo.textContent = 'Nueva cita';
        formCita.reset();
        errorBox.style.display = 'none';
        inputCitaId.value = '';
        btnCancelarCita.style.display = 'none';

        // FullCalendar manda algo como "2026-05-13T10:00:00+02:00";
        // al input datetime-local le valen los 16 primeros caracteres.
        inputFecha.value = fechaHoraISO.substring(0, 16);

        recargarClientes();
        recargarEmpleados();

        modalCita.style.display = 'flex';
    }

    function abrirModalEditarCita(evento) {
        titulo.textContent = 'Editar cita';
        formCita.reset();
        errorBox.style.display = 'none';

        const ext = evento.extendedProps;
        inputCitaId.value = evento.id;

        // asignamos en cascada respetando el orden centro → cliente → perro
        selCentro.value = ext.centro_id;
        recargarClientes();
        selCliente.value = ext.cliente_id;
        recargarPerros();
        selPerro.value = ext.perro_id;

        selServicio.value = ext.servicio_id;

        if (selEmpleado) {
            recargarEmpleados();
            selEmpleado.value = ext.usuario_id;
        }

        inputFecha.value = formatearFechaParaInput(evento.start);
        inputDur.value = ext.duracion_real;
        inputNotas.value = ext.notas || '';

        btnCancelarCita.style.display = 'inline-block';
        modalCita.style.display = 'flex';
    }

    function recargarClientes() {
        const cid = parseInt(selCentro.value, 10);
        const opciones = ['<option value="">Elige cliente...</option>'];
        CLIENTES
            .filter(cl => cl.centro_id == cid)
            .forEach(cl => opciones.push(`<option value="${cl.cliente_id}">${cl.nombre_cliente}</option>`));
        selCliente.innerHTML = opciones.join('');
        recargarPerros(); // al cambiar de cliente toca vaciar los perros
    }

    function recargarPerros() {
        const cliente_id = parseInt(selCliente.value, 10);
        const opciones = ['<option value="">Elige perro...</option>'];
        if (cliente_id) {
            PERROS
                .filter(p => p.cliente_id == cliente_id)
                .forEach(p => opciones.push(`<option value="${p.perro_id}">${p.nombre_perro}</option>`));
        }
        selPerro.innerHTML = opciones.join('');
    }

    function recargarEmpleados() {
        // si el rol es empleado no existe el selector, salimos
        if (!selEmpleado) {
            return;
        }
        const cid = parseInt(selCentro.value, 10);
        const opciones = ['<option value="">Elige empleado...</option>'];
        EMPLEADOS
            .filter(e => e.centro_id == cid)
            .forEach(e => opciones.push(`<option value="${e.usuario_id}">${e.nombre_usuario}</option>`));
        selEmpleado.innerHTML = opciones.join('');
    }

    selCentro.addEventListener('change', () => { recargarClientes(); recargarEmpleados(); });
    selCliente.addEventListener('change', recargarPerros);

    document.getElementById('cerrar-modal-cita').addEventListener('click', () => {
        modalCita.style.display = 'none';
    });
    modalCita.addEventListener('click', e => {
        if (e.target === modalCita) {
            modalCita.style.display = 'none';
        }
    });

    // submit por fetch (no recargamos la página para no perder la posición del calendario)
    formCita.addEventListener('submit', e => {
        e.preventDefault();
        errorBox.style.display = 'none';

        const fd = new FormData(formCita);
        // si hay cita_id estamos editando, si no creando
        fd.set('accion', inputCitaId.value ? 'editar' : 'crear');

        fetch('acciones/guardar_cita.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.ok) {
                    modalCita.style.display = 'none';
                    calendar.refetchEvents();
                    mostrarMensaje(inputCitaId.value ? 'Cita actualizada.' : 'Cita creada correctamente.');
                } else {
                    errorBox.textContent = res.error || 'No se pudo guardar la cita.';
                    errorBox.style.display = 'block';
                }
            })
            .catch(() => {
                errorBox.textContent = 'Error de red al guardar la cita.';
                errorBox.style.display = 'block';
            });
    });

    // cancelar cita = cambio de estado, no borrado físico
    btnCancelarCita.addEventListener('click', () => {
        if (!confirm('¿Cancelar esta cita?\nQuedará marcada como cancelada y desaparecerá del calendario.')) return;

        const fd = new FormData();
        fd.append('accion', 'cancelar');
        fd.append('cita_id', inputCitaId.value);

        fetch('acciones/guardar_cita.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.ok) {
                    modalCita.style.display = 'none';
                    calendar.refetchEvents();
                    mostrarMensaje('Cita cancelada.');
                } else {
                    errorBox.textContent = res.error || 'No se pudo cancelar la cita.';
                    errorBox.style.display = 'block';
                }
            })
            .catch(() => {
                errorBox.textContent = 'Error de red al cancelar la cita.';
                errorBox.style.display = 'block';
            });
    });

    function mostrarMensaje(texto) {
        const box = document.getElementById('cal-mensaje');
        box.textContent = texto;
        box.style.display = 'block';
        box.classList.remove('flash-ocultar');
        setTimeout(() => box.classList.add('flash-ocultar'), 3000);
    }

    // drag & drop / resize: mandamos al servidor la nueva fecha y duración.
    // si rechaza (p. ej. solapamiento) revertimos visualmente el cambio.
    function guardarCambioRapido(info) {
        const ev = info.event;
        const dur = Math.round((ev.end - ev.start) / 60000); // ms → minutos

        const fd = new FormData();
        fd.append('accion', 'mover');
        fd.append('cita_id', ev.id);
        fd.append('fecha_hora', formatearFechaParaInput(ev.start) + ':00');
        fd.append('duracion_real', dur);

        fetch('acciones/guardar_cita.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.ok) {
                    mostrarMensaje('Cita actualizada.');
                    // actualizamos la duración en local para que el modal lo vea bien luego
                    ev.setExtendedProp('duracion_real', dur);
                } else {
                    info.revert();
                    alert(res.error || 'No se pudo mover la cita.');
                }
            })
            .catch(() => {
                info.revert();
                alert('Error de red al mover la cita.');
            });
    }
});
</script>
