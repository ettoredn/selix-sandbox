CREATE TABLE `php` (
  `session` int(11) NOT NULL,
  `timestamp` decimal(20,9) NOT NULL,
  `delta` decimal(15,9) DEFAULT NULL,
  `loglevel` smallint(6) NOT NULL,
  `name` varchar(80) NOT NULL,
  `args` text,
  PRIMARY KEY (`timestamp`),
  KEY `name` (`name`) USING BTREE,
  KEY `session` (`session`) USING BTREE
);

CREATE TABLE `selix` (
  `session` int(11) NOT NULL,
  `timestamp` decimal(20,9) NOT NULL,
  `delta` decimal(15,9) DEFAULT NULL,
  `loglevel` smallint(6) NOT NULL,
  `name` varchar(80) NOT NULL,
  `args` text,
  PRIMARY KEY (`timestamp`),
  KEY `name` (`name`) USING BTREE,
  KEY `session` (`session`) USING BTREE
);
