# Dental Guru - Sistema clinico

Este proyecto es una aplicacion PHP para una clinica dental. La idea principal es que cada rol tenga su propio panel y que todos trabajen sobre la misma base de datos: paciente, doctor, recepcion y administrador.

Durante los ultimos cambios se conectaron varias partes que antes estaban como maqueta o con datos fijos. Ahora el paciente puede pedir citas, el doctor puede ver esas solicitudes y atenderlas, y el sistema guarda expedientes y recetas para que el paciente pueda consultarlas despues.

## Como abrirlo

Servidor local usado durante las pruebas:

```bash
http://127.0.0.1:8000/login.php
```

Si necesitas levantarlo desde XAMPP PHP:

```bash
C:\xampp\php\php.exe -S 127.0.0.1:8000 -t frontend
```

## Usuarios de prueba

Paciente:

```text
Correo: leandrolemus800@gmail.con
Clave: 1111
```

Doctor:

```text
Correo: mateo.doctor@dentalguru.test
Clave: doctor123
```

Administrador:

```text
Correo: sofia.admin@dentalguru.test
Clave: admin123
```

Nota: el correo del paciente termina en `.con`, porque asi fue solicitado originalmente.

## Cambios principales

### Login y sesiones

Se reviso el flujo de inicio de sesion para que todos los roles entren por `frontend/login.php` y luego `frontend/index.php` los envie a su panel correspondiente.

Tambien se corrigieron rutas que antes apuntaban a `../../login.php`, porque eso sacaba al usuario fuera de la carpeta real del frontend. Ahora las paginas internas usan `../login.php` cuando necesitan regresar al login.

Para el usuario Leandro se agrego compatibilidad con una clave temporal en texto plano. Cuando ese usuario entra por primera vez, `login.php` convierte la clave a `password_hash()`. Es una solucion practica para importar el usuario inicial sin depender de generar el hash manualmente antes.

### Panel del paciente

Archivo principal:

```text
frontend/pages/paciente.php
```

Ahora el paciente puede:

- Ver sus datos basicos.
- Agendar una cita real en la tabla `CITA`.
- Elegir doctor o dejar la cita sin asignar.
- Cancelar citas pendientes o confirmadas.
- Ver historial de citas.
- Buscar recetas por medicamento, dosis, indicaciones o diagnostico.
- Abrir una receta con la opcion `Mostrar receta completa`.

La receta completa se muestra como una tarjeta tipo comprobante medico, con folio, fecha, paciente, doctor, medicamento, dosis, indicaciones completas, diagnostico y tratamiento.

### Panel del doctor

Archivo principal:

```text
frontend/pages/doctor.php
```

Antes tenia pacientes de ejemplo. Ahora lee datos reales de la base.

El doctor puede:

- Ver citas asignadas a el.
- Ver solicitudes sin doctor asignado.
- Tomar y confirmar una cita.
- Atender una cita.
- Guardar diagnostico, tratamiento y notas.
- Crear una receta para el paciente.
- Marcar la cita como atendida.

Cuando una cita se atiende, se guardan datos en:

```text
CITA
EXPEDIENTE
RECETA
HISTORIAL_CITA
```

Esto permite que el paciente vea despues la receta que escribio el doctor.

### Panel administrativo

Archivos principales:

```text
frontend/pages/admin.php
frontend/pages/crud_admin.php
```

Se corrigio el panel admin para que use datos reales y no rompa el listado de usuarios.

Tambien se reconstruyo el CRUD dinamico para que:

- Solo entre un usuario con rol `admin`.
- Valide que la tabla exista antes de consultarla.
- Inserte registros con consultas preparadas.
- Elimine por llave primaria, no borrando una fila cualquiera.

### Base de datos

Se actualizaron estos archivos SQL:

```text
clinica (1).sql
frontend/clinica_bd.sql
```

Se agregaron usuarios de prueba para paciente, doctor y admin. Tambien se agrego el registro del doctor en la tabla `DOCTOR`.

## Flujo completo esperado

1. El paciente entra al sistema.
2. Agenda una cita.
3. La cita queda `pendiente`.
4. El doctor entra a su panel.
5. El doctor ve la cita pendiente.
6. El doctor toma y confirma la cita.
7. El doctor atiende al paciente.
8. El sistema guarda expediente y receta.
9. El paciente entra de nuevo y puede leer la receta completa.

## Archivos mas importantes

```text
frontend/login.php
frontend/index.php
frontend/pages/paciente.php
frontend/pages/doctor.php
frontend/pages/admin.php
frontend/pages/crud_admin.php
frontend/config/conexion.php
frontend/config/cerrar_sesion.php
frontend/css/styles.css
```

## Verificaciones realizadas

Se reviso sintaxis PHP con:

```bash
C:\xampp\php\php.exe -l frontend/pages/paciente.php
C:\xampp\php\php.exe -l frontend/pages/doctor.php
C:\xampp\php\php.exe -l frontend/login.php
```

Tambien se probo el flujo en navegador local:

- Login de paciente.
- Agenda de cita.
- Login de doctor.
- Visualizacion de cita.
- Confirmacion de cita.
- Carga correcta del panel paciente.

## Pendientes recomendados

- Crear un modulo para editar datos del expediente del paciente.
- Permitir que recepcion confirme o reasigne citas.
- Agregar impresion o descarga PDF de receta.
- Mejorar validaciones de horarios disponibles.
- Separar logica PHP repetida en helpers comunes.

El proyecto ya camina como sistema integrado. Todavia puede crecer, pero la base funcional entre paciente, doctor, recetas y citas ya esta conectada.
