-- Bases separadas para la suite de pruebas, la cobertura y la mutación.
--
-- El `environment` del contenedor `db` fija MARIADB_DATABASE=sistema y eso es lo
-- único que MariaDB crea sola al arrancar. `make test` pasa -e DB_DATABASE=sistema_test
-- (y `make cobertura`/`make mutacion` sus propias bases) precisamente para no
-- correr RefreshDatabase contra la base de desarrollo, pero esas bases tienen que
-- existir y el usuario de la app tener permiso sobre ellas.
--
-- Sin este archivo, en un clon limpio del scaffold cada uno de esos comandos
-- falla con «Access denied for user 'sistema'@'%' to database '…'» — decenas de
-- tests que no son fallos reales, solo la base ausente.
CREATE DATABASE IF NOT EXISTS `sistema_test`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE DATABASE IF NOT EXISTS `sistema_cobertura_test`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE DATABASE IF NOT EXISTS `sistema_mutacion_test`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

GRANT ALL PRIVILEGES ON `sistema_test`.* TO `sistema`@`%`;
GRANT ALL PRIVILEGES ON `sistema_cobertura_test`.* TO `sistema`@`%`;
GRANT ALL PRIVILEGES ON `sistema_mutacion_test`.* TO `sistema`@`%`;
FLUSH PRIVILEGES;
