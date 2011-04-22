#ifdef HAVE_CONFIG_H
#include "config.h"
#endif

#include "php.h"
#include "php_ini.h"
#include "ext/standard/info.h"
#include "php_sephp.h"

/* If you declare any globals in php_sephp.h uncomment this:
ZEND_DECLARE_MODULE_GLOBALS(sephp)
*/

/*
 * Every user visible function must have an entry in sephp_functions[].
 */
const zend_function_entry sephp_functions[] = {
	PHP_FE(confirm_sephp_compiled,	NULL) /* For testing, remove later */
	{NULL, NULL, NULL}
};

zend_module_entry sephp_module_entry = {
#if ZEND_MODULE_API_NO >= 20010901
	STANDARD_MODULE_HEADER,
#endif
	"sephp",
	sephp_functions,
	PHP_MINIT(sephp),
	PHP_MSHUTDOWN(sephp),
	PHP_RINIT(sephp),		/* Replace with NULL if there's nothing to do at request start */
	PHP_RSHUTDOWN(sephp),	/* Replace with NULL if there's nothing to do at request end */
	PHP_MINFO(sephp),
#if ZEND_MODULE_API_NO >= 20010901
	"0.1", /* Replace with version number for your extension */
#endif
	STANDARD_MODULE_PROPERTIES
};

#ifdef COMPILE_DL_SEPHP
ZEND_GET_MODULE(sephp)
#endif

PHP_MINIT_FUNCTION(sephp)
{
	return SUCCESS;
}

PHP_MSHUTDOWN_FUNCTION(sephp)
{
	return SUCCESS;
}

PHP_RINIT_FUNCTION(sephp)
{
	return SUCCESS;
}

PHP_RSHUTDOWN_FUNCTION(sephp)
{
	return SUCCESS;
}

PHP_MINFO_FUNCTION(sephp)
{
	php_info_print_table_start();
	php_info_print_table_header(2, "sephp support", "enabled");
	php_info_print_table_end();
}

PHP_FUNCTION(confirm_sephp_compiled)
{
	char *arg = NULL;
	int arg_len, len;
	char *strg;

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "s", &arg, &arg_len) == FAILURE) {
		return;
	}

	len = spprintf(&strg, 0, "Congratulations! Module %.78s is now loaded into PHP.", arg);
	RETURN_STRINGL(strg, len, 0);
}
