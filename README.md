# Sistema de Gestión para Peluquerías Caninas

Trabajo Fin de Grado dirigido a la gestión de citas, clientes y empleados en peluquerías caninas con varios centros.

Autor: Miguel Ángel Rincón Amigo
Tutor: Francisco Javier Segovia Bernardos
Curso 2025 / 2026

## Descripción

En el sector de la peluquería canina es habitual que los profesionales trabajen en varios establecimientos y gestionen sus citas mediante herramientas genéricas como Excel o aplicaciones de mensajería. Estas herramientas no contemplan ni el carácter multicentro del trabajo ni las particularidades del servicio: la duración real de un baño o de un corte varía considerablemente según la raza del animal.

La aplicación trata de centralizar esa gestión en una sola plataforma web. Su principal aportación es un calendario unificado por empleado: un peluquero que trabaja en varios centros visualiza todas sus citas en una única vista, diferenciadas por color según el establecimiento. A esto se suma un sistema de duración estimada por raza y servicio, basado en una tabla precargada con las 26 razas más habituales y los 5 servicios ofrecidos, que se aplica de forma automática mediante un disparador SQL cada vez que se crea una cita nueva.

## Funcionalidades

La aplicación cubre el registro y autenticación de los usuarios, diferenciando entre dos roles (empleador y empleado) con permisos distintos. Cada usuario puede editar sus datos personales y su contraseña desde el perfil. Los empleadores administran sus centros y los empleados pueden vincularse a varios de ellos al mismo tiempo.

La gestión de clientes y perros está disponible para los dos roles e incluye un buscador instantáneo y un borrado en cascada lógico: al eliminar un cliente se eliminan también sus perros, dentro de una transacción para garantizar que ambos cambios ocurran a la vez.

El calendario es la pieza principal. Se ha integrado con la librería FullCalendar y permite ver las citas en vista semanal o diaria, crearlas arrastrando sobre una franja libre, editarlas o cancelarlas haciendo clic sobre ellas, y moverlas o redimensionarlas mediante drag and drop. Antes de guardar un cambio, el servidor comprueba que la nueva ubicación no se solape con otra cita del mismo empleado.

## Stack tecnológico

El backend está escrito en PHP 8 sin frameworks y usa PDO para conectar con una base de datos MySQL en motor InnoDB y codificación 'utf8mb4_spanish_ci'. El frontend emplea HTML, CSS y JavaScript estándar (Fetch API), sin librerías de cliente más allá de FullCalendar 6 y Font Awesome 6, ambos cargados por CDN. El entorno de desarrollo es XAMPP sobre Windows.

## Requisitos previos

Para instalarlo en local hace falta XAMPP 8.0 o superior (o una combinación equivalente de Apache, PHP 8 y MySQL/MariaDB), acceso a phpMyAdmin u otro cliente SQL, y un navegador moderno.

## Instalación

1. Copia el proyecto en 'D:\XAMPP\htdocs\peluqueria-canina\' (o la ruta equivalente en tu instalación de XAMPP).

2. Desde phpMyAdmin, crea una base de datos nueva llamada 'peluqueria-canina' con cotejamiento 'utf8mb4_spanish_ci' e importa el archivo 'peluqueria-canina.sql'.

3. Aplica la vista y el disparador, que no están incluidos en el SQL inicial. Desde la pestaña *SQL* de phpMyAdmin, ejecuta el siguiente bloque:

   ```sql
   CREATE OR REPLACE VIEW `vista_citas_calendario` AS
   SELECT
     c.cita_id, c.fecha_hora, c.duracion_real, c.estado, c.notas,
     c.usuario_id, u.nombre_usuario,
     c.centro_id, ct.nombre_centro,
     c.perro_id,p.nombre_perro,
     r.raza_id, r.nombre_raza,
     c.servicio_id, s.nombre_servicio,
     cl.cliente_id, cl.nombre_cliente, cl.telefono_cliente
   FROM citas c
   INNER JOIN usuarios u ON u.usuario_id = c.usuario_id
   INNER JOIN centros ct ON ct.centro_id = c.centro_id
   INNER JOIN servicios s ON s.servicio_id = c.servicio_id
   INNER JOIN perros p ON p.perro_id = c.perro_id
   INNER JOIN razas r ON r.raza_id = p.raza_id
   INNER JOIN clientes cl ON cl.cliente_id = p.cliente_id
      WHERE u.activo = 1
      AND ct.activo = 1
      AND p.activo = 1 AND
      cl.activo = 1;

   DELIMITER //
   CREATE TRIGGER `trg_citas_duracion_antes_insertar`
   BEFORE INSERT ON `citas`
   FOR EACH ROW
   BEGIN
     IF NEW.duracion_real IS NULL THEN

       SELECT ds.duracion INTO NEW.duracion_real
       FROM  duracion_servicio ds
       INNER JOIN perros p ON p.raza_id = ds.raza_id
       WHERE p.perro_id = NEW.perro_id
         AND ds.servicio_id = NEW.servicio_id
       LIMIT 1;

     END IF;
   END //
   DELIMITER ;
   ```

4. Revisa el archivo 'conexion.php' y ajusta el usuario, contraseña o nombre de la base de datos si tu instalación de MySQL no usa los valores por defecto de XAMPP.

5. Abre la aplicación en el navegador desde 'http://localhost/peluqueria-canina/'.

## Datos de prueba

Si quieres tener la aplicación poblada con datos de prueba sin tener que registrar usuarios uno a uno, ejecuta este bloque SQL en la pestaña SQL de phpMyAdmin (con la base de datos seleccionada). Crea dos usuarios, un centro, un cliente, un perro y una cita lista para verse en el calendario.

```sql
INSERT INTO usuarios (nombre_usuario, email_usuario, password_usuario, telefono_usuario, rol_usuario) VALUES
('Ana García',   'ana@ejemplo.com',    '$2y$10$YZj4QXTzwUQ0pyExUMtPneRPmCWfgZ3RDvDDpYW5/eK7JlVLLp.7e', '611222333', 'empleador'),
('Carlos López', 'carlos@ejemplo.com', '$2y$10$YZj4QXTzwUQ0pyExUMtPneRPmCWfgZ3RDvDDpYW5/eK7JlVLLp.7e', '622333444', 'empleado');

INSERT INTO centros (nombre_centro, telefono_centro, localizacion_centro, empleador_id) VALUES
('Peluquería Canina El Bigote', '933111222', 'Calle Mayor 10, Madrid', 1);

INSERT INTO usuario_centro (usuario_id, centro_id) VALUES (2, 1);

INSERT INTO clientes (nombre_cliente, telefono_cliente, centro_id) VALUES
('María Fernández', '644555666', 1);

INSERT INTO perros (nombre_perro, raza_id, cliente_id) VALUES
('Tobi', 4, 1);

INSERT INTO citas (usuario_id, centro_id, perro_id, servicio_id, fecha_hora, estado) VALUES
(2, 1, 1, 3, '2026-05-20 10:00:00', 'pendiente');
```

La contraseña de ambos usuarios es 'pass1234'. Ana García actúa como empleadora ('ana@ejemplo.com') y Carlos López como empleado ('carlos@ejemplo.com'). El cliente de ejemplo es María Fernández, su perro Tobi es un Yorkshire Terrier y la cita está asignada a Carlos.

Si el hash bcrypt incluido no verifica correctamente con 'pass1234' al iniciar sesión, genera uno nuevo desde un archivo PHP del proyecto con 'password_hash("pass1234", PASSWORD_BCRYPT)' y actualízalo en la tabla 'usuarios'.

Créate gen_hash.php en la raíz:

<?php
echo password_hash('pass1234', PASSWORD_BCRYPT);

Pasos:
1.	Crea ese archivo en D:\XAMPP\htdocs\peluqueria-canina\gen_hash.php.
2.	Abre en el navegador: http://localhost/peluqueria-canina/gen_hash.php.
3.	Copia el hash que aparece.
4.	En phpMyAdmin:
5.	UPDATE usuarios SET password_usuario = 'EL_HASH_QUE_COPIASTE' WHERE usuario_id IN (1, 2);
6.	Borra gen_hash.php (acuérdate de no dejarlo en la entrega final).


## Estructura del proyecto

En la raíz están los archivos principales: el 'conexion.php' que centraliza la conexión PDO, la página de login ('index.php') y su procesador, los formularios de registro y perfil, el dashboard que sirve de contenedor para las distintas pestañas, y el cierre de sesión.

La carpeta 'dashboard_secciones/' agrupa los fragmentos PHP de cada pestaña del dashboard: el calendario, el listado de clientes y el panel de empleados. En 'api/' viven los endpoints que devuelven JSON, usados por los buscadores AJAX y por la carga del calendario, y en 'acciones/' los endpoints POST que crean, modifican o eliminan registros.

Los estilos y recursos estáticos están en 'assets/', separados en una hoja para login y registro y otra para el dashboard. El script de creación e inicialización de la base de datos, 'peluqueria_canina.sql', se encuentra en la raíz.
