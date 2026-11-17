# Sitio Web Institucional · Colegio José Manuel Balmaceda (San Javier)

Portal web oficial del **Colegio José Manuel Balmaceda** de San Javier (Región del Maule, Chile), estructurado con diseño institucional unificado, galería de actividades, integración con **Instagram oficial (`@balmaceda403san_javier`)**, formulario de contacto **Escríbenos** conectado a **MySQL / DBeaver**, limpieza automática semanal de mensajes y **Modo Administrador** protegido.

---

## Estructura del proyecto

```text
balmaceda/
├── index.html                  # Portada principal, videos, Instagram oficial y contacto
├── conoce-nuestra-escuela.html # Reseña histórica (1928–2026)
├── vision-y-mision.html        # Visión y misión institucional
├── himno.html                  # Himno oficial de la Escuela E-403
├── nuestra-gente.html          # Equipo directivo y convivencia escolar
├── personal.html               # Cuerpo docente y personal administrativo
├── talleres.html               # Catálogo de talleres extraprogramáticos (A.C.L.E.S.)
├── taller.html                 # Ficha dinámica por taller (?actividad=...)
├── cra.html                    # Centro de Recursos de Aprendizajes (C.R.A.)
├── documentos.html             # Documentos oficiales (PEI, RICE, PISE, Evaluación)
├── admin.html                  # Modo Administrador (login + bandeja MySQL + gestión IG)
├── styles.css                  # Hoja de estilos institucional unificada
├── app.js                      # Lógica de interfaz, formularios e Instagram
├── config/
│   └── conexion.php            # Conexión PDO MySQL (lee .env sin exponer claves)
├── api/
│   ├── auth.php                # Autenticación y sesiones del modo administrador
│   ├── mensajes.php            # API REST de mensajes ("Escríbenos") + purga 7 días
│   └── instagram.php           # API REST de videos/reels recientes de Instagram
├── database/
│   └── schema.sql              # Esquema MySQL + evento automático semanal para DBeaver
├── scripts/
│   └── configurar_admin.php    # Utilidad CLI para sincronizar hash de admin en MySQL
├── imagenes/                   # Activos gráficos institucionales
├── .env.example                # Plantilla segura de variables de entorno
├── .gitignore                  # Bloquea .env y credenciales locales para GitHub
└── .github/workflows/pages.yml # Despliegue automatizado en GitHub Pages
```

---

## Conexión a MySQL y DBeaver

1. **Crear la base de datos en DBeaver:**
   - Abre **DBeaver** y conéctate a tu servidor **MySQL** (`127.0.0.1:3306`).
   - Abre el archivo `database/schema.sql` y ejecútalo completo (`Alt + X`).
   - Esto creará la base de datos `balmaceda_db`, las tablas `mensajes_contacto`, `instagram_videos`, `admin_usuarios` y el evento programado `ev_eliminar_mensajes_semanales`.

2. **Configurar las variables locales (`.env`):**
   - Copia `.env.example` a `.env` en la raíz del proyecto:
     ```bash
     cp .env.example .env
     ```
   - Edita `.env` con tus credenciales locales de MySQL (`DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`).
   - El archivo `.env` está incluido en `.gitignore`, por lo que **nunca se subirá a GitHub**.

3. **Ejecutar el servidor local con PHP + MySQL:**
   ```bash
   php -S localhost:8000
   ```
   - Al enviar un mensaje desde cualquier formulario **Escríbenos** de la web, el registro se guardará inmediatamente en la tabla `balmaceda_db.mensajes_contacto` (visible en **DBeaver** y en **`admin.html`**).

---

## Limpieza automática semanal (7 días)

Para evitar la saturación de la base de datos, los mensajes tienen una retención máxima de **7 días (1 semana)** mediante doble mecanismo:
1. **Evento nativo en MySQL (`ev_eliminar_mensajes_semanales`)**: definido en `database/schema.sql`, elimina diariamente todo mensaje cuya fecha `creado_en` supere los 7 días.
2. **Purga automática en `api/mensajes.php`**: cada vez que se registra un nuevo mensaje o se consulta la bandeja en el modo administrador, el backend depura los registros vencidos (`> 7 días`) aunque el planificador de eventos de MySQL esté desactivado.

---

## Seguridad antes de subir a GitHub

- Ninguna contraseña o credencial de base de datos está escrita en texto plano en el código fuente.
- El acceso administrativo valida mediante resúmenes criptográficos (`SHA-256` / `password_verify`) y cookies de sesión `HttpOnly` + `SameSite=Strict`.
- Verifica con `git status` antes de hacer `git push` que `.env` no aparezca en los archivos rastreados.
