# 6IS (Integrated Information System)

## Overview

6IS is a modular web-based information system designed to help organizations manage daily operations through a centralized platform.

---

## ⚡ 1-Click Master Setup Guide (For New Computers)

Follow these simple steps to set up 6IS on any new Windows desktop with **zero coding or terminal knowledge**:

### Step 1: Prepare the USB Drive (Developer / Admin Setup)
1. Download the official XAMPP installer for Windows from [apachefriends.org](https://www.apachefriends.org).
2. Rename the downloaded file to **`xampp-installer.exe`**.
3. Place `xampp-installer.exe` inside the **`6IS/installer/`** folder on your USB drive.

---

### Step 2: 1-Click Installation on Target Desktop
1. Plug the USB drive into the new computer.
2. Open the `6IS` folder and double-click:
   ⚡ **`Setup_Everything_1Click.bat`**

🤖 **What happens automatically**:
- Installs XAMPP silently in the background (no manual choices required).
- Copies application files to `C:\xampp\htdocs\6IS`.
- Starts Apache & MySQL services.
- Initializes MySQL database `db_ict_system` and inserts initial seed data.
- Creates a **"6IS App" Shortcut** right on the user's Desktop screen.
- Launches `http://localhost/6IS` in the web browser automatically.

---

## 🚀 Manual Launch / Usage

To start 6IS at any time on the computer:
- Double-click the **6IS App** shortcut on the Desktop (or run **`Start_6IS.bat`** in `C:\xampp\htdocs\6IS`).
- Open your browser to: **`http://localhost/6IS`**

---

## 🛠️ Tech Stack

- **Frontend**: Vue 3 (Composition API), TypeScript, Ionic Framework, Vanilla CSS.
- **Backend**: PHP REST API.
- **Database**: MySQL (`db_ict_system`).