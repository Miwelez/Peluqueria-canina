<?php
session_start();
require_once 'conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}

$id = $_SESSION['usuario_id'];
$rol = $_SESSION['rol'];

$stmt = $pdo->prepare('SELECT nombre_usuario, email_usuario, telefono_usuario FROM usuarios WHERE usuario_id = ?');
$stmt->execute([$id]);
$usuario = $stmt->fetch();

// si es empleador, cargamos también su centro para poder editarlo
$centro = null;
if ($rol === 'empleador') {
    $stmt = $pdo->prepare('SELECT nombre_centro, localizacion_centro FROM centros WHERE empleador_id = ?');
    $stmt->execute([$id]);
    $centro = $stmt->fetch();
}

// si es empleado, cargamos los centros donde trabaja
$mis_centros = [];
if ($rol === 'empleado') {
    $stmt = $pdo->prepare('
        SELECT c.centro_id, c.nombre_centro, c.localizacion_centro
        FROM centros c
        INNER JOIN usuario_centro uc ON uc.centro_id = c.centro_id
        WHERE uc.usuario_id = ? AND c.activo = 1
        ORDER BY c.nombre_centro');

    $stmt->execute([$id]);
    $mis_centros = $stmt->fetchAll();
}

$ok = isset($_GET['ok']);
$error = $_GET['error'] ?? null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Peluquería Canina — Mi perfil</title>
    <link rel="stylesheet" href="assets/css/login_registro.css">
    <link rel="stylesheet" href="assets/css/perfil.css">
</head>
<body>

<div class="logo-top">
    <img src="assets/img/logo.png" alt="Logo" onerror="this.style.display='none'">
</div>

<div class="login-wrapper">
    <div class="login-container">

        <p class="subtitulo">Cuenta</p>
        <h1>Mi perfil</h1>

        <?php if ($ok): ?>
            <p class="exito">Cambios guardados correctamente.</p>
        <?php endif; ?>

        <?php if ($error): ?>
            <p class="error">
                <?php if ($error === 'password_actual'): ?>
                    La contraseña actual no es correcta.
                <?php elseif ($error === 'password_match'): ?>
                    Las contraseñas nuevas no coinciden.
                <?php elseif ($error === 'password_debil'): ?>
                    La nueva contraseña debe tener al menos 8 caracteres, un número y un carácter especial.
                <?php else: ?>
                    Ha ocurrido un error. Inténtalo de nuevo.
                <?php endif; ?>
            </p>
        <?php endif; ?>

        <form action="procesar_perfil.php" method="POST">

            <label for="nombre">Nombre completo</label>
            <input type="text" id="nombre" name="nombre"
                   value="<?= htmlspecialchars($usuario['nombre_usuario']) ?>" required>

            <label for="email">Email <span class="label-aux">(no editable)</span></label>
            <input type="email" id="email"
                   value="<?= htmlspecialchars($usuario['email_usuario']) ?>"
                   class="campo-desactivado" disabled>

            <label for="telefono">Teléfono</label>
            <input type="tel" id="telefono" name="telefono"
                   value="<?= htmlspecialchars($usuario['telefono_usuario'] ?? '') ?>">

            <?php if ($rol === 'empleado'): ?>
                <hr class="separador">

                <p class="seccion-titulo">Centros donde trabajo</p>

                <ul class="centros-lista" id="mis-centros">
                    <?php if (empty($mis_centros)): ?>
                        <li class="sin-centros" id="msg-sin-centros">Aún no estás asociado a ningún centro.</li>
                    <?php else: ?>
                        <?php foreach ($mis_centros as $c): ?>
                            <li class="centro-item" data-id="<?= $c['centro_id'] ?>">
                                <div>
                                    <span><?= htmlspecialchars($c['nombre_centro']) ?></span>
                                    <small><?= htmlspecialchars($c['localizacion_centro'] ?? '') ?></small>
                                </div>
                                <button type="button" class="btn-salir" title="Dejar este centro">×</button>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>

                <label for="buscador">Buscar un centro por nombre</label>
                <input type="text" id="buscador" class="buscador-centros"
                       placeholder="Escribe el nombre del centro..." autocomplete="off">
                <ul class="resultados-lista" id="resultados"></ul>
            <?php endif; ?>

            <?php if ($rol === 'empleador' && $centro): ?>
                <hr class="separador">

                <label for="nombre_centro">Nombre del centro</label>
                <input type="text" id="nombre_centro" name="nombre_centro"
                       value="<?= htmlspecialchars($centro['nombre_centro']) ?>">

                <label for="localizacion">Dirección del centro</label>
                <input type="text" id="localizacion" name="localizacion"
                       value="<?= htmlspecialchars($centro['localizacion_centro'] ?? '') ?>"
                       placeholder="Calle, ciudad...">
            <?php endif; ?>

            <hr class="separador">

            <label for="password_actual">Contraseña actual</label>
            <input type="password" id="password_actual" name="password_actual"
                   placeholder="Solo si quieres cambiarla">

            <label for="password_nueva">Nueva contraseña</label>
            <input type="password" id="password_nueva" name="password_nueva"
                   placeholder="Dejar en blanco para no cambiar"
                   pattern="^(?=.*\d)(?=.*[^A-Za-z0-9\s]).{8,}$"
                   title="Mínimo 8 caracteres, al menos un número y un carácter especial">

            <label for="password_confirmar">Confirmar nueva contraseña</label>
            <input type="password" id="password_confirmar" name="password_confirmar"
                   placeholder="Repite la nueva contraseña">

            <button type="submit">Guardar cambios</button>

        </form>

        <p class="link-pie"><a href="dashboard.php">← Volver al dashboard</a></p>

    </div>
</div>



<?php if ($rol === 'empleado'): ?>
    <script>
    const lista = document.getElementById('mis-centros');
    const resultados = document.getElementById('resultados');
    let timer;

    // buscador con debounce — no queremos lanzar fetch a cada tecla
    document.getElementById('buscador').addEventListener('input', function () {
        clearTimeout(timer);
        const q = this.value.trim();
        if (q.length < 2) { resultados.innerHTML = ''; return; }
        timer = setTimeout(() => buscar(q), 300);
    });

    function buscar(q) {
        fetch('api/buscar_centros.php?q=' + encodeURIComponent(q))
            .then(r => r.json())
            .then(centros => {
                if (centros.length === 0) {
                    resultados.innerHTML = '<li class="resultado-vacio">Sin resultados.</li>';
                    return;
                }
                resultados.innerHTML = centros.map(c =>
                    `<li class="resultado-item" data-id="${c.centro_id}" data-nombre="${c.nombre_centro}" data-loc="${c.localizacion_centro ?? ''}">
                        <strong>${c.nombre_centro}</strong>
                        <small>${c.localizacion_centro ?? ''}</small>
                    </li>`
                ).join('');

                resultados.querySelectorAll('.resultado-item').forEach(li => {
                    li.addEventListener('click', () => unirse(li.dataset.id, li.dataset.nombre, li.dataset.loc));
                });
            });
    }

    function unirse(centroId, nombre, loc) {
        const fd = new FormData();
        fd.append('accion', 'unirse');
        fd.append('centro_id', centroId);

        fetch('acciones/gestionar_centro.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (!res.ok) {
                    return;
                }

                document.getElementById('buscador').value = '';
                resultados.innerHTML = '';

                const sinCentros = document.getElementById('msg-sin-centros');

                if (sinCentros) {
                    sinCentros.remove();
                }
                lista.appendChild(crearItem(centroId, nombre, loc));
            });
    }

    function salir(centroId, li) {
        const fd = new FormData();
        fd.append('accion', 'salir');
        fd.append('centro_id', centroId);

        fetch('acciones/gestionar_centro.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (!res.ok) {
                    return;
                }
                li.remove();
                // si se queda sin centros volvemos a poner el mensajito de "aún no..."
                if (lista.querySelectorAll('.centro-item').length === 0) {
                    const msg = document.createElement('li');
                    msg.className = 'sin-centros';
                    msg.id = 'msg-sin-centros';
                    msg.textContent = 'Aún no estás asociado a ningún centro.';
                    lista.appendChild(msg);
                }
            });
    }

    function crearItem(centroId, nombre, loc) {
        const li = document.createElement('li');
        li.className = 'centro-item';
        li.dataset.id = centroId;
        
        li.innerHTML = `<div><span>${nombre}</span><small>${loc}</small></div>
                        <button type="button" class="btn-salir" title="Dejar este centro">×</button>`;
        li.querySelector('.btn-salir').addEventListener('click', () => salir(centroId, li));
        return li;
    }

    // enganchamos los botones de salir de los items que ya venían renderizados desde PHP
    lista.querySelectorAll('.centro-item').forEach(li => {
        li.querySelector('.btn-salir').addEventListener('click', () => salir(li.dataset.id, li));
    });
    </script>
<?php endif; ?>

</body>
</html>