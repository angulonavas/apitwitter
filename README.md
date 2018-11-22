apitwitter
==========

A Symfony project created on November 20, 2018, 9:50 am.

Notas
=====

- los usuarios estandar y admin están configurados en app/config/security.yml (no usamos Doctrine)
- todas las claves que se deben definir están nombradas en /app/config/parameters.yml.dist
- Bundles y librerías de terceros utilizados:
	- LexikJWTAuthenticationBundle() --> para autenticación JWT por token
	- twitteroauth --> para autenticación y uso del API de Twitter

- pruebas de acceso realizadas con POSTMAN


interfaz del API TWITTER
========================

/api/login_check --> para autentificar al usuario

/api/buscar/usuario_alias/ --> para buscar todos los tweets del usuario
/api/buscar/usuario_alias/id --> para buscar el tweet "id" del usuario
/api/buscar/N/usuario_alias/n --> para buscar los últimos n tweets del usuario

/api/enviar/usuario_alias/texto --> para enviar un nuevo tweet a nombre de usuario

/api/eliminar/usuario_alias/id --> para eliminar el tweet id del usuario
