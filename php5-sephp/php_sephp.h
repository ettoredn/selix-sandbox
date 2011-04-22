#ifndef PHP_SEPHP_H
#define PHP_SEPHP_H

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

/* In every utility function you add that needs to use variables 
   in php_sephp_globals, call TSRMLS_FETCH(); after declaring other 
   variables used by that function, or better yet, pass in TSRMLS_CC
   after the last function argument and declare your utility function
   with TSRMLS_DC after the last declared argument.  Always refer to
   the globals in your function as SEPHP_G(variable).  You are 
   encouraged to rename these macros something shorter, see
   examples in any other php module directory.
*/

#ifdef ZTS
#define SEPHP_G(v) TSRMG(sephp_globals_id, zend_sephp_globals *, v)
#else
#define SEPHP_G(v) (sephp_globals.v)
#endif

#endif