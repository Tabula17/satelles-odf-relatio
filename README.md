# XVII: 🛰️ satelles-odf-relatio
![PHP Version](https://img.shields.io/badge/PHP-8.4%2B-blue)
![License](https://img.shields.io/github/license/Tabula17/satelles-odf-relatio)
![Last commit](https://img.shields.io/github/last-commit/Tabula17/satelles-odf-relatio)

**Librería satélite para manipulación de documentos ODF** (Open Document Format).

## Características

- Sistema avanzado de plantillas ODF con soporte para:
  - Variables simples y anidadas
  - Bucles
  - Condiciones IF
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
- Compatible con Swoole y procesamiento concurrente mediante corrutinas

## Requisitos

- PHP 8.4+
- Extensión ZIP de PHP
- LibreOffice (opcional, para conversión a PDF)
- Composer

## Instalación

```bash
composer require xvii/satelles-odf-relatio
```

## Uso Básico

### Crear y Procesar un Documento
El proceso de creación y procesamiento de un documento ODF es sencillo. 
A continuación se muestra un ejemplo básico de cómo utilizar la biblioteca para generar un documento a partir de una plantilla ODF.

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
El método `process` aplica los datos a la plantilla, `compile` genera el documento ODF.
`OdfProcessor` admite implementaciones de `OdfContainerInterface`, `DataRendererInterface` y `XmlProcessorInterface`.
Si se proporciona un procesador XML, se utilizan su contenedor y su renderer, salvo que se pasen explícitamente al constructor; en ese caso, los argumentos explícitos prevalecen y se asignan al procesador XML. El renderer compartido recibe el directorio de trabajo y los datos del procesamiento.
El método `exportTo` puede ser utilizado para exportar a diferentes formatos, como ODF nativo o PDF, dependiendo de la implementación del exportador.
Hasta ese momento, el documento ODF generado se encuentra en el directorio de trabajo temporal especificado.
Este método se puede encadenar y volver a ejecutar con otros exportadores para realizar operaciones adicionales, como enviar por correo o imprimir.
La clase `ExportToFile` utilizada en los ejemplos guarda el archivo generado en el disco. 
Los "exportadores" pueden incluir una instancia de una clase que implemente `ConverterInterface` para la conversión del formato final (por ejemplo, `SofficeConverter` para convertir el archivo ODF a PDF si encuentra instalado LibreOffice en el sistema).
`SofficeConverter` ejecuta el comando `soffice` localmente. Para escenarios de alto rendimiento y producción asíncrona donde no se desea bloquear el proceso, se recomienda combinar la exportación con la librería [`satelles-odf-adiutor`](https://github.com/Tabula17/satelles-odf-adiutor), que realiza conversiones de forma asincrónica mediante Swoole y Unoserver.
Al finalizar, se limpia el directorio de trabajo temporal con `cleanUpWorkingDir`.

### Múltiples reportes desde una misma plantilla

Cuando se generan varios documentos a partir del mismo template, crear un `OdfProcessor` por cada set de datos implica descomprimir y parsear el ODT una y otra vez. `OdfTemplate` resuelve eso: parsea la plantilla **una sola vez** y produce trabajos independientes con copias de la estructura XML ya cacheada.

```php
use Tabula17\Satelles\Odf\OdfTemplate;

$odfTemplate = new OdfTemplate('template.odt', '/tmp', $fileContainer, $renderer);
$odfTemplate->load(); // warm-up opcional para workers de larga duración

$results = $odfTemplate->processAll(
    $dataSets,
    static fn(int $index) => "/output/report-{$index}.odt"
);
```

`processAll()` devuelve un array con el resultado de cada trabajo (`processId`, exporters, errores, duración, etc.). Si necesitas controlar la exportación de cada uno (por ejemplo, convertir a PDF, enviar por correo o imprimir), crea los trabajos manualmente:

```php
foreach ($dataSets as $index => $data) {
    $job = $odfTemplate->createProcessor();
    try {
        $job->process($data)
            ->compile()
            ->exportTo(new ExportToFile('/output', "report-{$index}.odt"));
    } finally {
        $job->cleanUpWorkingDir();
    }
}
```

Cada trabajo tiene su propio directorio temporal y su propio `OdfContainer` (con copias de los nodos XML), por lo que las mutaciones de un reporte no afectan a los demás ni al template cacheado. El `DataRenderer` se comparte entre trabajos porque es stateless. Ver `examples/multipleReports.php`.

## Sintaxis de Plantillas

Las plantillas pueden editarse directamente en openoffice o libreoffice.
Para utilizar las variables y estructuras de control, debes agregar etiquetas específicas en el documento ODF. Estas etiquetas se utilizan para identificar dónde se deben insertar los datos dinámicos.
Es necesario conocer la estructura XML del ODF para agregar las etiquetas correctamente.

Para agregar estos valores desde la inetrfaz de usuario, utiliza el menú "Insertar" -> "Campo" -> "Otro campo" -> "Funciones".
Agregar en la referencia la etiqueta correspondiente (ej. `odf-tpl-text`) , posar el puntero en donde quiere agregar la etiqueta, accionar el botón "Insertar" y en el agregar el valor de la variable (ej. `${variable}`). 

### Variables Simples
El nombre de la variable (`${variable}`) debe corresponder al nombre del miembro en el set de datos que se está utilizando.
En el caso de que la variable necesite tener un valor por defecto, se puede utilizar la sintaxis `${variable?defaultValue}`.
```xml
<text:text-input text:description="odf-tpl-text">${variable}</text:text-input>
```

### Bucles
Los bucles se utilizan para iterar sobre colecciones de datos. Se definen con la etiqueta `odf-tpl-loop` y se pueden utilizar dentro de tablas o listas.
```xml
<text:text-input text:description="odf-tpl-loop">items#up@table:table-row as item</text:text-input>
<text:text-input text:description="odf-tpl-text">${item.name}</text:text-input>
```
La variable está conformada por el miembro en el set de datos que define el bucle (`items` en este caso) seguido del descriptor del elemento que va a repetirse en la iteración. 
En este ejemplo`#up@table:table-row` repite en la iteración una fila de tabla ubicada como padre del nodo `<text:text-input />` que contiene la variable. 
El miembro anterior al `@` indíca el nivel de iteración, mientras que el miembro posterior define al elemento XML que se está iterando.
Por ejemplo si se necesita iterar un elemento en el mismo nivel, se utiliza `#left@text:p` si está posicionado antes o `#right@text:span` si está después. 
Si es un elemento hijo del contenedor, se utiliza `#down@text:p`.
En `as item` se define el alias de la variable que se utilizará para denominar a las variables hijas dentro del bucle.


### Condiciones
Las condiciones se definen con la etiqueta `odf-tpl-if` y permiten mostrar u ocultar contenido basado en condiciones lógicas.
La sintaxis es similar a la de las variables, pero se utiliza para evaluar expresiones. Ver el método `evaluateExpression` en la clase `XmlProcessor` para más detalles sobre cómo se evalúan las condiciones.
```xml
<text:text-input text:description="odf-tpl-if">${total} > 1000#up@table:table-row</text:text-input>
```

### Imágenes Dinámicas

Para insertar imágenes dinámicas se debe agregar una imágen como 'placeholder' en donde irá la generada en el proceso y mediante las propiedades de la misma se agregan las etiquetas (nombre) y las variables (texto alternativo) utilizando la siguiente sintaxis:

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
Para realizar operaciones aritméticas básicas dentro de las plantillas, puedes utilizar la sintaxis `${value1 + value2}`.
```xml
<text:text-input text:description="odf-tpl-text">${value1}+${value2}</text:text-input>
```
Estas operaciones son evaluadas mediante la clase `Tabula17\Satelles\Securitas\Evaluator\SafeMathEvaluator` de la librería `xvii/satelles-securitas`.

### Funciones de formateo y transformación de datos
La clase `DataRenderer` permite definir funciones personalizadas que pueden ser utilizadas en las plantillas como "transformadores" de los datos pasados a las variables. 
Estas se definen en la clase de funciones que se pasa al `DataRenderer`. La clase `Base` proporciona un método mágico que llama a funciones `PHP` lo cual permite utilizar funciones nativas de PHP directamente en las plantillas.
En la sintaxis de la plantilla, se utiliza el símbolo `#` seguido del nombre de la función y sus parámetros separados por `|`. 
El primer parámetro pasado automáticamente a la función es el valor de la variable, por lo cual no hay que incluirlo. 
Los siguientes son los parámetros adicionales que la función pueda requerir.
Si la función requiere que el parámetro con el valor de la variable NO sea el primero se debe ubicar el término `__VALUE__` en la posición requerida.

Por ejemplo, para utilizar la función `strtoupper` de PHP, puedes hacer lo siguiente:

```xml
<text:text-input text:description="odf-tpl-text">${variable#strtoupper}</text:text-input>
```
O para formatear un número con dos decimales:
```xml
<text:text-input text:description="odf-tpl-text">${variable#number_format|2|,}</text:text-input>
```
#### Funciones Personalizadas y/o Avanzadas

Para crear funciones personalizadas, extiende la clase `Tabula17\Satelles\Odf\Functions\Base` o crea una nueva clase que implemente `Tabula17\Satelles\Odf\FunctionsInterface` y define tus métodos. 
Luego, pasa tu clase de funciones al `DataRenderer`.
Como ejemplo está la clase `Advanced` que incluye funciones para generar códigos QR y de barras (utilizados en los ejemplos).

```php
use Tabula17\Satelles\Odf\Functions\Base;

class MyFunctions extends Base { /*o implements FunctionsInterface */
    public function customFormat($value, $param) {
        // Implementación personalizada
        return $formatted;
    }
}

$renderer = new DataRenderer($data, new MyFunctions());
```
```xml
<text:text-input text:description="odf-tpl-text">${variable#customFormat|paramValue}</text:text-input>
```

## Ejemplos Incluidos

El directorio `examples/` contiene ejemplos completos:
- `saveToDiskComplex.php`: Procesamiento complejo con guardado a disco
- `sendMail.php`: Envío por correo electrónico
- `multipleActions.php`: Múltiples acciones de exportación
- `multipleReports.php`: Múltiples reportes reutilizando un template cacheado
- `printFile.php`: Impresión de documentos
- `saveToDisk.php`: Guardado básico a disco

Puede ver dentro de `examples/templates/` las plantillas utilizadas en los ejemplos, y dentro de `examples/media/` los datos y recursos necesarios para generar los reportes.

## Rendimiento y Entornos Asíncronos (Swoole)

`OdfProcessor` está diseñado y optimizado para entornos de alto rendimiento como **Swoole** (no utiliza estado global y cuenta con carga *lazy* y caché de partes XML).

### Procesamiento Paralelo con Corrutinas

Dentro de un servidor Swoole o en un contexto donde ya existan corrutinas activas, es posible habilitar el procesamiento paralelo invocando:

```php
use Tabula17\Satelles\Odf\OdfProcessor;

// Habilita el procesamiento concurrente de archivos y exportaciones mediante corrutinas
OdfProcessor::enableParallelProcessing(true);
```

### Conversión Asíncrona a PDF

Para la conversión de documentos ODF a PDF u otros formatos sin bloquear el bucle de eventos (*event loop*), se recomienda combinar esta librería con:
- [`🛰️ satelles-odf-adiutor`](https://github.com/Tabula17/satelles-odf-adiutor): Conversor asincrónico y de alto rendimiento para documentos ODF que utiliza Swoole y Unoserver.


## Licencia

MIT License

## Soporte

Para reportar problemas o solicitar nuevas características:
1. Revisa los issues existentes
2. Abre un nuevo issue con los detalles del problema o sugerencia

###### 🌌 Ad astra per codicem