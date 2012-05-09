PHP_ARG_ENABLE(passthrough, whether to enable passthrough extension,
[  --enable-passthrough           Enable passthrough extension])

if test "$PHP_PASSTHROUGH" != "no"; then
  CFLAGS="$CFLAGS -Wall -fvisibility=hidden"
    
  PHP_NEW_EXTENSION(passthrough, passthrough.c, $ext_shared,,,,yes)
  PHP_SUBST(PASSTHROUGH_SHARED_LIBADD)
fi
