$options = [
	'CUIT'=>int|string:required,
	'CERT'=>string:optional,
	'KEY'=>string:optional,
	'PASSPHRASE'=>string:optional,
	'TA'=>string:optional,
	'WSAA'=>string:optional,
	'WSDL'=>string:optional,
]


Descripcion | nombre por defecto 
CUIT : cuit de la empresa | REQUERIDO

CERT : archivo .crt que proporciona la afip | cert.crt

KEY	 : archivo .key que proporciona la afip | key.key

PASSPHRASE : clave de parseo para encriptacion openssl | xxxxx

TA : archivo .xml que proporciona la afip | TA-wsfe.xml

WSAA : archivo .wsdl que proporciona la afip | wsaa.wsdl

WSDL : archivo .wsdl que proporciona la afip | wsfe.wsdl



Por defecto la libreria buscara en el storage de laravel, en la carpeta public/afip

