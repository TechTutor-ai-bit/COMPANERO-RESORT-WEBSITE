
USE companero_resort;

CREATE TABLE IF NOT EXISTS site_content (
    page VARCHAR(40) NOT NULL,
    content_key VARCHAR(60) NOT NULL,
    content_value TEXT NOT NULL,
    updated_by INT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (page, content_key),

    CONSTRAINT fk_site_content_admin
        FOREIGN KEY (updated_by)
        REFERENCES admins(id)
        ON DELETE SET NULL
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;
SHOW TABLES;