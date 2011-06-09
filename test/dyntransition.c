/*
 * Compile with:
 *	gcc -lpthread -lselinux -o dyntransition dyntransition.c && chcon -t php_exec_t dyntransition
 */
#include <stdio.h>
#include <selinux/selinux.h>
#include <selinux/context.h>
#include <pthread.h>

int selinux_set_domain( char *domain, char *range )
{
	security_context_t current_ctx, new_ctx, newraw_ctx;
	context_t context;
	char buf[500];
	int ret;
	
	if (getcon( &current_ctx ) < 0)
	{
		printf("getcon() failed\n");
		return -1;
	}
	
	context = context_new( current_ctx );
	if (!context)
	{
		printf("context_new() failed\n");
		freecon( current_ctx );
		return -1;
	}
	printf( "[*] SELinux current context: %s\n", current_ctx );
	freecon( current_ctx );
	
	context_type_set( context, domain );
	context_range_set( context, range );
	new_ctx = context_str( context );
	if (!new_ctx)
	{
		printf("context_str() failed\n");
		context_free( context );		
		return -1;
	}
	printf( "[*] SELinux new context: %s\n", new_ctx );

	ret = setcon( new_ctx );
	if ( ret < 0)
	{
		printf("setcon() failed with return code %d\n", ret);
		context_free( context );
		return -1;
	}

	context_free( context );
	return 0;
}

void *dummy( void *data )
{
	security_context_t current_ctx;
	
	selinux_set_domain( "php_user_content_t", "s0-s0:c0.c1023" );
	
	if (getcon( &current_ctx ) < 0)
	{
		printf("getcon() failed\n");
		return;
	}
	printf( "[*] SELinux context after setcon: %s\n", current_ctx );
	freecon( current_ctx );
}

int main( int argc, char **argv )
{
	pthread_t execute_thread;
	
	if (pthread_create( &execute_thread, NULL, dummy, argv ))
	{
		printf("pthread_create() error");
		return;
	}
	pthread_join( execute_thread, NULL );

	return 0;
}
