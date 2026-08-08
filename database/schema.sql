CREATE DATABASE IF NOT EXISTS collabsphere
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE collabsphere;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('student', 'instructor') NOT NULL,
    membership_type ENUM('member', 'non_member') NOT NULL DEFAULT 'non_member',
    profile_photo VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE student_profiles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL UNIQUE,
    student_number VARCHAR(50) DEFAULT NULL UNIQUE,
    skills TEXT DEFAULT NULL,
    collaboration_mode ENUM('online', 'offline', 'hybrid') DEFAULT 'online',
    availability VARCHAR(255) DEFAULT NULL,
    bio TEXT DEFAULT NULL,
    CONSTRAINT fk_student_profiles_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE instructor_profiles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL UNIQUE,
    staff_number VARCHAR(50) DEFAULT NULL UNIQUE,
    department VARCHAR(120) DEFAULT NULL,
    office_location VARCHAR(120) DEFAULT NULL,
    bio TEXT DEFAULT NULL,
    CONSTRAINT fk_instructor_profiles_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE courses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    instructor_id INT UNSIGNED NOT NULL,
    course_code VARCHAR(30) NOT NULL UNIQUE,
    course_name VARCHAR(150) NOT NULL,
    description TEXT DEFAULT NULL,
    enrolment_key VARCHAR(100) DEFAULT NULL,
    is_published TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_courses_instructor
        FOREIGN KEY (instructor_id)
        REFERENCES users(id)
        ON DELETE CASCADE,
    INDEX idx_courses_instructor (instructor_id)
) ENGINE=InnoDB;

CREATE TABLE enrollments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    enrolled_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_course_student (course_id, student_id),
    CONSTRAINT fk_enrollments_course
        FOREIGN KEY (course_id)
        REFERENCES courses(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_enrollments_student
        FOREIGN KEY (student_id)
        REFERENCES users(id)
        ON DELETE CASCADE,
    INDEX idx_enrollments_student (student_id)
) ENGINE=InnoDB;

CREATE TABLE course_materials (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id INT UNSIGNED NOT NULL,
    uploaded_by INT UNSIGNED NOT NULL,
    title VARCHAR(180) NOT NULL,
    description TEXT DEFAULT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    mime_type VARCHAR(120) DEFAULT NULL,
    file_size INT UNSIGNED DEFAULT NULL,
    priority_access TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_materials_course
        FOREIGN KEY (course_id)
        REFERENCES courses(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_materials_uploader
        FOREIGN KEY (uploaded_by)
        REFERENCES users(id)
        ON DELETE CASCADE,
    INDEX idx_materials_course (course_id)
) ENGINE=InnoDB;

CREATE TABLE assignments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id INT UNSIGNED NOT NULL,
    created_by INT UNSIGNED NOT NULL,
    title VARCHAR(180) NOT NULL,
    description TEXT DEFAULT NULL,
    deadline DATETIME NOT NULL,
    maximum_marks DECIMAL(6,2) NOT NULL DEFAULT 100.00,
    allowed_file_types VARCHAR(255) DEFAULT 'pdf,doc,docx,zip',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_assignments_course
        FOREIGN KEY (course_id)
        REFERENCES courses(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_assignments_creator
        FOREIGN KEY (created_by)
        REFERENCES users(id)
        ON DELETE CASCADE,
    INDEX idx_assignments_course (course_id),
    INDEX idx_assignments_deadline (deadline)
) ENGINE=InnoDB;

CREATE TABLE submissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    mime_type VARCHAR(120) DEFAULT NULL,
    file_size INT UNSIGNED DEFAULT NULL,
    submission_number INT UNSIGNED NOT NULL DEFAULT 1,
    submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status ENUM('submitted', 'late', 'graded') NOT NULL DEFAULT 'submitted',
    grade DECIMAL(6,2) DEFAULT NULL,
    feedback TEXT DEFAULT NULL,
    graded_by INT UNSIGNED DEFAULT NULL,
    graded_at DATETIME DEFAULT NULL,
    CONSTRAINT fk_submissions_assignment
        FOREIGN KEY (assignment_id)
        REFERENCES assignments(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_submissions_student
        FOREIGN KEY (student_id)
        REFERENCES users(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_submissions_grader
        FOREIGN KEY (graded_by)
        REFERENCES users(id)
        ON DELETE SET NULL,
    UNIQUE KEY uq_submission_version (
        assignment_id,
        student_id,
        submission_number
    ),
    INDEX idx_submissions_assignment (assignment_id),
    INDEX idx_submissions_student (student_id)
) ENGINE=InnoDB;

CREATE TABLE announcements (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id INT UNSIGNED NOT NULL,
    posted_by INT UNSIGNED NOT NULL,
    title VARCHAR(180) NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_announcements_course
        FOREIGN KEY (course_id)
        REFERENCES courses(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_announcements_author
        FOREIGN KEY (posted_by)
        REFERENCES users(id)
        ON DELETE CASCADE,
    INDEX idx_announcements_course (course_id)
) ENGINE=InnoDB;