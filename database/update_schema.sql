-- Add views column to articles table
ALTER TABLE articles ADD COLUMN views INT DEFAULT 0;

-- New Table for Site Settings (Hero & WA)

CREATE TABLE IF NOT EXISTS `site_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(50) DEFAULT NULL,
  `setting_value` text DEFAULT NULL,
  `label` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Dumping initial data
INSERT INTO `site_settings` (`setting_key`, `setting_value`, `label`) VALUES
('hero_title', 'Sofa bersih, higienis, dan wangi tanpa repot.', 'Judul Utama (Hero)'),
('hero_description', 'Kami membersihkan sofa Anda dengan bahan aman, alat modern, dan teknisi terlatih. Layanan panggilan ke rumah, hasil maksimal, garansi kepuasan.', 'Deskripsi Utama (Hero)'),
('hero_btn_primary', 'Lihat Layanan', 'Teks Tombol Utama'),
('hero_btn_secondary', 'Konsultasi Gratis', 'Teks Tombol Kedua'),
('wa_template', 'Halo Arno D Clean, saya ingin tanya layanan & harga.', 'Template Pesan WhatsApp');

CREATE TABLE IF NOT EXISTS benefits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    description TEXT,
    image_path VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS articles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(250) NOT NULL,
    content TEXT,
    image_path VARCHAR(255),
    views INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS article_tags (
    article_id INT,
    tag_id INT,
    FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE,
    PRIMARY KEY (article_id, tag_id)
);

CREATE TABLE IF NOT EXISTS article_views (
    id INT AUTO_INCREMENT PRIMARY KEY,
    article_id INT,
    viewed_at DATE,
    FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS visitor_analytics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45),
    country VARCHAR(100),
    city VARCHAR(100),
    device VARCHAR(255),
    visited_at DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
-- Add platform column to testimonials
ALTER TABLE testimonials ADD COLUMN platform VARCHAR(20) DEFAULT 'Website';
ALTER TABLE testimonials ADD COLUMN profile_picture VARCHAR(255) DEFAULT NULL; -- For Google Profile Pic URL or Path

-- Add order_count to services
ALTER TABLE services ADD COLUMN order_count INT DEFAULT 0;


-- Add service_clicks table for Analytics
CREATE TABLE IF NOT EXISTS service_clicks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_id INT NOT NULL,
    clicked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
    INDEX (service_id),
    INDEX (clicked_at)
);

