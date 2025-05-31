# XVII: satelles-odf-relatio

Una biblioteca PHP para procesar documentos ODF (Open Document Format) con un potente sistema de plantillas y múltiples opciones de exportación.

## Características

- Sistema avanzado de plantillas ODF con soporte para:
  - Variables simples y anidadas
  - Bucles
  - Condiciones (if/else)
  - Imágenes y SVG dinámicos
  - Operaciones aritméticas
  - Funciones personalizables
- Múltiples formatos de exportación:
  - ODF nativo
  - PDF (requiere LibreOffice)
- Opciones de salida flexibles:
  - Guardar a disco
  - Enviar por correo (Symfony Mailer/Nette)
  - Imprimir (CUPS)

## Requisitos

- PHP 8.3+
- Extensión ZIP de PHP
- LibreOffice (opcional, para conversión a PDF)
- Composer

## Instalación

```bash
composer require xvii/satelles-odf-relatio
```

## Uso Básico

### Crear y Procesar un Documento

```php
use Tabula17\Satelles\Odf\OdfProcessor;
use Tabula17\Satelles\Odf\File\OdfContainer;
use Tabula17\Satelles\Odf\Renderer\DataRenderer;
use Tabula17\Satelles\Odf\Functions\Advanced;
use Tabula17\Satelles\Odf\Exporter\ExportToFile;

// Configuración básica
$zipHandler = new ZipArchive();
$fileContainer = new OdfContainer($zipHandler);
$functions = new Advanced('/tmp');
$data = [
    'title' => 'Mi Documento',
    'items' => ['item1', 'item2', 'item3']
];

// Crear el procesador
$renderer = new DataRenderer($data, $functions);
$processor = new OdfProcessor(
    'template.odt',
    '/tmp',
    $fileContainer,
    $renderer
);

// Procesar y exportar
$processor->loadFile()
    ->process($data)
    ->compile()
    ->exportTo(new ExportToFile('/output', 'result.odf'))
    ->cleanUpWorkingDir();
```

## Sintaxis de Plantillas

### Variables Simples
```xml
<text:text-input text:description="odf-tpl-text">${variable}</text:text-input>
```

### Bucles
```xml
<text:text-input text:description="odf-tpl-loop">items#up@table:table-row as item</text:text-input>
<text:text-input text:description="odf-tpl-text">${item.name}</text:text-input>
```

### Condiciones
```xml
<text:text-input text:description="odf-tpl-if">${total} > 1000#up@table:table-row</text:text-input>
```

### Imágenes Dinámicas
```xml
<draw:frame draw:name="odf-tpl-image">
    <svg:desc>${image_path}</svg:desc>
    <draw:image xlink:href=""/>
</draw:frame>
```

### Operaciones Aritméticas
```xml
<text:text-input text:description="odf-tpl-text">${value1}+${value2}</text:text-input>
```

## Funciones Personalizadas

```php
use Tabula17\Satelles\Odf\Functions\Base;

class MyFunctions extends Base {
    public function customFormat($value, $param) {
        // Implementación personalizada
        return $formatted;
    }
}

$renderer = new DataRenderer($data, new MyFunctions());
```
```xml
<text:text-input text:description="odf-tpl-text">${variable?customFormat|paramValue}</text:text-input>
```

## Ejemplos Incluidos

El directorio `examples/` contiene ejemplos completos:
- `saveToDiskComplex.php`: Procesamiento complejo con guardado a disco
- `sendMail.php`: Envío por correo electrónico
- `multipleActions.php`: Múltiples acciones de exportación

## Adaptaciones y Extensiones

### orbitalis-odf-exemplar
Adaptación para uso asincrónico mediante Swoole: [`orbitalis-odf-exemplar`](https://github.com/Tabula17/orbitalis-odf-exemplar).


## Licencia

MIT License

## Soporte

Para reportar problemas o solicitar nuevas características:
1. Revisa los issues existentes
2. Abre un nuevo issue con los detalles del problema o sugerencia
