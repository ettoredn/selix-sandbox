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
#ifdef HAVE_CONFIG_H
#include "config.h"
#endif

#include "php.h"
#include "php_variables.h"
#include "php_ini.h"
#include "ext/standard/info.h"
#include "php_selinux.h"

/* If you declare any globals in php_selinux.h uncomment this:
ZEND_DECLARE_MODULE_GLOBALS(selinux)
*/

void (*old_php_import_environment_variables)(zval *array_ptr TSRMLS_DC);
void selinux_php_import_environment_variables(zval *array_ptr TSRMLS_DC);

void (*old_zend_execute)(zend_op_array *op_array TSRMLS_DC);
void selinux_zend_execute(zend_op_array *op_array TSRMLS_DC);

/*
 * Every user visible function must have an entry in selinux_functions[].
 */
const zend_function_entry selinux_functions[] = {
	{NULL, NULL, NULL}
};

zend_module_entry selinux_module_entry = {
#if ZEND_MODULE_API_NO >= 20010901
	STANDARD_MODULE_HEADER,
#endif
	"selinux",
	selinux_functions,
	PHP_MINIT(selinux),
	PHP_MSHUTDOWN(selinux),
	PHP_RINIT(selinux),
	PHP_RSHUTDOWN(selinux),
	PHP_MINFO(selinux),
#if ZEND_MODULE_API_NO >= 20010901
	"0.1", /* Replace with version number for your extension */
#endif
	STANDARD_MODULE_PROPERTIES
};

#ifdef COMPILE_DL_SELINUX
ZEND_GET_MODULE(selinux)
#endif

PHP_MINIT_FUNCTION(selinux)
{
	return SUCCESS;
}

PHP_MSHUTDOWN_FUNCTION(selinux)
{
	return SUCCESS;
}

PHP_RINIT_FUNCTION(selinux)
{
	/* Hijack php_import_environment_variables ( main/php_variables.c:824 ) */
	old_php_import_environment_variables = php_import_environment_variables;
	php_import_environment_variables = selinux_php_import_environment_variables;

	/* Hijack zend_execute to execute it in a SELinux context */
	old_zend_execute = zend_execute;
	zend_execute = selinux_zend_execute;
	
	return SUCCESS;
}

PHP_RSHUTDOWN_FUNCTION(selinux)
{
	php_import_environment_variables = old_php_import_environment_variables;
	zend_execute = old_zend_execute;
	
	return SUCCESS;
}

PHP_MINFO_FUNCTION(selinux)
{
	php_info_print_table_start();
	php_info_print_table_header(2, "SELinux support", "enabled");
	php_info_print_table_end();
}

/*
 * struct zend_op_array 	@ Zend/zend_compile.h:191
 * struct zend_execute_data @ Zend/zend_compile.h:308
 */
void selinux_zend_execute(zend_op_array *op_array TSRMLS_DC)
{
	zend_execute_data    *edata = EG(current_execute_data);

	// @DEBUG
	char buf[500];
	memset( buf, 0, sizeof(buf) );
	sprintf( buf, "[*] Executing %s <br>", op_array->filename );
	PHPWRITE( buf, strlen(buf) );
	
	old_zend_execute(op_array TSRMLS_DC);
}

void selinux_php_import_environment_variables(zval *array_ptr TSRMLS_DC)
{
	char *fcgi_params[SELINUX_PARAMS_COUNT] = { SELINUX_PARAMS };
	char *fcgi_values[SELINUX_PARAMS_COUNT];
	zval **data;
	HashTable *arr_hash;
	HashPosition pointer;
	int i;
	
	// @DEBUG
	char buf[500];
	memset( buf, 0, sizeof(buf) );
	sprintf( buf, "[*] Hijacking environment variables import..<br>" );
	PHPWRITE( buf, strlen(buf) );
	
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
			for (i=0; i < SELINUX_PARAMS_COUNT; i++)
			{
				/*
				 * Apache mod_fastcgi adds a parameter for every SetEnv <name> <value>
				 * in the form of "REDIRECT_<name>". It needs to be hide too.
				 */
				int redirect_len = strlen("REDIRECT_") + strlen(fcgi_params[i]) + 1;
				char *redirect_param = (char *) emalloc( redirect_len );
				
				memset( redirect_param, 0, redirect_len );
				strcat( redirect_param, "REDIRECT_" );
				strcat( redirect_param, fcgi_params[i] );
					
				if (!strncmp( key, fcgi_params[i], strlen(fcgi_params[i])))
				{
					// TODO handle of other types (int, null, etc) if needed
					if (Z_TYPE_PP(data) == IS_STRING)
					fcgi_values[i] = Z_STRVAL_PP(data);
					
					// @DEBUG
					memset( buf, 0, sizeof(buf) );
					sprintf( buf, "[*] Got %s => %s <br>", fcgi_params[i], fcgi_values[i] );
					PHPWRITE( buf, strlen(buf) );
					
					// Hide <selinux_param>
					zend_hash_del(arr_hash, key, strlen(key) + 1);
				}
				
				// Hide REDIRECT_<selinux_param> entries
				if (!strncmp( key, redirect_param, redirect_len ))
					zend_hash_del(arr_hash, key, strlen(key) + 1);
				
				efree( redirect_param );
			}
		}
	}
}
