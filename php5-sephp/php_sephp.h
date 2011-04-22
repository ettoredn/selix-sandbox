#ifndef PHP_SEPHP_H
#define PHP_SEPHP_H

#define SEPHP_PARAMS_COUNT		2
#define SEPHP_PARAMS	"SERVER_SIGNATURE", "HTTP_USER_AGENT"
#define SEPHP_PARAM_SELINUX_CONTEXT	0
#define SEPHP_PARAM_SELINUX_TEST	1

extern zend_module_entry sephp_module_entry;
#define phpext_sephp_ptr &sephp_module_entry

#ifdef PHP_WIN32
#	define PHP_SEPHP_API __declspec(dllexport)
#elif defined(__GNUC__) && __GNUC__ >= 4
#	define PHP_SEPHP_API __attribute__ ((visibility("default")))
#else
#	define PHP_SEPHP_API
#endif

#ifdef ZTS
#include "TSRM.h"
#endif

PHP_MINIT_FUNCTION(sephp);
PHP_MSHUTDOWN_FUNCTION(sephp);
PHP_RINIT_FUNCTION(sephp);
PHP_RSHUTDOWN_FUNCTION(sephp);
PHP_MINFO_FUNCTION(sephp);

PHP_FUNCTION(confirm_sephp_compiled); /* For testing, remove later */

/* 
ZEND_BEGIN_MODULE_GLOBALS(sephp)
	long  global_value;
	char *global_string;
ZEND_END_MODULE_GLOBALS(sephp)
*/

#ifdef ZTS
#define SEPHP_G(v) TSRMG(sephp_globals_id, zend_sephp_globals *, v)
#else
#define SEPHP_G(v) (sephp_globals.v)
#endif

#endif