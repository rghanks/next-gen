# LM-Path: Complete Step-by-Step Installation & Licensing Guide

This single guide covers everything needed to deploy the software on any client machine, generate a valid license, and keep the application running 24/7.

---

## 📋 The 3-Step Process Overview

```
[ Step 1: Client PC ]     ──►  [ Step 2: Your Laptop ]      ──►  [ Step 3: Client PC ]
Run 1-Click Installer          Generate License in Portal          Activate & Done!
Get Hardware Code              (Using Client Code)                 (Instant Unlock)
```

---

## 🚀 Step 1: Install on Client's PC (5 Minutes)

1. **Copy the Installer Files to the Client PC**
   * Take the `installer` folder (or copy `install.bat` and `install.ps1`) via a USB drive or direct download.
   * Put it on the client's desktop or drive.

2. **Run the 1-Click Installer**
   * **Right-click `install.bat`** and click **"Run as administrator"**.
   * The script will automatically:
     - Install/Configure XAMPP (Apache, PHP 8.2, MySQL).
     - Configure Apache and MySQL as **Auto-Starting Windows Services** (the app will always start on reboot).
     - Pull the encrypted production software from GitHub.
     - Migrate the database (`lmpath_db`).
     - Create an **"LM-Path Laboratory System"** icon on their Windows Desktop.
     - Automatically open the browser to the activation page: `http://localhost/lmpath/public/`.

3. **Copy the Client's Machine ID**
   * On the client's screen, you will see their unique 16-character **Hardware Code**:
     > Example: `F500-AD79-0D79-F9A4`
   * Copy or note down this code.

---

## 🔑 Step 2: Generate the License (On Your Personal Laptop)

1. **Open Your Vendor License Portal**
   * On your computer, open your web browser and go to:
     ```
     http://localhost/license-portal/
     ```

2. **Fill in the License Details**
   * **Laboratory / Clinic Name:** Enter the client's name (e.g., `City Care Diagnostic Center`).
   * **Client Machine Code:** Paste the 16-character code from Step 1 (e.g., `F500-AD79-0D79-F9A4`).
   * **License Duration:** Select validity:
     - `30 Days` (Trial / Demo)
     - `6 Months`
     - `1 Year` (Standard Annual Subscription)
     - `Custom Expiry Date` (Select exact date)

3. **Click "Cryptographically Sign & Generate License"**
   * The portal uses your private RSA master key to sign the license.
   * You will receive the signed license instantly on screen.

4. **Get the License**
   * **Option A:** Click **"Download .lic File"** to save `license.lic`, OR
   * **Option B:** Click **"Copy Code"** to copy the license text block.

---

## ✅ Step 3: Activate the Software on the Client PC

On the client's computer at `http://localhost/lmpath/public/`:

* **If you downloaded `license.lic`:**
  - Click **"Upload License File"**, choose `license.lic`, and click **Activate**.
* **If you copied the text code:**
  - Paste the code into the **"Activation Key"** box and click **Activate**.

🎉 **Done!** The system will unlock immediately, displaying the client's name, expiration date, and access to all lab modules.

---

## 🔄 What Happens When the Client PC Restarts?

* **Zero manual work needed.**
* Apache and MySQL run as **Automatic Windows Services**.
* Even after a complete power outage or Windows update reboot, the software starts silently in the background before anyone even logs in.
* Any PC in the clinic on the local Wi-Fi / LAN can open `http://<SERVER-IP>/lmpath/public/` anytime.

---

## ⚡ Useful Reference Commands

| Task | Where to Do It | How |
| :--- | :--- | :--- |
| **Check License Status** | Client PC Terminal | `php artisan license:status` |
| **Open Billing Desk** | Client PC Browser | `http://localhost/lmpath/public/lab/billing` |
| **Generate New License** | Your Laptop Browser | `http://localhost/license-portal/` |
| **Re-enable Auto-Startup** | Client PC | Double-click `enable-auto-startup.bat` |
