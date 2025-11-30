

-- USERS
CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','reader') NOT NULL DEFAULT 'reader',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- NOVELS
CREATE TABLE novels (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  cover_url VARCHAR(500) DEFAULT NULL,
  description TEXT,
  author VARCHAR(255),
  tags TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- CHAPTERS
CREATE TABLE chapters (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  novel_id INT UNSIGNED NOT NULL,
  title VARCHAR(255) NOT NULL,
  content LONGTEXT NOT NULL,
  order_index INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (novel_id) REFERENCES novels(id) ON DELETE CASCADE,
  INDEX (novel_id),
  INDEX (order_index)
) ENGINE=InnoDB;

-- COMMENTS
CREATE TABLE comments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  novel_id INT UNSIGNED NULL,
  chapter_id INT UNSIGNED NULL,
  user_id INT UNSIGNED NOT NULL,
  text VARCHAR(500) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (novel_id) REFERENCES novels(id) ON DELETE CASCADE,
  FOREIGN KEY (chapter_id) REFERENCES chapters(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX (novel_id),
  INDEX (chapter_id),
  INDEX (user_id)
) ENGINE=InnoDB;

-- LIKES
CREATE TABLE likes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  target_type ENUM('novel','chapter') NOT NULL,
  target_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_like (target_type, target_id, user_id),
  INDEX (target_type, target_id)
) ENGINE=InnoDB;

-- DEFAULT ADMIN (password: admin123)
INSERT INTO users (username, password_hash, role)
VALUES (
  'admin',
  -- password_hash('admin123', PASSWORD_DEFAULT) example; replace if needed
  '$2y$10$0x9h1jJfX6h9ZqCjJM2V2uXvVhOeQ3M5M2Y8bY7iqh4vS8pM/u7xK',
  'admin'
);

-- SAMPLE NOVEL
INSERT INTO novels (title, cover_url, description, author, tags)
VALUES (
  'Heavenly Sword, Mortal Heart',
  'https://via.placeholder.com/400x600?text=Wuxia+Cover',
  'A wandering swordsman struggles between vengeance and compassion in a fractured jianghu.',
  'Anonymous',
  'wuxia, cultivation, swordsman'
);

SET @novel_id := LAST_INSERT_ID();

INSERT INTO chapters (novel_id, title, content, order_index) VALUES
(@novel_id, 'Chapter 1: The Rusted Blade', 'The rain fell over Lotus Bridge as a lone figure walked, his sword wrapped in cloth...', 1),
(@novel_id, 'Chapter 2: Wine in a Broken Cup', 'In the dim tavern light, old grudges stirred like dust in the air...', 2);
