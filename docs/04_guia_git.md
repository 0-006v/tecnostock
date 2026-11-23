# Guía Git / GitHub

```bash
cd tecnostock
git init
git branch -M main

git add README.md docs/01_analisis_requerimientos.md
git commit -m "docs: análisis, alcance y requerimientos del caso TecnoStock"

git add tecnostock.sql docs/02_modelo_datos.md
git commit -m "feat(db): script SQL con PK, FK, UNIQUE, CHECK y datos de prueba"

git add config/ includes/funciones.php includes/auth_check.php includes/header.php includes/footer.php
git commit -m "feat(core): conexión PDO, helpers, CSRF y layout Bootstrap"

git add login.php logout.php
git commit -m "feat(auth): inicio y cierre de sesión con password_verify"

git add index.php producto_form.php producto_estado.php
git commit -m "feat(productos): listado, búsqueda, alta, edición y borrado lógico"

git add movimientos.php
git commit -m "feat(movimientos): entradas y salidas con transacción PDO y control de stock"

git add assets/js/validaciones.js
git commit -m "feat(ui): validaciones JS espejo de PHP, confirmaciones y buscador en vivo"

git add docs/03_plan_pruebas.md docs/04_guia_git.md docs/05_guion_video.md
git commit -m "test(docs): plan de pruebas y guion del video"

git remote add origin https://github.com/TU_USUARIO/tecnostock.git
git push -u origin main
```
