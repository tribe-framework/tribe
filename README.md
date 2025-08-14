# Tribe Complete Setup Guide

Frontend (EmberJS) Setup & Backend (Junction + Tribe API)

---

## FRONTEND SETUP

### Step 1: Install from Template

Use tribe-framework.org to install this Git template for your frontend application.

**Visit Tribe Framework:**
Go to the official Tribe Framework website to get the template: [https://tribe-framework.org](https://tribe-framework.org)

### Step 2: Open Command Line

Open command line in the directory that has been installed from the template:

```bash
# Navigate to your frontend directory
cd /path/to/frontend/directory
```

### Step 3: Install EmberJS

Install EmberJS by running the following command based on your operating system. You'll be asked to enter the name of the directory when prompted:

**Linux and macOS:**

```bash
chmod +x ./config/ember/ember-init.sh; bash ./config/ember/ember-init.sh;
```

**Windows:**

```powershell
powershell -ExecutionPolicy Bypass -File .\config\ember\ember-init.ps1
```

> **Note:** When prompted, enter the name of your EmberJS application directory.

### Step 4: Start Development Server

Serve the EmberJS application to start coding:

```bash
ember s
```

**EmberJS Development Server:**
Your EmberJS frontend will be available at: **http://localhost:4200**

> **Tip:** The development server includes hot-reload functionality. Changes to your code will automatically refresh in the browser.

### Step 5: Build for Production

When you're ready to deploy, build your application for production:

```bash
ember build -prod
```

**Build Output:**
The production-ready build will be generated in the `dist/` directory.

### Step 6: Deploy to Production

Deploy your built application to your preferred hosting platform:

**Deployment Options:**

- **Netlify** - Easy deployment with continuous integration: [https://www.netlify.com](https://www.netlify.com)
- **Cloudflare Pages** - Fast, secure deployment with global CDN: [https://pages.cloudflare.com](https://pages.cloudflare.com)

> **Deployment tip:** Both platforms support drag-and-drop deployment of your `dist/` folder or Git-based continuous deployment.

---

## BACKEND SETUP

### Step 1: Check Docker Installation

Check if Docker is installed on your system:

**Command Line:**

```bash
docker --version
```

**Platform-specific commands:**

- **Windows:** `winget list docker`
- **macOS:** `brew list | grep docker`
- **Linux:** `which docker`

> **Docker Not Installed?**
> Download and install Docker from the official website: [https://www.docker.com/get-started/](https://www.docker.com/get-started/)

### Step 2: Check Docker Compose

Verify Docker Compose installation:

**Command Line:**

```bash
docker-compose --version
```

**Alternative (newer Docker versions):**

```bash
docker compose version
```

**Linux (which command):**

```bash
which docker-compose
```

> **Docker Compose Not Installed?**
> Docker Compose installation instructions: [https://docs.docker.com/compose/install/](https://docs.docker.com/compose/install/)

> **Note:** Docker Desktop for Windows and macOS includes Docker Compose by default. If you have Docker Desktop, you should already have Docker Compose installed.

### Step 3: Navigate to Directory

Open your terminal/command prompt and navigate to the directory containing your docker-compose.yml:

```bash
# Replace /path/to/your/project with your actual project path
cd /path/to/your/project
```

**Platform-specific examples:**

- **Windows:** `cd C:\Users\YourName\Documents\MyProject`
- **macOS/Linux:** `cd ~/Documents/MyProject`

> **📁 Important:** Make sure you're in the directory that contains your `docker-compose.yml` file before running the next commands.

### Step 4: Start Backend (Junction + Tribe API)

Once you're in the correct directory, start your Docker application:

```bash
docker-compose up -d --build
```

**What this command does:**

- `up` - Starts the containers
- `-d` - Runs in detached mode (in background)
- `--build` - Rebuilds images before starting

**Alternative command for newer Docker versions:**

```bash
docker compose up -d --build
```

### Step 5: Access Junction

Once your backend is running, you can access Junction through your web browser:

**Access Junction:**
Open your web browser and navigate to: **http://localhost:4488**

**Junction Credentials:**

- **Username/Slug:** junction
- **Password:** password

> **Note:** Make sure your Docker containers are running before attempting to access Junction. If you can't access the page, wait a few moments for the containers to fully start up.

### Step 6: Stop Backend Services

When you want to stop and remove the containers, use:

```bash
docker-compose down
```

**Alternative command for newer Docker versions:**

```bash
docker compose down
```

> **⚠️ Important:** Always use `docker-compose down` to properly stop and clean up your containers. This ensures all resources are properly released.

**Additional useful commands:**

- `docker-compose logs` - View application logs
- `docker-compose ps` - List running containers
- `docker-compose restart` - Restart services

---

## 🎉 Quick Reference

### Backend (Junction + Tribe API)

- **Start:** `docker-compose up -d --build`
- **Access Junction:** http://localhost:4488
- **Stop:** `docker-compose down`

### Frontend (EmberJS)

- **Serve:** `ember s`
- **Access Frontend:** http://localhost:4200
- **Build:** `ember build -prod`

---

## About Tribe Framework

Tribe is a web project management framework that allows us to build platforms, products, applications and web interfaces using a modular approach. It provides a coherent language for UX, content, design and code collaborations.

## License

The GNU General Public License is a free, copyleft license for software and other kinds of works.
