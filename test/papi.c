// gcc -lpapi papi.c -o papi
#include <stdlib.h>
#include <stdio.h>
#include <papi.h>

#define NUM_FLOPS  20000000

void handle_error (int retval)
{
     printf("PAPI error %d: %s\n", retval, PAPI_strerror(retval));
     exit(1);
}

void dummy( void *array )
{
/* Confuse the compiler so as not to optimize
   away the flops in the calling routine    */
/* Cast the array as a void to eliminate unused argument warning */
	( void ) array;
}

do_flops( int n )
{
	int i;
	double c = 0.11;
	double a = 3.123;
	double b = 39.129;

	for ( i = 0; i < n; i++ ) {
		c += a * b;
	}
	dummy( ( void * ) &c );
}


int main( int argc, char **argv )
{
	int EventSet = PAPI_NULL;
	long_long values[1];
	
	if (PAPI_library_init(PAPI_VER_CURRENT) != PAPI_VER_CURRENT)
		fprintf(stderr, "PAPI_library_init error!\n");
	
	/* Create the Event Set */
	if (PAPI_create_eventset(&EventSet) != PAPI_OK)
	    handle_error(1);

	/* Add Total Instructions Executed to our Event Set */
	if (PAPI_add_event(EventSet, PAPI_TOT_CYC) != PAPI_OK)
	    handle_error(1);

	/* Start counting events in the Event Set */
	if (PAPI_start(EventSet) != PAPI_OK)
	    handle_error(1);

	/* Defined in tests/do_loops.c in the PAPI source distribution */
	do_flops(NUM_FLOPS);

	/* Read the counting events in the Event Set */
	if (PAPI_read(EventSet, values) != PAPI_OK)
	    handle_error(1);

	printf("After reading the counters: %lld\n",values[0]);

	/* Reset the counting events in the Event Set */
	if (PAPI_reset(EventSet) != PAPI_OK)
	  handle_error(1);

	do_flops(NUM_FLOPS);

	/* Add the counters in the Event Set */
	if (PAPI_accum(EventSet, values) != PAPI_OK)
	   handle_error(1);
	printf("After adding the counters: %lld\n",values[0]);

	do_flops(NUM_FLOPS);

	/* Stop the counting of events in the Event Set */
	if (PAPI_stop(EventSet, values) != PAPI_OK)
	    handle_error(1);

	printf("After stopping the counters: %lld\n",values[0]);
	
	return 0;
}
