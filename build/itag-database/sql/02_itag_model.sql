
CREATE SCHEMA IF NOT EXISTS datasources;
CREATE SCHEMA IF NOT EXISTS gpw;
CREATE SCHEMA IF NOT EXISTS landcover;


CREATE TABLE IF NOT EXISTS gpw.glp15ag60 (
    gid                 VARCHAR(8) PRIMARY KEY,
    pcount              NUMERIC,
    footprint           GEOMETRY
);

-- ===============================
-- Population 2015 0.5x0.5 degrees
-- ===============================
CREATE TABLE IF NOT EXISTS gpw.glp15ag30 (
    gid                 VARCHAR(8) PRIMARY KEY,
    pcount              NUMERIC,
    footprint           GEOMETRY
);

-- =================================
-- Population 2015 0.25x0.25 degrees
-- =================================
CREATE TABLE IF NOT EXISTS gpw.glp15ag15 (
    gid                 VARCHAR(8) PRIMARY KEY,
    pcount              NUMERIC,
    footprint           GEOMETRY
);

-- ===================================
-- Population 2015 2.5x2.5 arc minutes
-- ===================================
CREATE TABLE IF NOT EXISTS gpw.glp15ag (
    gid                 VARCHAR(8) PRIMARY KEY,
    pcount              NUMERIC,
    footprint           GEOMETRY
);

DROP TABLE IF EXISTS landcover.landcover CASCADE;
CREATE TABLE IF NOT EXISTS landcover.landcover (
    ogc_fid         SERIAL,
    dn              NUMERIC,
    wkb_geometry    GEOMETRY
);

DROP TABLE IF EXISTS landcover.landcover2009 CASCADE;
CREATE TABLE IF NOT EXISTS landcover.landcover2009 (
    ogc_fid         SERIAL,
    dn              INTEGER,
    wkb_geometry    GEOMETRY
);
