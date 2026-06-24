# 🎓 Learning Management System (LMS)

A complete Learning Management System (LMS) built using PHP, MySQL, HTML, CSS, JavaScript, and Bootstrap. The platform enables administrators to manage courses, lessons, quizzes, users, certificates, and student progress through a centralized dashboard.

## 🚀 Features

### 👨‍💼 Admin Panel
- Admin Authentication
- Dashboard Analytics
- Manage Courses
- Manage Lessons
- Manage Quizzes
- Manage Students
- View Quiz Attempts
- Generate Reports
- System Settings
- Certificate Management

### 👨‍🎓 Student Panel
- Student Registration & Login
- Browse Available Courses
- Enroll in Courses
- View Course Details
- Access Lessons
- Track Learning Progress
- Take Quizzes
- View Quiz Results
- Download Certificates
- Personal Dashboard

### 📚 Course Management
- Add/Edit/Delete Courses
- Upload Course Materials
- Lesson Organization
- Course Enrollment Tracking

### 📝 Quiz System
- Create Quizzes
- Attempt Tracking
- Score Evaluation
- Performance Monitoring

### 🏆 Certificate Module
- Certificate Generation
- Certificate Download
- Completion Tracking

---

## 🛠️ Technologies Used

### Frontend
- HTML5
- CSS3
- JavaScript
- Bootstrap

### Backend
- PHP

### Database
- MySQL

### Server
- Apache (XAMPP)

---

## 📂 Project Structure

```text
lms-project/
│
├── admin/
│   ├── add-course.php
│   ├── add-lesson.php
│   ├── add-quiz.php
│   ├── users.php
│   ├── reports.php
│   └── index.php
│
├── assets/
│   ├── css/
│   ├── js/
│
├── config/
│   ├── db.php
│   ├── users.sql
│   ├── lessons.sql
│   ├── enrollments.sql
│   └── quiz_tables.sql
│
├── includes/
│
├── uploads/
│
├── login.php
├── register.php
├── dashboard.php
├── browse-courses.php
├── course-details.php
├── my-courses.php
├── take-quiz.php
├── download-certificate.php
└── logout.php
```

---

## ⚙️ Installation

### 1. Clone Repository

```bash
git clone https://github.com/Chandu2401/Learning-Management-System.git
```

### 2. Move Project

Copy project folder into:

```text
xampp/htdocs/
```

### 3. Create Database

Create a MySQL database:

```sql
CREATE DATABASE lms_db;
```

### 4. Import Database

Import all SQL files from:

```text
config/
```

and

```text
courses.sql
```

using phpMyAdmin.

### 5. Configure Database

Update database credentials inside:

```php
config/db.php
```

### 6. Start Server

Start:

- Apache
- MySQL

### 7. Run Project

```text
http://localhost/lms-project
```

---

## 📊 Modules Included

- User Authentication
- Course Management
- Lesson Management
- Enrollment System
- Quiz Management
- Progress Tracking
- Certificate Generation
- Student Dashboard
- Admin Dashboard
- Reports & Analytics

---

## 🔮 Future Enhancements

- Video Lectures
- Live Classes
- Payment Gateway Integration
- Assignment Submission
- Email Notifications
- Responsive Mobile App
- AI-Based Learning Recommendations

---

## 👨‍💻 Developer

**Chandrakant Sadashiv Mate**

- MCA Graduate
- PHP Developer
- Web Developer
- Frontend & Backend Developer

GitHub:
https://github.com/Chandu2401

---

## 📄 License

This project is developed for educational and learning purposes.

---

⭐ If you found this project useful, please give it a star on GitHub.
