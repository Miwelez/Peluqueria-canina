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



// catálogo de razas 

$razas = $pdo->query('
    SELECT raza_id, nombre_raza, tamano_raza, tipo_pelo_raza
    FROM razas
    ORDER BY nombre_raza
')->fetchAll();


// --- clientes del usuario ---

if ($_SESSION['rol'] === 'empleador') {
    $stmt = $pdo->prepare('
        SELECT cl.cliente_id, cl.nombre_cliente, cl.telefono_cliente,
               c.centro_id, c.nombre_centro
        FROM clientes cl
        INNER JOIN centros c ON c.centro_id = cl.centro_id
        WHERE c.empleador_id = ? AND cl.activo = 1
        ORDER BY c.nombre_centro, cl.nombre_cliente
    ');
    $stmt->execute([$_SESSION['usuario_id']]);
} else {
    $stmt = $pdo->prepare('
        SELECT cl.cliente_id, cl.nombre_cliente, cl.telefono_cliente,
               c.centro_id, c.nombre_centro
        FROM clientes cl
        INNER JOIN centros c ON c.centro_id = cl.centro_id
        INNER JOIN usuario_centro uc ON uc.centro_id = c.centro_id
        WHERE uc.usuario_id = ? AND cl.activo = 1
        ORDER BY c.nombre_centro, cl.nombre_cliente
    ');
    $stmt->execute([$_SESSION['usuario_id']]);
}
$clientes = $stmt->fetchAll();


// --- perros de esos clientes (una sola consulta IN para no hacer N+1) ---

$perros = [];
if (!empty($clientes)) {
    $ids = array_column($clientes, 'cliente_id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $stmt = $pdo->prepare("
        SELECT p.perro_id, p.nombre_perro, p.cliente_id, p.raza_id, r.nombre_raza
        FROM perros p
        INNER JOIN razas r ON r.raza_id = p.raza_id
        WHERE p.cliente_id IN ($placeholders) AND p.activo = 1
        ORDER BY p.nombre_perro
    ");
    $stmt->execute($ids);
    $perros = $stmt->fetchAll();
}


// agrupamos los perros bajo cada cliente para pintar la tabla
$clientesPorId = [];
foreach ($clientes as $c) {
    $c['perros'] = [];
    $clientesPorId[$c['cliente_id']] = $c;
}
foreach ($perros as $p) {
    $clientesPorId[$p['cliente_id']]['perros'][] = $p;
}
?>


                    <!--HEADER-->
<div class="seccion-header">
    <h2>Clientes</h2>
    <?php if (!empty($mis_centros)): ?>
        <button type="button" id="btn-añadir-cliente" class="btn-primario">+ Añadir cliente</button>
    <?php endif; ?>
</div>



<!-- mensajes flash que vienen del POST-redirect-GET de los endpoints -->
<?php if (isset($_GET['ok'])):
    $okMsg = match ($_GET['ok']) {
        'creado' => 'Cliente añadido correctamente.',
        'perro_creado' => 'Perro añadido correctamente.',
        'cliente_borrado' => 'Cliente eliminado correctamente.',
        'perro_borrado' => 'Perro eliminado correctamente.',
        'cliente_editado' => 'Cliente actualizado.',
        'perro_editado' => 'Perro actualizado.',
        default => 'Acción completada.',
    };
?>


    <div class="seccion-ok"><?= $okMsg ?></div>


<?php endif; ?>


<?php if (isset($_GET['error'])):
    $msg = match ($_GET['error']) {
        'campos' => 'Faltan campos por rellenar.',
        'permiso' => 'No tienes permiso para esa acción.',
        'raza' => 'La raza seleccionada no existe.',
        'transaccion' => 'No se pudo completar el borrado. Inténtalo de nuevo.',
        default => 'Ha ocurrido un error.',
    };
?>
    <div class="seccion-error"><?= $msg ?></div>
<?php endif; ?>




<?php if (empty($mis_centros)): ?>
    <p class="tabla-vacia">No estás asignado a ningún centro.<br>Únete a uno desde tu perfil para poder gestionar clientes.</p>
<?php endif; ?>

<!-- panel desplegable para crear cliente (oculto por defecto) -->
<?php if (!empty($mis_centros)): ?>
<div id="panel-añadir-cliente" class="panel-busqueda" style="display:none">

    <form method="POST" action="acciones/guardar_cliente.php" class="form-inline">
        <input type="hidden" name="accion" value="crear">
        <input type="text" name="nombre_cliente" placeholder="Nombre del cliente" required maxlength="100">
        <input type="text" name="telefono_cliente" placeholder="Teléfono" required maxlength="20">

        <?php if (count($mis_centros) > 1): ?>

            <select name="centro_id" required>
                <option value="">Centro...</option>
                <?php foreach ($mis_centros as $c): ?>
                    <option value="<?= $c['centro_id'] ?>"><?= htmlspecialchars($c['nombre_centro']) ?></option>
                <?php endforeach; ?>
            </select>

        <?php else: ?>
            <input type="hidden" name="centro_id" value="<?= $mis_centros[0]['centro_id'] ?>">
        <?php endif; ?>

        <button type="submit" class="btn-primario">Guardar</button>
    </form>
</div>
<?php endif; ?>

<?php if (empty($clientesPorId) && !empty($mis_centros)): ?>
    <p class="tabla-vacia">Aún no hay clientes.<br>Pulsa "+ Añadir cliente" para crear el primero.</p>
<?php elseif (!empty($clientesPorId)): ?>
    <input type="text" id="buscador-clientes" class="buscador-tabla"
           placeholder="Buscar por nombre, teléfono, centro o perro..."
           autocomplete="off">
    <table class="tabla-dashboard">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Teléfono</th>
                <th>Centro</th>
                <th>Perros</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($clientesPorId as $cliente):
                $nombresPerros = implode(', ', array_column($cliente['perros'], 'nombre_perro'));
            ?>
                <tr>
                    <td><?= htmlspecialchars($cliente['nombre_cliente']) ?></td>
                    <td><?= htmlspecialchars($cliente['telefono_cliente']) ?></td>
                    <td><?= htmlspecialchars($cliente['nombre_centro']) ?></td>
                    <td>
                        <?php foreach ($cliente['perros'] as $perro): ?>
                            <span class="chip-perro" title="<?= htmlspecialchars($perro['nombre_raza']) ?>">
                                <button type="button" class="chip-nombre btn-editar-perro"
                                        data-id="<?= $perro['perro_id'] ?>"
                                        data-nombre="<?= htmlspecialchars($perro['nombre_perro'], ENT_QUOTES) ?>"
                                        data-raza-id="<?= $perro['raza_id'] ?>"
                                        title="Editar perro">
                                    <?= htmlspecialchars($perro['nombre_perro']) ?>
                                </button>
                                <button type="button" class="chip-cerrar btn-quitar-perro"
                                        data-id="<?= $perro['perro_id'] ?>"
                                        data-nombre="<?= htmlspecialchars($perro['nombre_perro'], ENT_QUOTES) ?>"
                                        title="Eliminar perro">×</button>
                            </span>
                        <?php endforeach; ?>
                        <?php if (empty($cliente['perros'])): ?>
                            <span class="chip-perro chip-vacio">Sin perros...</span>
                        <?php endif; ?>
                        <button type="button" class="btn-añadir-perro"
                                data-cliente-id="<?= $cliente['cliente_id'] ?>"
                                data-cliente-nombre="<?= htmlspecialchars($cliente['nombre_cliente'], ENT_QUOTES) ?>"
                                title="Añadir perro">+</button>
                    </td>
                    <td class="tabla-acciones">
                        <button type="button" class="btn-editar btn-editar-cliente"
                                data-id="<?= $cliente['cliente_id'] ?>"
                                data-nombre="<?= htmlspecialchars($cliente['nombre_cliente'], ENT_QUOTES) ?>"
                                data-telefono="<?= htmlspecialchars($cliente['telefono_cliente'], ENT_QUOTES) ?>"
                                title="Editar cliente">
                            <i class="fa-solid fa-pen"></i>
                        </button>
                        <button type="button" class="btn-quitar btn-quitar-cliente"
                                data-id="<?= $cliente['cliente_id'] ?>"
                                data-nombre="<?= htmlspecialchars($cliente['nombre_cliente'], ENT_QUOTES) ?>"
                                data-perros="<?= htmlspecialchars($nombresPerros, ENT_QUOTES) ?>"
                                title="Eliminar cliente">×</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            <tr id="fila-sin-resultados" class="fila-sin-resultados" style="display:none">
                <td colspan="5">No hay clientes que coincidan.</td>
            </tr>
        </tbody>
    </table>
<?php endif; ?>




<!-- modal: añadir perro -->

<?php if (!empty($mis_centros)): ?>
<div id="modal-añadir-perro" class="modal-overlay" style="display:none">
    <div class="modal-caja modal-form">
        <h3>Añadir perro</h3>
        <p class="modal-subtexto">Cliente: <strong id="modal-perro-cliente-nombre"></strong></p>

        <form method="POST" action="acciones/guardar_perro.php">
            <input type="hidden" name="accion" value="crear">
            <input type="hidden" name="cliente_id" id="modal-perro-cliente-id">

            <input type="text" name="nombre_perro" placeholder="Nombre del perro" required maxlength="100">

            <select name="raza_id" id="modal-perro-raza" required>
                <option value="">Elige una raza...</option>
                <?php foreach ($razas as $r): ?>
                    <option value="<?= $r['raza_id'] ?>"><?= htmlspecialchars($r['nombre_raza']) ?></option> <!--Creamos una opcion por cada raza -->
                <?php endforeach; ?>
            </select>

            <div id="info-raza" class="info-raza" style="display:none">
                <span>Tamaño: <strong id="info-tamano">—</strong></span>
                <span>Pelo: <strong id="info-pelo">—</strong></span>
            </div>

            <div class="modal-botones">
                <button type="button" class="btn-secundario" id="cancelar-añadir-perro">Cancelar</button>
                <button type="submit" class="btn-primario">Guardar perro</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>




<!-- modal: borrar cliente -->

<div id="modal-borrar-cliente" class="modal-overlay" style="display:none">
    <div class="modal-caja">
        <p class="modal-texto">
            ¿Eliminar a <strong id="modal-cli-nombre"></strong>?
            <span id="modal-cli-aviso-perros" class="modal-aviso" style="display:none">
                <br>También se eliminarán sus perros: <strong id="modal-cli-perros"></strong>.
            </span>
        </p>
        <div class="modal-botones">
            <button type="button" id="cancelar-borrar-cliente" class="btn-secundario">Cancelar</button>
            <form method="POST" action="acciones/guardar_cliente.php" style="display:inline">
                <input type="hidden" name="accion" value="quitar">
                <input type="hidden" name="cliente_id" id="modal-cli-id">
                <button type="submit" class="btn-peligro">Eliminar</button>
            </form>
        </div>
    </div>
</div>



<!-- modal: borrar perro -->

<div id="modal-borrar-perro" class="modal-overlay" style="display:none">
    <div class="modal-caja">
        <p class="modal-texto">¿Eliminar a <strong id="modal-perro-nombre"></strong>?</p>
        <div class="modal-botones">
            <button type="button" id="cancelar-borrar-perro" class="btn-secundario">Cancelar</button>
            <form method="POST" action="acciones/guardar_perro.php" style="display:inline">
                <input type="hidden" name="accion" value="quitar">
                <input type="hidden" name="perro_id" id="modal-perro-borrar-id">
                <button type="submit" class="btn-peligro">Eliminar</button>
            </form>
        </div>
    </div>
</div>


<!-- modal: editar cliente -->


<div id="modal-editar-cliente" class="modal-overlay" style="display:none">
    <div class="modal-caja modal-form">
        <h3>Editar cliente</h3>
        <form method="POST" action="acciones/guardar_cliente.php">
            <input type="hidden" name="accion" value="editar">
            <input type="hidden" name="cliente_id" id="edit-cli-id">

            <input type="text" name="nombre_cliente" id="edit-cli-nombre" placeholder="Nombre" required maxlength="100">
            <input type="text" name="telefono_cliente" id="edit-cli-telefono" placeholder="Teléfono" required maxlength="20">

            <div class="modal-botones">
                <button type="button" id="cancelar-editar-cliente" class="btn-secundario">Cancelar</button>
                <button type="submit" class="btn-primario">Guardar cambios</button>
            </div>
        </form>
    </div>
</div>



<!-- modal: editar perro -->

<?php if (!empty($mis_centros)): ?>
<div id="modal-editar-perro" class="modal-overlay" style="display:none">
    <div class="modal-caja modal-form">
        <h3>Editar perro</h3>
        <form method="POST" action="acciones/guardar_perro.php">
            <input type="hidden" name="accion" value="editar">
            <input type="hidden" name="perro_id" id="edit-perro-id">

            <input type="text" name="nombre_perro" id="edit-perro-nombre" placeholder="Nombre del perro" required maxlength="100">

            <select name="raza_id" id="edit-perro-raza" required>
                <option value="">Elige una raza...</option>
                <?php foreach ($razas as $r): ?>
                    <option value="<?= $r['raza_id'] ?>"><?= htmlspecialchars($r['nombre_raza']) ?></option>
                <?php endforeach; ?>
            </select>

            <div id="edit-info-raza" class="info-raza" style="display:none">
                <span>Tamaño: <strong id="edit-info-tamano">—</strong></span>
                <span>Pelo: <strong id="edit-info-pelo">—</strong></span>
            </div>

            <div class="modal-botones">
                <button type="button" id="cancelar-editar-perro" class="btn-secundario">Cancelar</button>
                <button type="submit" class="btn-primario">Guardar cambios</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
// --- mensajes flash: auto-ocultar y limpiar la URL ---
const flashes = document.querySelectorAll('.seccion-ok, .seccion-error');
if (flashes.length > 0) {
    setTimeout(() => flashes.forEach(f => f.classList.add('flash-ocultar')), 4000);
    // quitamos ?ok=... / ?error=... para que un F5 no vuelva a mostrar el banner
    const url = new URL(window.location.href);
    url.searchParams.delete('ok');
    url.searchParams.delete('error');
    history.replaceState({}, '', url);
}

// catálogo de razas indexado por id para acceso O(1) desde el modal
const RAZAS = <?= json_encode(array_column($razas, null, 'raza_id')) ?>;
const TAMANOS = { mini: 'Mini', pequenio: 'Pequeño', mediano: 'Mediano', grande: 'Grande', gigante: 'Gigante' };
const PELOS = { corto: 'Corto', medio: 'Medio', largo: 'Largo', rizado: 'Rizado', duro: 'Duro' };


// --- abrir/cerrar el panel de añadir cliente ---
const btnAdd = document.getElementById('btn-añadir-cliente');
const panel = document.getElementById('panel-añadir-cliente');
if (btnAdd && panel) {
    btnAdd.addEventListener('click', () => {
        const visible = panel.style.display !== 'none';
        panel.style.display = visible ? 'none' : 'block';
        if (!visible) {
            panel.querySelector('input[name="nombre_cliente"]').focus();
        }
    });
}


// --- Modal de añadir perro ---
const modalPerro = document.getElementById('modal-añadir-perro');
if (modalPerro) {
    const selectRaza = document.getElementById('modal-perro-raza');
    const infoRaza = document.getElementById('info-raza');
    const infoTamano = document.getElementById('info-tamano');
    const infoPelo = document.getElementById('info-pelo');
    const inputCliente = document.getElementById('modal-perro-cliente-id');
    const nombreCliente = document.getElementById('modal-perro-cliente-nombre');
    const formPerro = modalPerro.querySelector('form');

    document.querySelectorAll('.btn-añadir-perro').forEach(btn => {
        btn.addEventListener('click', () => {
            formPerro.reset();
            infoRaza.style.display = 'none';
            inputCliente.value = btn.dataset.clienteId;
            nombreCliente.textContent = btn.dataset.clienteNombre;
            modalPerro.style.display = 'flex';
            formPerro.querySelector('input[name="nombre_perro"]').focus();
        });
    });

    document.getElementById('cancelar-añadir-perro').addEventListener('click', () => {
        modalPerro.style.display = 'none';
    });
    modalPerro.addEventListener('click', e => {
        if (e.target === modalPerro) {
            modalPerro.style.display = 'none';
        }
    });


    // al cambiar de raza pintamos la cajita con tamaño y tipo de pelo

    selectRaza.addEventListener('change', () => {
        const rid = selectRaza.value;
        if (!rid || !RAZAS[rid]) {
            infoRaza.style.display = 'none';
            return;
        }
        const r = RAZAS[rid];
        infoTamano.textContent = TAMANOS[r.tamano_raza] || r.tamano_raza;
        infoPelo.textContent = PELOS[r.tipo_pelo_raza] || r.tipo_pelo_raza;
        infoRaza.style.display = 'flex';
    });
}


// --- Modal de borrar cliente ---
const modalBorrarCli = document.getElementById('modal-borrar-cliente');
const avisoPerros = document.getElementById('modal-cli-aviso-perros');
document.querySelectorAll('.btn-quitar-cliente').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('modal-cli-id').value = btn.dataset.id;
        document.getElementById('modal-cli-nombre').textContent = btn.dataset.nombre;
        const perros = btn.dataset.perros;
        if (perros) {
            document.getElementById('modal-cli-perros').textContent = perros;
            avisoPerros.style.display = 'inline';
        } else {
            avisoPerros.style.display = 'none';
        }
        modalBorrarCli.style.display = 'flex';
    });
});
document.getElementById('cancelar-borrar-cliente').addEventListener('click', () => {
    modalBorrarCli.style.display = 'none';
});
modalBorrarCli.addEventListener('click', e => {
    if (e.target === modalBorrarCli) {
        modalBorrarCli.style.display = 'none';
    }
});


// --- Modal de borrar perro ---


const modalBorrarPerro = document.getElementById('modal-borrar-perro');
document.querySelectorAll('.btn-quitar-perro').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('modal-perro-borrar-id').value = btn.dataset.id;
        document.getElementById('modal-perro-nombre').textContent = btn.dataset.nombre;
        modalBorrarPerro.style.display = 'flex';
    });
});
document.getElementById('cancelar-borrar-perro').addEventListener('click', () => {
    modalBorrarPerro.style.display = 'none';
});
modalBorrarPerro.addEventListener('click', e => {
    if (e.target === modalBorrarPerro) {
        modalBorrarPerro.style.display = 'none';
    }
});



// --- Modal de editar cliente ---

const modalEditCli = document.getElementById('modal-editar-cliente');
document.querySelectorAll('.btn-editar-cliente').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('edit-cli-id').value = btn.dataset.id;
        document.getElementById('edit-cli-nombre').value = btn.dataset.nombre;
        document.getElementById('edit-cli-telefono').value = btn.dataset.telefono;
        modalEditCli.style.display = 'flex';
        document.getElementById('edit-cli-nombre').focus();
    });
});
document.getElementById('cancelar-editar-cliente').addEventListener('click', () => {
    modalEditCli.style.display = 'none';
});
modalEditCli.addEventListener('click', e => {
    if (e.target === modalEditCli) {
        modalEditCli.style.display = 'none';
    }
});



// --- Modal de editar perro ---
const modalEditPerro = document.getElementById('modal-editar-perro');
if (modalEditPerro) {
    const selRazaEd = document.getElementById('edit-perro-raza');
    const infoRazaEd = document.getElementById('edit-info-raza');
    const tamEd = document.getElementById('edit-info-tamano');
    const peloEd = document.getElementById('edit-info-pelo');

    function pintarInfoRazaEdit() {
        const rid = selRazaEd.value;
        if (!rid || !RAZAS[rid]) { infoRazaEd.style.display = 'none'; return; }
        const r = RAZAS[rid];
        tamEd.textContent = TAMANOS[r.tamano_raza] || r.tamano_raza;
        peloEd.textContent = PELOS[r.tipo_pelo_raza] || r.tipo_pelo_raza;
        infoRazaEd.style.display = 'flex';
    }

    document.querySelectorAll('.btn-editar-perro').forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('edit-perro-id').value = btn.dataset.id;
            document.getElementById('edit-perro-nombre').value = btn.dataset.nombre;
            selRazaEd.value = btn.dataset.razaId;
            pintarInfoRazaEdit();
            modalEditPerro.style.display = 'flex';
            document.getElementById('edit-perro-nombre').focus();
        });
    });
    selRazaEd.addEventListener('change', pintarInfoRazaEdit);

    document.getElementById('cancelar-editar-perro').addEventListener('click', () => {
        modalEditPerro.style.display = 'none';
    });
    modalEditPerro.addEventListener('click', e => {
        if (e.target === modalEditPerro) {
            modalEditPerro.style.display = 'none';
        }
    });
}


// --- Buscador client-side (filtra filas de la tabla sin ir al servidor) ---

const buscadorCli = document.getElementById('buscador-clientes');
const filaSinResult = document.getElementById('fila-sin-resultados');

if (buscadorCli) {
    buscadorCli.addEventListener('input', function () {
        const q = this.value.trim().toLowerCase();
  
        const filas = document.querySelectorAll('.tabla-dashboard tbody tr:not(.fila-sin-resultados)'); // recogemos las filas con datos
        let visibles = 0;

        filas.forEach(tr => {
            const texto = tr.textContent.toLowerCase();
            const coincide = q === '' || texto.includes(q);
            tr.style.display = coincide ? '' : 'none';
            if (coincide) {
                visibles++;
            }
        });

        filaSinResult.style.display = (visibles === 0) ? '' : 'none';
    });
}
</script>
