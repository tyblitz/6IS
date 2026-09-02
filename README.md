# 6IS (Integrated Information System) v1.2.0


## Overview

6IS is a comprehensive, modular web-based information system designed for managing organizational operations, equipment inventory readiness, communications tracking, and accomplishment logging.

---

## ⚡ 1-Click Master Setup Guide (For New Computers)

Follow these simple steps to set up 6IS on any new Windows computer:

### Step 1: Prepare the USB Drive / Installer Folder
1. Download XAMPP installer for Windows from [apachefriends.org](https://www.apachefriends.org).
2. Rename the file to **`xampp-installer.exe`**.
3. Place `xampp-installer.exe` inside the **`6IS/installer/`** folder.

---

### Step 2: 1-Click Installation on Target Desktop
1. Open the `6IS` folder and right-click & run:
   ⚡ **`Setup_Everything_1Click.bat`** (Run as Administrator)

🤖 **What happens automatically**:
- Installs XAMPP silently in the background.
- Deploys application files to `C:\xampp\htdocs\6IS`.
- Starts Apache & MySQL services.
- Initializes MySQL database `db_ict_system` and inserts initial seed data.
- Creates a **"6IS App" Shortcut** on the user's Desktop screen.
- Launches `http://localhost/6IS` in your web browser.

---

## 🔑 Default Credentials

- **Administrator Account**:
  - **Username**: `Admin01`
  - **Password**: `adminpassword01`

- **Standard User Account**:
  - **Username**: `User01`
  - **Password**: `userpassword01`

---

## 🚀 Usage & Deployment

To launch 6IS at any time:
- Double-click the **6IS App** shortcut on the Desktop (or run **`Start_6IS.bat`**).
- Open your browser to: **`http://localhost/6IS`**

---

## 📦 Inno Setup Installer Compilation (Optional)

To compile a single executable setup file (`6IS_Setup_v0.1.1.exe`):
1. Download & install [Inno Setup Compiler](https://jrsoftware.org/isdl.php).
2. Open `installer/6IS_Setup_Script.iss`.
3. Click **Compile** (`Ctrl + F9`).
4. Output setup installer will be generated in `Output/6IS_Setup_v0.1.1.exe`.

---

## 🛠️ Tech Stack

- **Frontend**: Vue 3 (Composition API), TypeScript, Ionic Framework, Vanilla CSS.
- **Backend**: PHP REST API with session security & auth guard.
- **Database**: MySQL (`db_ict_system`).