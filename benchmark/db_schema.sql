CREATE TABLE `tracedata` (
  `session` int(11) NOT NULL,
  `configuration` varchar(40) NOT NULL,
  `timestamp` decimal(20,9) NOT NULL,
  `delta` decimal(15,9) DEFAULT NULL,
  `loglevel` smallint(6) NOT NULL,
  `name` varchar(80) NOT NULL,
  `args` text,
  PRIMARY KEY (`timestamp`),
  KEY `name` (`name`) USING BTREE,
  KEY `session` (`session`) USING BTREE
);

CREATE TABLE `session` (
  `session` int(11) NOT NULL,
  `benchmarks` text NOT NULL,
  `runs` int(11) NOT NULL,
  `description` text,
  PRIMARY KEY (`session`)
);
