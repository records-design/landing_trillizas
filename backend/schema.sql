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
    visitor_id     VARCHAR(64)  DEFAULT NULL,  -- persiste entre visitas (localStorage): nuevo vs. recurrente
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
    KEY idx_utm_source (utm_source),
    KEY idx_visitor_id (visitor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Eventos: una fila por page_view / click.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS events (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id       VARCHAR(64)  NOT NULL,      -- id único (dedup con Meta CAPI)
    session_id     VARCHAR(64)  NOT NULL,
    event_name     VARCHAR(30)  NOT NULL,      -- page_view | click | engagement
    button         VARCHAR(60)  DEFAULT NULL,  -- videoclip, cancion_spotify, social_*, ...
    destination    VARCHAR(60)  DEFAULT NULL,
    dwell_ms       INT UNSIGNED DEFAULT NULL,  -- ms desde que cargó la página hasta el clic (o hasta que se fue, en "engagement")
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
    ad_id        VARCHAR(64)  DEFAULT NULL,  -- qué anuncio generó esta suscripción
    session_id   VARCHAR(64)  DEFAULT NULL,  -- para cruzar con los clics de esa misma sesión
    ip_hint      VARCHAR(64)  DEFAULT NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uniq_email (email),
    KEY idx_created_at (created_at),
    KEY idx_ad_id (ad_id),
    KEY idx_session_id (session_id)
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

-- ------------------------------------------------------------
-- Contenido editable de la landing (textos y links) desde el panel
-- privado, sin tocar código. Clave/valor simple: cada fila es un
-- campo editable (ver statics-8f2k1/content.php). Si una clave no
-- está en la tabla, la landing usa el texto que ya tiene puesto en
-- el HTML (no rompe nada mientras no se haya guardado nada todavía).
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS site_content (
    content_key    VARCHAR(80)  NOT NULL,
    content_value  TEXT         NOT NULL,
    updated_at     DATETIME     NOT NULL,
    PRIMARY KEY (content_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Episodios de la serie, editables desde el panel. Reemplaza al
-- array fijo `episodes` que antes vivía escrito a mano en el HTML.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS episodes (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    season         INT UNSIGNED NOT NULL DEFAULT 1,
    episode_number INT UNSIGNED NOT NULL,
    title          VARCHAR(255) NOT NULL,
    thumbnail      VARCHAR(500) DEFAULT NULL,  -- ruta/URL de la miniatura, o vacío (ícono de play)
    youtube_url    VARCHAR(500) NOT NULL,
    sort_order     INT NOT NULL DEFAULT 0,     -- orden en la grilla (no siempre = episode_number)
    is_visible     TINYINT(1)   NOT NULL DEFAULT 1,
    created_at     DATETIME     NOT NULL,
    updated_at     DATETIME     NOT NULL,
    PRIMARY KEY (id),
    KEY idx_sort_order (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Migraciones: agrega columnas nuevas a tablas que ya existan de
-- una importación anterior de este archivo. Si la tabla se crea
-- recién ahora (arriba), ya nace con estas columnas y esto no hace
-- nada. Seguro de re-ejecutar las veces que haga falta.
-- ------------------------------------------------------------
ALTER TABLE sessions
    ADD COLUMN IF NOT EXISTS visitor_id VARCHAR(64) DEFAULT NULL AFTER session_id,
    ADD KEY IF NOT EXISTS idx_visitor_id (visitor_id);

ALTER TABLE subscribers
    ADD COLUMN IF NOT EXISTS ad_id VARCHAR(64) DEFAULT NULL,
    ADD KEY IF NOT EXISTS idx_ad_id (ad_id),
    ADD COLUMN IF NOT EXISTS session_id VARCHAR(64) DEFAULT NULL,
    ADD KEY IF NOT EXISTS idx_session_id (session_id);

ALTER TABLE events
    ADD COLUMN IF NOT EXISTS dwell_ms INT UNSIGNED DEFAULT NULL AFTER destination;

-- ------------------------------------------------------------
-- Datos iniciales: lo que la landing ya tiene puesto a mano hoy,
-- para que el panel arranque mostrando exactamente lo mismo que ya
-- está en vivo (no un formulario vacío). INSERT IGNORE: si ya existe
-- la fila (se re-corre este archivo de nuevo), no la pisa.
-- ------------------------------------------------------------
INSERT IGNORE INTO site_content (content_key, content_value, updated_at) VALUES
    ('hero_claim', 'Tres hermanas, un libro, música y magia', UTC_TIMESTAMP()),
    ('hero_title_line1', 'Cada página abre un portal', UTC_TIMESTAMP()),
    ('hero_title_line2', 'Cada canción, una aventura', UTC_TIMESTAMP()),
    ('cta_serie_link', 'https://linktw.in/Dnofjd', UTC_TIMESTAMP()),
    ('cta_album_link', 'https://ffm.to/libromagico1', UTC_TIMESTAMP()),
    ('youtube_channel_link', 'https://linktw.in/qeBBaV', UTC_TIMESTAMP()),
    ('spotify_link', 'https://linktw.in/iJcKCL', UTC_TIMESTAMP()),
    ('instagram_link', 'https://www.instagram.com/lastrillizasdeoro_libromagico/?hl=es', UTC_TIMESTAMP()),
    ('babidibu_records_link', 'https://babidiburecords.com/', UTC_TIMESTAMP()),
    ('album_name_line1', 'Las Trillizas de Oro', UTC_TIMESTAMP()),
    ('album_name_line2', 'y El Libro Mágico', UTC_TIMESTAMP()),
    ('album_volume', 'Volumen 1', UTC_TIMESTAMP());

INSERT IGNORE INTO episodes (id, season, episode_number, title, thumbnail, youtube_url, sort_order, is_visible, created_at, updated_at) VALUES
    (1, 1, 1, 'Trixipop', 'imagenes/miniaturas-ytminiatura_-trixipop%201.png', 'https://linktw.in/WLomzs', 1, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
    (2, 1, 2, 'Locomotora llega a la Estación', 'imagenes/miniatura_locomotora_web.jpg', 'https://linktw.in/QtAEqZ', 2, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP());
