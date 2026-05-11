-- API Bearer Tokens
-- Permite autenticación stateless para clientes externos.
-- El token en texto plano NUNCA se almacena; solo su SHA-256 hex.
CREATE TABLE IF NOT EXISTS api_tokens (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     BIGINT UNSIGNED NOT NULL,
    name        VARCHAR(100)    NOT NULL,
    token_hash  CHAR(64)        NOT NULL,
    last_used_at DATETIME       DEFAULT NULL,
    expires_at  DATETIME        DEFAULT NULL,
    revoked_at  DATETIME        DEFAULT NULL,
    created_at  TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_api_tokens_token_hash (token_hash),
    INDEX idx_api_tokens_user (user_id),
    CONSTRAINT fk_api_tokens_users FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
