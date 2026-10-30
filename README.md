# hospital-php-sql

Bienvenido al repositorio del proyecto de gestión hospitalaria desarrollado con PHP y PostgreSQL, pensado para gestionar accesos, pacientes, médicos y enfermeros desde un portal web con Apache.

El sistema está orientado a un entorno de hospital donde cada usuario accede mediante su CIP y se redirige según su rol:

- Paciente
- Médico
- Enfermero/a

La idea principal es demostrar la integración entre una base de datos relacional y una interfaz web para gestionar información clínica básica, stock de vacunas y asignación de pacientes.

## Estructura del proyecto

+------------------------------------------------------------+
| hospital-php-sql                                           |
|------------------------------------------------------------|
| README.md               -> Documentación del proyecto      |
| index.php               -> Login de acceso y redirección    |
| infermer.php            -> Panel para enfermería            |
| metge.php               -> Panel para médicos                |
| logout.php              -> Cierre de sesión                 |
| logo.png                -> Logo del hospital                |
+------------------------------------------------------------+

    +-------------------+
    | Apache + PHP      |
    | + PostgreSQL      |
    +-------------------+
              |
              v
    +-------------------+
    | Aplicación web    |
    | hospital-php-sql  |
    +-------------------+
              |
              v
    +-------------------+
    | Base de datos     |
    | hp                |
    +-------------------+

## Español

### Descripción
Este proyecto consiste en una aplicación web desarrollada en PHP para gestionar procesos básicos de un hospital. Permite iniciar sesión con un CIP y acceder a un panel según el rol:

- `metge.php`: gestión médica, ingreso de pacientes, revisión de estados y control de carga.
- `infermer.php`: administración de vacunas, control de stock, registro de dosis y consulta de hospitales cercanos.
- `index.php`: formulario de autenticación principal.
- `logout.php`: cierre de sesión.

### Funcionalidades principales
- Inicio de sesión por CIP.
- Diferenciación de roles: paciente, médico y enfermero.
- Gestión de pacientes hospitalizados.
- Diferentes estados clínicos.
- Control de stock de vacunas y alertas si el inventario está bajo.
- Registro de dosis en la cartilla de vacunación.
- Búsqueda de hospitales cercanos con UCI disponible.
- Consulta del estado de estrés del personal médico.

### Requisitos
- PHP 7+ o 8+
- Apache o servidor compatible con PHP
- PostgreSQL
- Extensión PDO para PostgreSQL (`pdo_pgsql`)
- Acceso local a la base de datos con credenciales configuradas en los scripts

### Ejecución
1. Clona el repositorio.
2. Coloca el proyecto en la carpeta pública de Apache (por ejemplo `htdocs` o `www`).
3. Configura la base de datos PostgreSQL con el nombre `hp`.
4. Asegúrate de que el usuario `postgres` y la contraseña `http` estén disponibles o modifica los valores en los archivos PHP.
5. Inicia Apache.
6. Accede a la aplicación desde el navegador:
   `http://localhost/hospital-php-sql/`

### Nota importante
El código actual contiene credenciales locales y configuración de conexión directa a PostgreSQL. Para un entorno de producción, es recomendable mover la configuración a variables de entorno o un archivo seguro de configuración.

## Català

### Descripció
Aquest projecte consisteix en una aplicació web desenvolupada en PHP per gestionar processos bàsics d'un hospital. Permet iniciar sessió amb un CIP i accedir a un panell segons el rol:

- `metge.php`: gestió mèdica, ingrés de pacients, revisió d’estats i control de càrrega.
- `infermer.php`: administració de vacunes, control d’estoc, registre de dosis i consulta d’hospitals propers.
- `index.php`: formulari d’autenticació principal.
- `logout.php`: tancament de sessió.

### Funcions principals
- Inici de sessió per CIP.
- Diferenciació de rols: pacient, metge i infermer/a.
- Gestió de pacients hospitalitzats.
- Diferents estats clínics.
- Control d’estoc de vacunes i alertes si l’inventari està baix.
- Registre de dosis a la cartilla de vacunació.
- Cerca d’hospitals propers amb UCI disponible.
- Consulta de l’estat d’estrès del personal mèdic.

### Requisits
- PHP 7+ o 8+
- Apache o servidor compatible amb PHP
- PostgreSQL
- Extensió PDO per PostgreSQL (`pdo_pgsql`)
- Accés local a la base de dades amb credencials configurades als scripts

### Execució
1. Clona el repositori.
2. Col·loca el projecte a la carpeta pública d’Apache (per exemple `htdocs` o `www`).
3. Configura la base de dades PostgreSQL amb el nom `hp`.
4. Assegura’t que l’usuari `postgres` i la contrasenya `http` estiguin disponibles o modifica els valors als fitxers PHP.
5. Inicia Apache.
6. Accedeix a l’aplicació des del navegador:
   `http://localhost/hospital-php-sql/`

### Nota important
El codi actual conté credencials locals i configuració de connexió directa a PostgreSQL. Per a un entorn de producció, és recomanable moure la configuració a variables d’entorn o a un fitxer segur de configuració.

## English

### Description
This project is a web application developed in PHP to manage basic hospital processes. It allows users to log in with a CIP and access a panel according to their role:

- `metge.php`: medical management, patient admission, status review, and workload control.
- `infermer.php`: vaccine administration, stock control, dose registration, and nearby hospital search.
- `index.php`: main authentication form.
- `logout.php`: session termination.

### Main features
- Login by CIP.
- Role differentiation: patient, doctor, and nurse.
- Management of hospitalized patients.
- Different clinical status options.
- Vaccine stock control with alerts when inventory is low.
- Dose registration in the vaccination record.
- Search for nearby hospitals with ICU availability.
- Stress status check for medical staff.

### Requirements
- PHP 7+ or 8+
- Apache or any compatible PHP server
- PostgreSQL
- PDO extension for PostgreSQL (`pdo_pgsql`)
- Local database access with credentials configured in the scripts

### Running the project
1. Clone the repository.
2. Place the project in the public folder of Apache (for example `htdocs` or `www`).
3. Configure the PostgreSQL database with the name `hp`.
4. Make sure the `postgres` user and password `http` are available or update the values in the PHP files.
5. Start Apache.
6. Open the application in the browser:
   `http://localhost/hospital-php-sql/`

### Important note
The current code contains local credentials and direct PostgreSQL connection settings. For a production environment, it is recommended to move configuration to environment variables or a secure config file.

## Diagrama general de flujo

    [Usuario]
         |
         v
   +--------------------+
   | index.php          |
   | Login por CIP      |
   +--------------------+
         |
         +--------+------------------+
                  |
                  v
         +----------------------+
         | Valida rol en BD     |
         | paciente / metge /   |
         | infermer             |
         +----------------------+
                  |
         +--------+--------+
                  |
                  v
       +------------------+
       | metge.php       |
       | infermer.php     |
       +------------------+
                  |
                  v
      +---------------------------+
      | PostgreSQL (hp)           |
      | hospitales, pacientes,    |
      | médicos, vacunas, stock    |
      +---------------------------+

## Resumen rápido

Este repositorio muestra una implementación básica de un sistema hospitalario con interfaz web en PHP y conexión directa a PostgreSQL. Está pensado como proyecto académico o demostrativo para ilustrar la gestión operativa de un hospital utilizando una base de datos relacional.

## Licencia
Sin licencia declarada en el repositorio.

## Autor y contexto
Proyecto desarrollado para la gestión de una base de datos hospitalaria con implementación web en Apache/PHP.

---

Proyecto: `patomayi/hospital-php-sql`
Repositorio: GitHub
Tecnología principal: PHP + PostgreSQL + Apache

