--
-- itag extensions
--

--------------------------------  EXTENSION -----------------------------------------------

--
-- Unaccent extension to support text normalization
--
CREATE EXTENSION IF NOT EXISTS unaccent SCHEMA public;

-- 
-- PostGIS extension to support geometrical searches
--
CREATE EXTENSION IF NOT EXISTS postgis;
CREATE EXTENSION IF NOT EXISTS postgis_topology;
