# XVII: satelles-odf-relatio
<p>
	<img src="https://img.shields.io/github/license/Tabula17/satelles-odf-relatio?style=default&logo=opensourceinitiative&logoColor=white&color=2141ec" alt="license">
	<img src="https://img.shields.io/github/last-commit/Tabula17/satelles-odf-relatio?style=default&logo=git&logoColor=white&color=2141ec" alt="last-commit">
	<img src="https://img.shields.io/github/languages/top/Tabula17/satelles-odf-relatio?style=default&color=2141ec" alt="repo-top-language">
	<img src="https://img.shields.io/github/languages/count/Tabula17/satelles-odf-relatio?style=default&color=2141ec" alt="repo-language-count">
</p>

Procesador de plantillas ODF (OpenDocument) para PHP, inspirado en JODReports, que permite generar documentos dinámicos a partir de plantillas y datos personalizados.

## Características

- Carga y manipulación de archivos ODT.
- Inserción de datos y generación de reportes.
- Conversión a PDF usando LibreOffice/soffice.
- Soporte para imágenes y recursos embebidos.
- Ejemplos listos para usar.

## Requisitos

- PHP 8.1 o superior
- Composer
- LibreOffice (opcional, para conversión a PDF)

## Instalación

1. Clona el repositorio:
   ```sh
   git clone https://github.com/Tabula17/satelles-odf-relatio
   cd satelles-odf-relatio
   ```

2. Instala las dependencias:
   ```sh
   composer install
   ```

## Uso rápido

Genera un reporte y guárdalo en PDF (si tienes LibreOffice instalado):

```sh
php Examples/SaveToDisk.php
```

Los archivos generados se guardan en el directorio `Examples/Saves`.

Para más ejemplos y explicaciones consulta la sección de [ejemplos](./Examples/README.md).

## Estructura del proyecto

- `src/` — Código fuente principal.
- `Examples/` — Scripts de ejemplo y plantillas.
- `Examples/Templates/` — Plantillas ODT.
- `Examples/Media/` — Recursos y datos de ejemplo.
- `Examples/Saves/` — Reportes generados.

## Licencia

Consulta el archivo `LICENSE` para más información.