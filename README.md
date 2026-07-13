# Learn With Me 📚✨

**Learn With Me** is a sleek, responsive, single-page web application designed to organize, track, and share educational topics and learning units. Built with a custom dark-mode UI, it features a secure admin panel for content management, file attachment support, and a public commenting system.

---

## 🚀 Features

* **Topic Management:** Create, edit, and categorize different subjects or modules with custom emoji icons.
* **Unit Tracking:** Break down topics into individual units/lessons. Add dates, times, cover photos, and detailed summaries.
* **PDF Attachments:** Seamlessly upload and attach PDF resources to specific units (up to 20MB).
* **Public Comments:** Visitors can engage by leaving comments on individual units.
* **Secure Admin Panel:** Built-in password protection to restrict CRUD (Create, Read, Update, Delete) operations to authorized admins only.
* **Responsive Dark UI:** A beautifully crafted, modern interface using CSS variables, flexbox, and CSS grid.

---

## 🛠️ Tech Stack

### Frontend
* **HTML5 & CSS3:** Custom styling with a dark/gold theme (No external CSS frameworks).
* **Vanilla JavaScript:** Asynchronous API calls using the `Fetch API`, DOM manipulation, and drag-and-drop file upload handling.

### Backend
* **PHP (7.4+):** RESTful API handling (`api.php`) for data processing, routing, and file uploads.
* **MySQL (PDO):** Secure database interactions with prepared statements to prevent SQL injection.

---

## 📂 Project Structure

```text
/
├── index.html          # Main frontend application (UI & Logic)
├── api.php             # Backend API router and database controller
└── /uploads            # Auto-generated directory for file uploads
    └── /pdfs           # Stores uploaded PDF attachments
