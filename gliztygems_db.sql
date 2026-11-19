CREATE DATABASE IF NOT EXISTS gliztygems_db
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE gliztygems_db;
DROP VIEW  IF EXISTS v_booking_progress;
DROP VIEW  IF EXISTS v_dashboard_stats;
DROP TABLE IF EXISTS task;
DROP TABLE IF EXISTS payment;
DROP TABLE IF EXISTS booking;
DROP TABLE IF EXISTS hall;
DROP TABLE IF EXISTS admin;
CREATE TABLE hall (
  hall_id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name         VARCHAR(100) NOT NULL,
  capacity     INT UNSIGNED NOT NULL,
  price        DECIMAL(12,2) NOT NULL,
  description  TEXT,
  facilities   VARCHAR(255),
  image_url    VARCHAR(255),
  status       ENUM('Available','Maintenance') NOT NULL DEFAULT 'Available',
  PRIMARY KEY (hall_id),
  UNIQUE KEY uq_hall_name (name),
  CONSTRAINT chk_hall_capacity CHECK (capacity > 0),
  CONSTRAINT chk_hall_price CHECK (price >= 0)
) ENGINE=InnoDB;
CREATE TABLE booking (
  booking_id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  verification_code  VARCHAR(12) NULL,            -- set when payment is approved
  customer_name      VARCHAR(120) NOT NULL,
  email              VARCHAR(150) NOT NULL,
  phone              VARCHAR(25)  NOT NULL,
  event_type         VARCHAR(60)  NOT NULL,
  hall_id            INT UNSIGNED NOT NULL,
  event_date         DATE NOT NULL,
  guests             INT UNSIGNED NOT NULL,
  additional_info    TEXT,
  amount             DECIMAL(12,2) NOT NULL,      -- hall price at time of booking
  status             ENUM('Pending','Confirmed','Declined') NOT NULL DEFAULT 'Pending',
  created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  confirmed_slot     VARCHAR(30) GENERATED ALWAYS AS
                       (IF(status = 'Confirmed', CONCAT(hall_id, '#', event_date), NULL)) STORED,
  PRIMARY KEY (booking_id),
  UNIQUE KEY uq_verification_code (verification_code),
  UNIQUE KEY uq_confirmed_slot (confirmed_slot),
  KEY idx_booking_hall_date (hall_id, event_date),
  KEY idx_booking_status (status),
  CONSTRAINT fk_booking_hall FOREIGN KEY (hall_id) REFERENCES hall (hall_id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT chk_booking_guests CHECK (guests > 0)
) ENGINE=InnoDB;
CREATE TABLE payment (
  payment_id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  booking_id   INT UNSIGNED NOT NULL,
  amount       DECIMAL(12,2) NOT NULL,
  card_last4   CHAR(4) NOT NULL,
  status       ENUM('Approved','Declined') NOT NULL,
  paid_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (payment_id),
  KEY idx_payment_booking (booking_id),
  CONSTRAINT fk_payment_booking FOREIGN KEY (booking_id) REFERENCES booking (booking_id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE task (
  task_id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  booking_id   INT UNSIGNED NOT NULL,
  title        VARCHAR(200) NOT NULL,
  deadline     DATE NULL,
  status       ENUM('Pending','In Progress','Completed') NOT NULL DEFAULT 'Pending',
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (task_id),
  KEY idx_task_booking (booking_id),
  CONSTRAINT fk_task_booking FOREIGN KEY (booking_id) REFERENCES booking (booking_id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE admin (
  admin_id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username       VARCHAR(50)  NOT NULL,
  password_hash  VARCHAR(255) NOT NULL,           -- PHP: password_hash() / password_verify()
  full_name      VARCHAR(100),
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (admin_id),
  UNIQUE KEY uq_admin_username (username)
) ENGINE=InnoDB;
CREATE VIEW v_booking_progress AS
SELECT b.booking_id,
       b.verification_code,
       COUNT(t.task_id) AS total_tasks,
       SUM(t.status = 'Completed') AS completed_tasks,
       IF(COUNT(t.task_id) = 0, 0,
          ROUND(SUM(t.status = 'Completed') / COUNT(t.task_id) * 100, 1)) AS progress_percent
FROM booking b
LEFT JOIN task t ON t.booking_id = b.booking_id
GROUP BY b.booking_id, b.verification_code;
CREATE VIEW v_dashboard_stats AS
SELECT
  (SELECT COUNT(*) FROM booking)                                  AS total_bookings,
  (SELECT COUNT(*) FROM booking WHERE status = 'Confirmed')       AS confirmed_bookings,
  (SELECT COUNT(*) FROM booking WHERE status = 'Pending')         AS pending_bookings,
  (SELECT COUNT(*) FROM booking WHERE status = 'Declined')        AS declined_bookings,
  (SELECT COUNT(*) FROM payment)                                  AS total_payments,
  (SELECT COALESCE(SUM(amount),0) FROM payment WHERE status = 'Approved') AS approved_total;
INSERT INTO hall (name, capacity, price, description, facilities, image_url, status) VALUES
('Grand Marquee', 1500, 4000000.00, 'Our largest indoor hall for weddings and galas.',
 'Air-conditioning, VIP lounge, Stage, Sound system, Projector, Chandeliers and premium lighting, Standby generator, Tables and chairs included', 'images/grand-marquee.jpg', 'Available'),
('Small Marquee', 1000, 2000000.00, 'An intimate indoor hall for birthdays and meetings.',
 'Air-conditioning, VIP seating, Stage, Sound system, Projector, Premium lighting and draped décor, Standby generator, Tables and chairs included', 'images/small-marquee.jpg', 'Available'),
('Open Place',      500, 1000000.00, 'An open-air space for relaxed outdoor celebrations.',
 'Landscaped grounds, VIP lounge, Projector, Outdoor floodlighting, Ample parking, Stage area', 'images/open-place.jpg', 'Available');
INSERT INTO admin (username, password_hash, full_name) VALUES
('admin', '$6$gliztygems$8WyNGad2ZjHuRr.yBFS9AiVAnibVitWdjNu.EuYgUAyF6gGs8awYyq7OURuyrd5ZKV4Eq0aqtLkcQlp896eVI0', 'System Administrator');
