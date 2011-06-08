/*
   +----------------------------------------------------------------------+
   | PHP Version 5                                                        |
   +----------------------------------------------------------------------+
   | Copyright (c) 1997-2011 The PHP Group                                |
   +----------------------------------------------------------------------+
   | This source file is subject to version 3.01 of the PHP license,      |
   | that is bundled with this package in the file LICENSE, and is        |
   | available through the world-wide-web at the following url:           |
   | http://www.php.net/license/3_01.txt                                  |
   | If you did not receive a copy of the PHP license and are unable to   |
   | obtain it through the world-wide-web, please send a note to          |
   | license@php.net so we can mail you a copy immediately.               |
   +----------------------------------------------------------------------+
   | Authors: Ettore Del Negro <write@ettoredelnegro.me>                  |
   +----------------------------------------------------------------------+
 */
#ifndef PHP_SELINUX_H
#define PHP_SELINUX_H

#define SELINUX_PARAMS_COUNT		2
#define PARAM_DOMAIN_IDX	0
#define PARAM_DOMAIN_NAME	"SELINUX_DOMAIN"
#define PARAM_RANGE_IDX		1
#define PARAM_RANGE_NAME	"SELINUX_RANGE"

extern zend_module_entry selinux_module_entry;
#define phpext_selinux_ptr &selinux_module_entry

#ifdef PHP_WIN32
#	define PHP_SELINUX_API __declspec(dllexport)
#elif defined(__GNUC__) && __GNUC__ >= 4
#	define PHP_SELINUX_API __attribute__ ((visibility("default")))
#else
#	define PHP_SELINUX_API
#endif

#ifdef ZTS
#include "TSRM.h"
#endif

PHP_MINIT_FUNCTION(selinux);
PHP_MSHUTDOWN_FUNCTION(selinux);
PHP_RINIT_FUNCTION(selinux);
PHP_RSHUTDOWN_FUNCTION(selinux);
PHP_MINFO_FUNCTION(selinux);

ZEND_BEGIN_MODULE_GLOBALS(selinux)
	char *separams_names[SELINUX_PARAMS_COUNT];
	char *separams_values[SELINUX_PARAMS_COUNT];
ZEND_END_MODULE_GLOBALS(selinux)

#ifdef ZTS
#define SELINUX_G(v) TSRMG(selinux_globals_id, zend_selinux_globals *, v)
#else
#define SELINUX_G(v) (selinux_globals.v)
#endif

#endif
