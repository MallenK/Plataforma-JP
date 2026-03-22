# 🚀 Plataforma JP Preparation

Aplicación web desarrollada con **CodeIgniter 4 + Docker**, orientada a la gestión de jugadores, entrenadores y servicios de la academia JP Preparation.

---

# 🧱 Tecnologías

* PHP 8.x (Docker)
* CodeIgniter 4
* MySQL 8
* Docker + Docker Compose
* jQuery (frontend básico)

---

# 🐳 Entorno de desarrollo (Docker)

## ▶️ Levantar el entorno

```bash
docker-compose up -d --build
```

---

## 🛑 Parar el entorno

```bash
docker-compose down
```


## 🛑 Limpiar caché

```bash
docker exec -it jp_app php spark cache:clear
```



---

## 🔍 Ver contenedores activos

```bash
docker ps
```

---

## 💻 Entrar al contenedor PHP

```bash
docker exec -it jp_app bash
```

---

## 🚪 Salir del contenedor

```bash
exit
```

---

## ⚙️ Ejecutar comandos de CodeIgniter

Ejemplo:

```bash
docker exec -it jp_app php spark
```

Crear filtro:

```bash
docker exec -it jp_app php spark make:filter AuthFilter
```

---

# 🌐 Acceso a la aplicación

```text
http://localhost:8080
```

---

# 🧪 Base de datos

* Host: `localhost`
* Puerto: `3306`
* Contenedor: `jp_db`

phpMyAdmin:

```text
http://localhost:8081
```

---

# ⚙️ Configuración

## 🔧 Variables de entorno

Editar:

```bash
.env
```

Claves importantes:

```env
CI_ENVIRONMENT = development
app.baseURL = 'http://localhost:8080/'
database.default.hostname = db
```

---

# 📁 Estructura del proyecto

```text
app/
 ├── Controllers/
 ├── Models/
 ├── Views/

public/
 ├── assets/
 │    ├── js/
 │    ├── css/

docker/
```

---

# 🔐 Autenticación

Actualmente implementado:

* Registro de usuarios
* Login
* Logout
* Sesiones
* Filtro de protección de rutas

---

# 🧠 Flujo actual

* `/login`
* `/register`
* `/dashboard` (protegido)

---

# 🛠️ Git & GitHub

## 🔄 Estado del repo

```bash
git status
```

---

## ➕ Añadir cambios

```bash
git add .
```

---

## 💾 Commit

```bash
git commit -m "mensaje"
```

---

## 🚀 Subir cambios

```bash
git push origin main
```

---

## ⚠️ Forzar subida (usar con cuidado)

```bash
git push origin main --force
```

---

## 🔄 Traer cambios

```bash
git pull origin main
```

---

# 🚨 Problemas comunes

## ❌ No funciona `php spark`

👉 Ejecuta dentro de Docker:

```bash
docker exec -it jp_app php spark
```

---

## ❌ Rutas con /public

👉 Solucionado configurando Docker para apuntar a `/public`

---

## ❌ AJAX no funciona

* Revisar rutas (`/register`, `/login`)
* Revisar consola navegador
* Ver Network → POST vs GET

---

# 🚀 Próximos pasos

* Sistema de roles (admin / coach / player)
* Dashboard dinámico
* API interna
* Mejora de UI/UX

---
