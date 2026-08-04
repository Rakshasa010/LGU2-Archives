<?php
/**
 * Google OAuth 2.0 (Sign in with Google) callback handler.
 *
 * Receives the Google ID token (credential) sent by the frontend (Google
 * Identity Services SDK), verifies it with the official google/apiclient SDK,
 * then either logs in an existing user or auto-registers a new one.
 *
 * Expects a POST request with form field: credential=<id_token>
 * Responds with JSON: { success: bool, redirect?: string, error?: string }
 */

session_start();

require_once __DIR__ . '/../vendor/autoload.php';

header('Content-Type: application/json; charset=utf-8');

function google_json_fail($msg)
{
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

// Load environment (authdatabase.php defines load_env() and exposes $env)
include __DIR__ . '/authdatabase.php';

$clientId     = trim((string)($env['GOOGLE_CLIENT_ID'] ?? ''));
$clientSecret = trim((string)($env['GOOGLE_CLIENT_SECRET'] ?? ''));
$newUserStatus = strtolower(trim((string)($env['GOOGLE_NEW_USER_STATUS'] ?? 'active')));

if ($clientId === '') {
    google_json_fail('Google Sign-In is not configured. Please contact the administrator.');
}

$credential = trim((string)($_POST['credential'] ?? ''));
if ($credential === '') {
    google_json_fail('Missing Google credential token.');
}

try {
    $client = new Google_Client();
    $client->setClientId($clientId);
    $client->setClientSecret($clientSecret);
    $client->setAccessType('online');

    $ticket = $client->verifyIdToken($credential);
    if (!$ticket) {
        google_json_fail('Google ID token verification failed.');
    }

    $attributes = $ticket->getAttributes();
    $claims     = $attributes['payload'] ?? [];

    $googleId  = trim((string)($claims['sub'] ?? ''));
    $email     = strtolower(trim((string)($claims['email'] ?? '')));
    $name      = trim((string)($claims['name'] ?? ''));
    $picture   = trim((string)($claims['picture'] ?? ''));

    if ($googleId === '' || $email === '') {
        google_json_fail('Google did not return the required profile information.');
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        google_json_fail('Google returned an invalid email address.');
    }
} catch (Throwable $e) {
    google_json_fail('Unable to verify Google credential.');
}

// ---------------------------------------------------------------------------
// Find existing user (by email or linked Google ID)
// ---------------------------------------------------------------------------
$user = null;
$stmt = $conn->prepare("SELECT id, username, email, full_name, role, status, dark_mode,
                               must_change_password, google_id, profile_picture
                        FROM users
                        WHERE email = ? OR google_id = ?
                        LIMIT 1");
$stmt->bind_param('ss', $email, $googleId);
$stmt->execute();
$result = $stmt->get_result();
if ($result && $result->num_rows > 0) {
    $user = $result->fetch_assoc();
}
$stmt->close();

if ($user) {
    // Existing account: block non-active accounts
    $status = strtolower((string)($user['status'] ?? 'active'));
    if ($status !== 'active') {
        if ($status === 'pending') {
            google_json_fail('Your account is pending approval by an administrator.');
        } elseif ($status === 'rejected') {
            google_json_fail('Your account was rejected. Please contact support.');
        }
        google_json_fail('Your account is not active.');
    }

    $uid = (int)$user['id'];

    // Link Google identity if not already linked
    $currentGoogle = (string)($user['google_id'] ?? '');
    if ($currentGoogle === '' || strpos($currentGoogle, 'legacy_') === 0) {
        $upd = $conn->prepare("UPDATE users SET google_id = ?, google_picture = ? WHERE id = ?");
        $upd->bind_param('ssi', $googleId, $picture, $uid);
        $upd->execute();
        $upd->close();
    } elseif (empty($user['google_picture']) && $picture !== '') {
        $upd = $conn->prepare("UPDATE users SET google_picture = ? WHERE id = ?");
        $upd->bind_param('si', $picture, $uid);
        $upd->execute();
        $upd->close();
    }

    // Mirror Google avatar into profile_picture only if the user has none yet
    if ((empty($user['profile_picture']) || $user['profile_picture'] === '') && $picture !== '') {
        $upd = $conn->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
        $upd->bind_param('si', $picture, $uid);
        $upd->execute();
        $upd->close();
    }

    $conn->query("UPDATE users SET last_activity = NOW(), login_count = login_count + 1 WHERE id = " . $uid);

    $_SESSION['user_id']     = $uid;
    $_SESSION['last_activity'] = time();
    $_SESSION['dark_mode']   = (int)($user['dark_mode'] ?? 0);

    echo json_encode(['success' => true, 'redirect' => 'archives-landing.php']);
    exit;
}

// ---------------------------------------------------------------------------
// New user: auto-register
// ---------------------------------------------------------------------------
$usernameBase = 'google_' . $googleId;
$username     = substr($usernameBase, 0, 50);
$tempPassword = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
$fullName     = $name !== '' ? $name : (explode('@', $email)[0] ?? 'Google User');
$status       = ($newUserStatus === 'pending') ? 'pending' : 'active';
$role         = 'user';

// Collision-safe insert (email is UNIQUE; Google sub is unique per account)
$inserted = false;
for ($attempt = 0; $attempt < 3 && !$inserted; $attempt++) {
    $tryUsername = $username;
    if ($attempt > 0) {
        $tryUsername = substr($username, 0, 44) . '_' . substr(md5(uniqid('', true)), 0, 5);
    }
    $ins = $conn->prepare("INSERT INTO users
        (username, password, email, full_name, role, status, google_id, google_picture, must_change_password)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)");
    $ins->bind_param('ssssssss', $tryUsername, $tempPassword, $email, $fullName, $role, $status, $googleId, $picture);
    if ($ins->execute()) {
        $newUid = (int)$ins->insert_id;
        $inserted = true;

        // Notify admins of the new (pending) Google sign-up
        if ($status === 'pending') {
            $conn->query("CREATE TABLE IF NOT EXISTS notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                time VARCHAR(20) NOT NULL,
                date DATE NOT NULL,
                content VARCHAR(255) NOT NULL,
                about VARCHAR(100) NOT NULL,
                status ENUM('unread','read') NOT NULL DEFAULT 'unread',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
            $nt = $conn->prepare("INSERT INTO notifications (time, date, content, about, status) VALUES (?, ?, ?, ?, ?)");
            if ($nt) {
                $ntime    = date('h:i A');
                $ndate    = date('Y-m-d');
                $ncontent = 'New Google user pending approval: ' . $fullName . ' (' . $email . ')';
                $nabout   = 'User Management';
                $nstatus  = 'unread';
                $nt->bind_param('sssss', $ntime, $ndate, $ncontent, $nabout, $nstatus);
                $nt->execute();
                $nt->close();
            }
        }

        $conn->query("UPDATE users SET last_activity = NOW(), login_count = login_count + 1 WHERE id = " . $newUid);

        $_SESSION['user_id']       = $newUid;
        $_SESSION['last_activity'] = time();
        $_SESSION['dark_mode']     = 0;

        echo json_encode(['success' => true, 'redirect' => 'archives-landing.php']);
        exit;
    }
    $ins->close();
}

if (!$inserted) {
    // If the email already exists in another account, fall back to logging that user in
    $stmt = $conn->prepare("SELECT id, status, dark_mode, google_id FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
        $existing = $res->fetch_assoc();
        if (strtolower((string)$existing['status']) === 'active') {
            $uid = (int)$existing['id'];
            $upd = $conn->prepare("UPDATE users SET google_id = ?, google_picture = ? WHERE id = ?");
            $upd->bind_param('ssi', $googleId, $picture, $uid);
            $upd->execute();
            $upd->close();
            $conn->query("UPDATE users SET last_activity = NOW(), login_count = login_count + 1 WHERE id = " . $uid);
            $_SESSION['user_id'] = $uid;
            $_SESSION['last_activity'] = time();
            $_SESSION['dark_mode'] = (int)($existing['dark_mode'] ?? 0);
            echo json_encode(['success' => true, 'redirect' => 'archives-landing.php']);
            exit;
        }
    }
    google_json_fail('Failed to register Google account.');
}
