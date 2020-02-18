<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>Document</title>
</head>
<body>
	options = [
		'CUIT'=>int|string:required,
		'CERT'=>string:optional,
		'KEY'=>string:optional,
		'PASSPHRASE'=>string:optional,
		'TA'=>string:optional,
		'WSAA'=>string:optional,
		'WSDL'=>string:optional
	]
	
	<br>

	<p>Descripcion | nombre por defecto</p>

	<p>CUIT : cuit de la empresa | REQUERIDO</p>

	<p>CERT : archivo .crt que proporciona la afip | cert.crt</p>

	<p>KEY	 : archivo .key que proporciona la afip | key.key</p>

	<p>PASSPHRASE : clave de parseo para encriptacion openssl | xxxxx</p>

	<p>TA : archivo .xml que proporciona la afip | TA-wsfe.xml</p>

	<p>WSAA : archivo .wsdl que proporciona la afip | wsaa.wsdl</p>

	<p>WSDL : archivo .wsdl que proporciona la afip | wsfe.wsdl</p>



	<p>Por defecto la libreria buscara en el storage de laravel, en la carpeta public/afip</p>
</body>
</html>



