CREATE DATABASE sicode_core;
CREATE DATABASE sicodesk;

CREATE USER sicode_core WITH PASSWORD 'local_dev_password';
CREATE USER sicodesk WITH PASSWORD 'local_dev_password';

GRANT ALL PRIVILEGES ON DATABASE sicode_core TO sicode_core;
GRANT ALL PRIVILEGES ON DATABASE sicodesk TO sicodesk;

\connect sicode_core
GRANT ALL ON SCHEMA public TO sicode_core;
ALTER SCHEMA public OWNER TO sicode_core;

\connect sicodesk
GRANT ALL ON SCHEMA public TO sicodesk;
ALTER SCHEMA public OWNER TO sicodesk;

