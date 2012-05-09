#ifndef PHP_PASSTHROUGH_H
#define PHP_PASSTHROUGH_H

#define PASSTHROUGH_NAME			"passthrough"
#define PASSTHROUGH_VERSION 		"0.1"
#define PASSTHROUGH_AUTHOR 		"Ettore Del Negro"
#define PASSTHROUGH_URL 			"http://github.com/ettoredn/sephp-sandbox/zend-passthrough"
#define PASSTHROUGH_COPYRIGHT  	"Copyright (c) 2011-2012"

extern zend_module_entry passthrough_module_entry;
#define phpext_passthrough_ptr &passthrough_module_entry

#ifdef PHP_WIN32
#	define PHP_PASSTHROUGH_API __declspec(dllexport)
#elif defined(__GNUC__) && __GNUC__ >= 4
#	define PHP_PASSTHROUGH_API __attribute__ ((visibility("default")))
#else
#	define PHP_PASSTHROUGH_API
#endif

#ifdef ZTS
#include "TSRM.h"
#endif

PHP_MINIT_FUNCTION(passthrough);
PHP_MSHUTDOWN_FUNCTION(passthrough);
PHP_RINIT_FUNCTION(passthrough);
PHP_RSHUTDOWN_FUNCTION(passthrough);
PHP_MINFO_FUNCTION(passthrough);

ZEND_BEGIN_MODULE_GLOBALS(passthrough)
ZEND_END_MODULE_GLOBALS(passthrough)

#ifdef ZTS
#define PASSTHROUGH_G(v) TSRMG(passthrough_globals_id, zend_passthrough_globals *, v)
#else
#define PASSTHROUGH_G(v) (passthrough_globals.v)
#endif

#endif
