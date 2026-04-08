# Run HESK with Docker

Everything runs in containers: PHP/Apache + MySQL. No need to install PHP or MySQL on your machine.

---

## Steps

### 1. Start the stack

From the project root:

```bash
cd /Users/danylokuryliak/Desktop/University/BA/ucu-hesk
docker compose up -d --build
```

First time this builds the image and starts MySQL + web; later runs are quick.

### 2. Run the install wizard

1. Open in your browser: **http://localhost:8000/install/**
2. **Step 1:** Accept the license → Continue.
3. **Step 2:** Check requirements (all green) → Continue.
4. **Step 3:** Enter database details (must match `docker-compose`):
   - **Database host:** `db`
   - **Database name:** `hesk`
   - **Database user:** `hesk`
   - **Database password:** `hesk_local`  
   Click “Test connection”, then “Create database tables” and continue.
5. **Step 4:** Finish the wizard.

### 3. Disable the installer

So the site stops showing the install wizard:

```bash
mv install install.bak
```

Or delete the `install` folder. If you use `mv`, you can restore it later with `mv install.bak install`.

### 4. Use the help desk

- **Customer portal (submit ticket form):** http://localhost:8000/
- **Admin panel:** http://localhost:8000/admin/  
  Log in with the admin username and password you set in the install wizard (Step 3).

---

## Optional

- **Stop:** `docker compose down`
- **Stop and remove database data:** `docker compose down -v`
- **View logs:** `docker compose logs -f web` or `docker compose logs -f db`

---

## If the wizard can’t connect to the database

- Wait 10–20 seconds after `docker compose up` and reload the install page (MySQL may still be starting).
- Confirm Step 3 uses **host `db`** (not `localhost`).
