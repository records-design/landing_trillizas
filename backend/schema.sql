-- ============================================================
-- Esquema de la base de datos — Analíticas landing Trillizas
-- Importar desde phpMyAdmin (Hostinger) en la base ya creada.
-- Charset utf8mb4 para soportar emojis/acentos en nombres de campaña.
-- ============================================================

SET NAMES utf8mb4;

-- ------------------------------------------------------------
-- Sesiones: una fila por session_id. Guarda la campaña de origen
-- (primer ingreso) y datos que no cambian durante la sesión.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sessions (
    session_id     VARCHAR(64)  NOT NULL,
    first_seen     DATETIME     NOT NULL,
    last_seen      DATETIME     NOT NULL,

    -- Campaña / origen
    utm_source     VARCHAR(120) DEFAULT NULL,
    utm_medium     VARCHAR(120) DEFAULT NULL,
    utm_campaign   VARCHAR(255) DEFAULT NULL,
    utm_content    VARCHAR(255) DEFAULT NULL,
    campaign_id    VARCHAR(64)  DEFAULT NULL,
    adset_id       VARCHAR(64)  DEFAULT NULL,
    ad_id          VARCHAR(64)  DEFAULT NULL,
    placement      VARCHAR(80)  DEFAULT NULL,
    fbclid         VARCHAR(512) DEFAULT NULL,
    gclid          VARCHAR(512) DEFAULT NULL,

    -- Dispositivo / geo
    device_type    VARCHAR(20)  DEFAULT NULL,  -- mobile | desktop | tablet
    os             VARCHAR(40)  DEFAULT NULL,
    browser        VARCHAR(40)  DEFAULT NULL,
    country        VARCHAR(80)  DEFAULT NULL,
    country_code   VARCHAR(4)   DEFAULT NULL,
    region         VARCHAR(120) DEFAULT NULL,
    city           VARCHAR(120) DEFAULT NULL,

    referrer       VARCHAR(512) DEFAULT NULL,
    ip_hint        VARCHAR(64)  DEFAULT NULL,  -- IP anonimizada (último octeto en 0)
    user_agent     VARCHAR(512) DEFAULT NULL,

    PRIMARY KEY (session_id),
    KEY idx_first_seen (first_seen),
    KEY idx_ad_id (ad_id),
    KEY idx_campaign_id (campaign_id),
    KEY idx_utm_source (utm_source)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Eventos: una fila por page_view / click.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS events (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id       VARCHAR(64)  NOT NULL,      -- id único (dedup con Meta CAPI)
    session_id     VARCHAR(64)  NOT NULL,
    event_name     VARCHAR(30)  NOT NULL,      -- page_view | click
    button         VARCHAR(60)  DEFAULT NULL,  -- videoclip, cancion_spotify, social_*, ...
    destination    VARCHAR(60)  DEFAULT NULL,
    created_at     DATETIME     NOT NULL,

    url            VARCHAR(1000) DEFAULT NULL,
    referrer       VARCHAR(512)  DEFAULT NULL,

    -- Copia de campaña por evento (facilita queries sin JOIN)
    utm_source     VARCHAR(120) DEFAULT NULL,
    utm_campaign   VARCHAR(255) DEFAULT NULL,
    ad_id          VARCHAR(64)  DEFAULT NULL,
    placement      VARCHAR(80)  DEFAULT NULL,

    device_type    VARCHAR(20)  DEFAULT NULL,
    country_code   VARCHAR(4)   DEFAULT NULL,
    city           VARCHAR(120) DEFAULT NULL,

    sent_to_meta   TINYINT(1)   NOT NULL DEFAULT 0,  -- 1 si CAPI respondió OK

    PRIMARY KEY (id),
    UNIQUE KEY uniq_event_id (event_id),
    KEY idx_created_at (created_at),
    KEY idx_session (session_id),
    KEY idx_event_name (event_name),
    KEY idx_button (button),
    KEY idx_ad_id (ad_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Referencia de campañas/anuncios: ad_id -> etiqueta legible.
-- Se completa sola con el último ad.name visto para cada ad_id.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ad_reference (
    ad_id          VARCHAR(64)  NOT NULL,
    ad_name        VARCHAR(255) DEFAULT NULL,
    campaign_id    VARCHAR(64)  DEFAULT NULL,
    campaign_name  VARCHAR(255) DEFAULT NULL,
    adset_id       VARCHAR(64)  DEFAULT NULL,
    updated_at     DATETIME     NOT NULL,
    PRIMARY KEY (ad_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Suscriptores del formulario de newsletter (hero de la landing).
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS subscribers (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email       VARCHAR(255) NOT NULL,
    created_at  DATETIME     NOT NULL,

    utm_source   VARCHAR(120) DEFAULT NULL,
    utm_campaign VARCHAR(255) DEFAULT NULL,
    ip_hint      VARCHAR(64)  DEFAULT NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uniq_email (email),
    KEY idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Intentos de login al panel (protección fuerza bruta).
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS login_attempts (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ip_hint     VARCHAR(64) NOT NULL,
    attempted_at DATETIME   NOT NULL,
    success     TINYINT(1)  NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_ip_time (ip_hint, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
