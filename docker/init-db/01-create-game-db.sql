-- Grant the app user permission to create and manage any travium_* game world databases.
-- Each world gets its own database (e.g. travium_s1, travium_s2) created automatically by the installer.

GRANT ALL PRIVILEGES ON `travium\_%`.* TO 'maindb'@'%';
FLUSH PRIVILEGES;
