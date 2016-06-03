# utils-php

Contains utility and configuration classes used by fcs-aggregator and mysqlonsru.
Contains template XML files that are filled with data by the mysqlonsru classes.  

## Classes

* URL parmeter parsers and URL generators for an enhanced version of the SRU/FCS 1.2
protocol.
* Exceptions and report classes that are used to generate SRU/FCS diagnostics XML.
* Some abstraction classes for a Response and Headers to send in it.

### URL parmeter parsers and URL generators

[These classes](https://github.com/acdh-oeaw/utils-php/blob/master/common.php) provide three things:
* Parsing of query parameters specified in FCS/SRU and our own enhancements
** Also privides sane defaults for most parameters
* Generating a query string from the parameters stored or parsed
* Passing those parameters that are relevant in the XSL transform process
to the XSL processor

### Exceptions and report classes

[These classes](https://github.com/acdh-oeaw/utils-php/blob/master/diagnostics.php) provide:
* A means to transport an error condition as an exception that can be converted to a
corresponding XML snippet easiely
* A class that is returned directly using print

## Other Files

This project contains the [templates](https://github.com/acdh-oeaw/utils-php/tree/master/templates)
for vLIB that other parts use as the framework to deliver mostly FCS/SRU 1.2 compliant responses.
There is a [Tutorial and Examples document in German](http://vlib.clausvb.de/docs/vlib_einfuehrung.pdf)
and [Tutorial, quick reference, function list and documentation in English](http://vlib.clausvb.de/docs/vlibTemplate_english/table_of_content.html).
If those links stop working: [There is a local mirror of the docs.](https://acdh-oeaw.github.io/vLIB)

## Part of corpus_shell

Depends on [vLIB](https://github.com/acdh-oeaw/vLIB). See the umbrella project [corpus_shell](https://github.com/acdh-oeaw/corpus_shell).

## 3rd Party Software used

* Uses vLIB which is distributed under the Artistic License 2.0.
* Uses EpiCurl which is is part of the [epiphany framework](https://github.com/jmathai/epiphany). This is licensed using
The BSD 3-Clause License.

## More docs
