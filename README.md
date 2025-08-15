# Matriz Escolar PRO (PHP 8 + Bootstrap 5 + DataTables)

## Instalación rápida (XAMPP)
1) Copia esta carpeta en `C:\xampp\htdocs\matriz_escolar_pro\`
2) Crea la BD **bdsistemaiepin** e importa `database.sql`
3) Abre `http://localhost/matriz_escolar_pro/login.php`
4) Acceso: **admin@demo.com / admin123**

## Tecnologías
- Bootstrap 5, Bootstrap Icons
- DataTables + Buttons (exportar a Excel/CSV/PDF/Print)
- SweetAlert2
- Chart.js
- PHP 8, MySQL

## Estructura
- `index.php` → Router + layout
- `app/views/pages/*` → Vistas
- `public/actions/*` → Endpoints (guardar nota, exportar)
- `database.sql` → Esquema + datos demo
