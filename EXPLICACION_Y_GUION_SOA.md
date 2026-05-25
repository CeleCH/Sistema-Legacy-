# Guía Arquitectural del Sistema Académico Legacy y Camino hacia SOA

Este documento sirve como material de estudio y guión de exposición para presentar ante tu profesor. Explica a fondo cómo está estructurado el sistema monolítico actual, sus falencias, por qué sirve de base para una Arquitectura Orientada a Servicios (SOA) y cómo implementar los patrones de integración en el futuro.

---

## 1. Estructura del Proyecto Paso a Paso (¿Para qué sirve cada carpeta?)

El proyecto se organiza bajo un patrón clásico de **Monolito en Capas y Módulos Lógicos** en PHP. A continuación, se detalla la función exacta de cada elemento en la raíz:

### 📂 `/assets`
*   **Propósito:** Almacena los recursos estáticos del lado del cliente.
*   *   `css/`: Archivos de estilo en cascada.
    *   `js/`: Archivos JavaScript para comportamientos interactivos del navegador.
*   **Función:** Centraliza la presentación del cliente para evitar redundancia y mantener una interfaz de usuario consistente en todo el sitio.

### 📂 `/config`
*   **Propósito:** Configuración centralizada de infraestructura.
*   *   `db.php`: Contiene el puente de conexión a la base de datos mediante **PDO (PHP Data Objects)**.
*   **Función:** No solo abre la conexión con la base de datos SQLite (`colegio.db`), sino que además actúa como un gestor de migraciones básico: si la base de datos no existe, ejecuta las sentencias SQL `CREATE TABLE IF NOT EXISTS` para autogenerar el esquema y siembra datos demo enriquecidos (Seed Data).

### 📂 `/database`
*   **Propósito:** Almacenamiento de persistencia local y scripts de administración.
*   *   `colegio.db`: Archivo SQLite físico. Toda la base de datos reside en este único archivo.
    *   `reset_db.php`: Script de consola (CLI) desarrollado para restablecer y volver a sembrar la base de datos con un solo comando.
*   **Función:** Alojar los datos de manera embebida y liviana sin requerir la instalación de un servidor DBMS externo (como MySQL o PostgreSQL).

### 📂 `/includes`
*   **Propósito:** Reutilización de interfaces y lógica común.
*   *   `auth.php`: Middleware de seguridad de sesión. Bloquea el acceso a páginas protegidas si el usuario no ha iniciado sesión.
    *   `header.php`: Cabecera común del portal académico (HTML5 meta-tags, Bootstrap 5 CSS, Google Fonts y Chart.js).
    *   `sidebar.php`: Menú de navegación lateral responsivo que se adapta dinámicamente mostrando opciones personalizadas de acuerdo al rol del usuario logueado.
    *   `footer.php`: Cierre de etiquetas HTML y scripts comunes.
*   **Función:** Ahorrar la duplicación de código de diseño mediante la directiva PHP `require_once`.

### 📂 `/modules`
*   **Propósito:** Núcleo de las reglas de negocio del sistema académico. Contiene las operaciones de consulta y administración (CRUD) divididas por subdominios:
    *   `alumnos.php`: Registro, edición y monitoreo de la ficha académica del estudiante.
    *   `asistencia.php`: Registro y reporte de asistencia diaria en cursos específicos.
    *   `calificaciones.php`: Entrada de notas numéricas (escala 0-20) y observaciones por curso.
    *   `cursos.php`: Gestión de aulas virtuales, horarios y docentes asignados.
    *   `matriculas.php`: Control del estado de inscripción del alumno en el periodo lectivo.
    *   `notificaciones.php`: Canal de comunicación interno para emitir alertas automatizadas.
    *   `pagos.php`: Administración de pensiones devengadas, boletas canceladas y deudas.
    *   `profesores.php`: Mantenimiento de perfiles de docentes y sus especialidades.
*   **Función:** Separar la lógica funcional en módulos independientes en el código, facilitando su eventual extracción hacia microservicios.

### 📄 Archivos de la Raíz (`/`)
*   `index.php`: Punto de entrada de la URL raíz. Detecta si hay una sesión activa y redirige al usuario automáticamente al Login o al Dashboard.
*   `login.php`: Pantalla de autenticación que valida usuarios de forma segura mediante `password_verify` en contraseñas cifradas con `BCRYPT`. Incluye un panel interactivo de accesos demo rápidos para desarrolladores.
*   `logout.php`: Destruye las variables de sesión del servidor y redirige al login de manera segura.
*   `dashboard.php`: Panel de control principal que compila las métricas académicas y financieras en gráficos analíticos dinámicos basados en Chart.js (rendimiento y control de asistencia).

---

## 2. Explicación Arquitectural del Sistema (¿Qué tenemos hoy?)

### ¿Cómo está hecho el sistema?
Es un **Monolito Clásico**. Esto significa que:
1.  **Código Compartido:** Toda la lógica de presentación (HTML), lógica de negocios (PHP) y lógica de datos (SQL) está empaquetada en un solo despliegue y se ejecuta bajo el mismo servidor web.
2.  **Base de Datos Compartida:** Todos los módulos (`alumnos`, `pagos`, `asistencias`) se comunican escribiendo y leyendo de las mismas tablas dentro del único archivo `colegio.db`.
3.  **Persistencia Embebida:** SQLite gestiona los datos de forma local, guardando toda la información en un solo archivo físico en disco.

### ¿Es Orquestación o Coreografía?
En el sistema actual **no existe Orquestación ni Coreografía de Servicios en el sentido distribuido (Microservicios)**, ya que no hay servicios independientes.

Sin embargo, si analizamos cómo fluye la lógica de negocios internamente:
*   Es un modelo de **Orquestación Monolítica / Acoplamiento Procedural Directo**.
*   **¿Por qué?** Cuando ocurre un evento (por ejemplo, el docente registra una falta en `asistencia.php`), el propio script PHP actúa como un "Orquestador Centralizado" que de forma secuencial y en el mismo hilo de ejecución realiza múltiples consultas directas sobre la base de datos:
    1.  Inserta la asistencia en la tabla `asistencias`.
    2.  Inserta un mensaje de alerta en la tabla `notificaciones`.
    3.  Actualiza el estado si es necesario.
*   Todo el flujo se decide de manera imperativa y síncrona en un único script. Si el paso 2 falla o la base de datos se bloquea, toda la acción se cancela (o se rompe la página).

---

## 3. Falencias del Sistema Monolítico Actual (¿Por qué migrar a SOA?)

Aunque el monolito actual es rápido de desplegar y excelente para proyectos pequeños, posee graves limitaciones técnicas y de negocio a medida que la institución crece:

1.  **Fuerte Acoplamiento de Base de Datos:**
    Si el módulo de Finanzas (`pagos.php`) necesita modificar la estructura de la tabla `alumnos` para agregar un campo de facturación, podría romper accidentalmente el módulo de Asistencias (`asistencia.php`) o el Registro de Calificaciones, debido a que todos los archivos PHP ejecutan consultas SQL directamente sobre las mismas tablas compartidas.
2.  **Limitaciones de Concurrencia de SQLite (Bloqueos de Base de Datos):**
    SQLite implementa bloqueos a nivel de archivo completo para operaciones de escritura. Si el Director está generando un reporte financiero masivo de ingresos en `pagos.php` y al mismo tiempo 50 docentes están intentando tomar asistencia en hora pico, las escrituras fallarán o se retrasarán con errores de "Database Locked" (Base de datos bloqueada).
3.  **Escalabilidad de Todo o Nada:**
    Si el portal de Notificaciones se satura debido al envío masivo de boletines de fin de año, no podemos escalar de forma aislada el módulo de Notificaciones. Estamos obligados a duplicar todo el servidor web (con código de alumnos, pagos, etc.), lo cual desperdicia recursos del sistema.
4.  **Punto Único de Fallo (Single Point of Failure):**
    Un error crítico de PHP (por ejemplo, un bucle infinito o desbordamiento de memoria) en el módulo de calificaciones de un docente tumbará todo el servidor Apache/Nginx, inhabilitando el acceso de los alumnos o la recaudación de caja de pagos.
5.  **Rigidez Tecnológica:**
    Todo el sistema debe ser escrito en PHP. Si deseamos implementar un servicio de notificaciones en tiempo real sumamente veloz usando Node.js y WebSockets, o inteligencia artificial para predecir la deserción escolar con Python, es muy difícil integrarlo limpiamente dentro del monolito.

---

## 4. ¿Cómo este Sistema sirve de Base para Implementar SOA?

Este monolito está **muy bien diseñado para migrar a SOA** debido a su alta **Cohesión y Modularidad Lógica**.

*   **Límites de Dominio Claros:** El proyecto no es un "espagueti de código". Al tener carpetas bien delimitadas y archivos específicos para cada área de negocio dentro de `/modules` (`pagos.php`, `matriculas.php`, `calificaciones.php`), ya tenemos identificados los límites de nuestros futuros **Servicios de Dominio**.
*   **Estrategia de Extracción:**
    *   Podemos transformar el módulo `/modules/pagos.php` en el **Servicio de Pagos**.
    *   Podemos transformar `/modules/notificaciones.php` en el **Servicio de Notificaciones**.
    *   Cada uno de ellos expondrá una interfaz estándar de comunicación (API REST / JSON o gRPC) y gobernará su propio almacén de datos (Base de datos por servicio), eliminando la dependencia a SQLite compartido.

---

## 5. El Futuro en SOA: Orquestación vs. Coreografía de Servicios

Una vez que dividamos el monolito en servicios distribuidos, la coordinación de los flujos de negocio puede abordarse mediante dos paradigmas de integración:

### Opción A: Orquestación de Servicios (Coordinación Centralizada)
Se introduce un componente central llamado **Orquestador** (por ejemplo, un servicio de Matriculación o un motor BPMN).
*   **Flujo:**
    1.  El cliente solicita una matrícula al **Servicio Orquestador de Matrículas**.
    2.  El Orquestador llama secuencialmente al **Servicio de Alumnos** para verificar sus datos.
    3.  El Orquestador le indica al **Servicio de Pagos** que genere la cuota de inscripción.
    4.  El Orquestador le indica al **Servicio de Notificaciones** que envíe la confirmación al padre.
*   **Pros:** Control visual centralizado de todo el proceso de negocio. Fácil de depurar.
*   **Cons:** El Orquestador se vuelve un cuello de botella y un punto único de fallo lógico.

### Opción B: Coreografía de Servicios (Coordinación Descentralizada)
No existe un director central. Los servicios se comunican a través de eventos usando un **Message Broker** (como RabbitMQ, Apache Kafka o un Event Bus).
*   **Flujo:**
    1.  El **Servicio de Matrículas** procesa la solicitud y publica un evento en el bus: `MatriculaCreada`.
    2.  El **Servicio de Pagos** (que está escuchando el bus) reacciona al evento `MatriculaCreada` y genera de forma automática la cuenta por cobrar.
    3.  El **Servicio de Notificaciones** (que también escucha) reacciona al evento `MatriculaCreada` y despacha el correo electrónico de bienvenida.
*   **Pros:** Arquitectura altamente desacoplada y escalable. Si el Servicio de Notificaciones está caído, los pagos se procesan igual, y las notificaciones se enviarán automáticamente cuando el servicio vuelva a levantarse leyendo los eventos en cola.
*   **Cons:** El flujo global del sistema es más complejo de monitorear y rastrear en producción.

---

## 6. Guión Sugerido para la Exposición ante el Profesor

Aquí tienes un plan de presentación de 10-15 minutos paso a paso para deslumbrar al jurado o al docente:

### Introducción (Minutos 1-3)
> *"Buenos días profesor. Hoy les presentaremos nuestro Sistema de Gestión Académica para el 'Colegio Futuro Digital'. Actualmente, el sistema está desarrollado bajo una arquitectura de **Monolito en Capas**. Hemos utilizado tecnologías estándar de la industria web: **HTML5** para la estructura, **Bootstrap 5** y **Google Fonts** para una interfaz moderna y adaptativa, **Javascript** junto a **Chart.js** para analíticas de rendimiento interactivo en el dashboard, y **PHP** con **PDO** conectado a una base de datos **SQLite** para el backend."*

### Demostración del Sistema (Minutos 3-6)
> *"Como puede observar en pantalla, la interfaz de inicio de sesión cuenta con accesos directos interactivos que simulan los diferentes roles de la institución académica: Director, Administrador, Docente, Alumno y Padre de familia. Al presionar cualquiera de ellos, se inyectan las credenciales reales con una animación de pulso visual e inicia sesión automáticamente tras 500ms usando animaciones dinámicas.*
>
> *(Ingresa como Director)*
> *"El dashboard lee y totaliza de forma reactiva las métricas de la institución. Contamos con gráficos interactivos que controlan el Rendimiento Académico y la Asistencia General de los alumnos. El sistema se encuentra completamente poblado con un set de datos altamente denso y realista: 10 alumnos, 10 profesores con especialidades, asistencias estructuradas y registros financieros completos."*

### La Arquitectura Interna (Minutos 6-8)
> *"A nivel interno, nuestro código está organizado de forma altamente modular en carpetas. En `/config` centralizamos la conexión de datos, en `/includes` reutilizamos los layouts comunes de Bootstrap, y en `/modules` separamos las reglas de negocio en submódulos como pagos, calificaciones y asistencias. Actualmente, el flujo de negocio responde a una **orquestación monolítica síncrona**, donde cada módulo PHP ejecuta de manera secuencial y en un mismo hilo las actualizaciones sobre la base de datos."*

### Falencias Justificadoras (Minutos 8-10)
> *"A pesar de la alta modularidad, identificamos falencias que impiden que este sistema sea óptimo para la gran escala:
> 1. El **fuerte acoplamiento en base de datos**: si un módulo altera una tabla compartida, podría generar un fallo en cascada sobre otros módulos.
> 2. SQLite **bloquea el archivo completo al escribir**, lo que genera cuellos de botella ante accesos concurrente de muchos usuarios simultáneos en hora punta académica.
> 3. No podemos **escalar de manera independiente** los servicios (por ejemplo, notificaciones masivas de notas) sin duplicar toda la aplicación."*

### El Camino hacia SOA (Conclusión y Futuro) (Minutos 10-12)
> *"Es por estas razones que este sistema legacy es **la base ideal para migrar a una Arquitectura Orientada a Servicios (SOA)**. Gracias a la excelente delimitación de sus archivos en `/modules`, la delimitación de fronteras ya está lista. En la siguiente fase, extraeremos estos módulos convirtiéndolos en servicios independientes e intercomunicados mediante APIs RESTful. 
> 
> Para coordinar la arquitectura SOA, evaluaremos dos enfoques: **Orquestación**, mediante un componente centralizador que dirija secuencialmente las peticiones, o **Coreografía**, guiada por eventos a través de un Message Broker para lograr el desacoplamiento total y la máxima disponibilidad. Muchas gracias, quedamos atentos a sus preguntas."*
