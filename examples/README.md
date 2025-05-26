# Ejemplos

### Esquema de directorios

* [Templates](templates) contiene las plantillas utilizadas en los scripts.
  * [Report.odt](templates/Report.odt)
  * [Report_Complex.odt](templates/Report_Complex.odt)
* [Media](media)
  * [Data.php](media/data.php) genera datos aleatorios en combinación con las imágenes existentes en el directorio [Media](media) para alimentar las plantillas y generar reportes de ejemplo.
* [Saves](saves) es el directorio donde se guardan los reportes generados.
* El directorio [tmp](./tmp) es utilizado para almacenar los archivos temporales generados en el porceso.

### Scripts de ejemplo

> Para todos los scripts es neceasrio tener instalado los paquetes sugeridos en composer.

#### [SaveToDisk.php](saveToDisk.php):

Genera el reporte con base en la plantilla [Report.odt](templates/Report.odt) y guarda el resultado en el directorio [Saves](saves).
Si detecta la instalación de LibreOffice convierte el archivo resultante en PDF.
Para correr el script ejecutar 
```sh
php examples/saveToDisk.php
```

#### [SaveToDiskComplex.php](saveToDiskComplex.php):

Genera el reporte con base en la plantilla [Report_Complex.odt](templates/Report_Complex.odt) y guarda el resultado en el directorio [Saves](saves).
Si detecta la instalación de LibreOffice conierte el archivo resultante en PDF.
Para correr el script ejecutar 
```sh
php examples/saveToDiskComplex.php
```

#### [PrintFile.php](printFile.php):

Imprime el archivo generado desde la plantilla [Report.odt](templates/Report.odt).
Para correr el script ejecutar 
```sh
php examples/PrintFile.php --printer PRINTER_NAME
```
Donde `PRINTER_NAME` es el nombre de la impresora a utilizar.
Debe estar instalado y corriendo el servicio de `CUPS` en el sistema.

#### [SendMail.php](sendMail.php):

Genera el reporte con base en la plantilla [Report_Complex.odt](templates/Report_Complex.odt) y lo envía por correo elctrónico.
Si detecta LibreOffice lo envía en formato PDF. 
Este ejemplo utiliza como transporte `Symfony\Mailer` o `Nette\Mail`.
`Symfony\Mailer` está configurado para utilizar el `DSN` de GMail.
Para correr el script ejecutar

Para `Symfony\Mailer`:
```sh
php examples/sendMail.php -u YOUR_USERNAME -p YOUR_APPKEY -s SENDER@EMAIL -t TO_ADDRESSES_BY_COMMA
```

Para `Nette\Mail`:
```sh
php examples/sendMail.php --transport nette -u YOUR_USERNAME -p SMTP_PASSWORD -s SENDER@EMAIL -t TO_ADDRESSES_BY_COMMA  -h SMTP_HOST -e ENCRYPTION
```
* `YOUR_USERNAME`: Nombre de usuario para el envío, ya sea GMail o SMTP
* `YOUR_APPKEY`: App key para el envío mediante GMail. ([Info](https://support.google.com/mail/answer/185833))
* `SENDER@EMAIL`: La dirección de correo de quien envía.
* `TO_ADDRESSES_BY_COMMA`: Direcciones de los destinatarios separadas por coma.
* `SMTP_HOST`: Servidor SMTP (Nette).
* `SMTP_PASSWORD`: Clave para el envío mediante SMTP (Nette).
* `ENCRYPTION`:  'ssl' o 'tls'


#### [MultipleActions.php](multipleActions.php):

Este ejemplo combina las funcionalidades de [SaveToDiskComplex.php](saveToDiskComplex.php), [PrintFile.php](printFile.php) y [SendMail.php](sendMail.php).
Para correr el script ejecutar 
```sh
php examples/multipleActions.php
```
y combinar los parámetros de [PrintFile.php](printFile.php) y [SendMail.php](sendMail.php).
