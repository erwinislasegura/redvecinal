# RedVecinal

Plataforma web/PWA de seguridad ciudadana y colaboración comunitaria para Chile. Está construida en PHP MVC, MySQL y una interfaz compatible con Bootstrap 5 almacenada localmente, por lo que no depende de CDN ni de servicios externos para funcionar.

## Funciones incluidas

- Reportes de seguridad, emergencias y problemas del barrio.
- Geolocalización, dirección, prioridad, evidencia en imagen o video y reporte anónimo.
- Central de monitoreo con estados, asignación a operadores, notas internas y despacho de servicios.
- Historial completo de atención y conversación con el vecino.
- Mascotas perdidas, perfil público por enlace QR, avistamientos y notificación al responsable.
- Registro de cámaras, alarmas, sensores y botones de pánico.
- Entrada webhook por dispositivo para recibir eventos de integraciones externas.
- Usuarios, seis roles iniciales, permisos configurables, comunas y sectores.
- Notificaciones internas y números chilenos de emergencia.
- PWA instalable, recursos locales y cola de reportes cuando el teléfono pierde conexión.
- Instalador web para cPanel y base de datos MySQL completa.
- Protección CSRF, consultas preparadas, contraseñas con `password_hash`, sesiones seguras, autorización por permiso y archivos privados.

## Requisitos

- PHP 8.1 o superior.
- MySQL 5.7+ o MariaDB 10.4+.
- Extensiones PHP: PDO MySQL, mbstring, fileinfo y JSON.
- Apache con `mod_rewrite` y permiso para archivos `.htaccess`.
- HTTPS recomendado y obligatorio para geolocalización/PWA en producción.

## Instalación en la raíz de cPanel

1. Descarga el repositorio y sube **todo su contenido** a `public_html`.
2. En cPanel crea una base de datos MySQL y un usuario con todos los permisos sobre ella.
3. Verifica que `config/`, `storage/`, `storage/uploads/` y `storage/logs/` tengan permiso de escritura para PHP. Normalmente `755` es suficiente; algunos servidores requieren `775`.
4. Abre `https://tudominio.cl/install/`.
5. Completa los datos MySQL, la comuna inicial y el administrador principal.
6. Al finalizar, ingresa a la plataforma y elimina o renombra la carpeta `install`.

El repositorio incluye `config/database.php` preparado para XAMPP local con la base `redvecinal`, usuario `root` y contraseña vacía. En servidores cPanel, el instalador reemplaza automáticamente esos valores por las credenciales ingresadas. También importa `database/schema.sql` y crea o actualiza sin duplicar la comuna inicial y la cuenta de superadministrador.

## Funcionamiento sin conexión

La interfaz, los estilos y los scripts se almacenan en caché mediante `service-worker.js`. Si un vecino pierde conexión mientras completa un reporte, el formulario se guarda en el almacenamiento local del dispositivo y se sincroniza al volver internet.

Los archivos adjuntos no se guardan en la cola offline por privacidad y límites de almacenamiento del navegador. Deben añadirse con conexión.

## Integración de alarmas y cámaras

Al registrar un dispositivo se genera un token secreto de webhook. Un conector del fabricante puede enviar eventos con una petición JSON a:

```text
POST https://tudominio.cl/api/dispositivos/TOKEN_DEL_DISPOSITIVO/evento
Content-Type: application/json

{
  "event_type": "Movimiento detectado",
  "severity": "critica",
  "camera_id": "entrada-01"
}
```

Se aceptan las severidades `info`, `advertencia` y `critica`. La visualización de video en vivo requiere que la cámara ofrezca una URL HTTP compatible con navegador o un conector específico del fabricante; RTSP/ONVIF normalmente necesita un gateway de video en el servidor.

## Estructura

```text
index.php                 Front controller
app/Core                  Router, base de datos, autenticación y CSRF
app/Controllers           Controladores de cada módulo
app/Models                Modelos de dominio
app/Views                 Vistas responsive
config                    Configuración de aplicación y MySQL
database/schema.sql       Base de datos completa
install                   Instalador web
public                    PWA y recursos locales
storage/uploads           Evidencia privada
```

## Seguridad posterior a la instalación

- Utiliza HTTPS y activa copias de seguridad automáticas de MySQL y `storage/uploads`.
- Elimina la carpeta `install`.
- Mantén PHP y MySQL actualizados.
- No incluyas credenciales de cámaras en las URL. Crea conectores específicos y cifra los secretos.
- Revisa periódicamente roles, permisos y cuentas suspendidas.
- Define protocolos municipales reales antes de usar la plataforma para coordinar emergencias.

## Licencia

Proyecto privado de RedVecinal. Revisa y define una licencia antes de distribuirlo a terceros.
