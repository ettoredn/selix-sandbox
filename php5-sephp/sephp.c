#ifdef HAVE_CONFIG_H
#include "config.h"
#endif

#include "php.h"
#include "php_variables.h"
#include "php_ini.h"
#include "ext/standard/info.h"
#include "php_sephp.h"

/* If you declare any globals in php_sephp.h uncomment this:
ZEND_DECLARE_MODULE_GLOBALS(sephp)
*/

/* We get called by PHP when it import environment variables ( main/php_variables.c:824 ) */
void (*old_php_import_environment_variables)(zval *array_ptr TSRMLS_DC);
void sephp_php_import_environment_variables(zval *array_ptr TSRMLS_DC);

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
	old_php_import_environment_variables = php_import_environment_variables;
	php_import_environment_variables = sephp_php_import_environment_variables;
	return SUCCESS;
}

PHP_RSHUTDOWN_FUNCTION(sephp)
{
	php_import_environment_variables = old_php_import_environment_variables;
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

void sephp_php_import_environment_variables(zval *array_ptr TSRMLS_DC)
{
    char *fcgi_params[SEPHP_PARAMS_COUNT] = { SEPHP_PARAMS };
    char *fcgi_values[SEPHP_PARAMS_COUNT];
    zval **data;
    HashTable *arr_hash;
    HashPosition pointer;
    int i;
    
	/* call php's original import as a catch-all */
	old_php_import_environment_variables(array_ptr TSRMLS_CC);

    arr_hash = Z_ARRVAL_P(array_ptr);
    for (zend_hash_internal_pointer_reset_ex(arr_hash, &pointer); 
    	zend_hash_get_current_data_ex(arr_hash, (void**) &data, &pointer) == SUCCESS; 
    	zend_hash_move_forward_ex(arr_hash, &pointer))
    {
    	char *key;
    	int key_len;
		long index;
		
    	if (zend_hash_get_current_key_ex(arr_hash, &key, &key_len, &index, 0, &pointer) == HASH_KEY_IS_STRING)
    	{
     		for (i=0; i < SEPHP_PARAMS_COUNT; i++)
     		{
     			if (!strncmp( key, fcgi_params[i], strlen(fcgi_params[i])))
     			{
     			// TODO handle of other types (int, null, etc)
					if (Z_TYPE_PP(data) == IS_STRING)
						fcgi_values[i] = Z_STRVAL_PP(data);
     			}
     		}
    	}
    }
    
    // TODO selinux stuff (set context, etc)
    for (i=0; i < SEPHP_PARAMS_COUNT; i++)
    {
 		if (fcgi_values[i])
 		{
//  			char buf[500];
//  			memset( buf, 0, sizeof(buf) );
//  			sprintf( buf, "SELINUX %s => %s <br>", fcgi_params[i], fcgi_values[i] );
//  			PHPWRITE( buf, strlen(buf) );
		}
	}
    
    for (i=0; i < SEPHP_PARAMS_COUNT; i++)
 		if (fcgi_values[i])
 			// Don't expose variables to scripts through $_SERVER
 			zend_hash_del(arr_hash, fcgi_params[i], strlen(fcgi_params[i]) + 1);
}
