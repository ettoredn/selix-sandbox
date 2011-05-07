PHP_ARG_ENABLE(selinux, whether to enable selinux support,
dnl Make sure that the comment is aligned:
[  --enable-selinux           Enable selinux support])

if test "$PHP_SELINUX" != "no"; then
  PHP_NEW_EXTENSION(selinux, selinux.c, $ext_shared)
fi
