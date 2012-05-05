// gcc -lrt -o getcputime getcputime.c
#include <stdio.h>
#include <string.h>
#include <errno.h>
#include <time.h>
#include <sys/resource.h>
#include <sched.h>

#define LOOPS_1 10000
#define LOOPS_2 100000

void do_stuff()
{
	int i, j;
	double a, b = 1.2, c = 2.1;
	
	for (i=1; i<LOOPS_1; i++)
	{
		if ((i % 12) == 0)
			i += 2;
			
		for (j=0; j<LOOPS_2; j++)
		{
			a += b;
			b += 0.2;
			c = a * 0.1;
		}
	}
}

static inline unsigned long long getcputime( clockid_t clk_id )
{
	struct timespec ts;

	if (clock_gettime( clk_id, &ts ))
		perror("clock_gettime() error:");

	return ts.tv_sec * 1000000000ULL + ts.tv_nsec;
}

int main( int argc, char **argv )
{
	unsigned long long process_start, thread_start, real_start, mono_start;
	unsigned long long process_end, thread_end, real_end, mono_end;
	struct timespec cputime;
	struct sched_param pri;
	clockid_t thread = CLOCK_THREAD_CPUTIME_ID;
	clockid_t process = CLOCK_PROCESS_CPUTIME_ID;
	clockid_t real = CLOCK_REALTIME;
	clockid_t monotonic = CLOCK_MONOTONIC;
	pri.sched_priority = 99;
	
	if (sched_setscheduler(0, SCHED_FIFO, &pri))
		perror("sched_setscheduler() error:");
	
	errno = 0;
	if (setpriority(PRIO_PROCESS, 0, -20))
		perror("setpriority() error:");
	
	real_start = getcputime( real );
	mono_start = getcputime( monotonic );
	process_start = getcputime( process );
	thread_start = getcputime( thread );
	
	do_stuff();

	real_end = getcputime( real );
	mono_end = getcputime( monotonic );
	process_end = getcputime( process );
	thread_end = getcputime( thread );
	
	printf( "Real: %llu\n", real_end - real_start );
	printf( "Monotonic: %llu\n", mono_end - mono_start );
	printf( "Process: %llu\n", process_end - process_start );
	printf( "Thread: %llu\n", thread_end - thread_start );
	
	return 0;
}
