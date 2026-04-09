-- API Bearer Tokens
-- Permite autenticación stateless para clientes externos.
-- El token en texto plano NUNCA se almacena; solo su SHA-256 hex.
CREATE TABLE api_tokens (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     BIGINT UNSIGNED NOT NULL,
    name        VARCHAR(100)    NOT NULL,
    token_hash  CHAR(64)        NOT NULL,
    last_used_at DATETIME       DEFAULT NULL,
    expires_at  DATETIME        DEFAULT NULL,
    revoked_at  DATETIME        DEFAULT NULL,
    created_at  TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY  uq_token_hash (token_hash),
    INDEX       idx_user_id (user_id),
    CONSTRAINT  fk_at_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
