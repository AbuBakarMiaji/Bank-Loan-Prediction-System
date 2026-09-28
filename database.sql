-- =========================================================
-- Bank Loan Management & Prediction System — Database Schema
-- =========================================================

CREATE DATABASE IF NOT EXISTS loan_system CHARACTER SET utf8mb4;
USE loan_system;

-- ---------------------------------------------------------
-- Users (customers + administrators)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    full_name     VARCHAR(120)    NOT NULL,
    email         VARCHAR(150)    NOT NULL UNIQUE,
    phone         VARCHAR(20)     DEFAULT NULL,
    password_hash VARCHAR(255)    NOT NULL,
    role          ENUM('customer','admin') NOT NULL DEFAULT 'customer',
    created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Loan applications + prediction results
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS loans (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    user_id             INT NOT NULL,
    loan_amount         DECIMAL(14,2) NOT NULL,
    loan_term_months    INT NOT NULL,
    applicant_income    DECIMAL(14,2) NOT NULL,
    coapplicant_income  DECIMAL(14,2) NOT NULL DEFAULT 0,
    credit_history      TINYINT(1) NOT NULL DEFAULT 1,
    dependents          TINYINT NOT NULL DEFAULT 0,
    education           ENUM('Graduate','Not Graduate') NOT NULL DEFAULT 'Graduate',
    self_employed       ENUM('Yes','No') NOT NULL DEFAULT 'No',
    property_area       ENUM('Urban','Semiurban','Rural') NOT NULL DEFAULT 'Urban',
    prediction_result    ENUM('Approved','Rejected') DEFAULT NULL,
    prediction_confidence DECIMAL(5,2) DEFAULT NULL,
    status              ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_loans_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Seed: one administrator account
-- Login: admin@ledger.bank / Admin@123
-- (hash generated with PHP password_hash — change after first login)
-- ---------------------------------------------------------
INSERT INTO users (full_name, email, phone, password_hash, role)
VALUES (
  'System Administrator',
  'admin@ledger.bank',
  '0000000000',
  '$2y$10$ENx.jrW.zAFuNEphFFZpq.fH90ufg1RNsbfAOj.uDb8OR.pobyAq6',
  'admin'
)
ON DUPLICATE KEY UPDATE email = email;
