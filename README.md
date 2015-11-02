# Ansible Zray role
This role lets you install Zray (http://www.zend.com/en/products/server/z-ray) on your webserver

## Requirements
- Ubuntu (12.04 or higher), Debian (8 or higher) or CentOS (7 or higher)
- Apache v2.4 (all), Nginx (if Ubuntu, version 14+ needed)
- Php 5.5 or 5.6
- Following php extensions:
 - ctype
 - json
 - pdo
 - pdo_sqlite
 - SimpleXML
 - zip
 - curl
- Additionnal php package needed
 - fpm (only for Nginx)

## Variables
- zray_apache (true|false) Whether to configure Zray for apache. Defaults to `true`
- zray_nginx (true|false) Whether to configure Zray for nginx. Defaults to `false`
- zray_php_path (optionnal) Path to the php binary. Defaults to the return value of `which php`

## Additional information
On Debian or Ubuntu, the role should install the php dependencies automatically.

## Known issues
When both zray_nginx and zray_apache are set to true, ZRay will only be configured for nginx
