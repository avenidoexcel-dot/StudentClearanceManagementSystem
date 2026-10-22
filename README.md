# Student Clearance Management System (ClearanceHub)

A comprehensive, dual-level academic clearance management platform designed for colleges and universities. It streamlines clearance submissions, departmental sign-offs, subject instructor evaluations, deficiency tracking, and certificate generation.

---

## 🌟 Key Features

### 1. Dual-Level Clearance Workflow
- **Academic Subject Instructors**: Enrolled faculty members evaluate students on subject-level requirements (grades, lab equipment return, machine problems, final projects).
- **Administrative Offices (Signatories)**:
  - **University Library**: Unreturned books and library dues.
  - **Cashier / Accounting**: Tuition balances and clearance fees (with student payment receipt proof upload).
  - **Registrar**: Complete school credentials & documents.
  - **Guidance Office**: Exit counseling and student evaluation.
  - **Department Head / Program Chair**: Curriculum and academic compliance.
  - **Student Affairs (OSAS / SSC)**: Uniform, ID validation, organization dues.

### 2. Student Portal
- Real-time progress bar (e.g., *7 of 9 requirements cleared — 78%*).
- Submit clearance requests for open terms with one click.
- **View Subject Clearance Requirements**: Interactive modal checklists showing specific course requirements posted by each instructor.
- Upload proof/receipts (JPG, PNG, PDF) for specific departments (e.g. Cashier).
- Clear deficiency notices with actionable feedback from instructors/signatories.
- Automatic unlocking of the **Printable Official Clearance Certificate** with verification code once 100% cleared.

### 3. Faculty Instructor Portal
- View assigned classes and enrolled students.
- **Publish Subject Clearance Requirements**: Create and edit custom checklists/instructions for enrolled students (e.g. machine problems, final project code, equipment return).
- Single-click approval or "Flag Deficiency" with specific remarks.
- "Approve All Pending" batch action for fast clearance.

### 4. Administrative Signatory Portal
- Review student clearance queue for their specific office.
- Inspect student attachments/proof of payment.
- Approve or flag deficiencies with compliance instructions.
- Batch approve pending students.

### 5. Administrator Dashboard
- Institutional analytics: completion rate, cleared vs pending students.
- Manage Clearance Periods (create new term, set active, close).
- Manage Departments & Offices.
- Assign Subjects to Faculty Instructors.
- User management with role assignment and one-click password reset.
- Master Clearance Reports with filtering by course, year, and status.

### 6. Public Certificate Verification
- Anyone can verify the authenticity of an official certificate at `verify.php?code=XXXX`.

---

## 🔑 Default Demo Accounts

| Role | Username / ID | Password | Access / Scope |
| :--- | :--- | :--- | :--- |
| **Administrator** | `admin` | `admin123` | Full system control & settings |
| **Faculty Instructor** | `prof_ramirez` | `instructor123` | Subject: IT311 (Web Systems) |
| **Cashier Office** | `cashier` | `signatory123` | Cashier / Accounting approvals |
| **Library Office** | `lib_officer` | `signatory123` | Library clearances & dues |
| **Registrar Office** | `registrar` | `signatory123` | Student credentials & records |
| **Guidance Office** | `guidance` | `signatory123` | Guidance evaluation & exit interview |
| **Department Head** | `dept_head` | `signatory123` | Academic compliance |
| **Student** | `student1` *(2026-0001)* | `student123` | Student dashboard & clearance submission |

---

## 🚀 Running Locally on XAMPP

1. Place this project folder in `C:\xampp\htdocs\clearance`.
2. Start **Apache** and **MySQL** in your **XAMPP Control Panel**.
3. Open your web browser and visit:
   ```
   http://localhost/clearance/
   ```
4. If you ever need to re-install or re-seed sample data, visit:
   ```
   http://localhost/clearance/setup_db.php
   ```

---

## 🌐 Deploying to InfinityFree

1. **Log in to your InfinityFree Account** and open your Control Panel (vPanel).
2. **Create MySQL Database**:
   - Go to **MySQL Databases**.
   - Create a new database (e.g. `epiz_xxxxxx_clearance`).
3. **Configure Database Credentials**:
   - Open `config/db.php` in your editor.
   - Enter your InfinityFree MySQL details in the `else` block:
     ```php
     define('DB_HOST', 'sqlXXX.infinityfree.com'); // Your MySQL Hostname from InfinityFree
     define('DB_USER', 'epiz_XXXXXX');            // Your MySQL Username
     define('DB_PASS', 'your_account_password');  // Your hosting password
     define('DB_NAME', 'epiz_XXXXXX_clearance');  // Your Database Name
     ```
4. **Upload Files**:
   - Use FileZilla or InfinityFree's online **File Manager**.
   - Upload all files from this project into the **`htdocs`** folder of your InfinityFree domain.
5. **Run the Database Setup**:
   - Visit `http://your-domain.infinityfreeapp.com/setup_db.php` in your browser.
   - Click **Run Database Installer Now** to set up all tables and accounts automatically!
   - You can now log in at `http://your-domain.infinityfreeapp.com/`!

---

## 📤 Pushing to GitHub

To push your latest project code to your GitHub repository:
```bash
git add .
git commit -m "Complete dual-level Student Clearance Management System"
git push origin main
```
