dnl $Id$
dnl config.m4 for extension sephp

dnl Comments in this file start with the string 'dnl'.
dnl Remove where necessary. This file will not work
dnl without editing.

dnl If your extension references something external, use with:

dnl PHP_ARG_WITH(sephp, for sephp support,
dnl Make sure that the comment is aligned:
dnl [  --with-sephp             Include sephp support])

dnl Otherwise use enable:

PHP_ARG_ENABLE(sephp, whether to enable sephp support,
dnl Make sure that the comment is aligned:
[  --enable-sephp           Enable sephp support])

if test "$PHP_SEPHP" != "no"; then
  dnl Write more examples of tests here...

  dnl # --with-sephp -> check with-path
  dnl SEARCH_PATH="/usr/local /usr"     # you might want to change this
  dnl SEARCH_FOR="/include/sephp.h"  # you most likely want to change this
  dnl if test -r $PHP_SEPHP/$SEARCH_FOR; then # path given as parameter
  dnl   SEPHP_DIR=$PHP_SEPHP
  dnl else # search default path list
  dnl   AC_MSG_CHECKING([for sephp files in default path])
  dnl   for i in $SEARCH_PATH ; do
  dnl     if test -r $i/$SEARCH_FOR; then
  dnl       SEPHP_DIR=$i
  dnl       AC_MSG_RESULT(found in $i)
  dnl     fi
  dnl   done
  dnl fi
  dnl
  dnl if test -z "$SEPHP_DIR"; then
  dnl   AC_MSG_RESULT([not found])
  dnl   AC_MSG_ERROR([Please reinstall the sephp distribution])
  dnl fi

  dnl # --with-sephp -> add include path
  dnl PHP_ADD_INCLUDE($SEPHP_DIR/include)

  dnl # --with-sephp -> check for lib and symbol presence
  dnl LIBNAME=sephp # you may want to change this
  dnl LIBSYMBOL=sephp # you most likely want to change this 

  dnl PHP_CHECK_LIBRARY($LIBNAME,$LIBSYMBOL,
  dnl [
  dnl   PHP_ADD_LIBRARY_WITH_PATH($LIBNAME, $SEPHP_DIR/lib, SEPHP_SHARED_LIBADD)
  dnl   AC_DEFINE(HAVE_SEPHPLIB,1,[ ])
  dnl ],[
  dnl   AC_MSG_ERROR([wrong sephp lib version or lib not found])
  dnl ],[
  dnl   -L$SEPHP_DIR/lib -lm
  dnl ])
  dnl
  dnl PHP_SUBST(SEPHP_SHARED_LIBADD)

  PHP_NEW_EXTENSION(sephp, sephp.c, $ext_shared)
fi
