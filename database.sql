-- BD: bdsistemaiepin
-- CREATE DATABASE IF NOT EXISTS bdsistemaiepin CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE bdsistemaiepin;

DROP TABLE IF EXISTS calificaciones, docente_aula_area, competencias, areas, docentes, estudiantes, aulas, periodos, anios_academicos, users;

CREATE TABLE anios_academicos ( id INT AUTO_INCREMENT PRIMARY KEY, anio INT NOT NULL, activo TINYINT(1) DEFAULT 1 );
CREATE TABLE periodos ( id INT AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(50) NOT NULL, fecha_inicio DATE NOT NULL, fecha_fin DATE NOT NULL, anio_id INT NOT NULL, FOREIGN KEY (anio_id) REFERENCES anios_academicos(id) ON DELETE CASCADE );
CREATE TABLE aulas ( id INT AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(50) NOT NULL, seccion VARCHAR(10), grado VARCHAR(20), anio_id INT NOT NULL, FOREIGN KEY (anio_id) REFERENCES anios_academicos(id) ON DELETE CASCADE );
CREATE TABLE estudiantes ( id INT AUTO_INCREMENT PRIMARY KEY, dni VARCHAR(15), nombres VARCHAR(100) NOT NULL, apellidos VARCHAR(100) NOT NULL, aula_id INT NOT NULL, activo TINYINT(1) DEFAULT 1, FOREIGN KEY (aula_id) REFERENCES aulas(id) ON DELETE CASCADE );
CREATE TABLE docentes ( id INT AUTO_INCREMENT PRIMARY KEY, dni VARCHAR(15), nombres VARCHAR(100) NOT NULL, apellidos VARCHAR(100) NOT NULL, activo TINYINT(1) DEFAULT 1 );
CREATE TABLE areas ( id INT AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(100) NOT NULL );
CREATE TABLE competencias ( id INT AUTO_INCREMENT PRIMARY KEY, area_id INT NOT NULL, nombre VARCHAR(150) NOT NULL, FOREIGN KEY (area_id) REFERENCES areas(id) ON DELETE CASCADE );
CREATE TABLE docente_aula_area ( id INT AUTO_INCREMENT PRIMARY KEY, docente_id INT NOT NULL, aula_id INT NOT NULL, area_id INT NOT NULL, UNIQUE KEY uniq_assign (docente_id, aula_id, area_id), FOREIGN KEY (docente_id) REFERENCES docentes(id) ON DELETE CASCADE, FOREIGN KEY (aula_id) REFERENCES aulas(id) ON DELETE CASCADE, FOREIGN KEY (area_id) REFERENCES areas(id) ON DELETE CASCADE );
CREATE TABLE calificaciones ( id INT AUTO_INCREMENT PRIMARY KEY, estudiante_id INT NOT NULL, competencia_id INT NOT NULL, periodo_id INT NOT NULL, nota ENUM('AD','A','B','C') NOT NULL, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY uniq_grade (estudiante_id, competencia_id, periodo_id), FOREIGN KEY (estudiante_id) REFERENCES estudiantes(id) ON DELETE CASCADE, FOREIGN KEY (competencia_id) REFERENCES competencias(id) ON DELETE CASCADE, FOREIGN KEY (periodo_id) REFERENCES periodos(id) ON DELETE CASCADE );
CREATE TABLE users ( id INT AUTO_INCREMENT PRIMARY KEY, email VARCHAR(120) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL, role ENUM('admin','docente') DEFAULT 'admin', docente_id INT DEFAULT NULL, FOREIGN KEY (docente_id) REFERENCES docentes(id) ON DELETE SET NULL );

INSERT INTO anios_academicos (anio, activo) VALUES (2025, 1);
INSERT INTO periodos (nombre, fecha_inicio, fecha_fin, anio_id) VALUES ('Bimestre 1','2025-03-01','2025-05-01',1), ('Bimestre 2','2025-05-02','2025-07-15',1);
INSERT INTO aulas (nombre, seccion, grado, anio_id) VALUES ('Primaria 2','A','2°',1);
INSERT INTO docentes (dni, nombres, apellidos) VALUES ('12345678','María','Salas'),('87654321','Carlos','Ríos');
INSERT INTO areas (nombre) VALUES ('Matemática'),('Comunicación');
INSERT INTO competencias (area_id, nombre) VALUES (1,'Resuelve problemas de cantidad'),(1,'Elabora estrategias de cálculo'),(2,'Comprende textos'),(2,'Produce textos');
INSERT INTO estudiantes (dni, nombres, apellidos, aula_id) VALUES ('11111111','Juan','García',1),('22222222','María','López',1),('33333333','Carlos','Ruiz',1),('44444444','Ana','Pérez',1);
INSERT INTO docente_aula_area (docente_id, aula_id, area_id) VALUES (1,1,1),(2,1,2);
INSERT INTO users (email, password_hash, role) VALUES ('admin@demo.com', '$2y$10$8Vx5p4mOih4j3p1yS8J3F.H3sYp3c6gqT5kQHkYv3mS5Q7rV7Kkq2', 'admin');
-- La contraseña encriptada corresponde a: admin123
