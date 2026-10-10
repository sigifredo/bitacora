# Despliegue en cPanel

Esta guía instala Casiopea en un subdominio (por ejemplo `tareas.tudominio.com`) de un hosting con cPanel. Los archivos necesarios están en `deploy/`:

| Archivo                            | Contenido                                                                             |
| ---------------------------------- | ------------------------------------------------------------------------------------- |
| `casiopea-backend-0.1.0.zip`  | API en PHP, migraciones, scripts y `public/index.php` con su `.htaccess`              |
| `casiopea-frontend-0.1.0.zip` | Interfaz compilada (`index.html` y `assets/`) para la raíz de un dominio o subdominio |

Estructura final en el servidor (`USUARIO` es tu usuario de cPanel):

```
/home/USUARIO/casiopea/
    .env                 <- lo creas tú, nunca dentro de public
    VERSION
    bin/  database/  src/
    public/              <- raíz web del subdominio
        index.php  .htaccess  index.html  assets/
```

Solo `public/` es accesible desde Internet. El código, las migraciones y las credenciales quedan fuera de la raíz web.

## Requisitos del hosting

- PHP 8.3 o superior (probado con 8.4) con las extensiones `pdo_mysql` y `mbstring`.
- MySQL 8.0 o superior, o MariaDB 10.5 o superior. La consulta de tareas de subproyectos usa `WITH RECURSIVE`, que MySQL 5.7 no tiene. Con MySQL, usa `deploy/instalar-esquema.sql` (opción A2 del paso 8): `bin/migrate.php` solo funciona en MariaDB, porque las migraciones usan `IF NOT EXISTS` en `ALTER TABLE`.
- `mod_rewrite` y `.htaccess` habilitados (es lo normal en cPanel).
- Certificado SSL (Let's Encrypt/AutoSSL), porque se inicia sesión con contraseña.

## Pasos

### 1. Subir y descomprimir el backend

1. En cPanel abre **Administrador de archivos**.
2. Ve a tu carpeta personal `/home/USUARIO/` (fuera de `public_html`).
3. Pulsa **Cargar** y sube `casiopea-backend-0.1.0.zip`.
4. Selecciona el zip y pulsa **Extraer**. Se crea `/home/USUARIO/casiopea/`.
5. Borra el zip.

### 2. Subir y descomprimir el frontend

1. Entra en `/home/USUARIO/casiopea/public/`.
2. Sube `casiopea-frontend-0.1.0.zip` y extráelo ahí mismo. Deben quedar `index.html` y `assets/` junto a `index.php`.
3. Borra el zip.

El Administrador de archivos oculta los archivos que empiezan por punto. En **Configuración** (arriba a la derecha) activa **Mostrar archivos ocultos** para ver `.htaccess` y, más adelante, `.env`.

### 3. Crear la base de datos

1. En cPanel abre **Bases de datos MySQL**.
2. Crea una base (por ejemplo `casiopea`). cPanel le antepone tu usuario: queda `USUARIO_casiopea`.
3. Crea un usuario de base de datos con una contraseña larga y anótala.
4. En **Agregar usuario a la base de datos**, asocia ese usuario con esa base y marca **Todos los privilegios**.

### 4. Configurar `.env`

1. En `/home/USUARIO/casiopea/` copia `.env.example` como `.env`.
2. Edítalo con los datos del paso anterior:

```
DB_HOST=localhost
DB_PORT=3306
DB_NAME=USUARIO_casiopea
DB_USER=USUARIO_casiopea
DB_PASS=la_contraseña_del_paso_3
```

Sin comillas ni espacios alrededor del `=`. Si la contraseña tiene caracteres especiales, funciona igual siempre que no la rodees de comillas.

### 5. Crear el subdominio

1. En cPanel abre **Dominios** (o **Subdominios**, según la versión).
2. Crea el subdominio, por ejemplo `tareas.tudominio.com`.
3. En **Raíz del documento** escribe `casiopea/public` (la ruta relativa a tu carpeta personal). No dejes la que propone cPanel (`public_html/tareas`).

### 6. Elegir la versión de PHP

1. Abre **MultiPHP Manager**, selecciona el subdominio y asígnale PHP 8.3 o 8.4.
2. Abre **Seleccionar versión de PHP** (o **MultiPHP INI Editor**, según el hosting), elige la misma versión y comprueba que estén marcadas las extensiones `pdo_mysql` y `mbstring`.

### 7. Activar HTTPS

En **SSL/TLS Status** ejecuta **Run AutoSSL** para el subdominio y espera a que aparezca el candado.

### 8. Crear las tablas (migraciones)

**Opción A: Terminal de cPanel** (si tu hosting la tiene, en **Avanzado > Terminal**):

```bash
cd ~/casiopea
/usr/local/bin/ea-php84 bin/migrate.php
```

Cambia `ea-php84` por la versión que elegiste (`ea-php83`, etc.). La salida esperada termina en `Esquema al día.`

**Opción A2: sin Terminal, con phpMyAdmin** (la más simple). En **phpMyAdmin** selecciona la base de datos vacía, ve a **Importar** y sube `deploy/instalar-esquema.sql`. Equivale a ejecutar `migrate.php` y además registra las migraciones aplicadas, de modo que más adelante `migrate.php` solo ejecuta las nuevas. Para crear el administrador, importa después `deploy/usuario-actual.sql` (usuario local exportado) en lugar de seguir el paso 9.

**Opción B: sin Terminal, con Tareas Cron** (en **Avanzado > Trabajos de Cron**):

1. Añade un trabajo que corra cada minuto con este comando:
    ```
    /usr/local/bin/ea-php84 /home/USUARIO/casiopea/bin/migrate.php > /home/USUARIO/migrate.log 2>&1
    ```
2. Espera un par de minutos y revisa `/home/USUARIO/migrate.log`. Debe decir `Esquema al día.`
3. Borra el trabajo de cron y el log.

### 9. Crear el primer administrador

Con la misma técnica (Terminal o Cron de una sola vez):

```bash
cd ~/casiopea
/usr/local/bin/ea-php84 bin/create-user.php tuusuario tu@correo.com "Tu nombre completo" admin 'UnaContraseñaLarga'
```

Con Cron, el comando es el mismo con rutas absolutas y redirigiendo la salida a un log. Bórralo en cuanto confirmes que el usuario existe, porque la contraseña queda escrita en la configuración del cron. Cámbiala después desde la interfaz (botón **Contraseña**).

### 10. Probar

1. Abre `https://tareas.tudominio.com/api/version`. Debe responder `{"version":"0.1.0"}`.
2. Abre `https://tareas.tudominio.com/`, entra con tu usuario (o tu correo) y la contraseña.
3. Comprueba que el número de versión aparece en la barra lateral.

## Si algo falla

| Síntoma                                                               | Causa probable                                                                                                        |
| --------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------- |
| Error 500 en `/api/...` con `could not find driver` en el log         | Falta la extensión `pdo_mysql` en esa versión de PHP (paso 6)                                                         |
| Error 500 y `Access denied for user`                                  | Datos incorrectos en `.env` o usuario no asociado a la base (pasos 3 y 4)                                             |
| `/api/version` da 404                                                 | La raíz del subdominio no es `casiopea/public` o `.htaccess` no está (pasos 2 y 5)                               |
| La página carga pero las rutas como `/projects/3` dan 404 al recargar | `.htaccess` no se subió o `mod_rewrite` desactivado: pide al hosting que lo active                                    |
| El login responde bien pero la sesión se pierde                       | Entras por HTTP en lugar de HTTPS, o por otro dominio distinto al configurado                                         |
| Pantalla en blanco                                                    | Abre la consola del navegador. Casi siempre el `index.html` y `assets/` quedaron en una carpeta distinta de `public/` |

Los errores de PHP quedan en `error_log`, dentro de `casiopea/public/` o en **Errores** de cPanel.

## Actualizar a una versión nueva

1. Haz una copia de seguridad de la base de datos en **Copia de seguridad** o con phpMyAdmin (**Exportar**).
2. Sube y extrae el nuevo zip del backend sobre `casiopea/`, sin tocar `.env`. El zip no lo incluye, así que no se sobrescribe.
3. Sube y extrae el nuevo zip del frontend en `casiopea/public/`. Los archivos de `assets/` cambian de nombre con cada build, así que conviene borrar antes la carpeta `assets/` vieja.
4. Ejecuta de nuevo `bin/migrate.php` (paso 8): solo aplica las migraciones nuevas.
5. Comprueba `/api/version`.

## Instalar en una carpeta en vez de un subdominio

El zip del frontend está compilado para la raíz de un dominio. Servirlo desde una carpeta (por ejemplo `https://tudominio.com/casiopea/`) exige recompilar el frontend con `VITE_BASE=/casiopea/`. Además, `public/index.php` busca `src/` y `.env` en la carpeta superior a `public/`, así que habría que dejar `public/` en `public_html/casiopea/` y el resto en un lugar que `index.php` pueda alcanzar, lo que hoy requiere un pequeño ajuste del código. El subdominio evita todo eso y es la opción recomendada.
