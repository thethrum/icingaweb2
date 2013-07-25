create table icinga_users (
  user_name varchar(255) NOT NULL,
  first_name varchar(255),
  last_name varchar(255),
  email varchar(255),
  domain varchar(255),
  last_login timestamp,
  salt varchar(255),
  password varchar(255) NOT NULL,
  active BOOL,
  PRIMARY KEY (user_name)
);

/*
 *  user:     icingaadmin
 *  password: icinga
 */
INSERT INTO icinga_users (
    user_name,
    first_name,
    last_name,
    salt,
    password,
    active)
  VALUES (
    'icingaadmin',
    'john',
    'doe',
    'IepKgTTShC',
    '52deddb5cc7a5769484fcb0fbc5981a7c62cd9f3ddbb8ff3ddb1b89ea324ad16',
    true
  );