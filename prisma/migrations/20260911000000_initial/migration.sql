-- This is the Prisma representation of the existing Doctrine schema.
CREATE SCHEMA IF NOT EXISTS "public";

CREATE TABLE "plex_webhook" (
    "id" SERIAL NOT NULL,
    "content" JSON NOT NULL,
    "type" VARCHAR(255) NOT NULL,
    "created_at" TIMESTAMP(0) NOT NULL,
    "thumb" BYTEA,

    CONSTRAINT "plex_webhook_pkey" PRIMARY KEY ("id")
);
