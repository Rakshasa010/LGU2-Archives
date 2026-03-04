https://console.cloud.google.com/iam-admin/serviceaccounts/details/111035882376469185840;edit=true?project=archives-backup-489112

for google drive backup system

---

# Cloud SQL MySQL access from local XAMPP

Below are the exact steps and code you need to connect `my_database` on
Cloud SQL using the public IP **34.143.152.82**.  A user named `php_user`
already exists; the password must match what you set in the Cloud Console.

## 1. Grant privileges on the database

Connect to Cloud SQL (via the console query editor or `mysql` CLI) as a
privileged account and run:

```sql
CREATE DATABASE IF NOT EXISTS my_database;

GRANT ALL PRIVILEGES
    ON my_database.*
    TO 'php_user'@'%';

-- optional: change password if needed
-- ALTER USER 'php_user'@'%' IDENTIFIED BY 'your_password_here';

FLUSH PRIVILEGES;
```

This ensures `php_user` has full control over `my_database` from any host.

## 2. PHP connection script

Place the following file in your project folder (e.g. `cloudsql_connect.php`):

```php
<?php
// cloudsql_connect.php
$host     = '34.143.152.82';      // Cloud SQL public IP
$dbname   = 'my_database';
$user     = 'php_user';
$password = 'YOUR_PASSWORD_HERE';

$mysqli = new mysqli($host, $user, $password, $dbname);

if ($mysqli->connect_errno) {
    die('Connect failed: (' . $mysqli->connect_errno . ') ' .
        $mysqli->connect_error);
}

echo 'Connected to Cloud SQL as ' . htmlspecialchars($user) . "<br>";
$result = $mysqli->query('SELECT DATABASE()');
$row = $result->fetch_row();
echo 'Current database: ' . htmlspecialchars($row[0]) . "<br>";

$mysqli->close();
```

## 3. Testing in XAMPP

1. In the Cloud SQL console, add your local machine's IP under **Connections → Add network**
   (you can use `0.0.0.0/0` temporarily for testing).
2. Start Apache (no need for MySQL locally) via the XAMPP Control Panel.
3. Visit `http://localhost/my_project/cloudsql_connect.php` in a browser.
4. You should see confirmation lines showing the connection.
   Any error message will show the MySQL error code and explanation.

---

*(Further security such as using the Cloud SQL Proxy or SSL is described
below in the original instructions.)*

