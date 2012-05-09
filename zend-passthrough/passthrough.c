#define _GNU_SOURCE
#include <stdio.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <fcntl.h>
#include <signal.h>
#include <pthread.h>
#include <assert.h>

#ifdef HAVE_CONFIG_H
#include "config.h"
#endif
#include "php.h"
#include "php_variables.h"
#include "php_ini.h"
#include "ext/standard/info.h"
#include "zend_extensions.h"
#include "php_passthrough.h"

ZEND_DECLARE_MODULE_GLOBALS(passthrough)

void (*old_zend_execute)(zend_op_array *op_array TSRMLS_DC);
void passthrough_zend_execute(zend_op_array *op_array TSRMLS_DC);

zend_op_array *(*old_zend_compile_file)(zend_file_handle *file_handle, int type TSRMLS_DC);
zend_op_array *passthrough_zend_compile_file(zend_file_handle *file_handle, int type TSRMLS_DC);

int zend_passthrough_initialised = 0;

/*
 * Every user visible function must have an entry in passthrough_functions[].
 */
const zend_function_entry passthrough_functions[] = {
	{NULL, NULL, NULL}
};

zend_module_entry passthrough_module_entry = {
	STANDARD_MODULE_HEADER,
	"passthrough",
	passthrough_functions,
	PHP_MINIT(passthrough),
	PHP_MSHUTDOWN(passthrough),
	PHP_RINIT(passthrough),
	PHP_RSHUTDOWN(passthrough),
	PHP_MINFO(passthrough),
	"0.1",
	PHP_MODULE_GLOBALS(passthrough),
	NULL,
	NULL,
	NULL,
	STANDARD_MODULE_PROPERTIES_EX
};

#ifdef COMPILE_DL_PASSTHROUGH
ZEND_GET_MODULE(passthrough)
#endif

PHP_MINIT_FUNCTION(passthrough)
{
	if (zend_passthrough_initialised == 0)
	{
		zend_error(E_ERROR, "passthrough extension MUST be loaded as a Zend extension!");
		return FAILURE;
	}

	return SUCCESS;
}

PHP_MSHUTDOWN_FUNCTION(passthrough)
{
	return SUCCESS;
}

PHP_RINIT_FUNCTION(passthrough)
{
	/* Override zend_compile_file */
	old_zend_compile_file = zend_compile_file;
	zend_compile_file = passthrough_zend_compile_file;

	/* Override zend_execute */
	old_zend_execute = zend_execute;
	zend_execute = passthrough_zend_execute;
	
	return SUCCESS;
}

PHP_RSHUTDOWN_FUNCTION(passthrough)
{
	// Restore handlers
	zend_compile_file = old_zend_compile_file;
	zend_execute = old_zend_execute;
	
	return SUCCESS;
}

PHP_MINFO_FUNCTION(passthrough)
{
	php_info_print_table_start();
	php_info_print_table_header(2, "passthrough extension", "enabled");
	php_info_print_table_row(2, "Version", PASSTHROUGH_VERSION );
	php_info_print_table_row(2, "Compiled on", __DATE__ " at " __TIME__);
	php_info_print_table_end();
}

/*
 * zend_compile_file() handler
 */
zend_op_array *passthrough_zend_compile_file( zend_file_handle *file_handle, int type TSRMLS_DC )
{
 	return old_zend_compile_file( file_handle, type TSRMLS_CC );
}

/*
 * zend_execute() handler
 */
void passthrough_zend_execute( zend_op_array *op_array TSRMLS_DC )
{
	// int i, j=0;
	// for (i=0; i < 1000; i++)
	// 	j += i;
	return old_zend_execute( op_array TSRMLS_CC);
}

ZEND_DLEXPORT int passthrough_zend_startup(zend_extension *extension)
{
	zend_passthrough_initialised = 1;
	return zend_startup_module(&passthrough_module_entry);
}

ZEND_DLEXPORT void passthrough_zend_shutdown(zend_extension *extension)
{
	// Nothing
}

/* This is a Zend extension */
#ifndef ZEND_EXT_API
#define ZEND_EXT_API    ZEND_DLEXPORT
#endif
ZEND_EXTENSION();

ZEND_DLEXPORT zend_extension zend_extension_entry = {
	PASSTHROUGH_NAME,
	PASSTHROUGH_VERSION,
	PASSTHROUGH_AUTHOR,
	PASSTHROUGH_URL,
	PASSTHROUGH_COPYRIGHT,
	passthrough_zend_startup, 	// startup_func_t
	passthrough_zend_shutdown,	// shutdown_func_t
	NULL,					// activate_func_t
	NULL,					// deactivate_func_t
	NULL,					// message_handler_func_t
	NULL,					// op_array_handler_func_t
	NULL,					// statement_handler_func_t
	NULL,					// fcall_begin_handler_func_t
	NULL,					// fcall_end_handler_func_t
	NULL,					// op_array_ctor_func_t
	NULL,					// op_array_dtor_func_t
	STANDARD_ZEND_EXTENSION_PROPERTIES
};