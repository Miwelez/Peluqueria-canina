<?php
require_once 'conexion.php';

if ($_SESSION['rol'] !== 'empleador') {
    echo '<p>No tienes permiso para ver esta sección.</p>';
    exit;
}

$stmt = $pdo->prepare('
    SELECT u.usuario_id, u.nombre_usuario, u.telefono_usuario, c.centro_id, c.nombre_centro
    FROM usuarios u
    INNER JOIN usuario_centro uc ON uc.usuario_id = u.usuario_id
    INNER JOIN centros c ON c.centro_id = uc.centro_id
    WHERE c.empleador_id = ? AND u.activo = 1
    ORDER BY c.nombre_centro, u.nombre_usuario
');
$stmt->execute([$_SESSION['usuario_id']]);
$empleados = $stmt->fetchAll();
?>

<div class="seccion-header">
    <h2>Empleados</h2>
    <button type="button" id="btn-añadir-empleado" class="btn-primario">+ Añadir empleado</button>
</div>

<div id="panel-busqueda-empleado" class="panel-busqueda" style="display:none">
    <input type="text" id="buscador-empleado" placeholder="Busca por nombre..." autocomplete="off">
    <ul id="resultados-empleado"></ul>
</div>

<?php if (empty($empleados)): ?>
<!-- comprobamos si hay empleados y si no lanzamos este mensaje -->
    <p class="tabla-vacia">Aún no hay empleados en tus centros. <br>
                           Los empleados pueden unirse desde su perfil.</p>

<?php else: ?>
    <table class="tabla-dashboard">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Teléfono</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($empleados as $e): ?>
                <?php 
                    $usuario_dueno = ($e['usuario_id'] === $_SESSION['usuario_id']); // guardamos true si es el usuario es dueño de la peluqueria
                ?>

                <tr>
                    <td>
                        <?php echo htmlspecialchars($e['nombre_usuario']); ?>
                        <?php if ($usuario_dueno): ?>
                            <span class="etiqueta-yo">Yo</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($e['telefono_usuario'] ?? '—') ?></td>
                    <td>
                        <?php if (!$usuario_dueno): ?>
                            <button type="button" class="btn-quitar"
                                data-id="<?= $e['usuario_id'] ?>"
                                data-centro="<?= $e['centro_id'] ?>"
                                data-nombre="<?= htmlspecialchars($e['nombre_usuario'], ENT_QUOTES) ?>">
                                ×
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>


<!-- modal de confirmación para quitar empleado -->

<div id="modal-overlay" class="modal-overlay" style="display:none">
    <div class="modal-caja">
        <p class="modal-texto">¿Seguro que quieres eliminar a <strong id="modal-nombre"></strong> de tu centro?</p>
        <div class="modal-botones">
            <button type="button" id="modal-cancelar" class="btn-secundario">Cancelar</button>
            <form method="POST" action="acciones/guardar_empleado.php" style="display:inline">
                <input type="hidden" name="accion" value="quitar">
                <input type="hidden" name="usuario_id" id="modal-usuario-id">
                <input type="hidden" name="centro_id" id="modal-centro-id">
                <button type="submit" class="btn-peligro">Eliminar</button>
            </form>
        </div>
    </div>
</div>

<script>


// --- Buscador de empleados ---


const panelBusqueda = document.getElementById('panel-busqueda-empleado');
const buscador = document.getElementById('buscador-empleado');
const resultados = document.getElementById('resultados-empleado');
let timer;

// el botón "+ Añadir empleado" abre/cierra el panel del buscador
document.getElementById('btn-añadir-empleado').addEventListener('click', () => {
    const visible = panelBusqueda.style.display !== 'none';
    panelBusqueda.style.display = visible ? 'none' : 'block';
    if (!visible) {
        buscador.focus();
    }
});

// debounce de 300ms para no machacar al servidor en cada tecla

buscador.addEventListener('input', function () {
    clearTimeout(timer);
    const q = this.value.trim();
    if (q.length < 2) { resultados.innerHTML = ''; return; }
    timer = setTimeout(() => buscarEmpleado(q), 300);
});


function buscarEmpleado(q) {

    fetch('api/buscar_empleados.php?q=' + encodeURIComponent(q))
        .then(r => r.json())
        .then(lista => {
            if (lista.length === 0) {
                resultados.innerHTML = '<li class="resultado-vacio">Sin resultados.</li>';
                return;
            }

            resultados.innerHTML = lista.map(u =>
                `<li class="resultado-item" data-id="${u.usuario_id}" data-nombre="${u.nombre_usuario}">
                    ${u.nombre_usuario} <small>${u.email_usuario}</small>
                </li>`
            ).join('');

            resultados.querySelectorAll('.resultado-item').forEach(li => {
                li.addEventListener('click', () => añadirEmpleado(li.dataset.id, li.dataset.nombre, li));
            });
        });
}

function añadirEmpleado(usuarioId, nombre, li) {
    const fd = new FormData();
    fd.append('accion', 'añadir');
    fd.append('usuario_id', usuarioId);

    fetch('acciones/guardar_empleado.php', { method: 'POST', body: fd })
        .then(() => {
            li.remove();
            buscador.value = '';
            resultados.innerHTML = '';
            panelBusqueda.style.display = 'none';
         
            const tbody = document.querySelector('.tabla-dashboard tbody');
            const tr = document.createElement('tr');
            tr.innerHTML = `<td>${nombre}</td><td>—</td><td></td>`; // metemos al empleado en la tabla sin recargar la página
            tbody.appendChild(tr);
        });
}


// --- Modal de confirmación ---
const overlay = document.getElementById('modal-overlay');
const modalNombre = document.getElementById('modal-nombre');

document.querySelectorAll('.btn-quitar').forEach(btn => {
    btn.addEventListener('click', () => {
        modalNombre.textContent = btn.dataset.nombre;
        document.getElementById('modal-usuario-id').value = btn.dataset.id;
        document.getElementById('modal-centro-id').value = btn.dataset.centro;
        overlay.style.display = 'flex';
    });
});

document.getElementById('modal-cancelar').addEventListener('click', () => {
    overlay.style.display = 'none';
});

// clic fuera de la caja también cierra el modal
overlay.addEventListener('click', e => {
    if (e.target === overlay) {
        overlay.style.display = 'none';
    }
});
</script>
