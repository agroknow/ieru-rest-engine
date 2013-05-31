IERU REST Engine
================

About
-----
This is a simple REST Engine written in PHP, with a focus on being lightweight and fast. For making it work, it requires an API written also in PHP and copied in the folder of this engine, according to the PSR-0 standard (there is an example in the Github project [IERU Organic.Edunet APIs](https://github.com/ieru/ieru-oe-apis)). Further documentation for building an example API will be added in the future. It does not require any external libraries.

Usage example
-------------
This example is using Laravel as the main framework for a site, but it is not using Laravel at all.

In the /public directory, there is an .htacces file that makes the requests to an URL like //localhost/api/ avoid the Laravel framework, and use instead the REST API engine.

```
<IfModule mod_rewrite.c>
    
	RewriteCond %{REQUEST_FILENAME} !-f
	RewriteCond %{REQUEST_FILENAME} !-d
	RewriteRule ^(?!api) index.php/$1 [L]
    
</IfModule>
```
Following the above example, a file called api.php could have the following code for using the API.
```
<?php
// Autoload files with the Symfony autoloader, according to PSR-0
// It can be downloaded from the Symfony Github project
// https://github.com/symfony/ClassLoader/blob/master/ClassLoader.php
// I have placed it in a vendor/Symfony/Component directory, relative to this file
require_once( 'vendor/Symfony/Component/ClassLoader.php' );
$loader = new \Symfony\Component\ClassLoader\ClassLoader();

// register classes with namespaces
$loader->addPrefix( 'Ieru\\', __DIR__.'/ieru' );
$loader->register();
$loader->setUseIncludePath(true);

// Start ieru restengine, with api URI identifier and API URI namespace
$api = new \Ieru\Restengine\Engine\Engine( 'api', 'Ieru\Ieruapis' );
$api->start();
```
A call to a resource like /api/analytics/translate?text=computer&to=es would get an output like:
```
{
  "success": true,
  "message": "Translation done.",
  "data": {
    "translation": "Computadora"
  }
}
```