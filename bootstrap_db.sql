-- bootstrap_bf.sql
-- Run with:
--   ysqlsh -h <db-node-ip> -p 5433 -U yugabyte -f bootstrap_bf.sql

-- 1) Create database and user
CREATE DATABASE bf;
CREATE USER bfuser;
GRANT ALL ON DATABASE bf TO bfuser;

-- 2) Connect to the new database
\c bf

-- 3) Enable UUID generation so we can mimic unique_rowid() as text
CREATE EXTENSION IF NOT EXISTS pgcrypto;

-- 4) Tables

CREATE TABLE users (
  userID        VARCHAR(50) PRIMARY KEY DEFAULT gen_random_uuid()::text,
  name          VARCHAR(50),
  age           INT,
  picture       VARCHAR(300),
  status        VARCHAR(10),
  bio           TEXT,
  posts         INT,
  stats         VARCHAR(50),
  comments      INT,
  lastPostDate  TIMESTAMPTZ DEFAULT now(),
  createDate    TIMESTAMPTZ DEFAULT now()
);

CREATE TABLE posts (
  postID        VARCHAR(50) PRIMARY KEY DEFAULT gen_random_uuid()::text,
  userID        VARCHAR(50),
  text          TEXT,
  stats         VARCHAR(200),
  name          VARCHAR(150),
  image         VARCHAR(50),
  postDate      TIMESTAMPTZ DEFAULT now()
);

CREATE TABLE comments (
  commentID     VARCHAR(50) PRIMARY KEY DEFAULT gen_random_uuid()::text,
  postID        VARCHAR(50),
  userID        VARCHAR(50),
  stats         VARCHAR(200),
  text          TEXT,
  postDate      TIMESTAMPTZ DEFAULT now()
);

CREATE TABLE pictures (
  pictureID     VARCHAR(300),
  stats         VARCHAR(200),
  picture       BYTEA
);

-- 5) Privileges for bfuser (table-level)
GRANT SELECT, INSERT, UPDATE ON ALL TABLES IN SCHEMA public TO bfuser;

ALTER DEFAULT PRIVILEGES IN SCHEMA public
  GRANT SELECT, INSERT, UPDATE ON TABLES TO bfuser;

