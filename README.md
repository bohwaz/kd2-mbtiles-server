# KD2 mini MBTiles tile server (PHP)

This is a simple yet powerful and fast MBTiles server.

It will serve tiles out of .mbtiles files (SQLite database) using the /z/x/y.png format requests.

You can enable a local file cache to serve files faster.

Support for X-SendFile (Apache and Lighttpd) is included but you have to enable it.

It's currently the lightest and fastest MBTiles tile server.

Requirements:
* PHP 5.4+
* SQLite3 binding for PHP (debian/ubuntu: apt-get install php5-sqlite)

## Use

* Edit the PHP file to change the path to the MBTiles file.
* Copy the PHP file and .htaccess to a directory.
* Configure OpenLayers or LeafLet to make requests on http://yoursite.tld/mbtiles/directory/{z}/{x}/{y}.png

## Best configuration for Apache

This is actually the fastest configuration.
This way PHP gets called only when the file is not cached.
So 99% of times the file is served by Apache.

* Optional: Install and enable the X-Sendfile extension, set ENABLE_X_SENDFILE to true.
* Enable local cache: set ENABLE_LOCAL_CACHE to true
* Set your virtual host to the cache/ directory
* Add this to your Apache vhost config:
    Options None
    DocumentRoot /path/to/mbtiles/directory/cache
    ErrorDocument 404 /path/to/mbtiles/directory/mbtiles-server.php

You don't need to enable the RewriteEngine.
