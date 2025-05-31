# XVII: satelles-odf-relatio
<p>
	<img src="https://img.shields.io/github/license/Tabula17/satelles-odf-relatio?style=default&logo=opensourceinitiative&logoColor=white&color=2141ec" alt="license">
	<img src="https://img.shields.io/github/last-commit/Tabula17/satelles-odf-relatio?style=default&logo=git&logoColor=white&color=2141ec" alt="last-commit">
	<img src="https://img.shields.io/github/languages/top/Tabula17/satelles-odf-relatio?style=default&color=2141ec" alt="repo-top-language">
	<img src="https://img.shields.io/github/languages/count/Tabula17/satelles-odf-relatio?style=default&color=2141ec" alt="repo-language-count">
</p>
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
    'items' => [ 'name' => 'item1', 'name' => 'item2', 'name' => 'item3']
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

Las plantillas pueden editarse directamente en openoffice o libreoffice.
Para utilizar las variables y estructuras de control, debes agregar etiquetas específicas en el documento ODF. Estas etiquetas se utilizan para identificar dónde se deben insertar los datos dinámicos.
Es necesario conocer la estructura XML del ODF para agregar las etiquetas correctamente.

Para agregar estos valores desde la inetrfaz de usuario, utiliza el menú "Insertar" -> "Campo" -> "Otro campo" -> "Funciones".
Agregar en la referencia la etiqueta correspondiente, posar el puntero en donde quiere agergar la etiqueta, accionar el botón "Insertar" y en el agregar el valor de la variable utilizando los siguientes patrones:

### Variables Simples
```xml
<text:text-input text:description="odf-tpl-text">${variable}</text:text-input>
```

### Bucles
Los bucles se utilizan para iterar sobre colecciones de datos. Se definen con la etiqueta `odf-tpl-loop` y se pueden utilizar dentro de tablas o listas.
La variable está conformada por el miembro en el set de datos que define el bucle (`item`) seguido de `#up@table:table-row` para indicar que se trata de una fila de tabla ubicada como padre del nodo `<text:text-input />` que contirnr la variable. 
El miembro anterior al `@` indica el nivel de iteración, mientras que el miembro posterior indica el tipo de elemento XML que se está iterando.
Por ejemplo si se necesita iterar un elemento en el mismo nivel, se utiliza `#left@text:p` o `#right@text:span` según corresponda. Si es un elemento hijo del contenedor, se utiliza `#down@text:p`.
En `as item` se define el alias de la variable que se utilizará para denominar a las variables hijas dentro del bucle.

```xml
<text:text-input text:description="odf-tpl-loop">items#up@table:table-row as item</text:text-input>
<text:text-input text:description="odf-tpl-text">${item.name}</text:text-input>
```

### Condiciones
```xml
<text:text-input text:description="odf-tpl-if">${total} > 1000#up@table:table-row</text:text-input>
```

### Imágenes Dinámicas

Para insertar imágenes dinámicas se debe agregar una imágen como 'placeholder' en donde irá la generada en el proceso y mediante las propiedades de la misma se agregan las etiquetas y las variables utilizando la siguiente sintaxis:

```xml
<draw:frame draw:name="odf-tpl-image">
    <svg:desc>${image_path}</svg:desc>
    <draw:image xlink:href=""/>
</draw:frame>
```
Si la imágen está dentro de un buclé y se genera de manera diferente en cada iteración, utiliza:
```xml   
<draw:frame draw:name="odf-tpl-image-loop">
   <svg:desc>${image_path}</svg:desc>
   <draw:image xlink:href=""/>
</draw:frame>
```
Si la imagen es un SVG, utiliza:
```xml
<draw:frame draw:name="odf-tpl-svg">
    <svg:desc>${svg_content}</svg:desc>
    <draw:image xlink:href=""/>
</draw:frame>
```
Al igual que las imágenes, si el SVG está dentro de un bucle, utiliza:
```xml
<draw:frame draw:name="odf-tpl-svg-loop">
    <svg:desc>${svg_content}</svg:desc>
    <draw:image xlink:href=""/>
</draw:frame>
```

### Operaciones Aritméticas
```xml
<text:text-input text:description="odf-tpl-text">${value1}+${value2}</text:text-input>
```

## Funciones Personalizadas

Para crear funciones personalizadas, extiende la clase `Tabula17\Satelles\Odf\Functions\Base` y define tus métodos. Luego, pasa tu clase de funciones al `DataRenderer`. 
La clase `Base` proporciona un método mágico que llama a funciones `PHP`. La clase `Advanced` ya incluye funciones para generar códigos QR y de barras.

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
- `printFile.php`: Impresión de documentos
- `saveToDisk.php`: Guardado básico a disco

Puede ver dentro de `examples/templates/` las plantillas utilizadas en los ejemplos, y dentro de `examples/media/` los datos y recursos necesarios para generar los reportes.

## Adaptaciones y Extensiones

### orbitalis-odf-exemplar
Adaptación para uso asincrónico mediante Swoole: [`orbitalis-odf-exemplar`](https://github.com/Tabula17/orbitalis-odf-exemplar).


## Licencia

MIT License

## Soporte

Para reportar problemas o solicitar nuevas características:
1. Revisa los issues existentes
2. Abre un nuevo issue con los detalles del problema o sugerencia
