# Cradle

> Simple PHP library for creating Web-based applications

##Includes:

A set of simple PHP classes, the most commonly used by the [SAD-Sysytems](http://sad-systems.ru) company
when:

 * creating their applications
 * making automation of the development process

The library also contains a micro framework for rapid start a simple web application.

##Pre requirements

Before you begin, you should install:

 * [PHP](http://php.net/downloads.php) version 5.4 or later.
 * [Composer](https://getcomposer.org) (recommended)

##Installation

### Install via `Git`

Just clone the repo: `git clone https://github.com/sad-systems/cradle.git`

### Install via `Composer`

#### Option 1:

Just add into the file `composer.json` the following settings:

~~~js
    "require": {
            "digger/cradle": "*"
        }
~~~

and run the command:

~~~sh
$ php composer.phar update
~~~

#### Option 2:

Just run the command:

~~~sh
$ php composer.phar global require "digger/cradle:*"
~~~

##Usage

All classes of this library are in the namespace `digger\cradle`.
It is recommended to include all classes using the autoloader such as `Composer` (you can also use
library's autoloader or your own).

A detailed description of the classes can be found in the section:
[Data Structures](http://sad-systems.ru/projects/cradle/doc/phpdoc/annotated.html)


## Creators

[Mr Digger](mailto://mrdigger@mail.ru)

## Copyright

Code and documentation copyright 2015 [SAD-Systems](http://sad-systems.ru) 
