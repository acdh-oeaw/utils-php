# Things that need to be done to make this project future proof

* This component should be described using [composer](https://getcomposer.org) and then imported using it.
* This component should use [composer](https://getcomposer.org) to fetch its dependencies
* This component relies on global variables for configuration. They should be replaced with something better suited
* There should be PHPUnit tests
* Files are to long they should be split up
* Replace the custim HTTPRequest and HTTPResponse with some [PSR-7 implementation](http://www.php-fig.org/psr/psr-7/).
