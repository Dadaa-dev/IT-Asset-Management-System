<?php
require 'config.php';
if (!empty($_SESSION['user_id'])) { header('Location: dashboard.php'); exit; }
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $pass  = $_POST['password'] ?? '';

    $stmt = $conn->prepare('SELECT id, gmail, password, google_uid, auth_provider, role, google_avatar FROM users WHERE gmail = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if ($user && $user['auth_provider'] === 'google') {
        $error = 'This account was registered via Google Sign-In. Please use the "Sign in with Google" button.';
    } elseif ($user && password_verify($pass, $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id']       = $user['id'];
        $_SESSION['gmail']         = $user['gmail'];
        $_SESSION['role']          = $user['role'];
        $_SESSION['google_avatar'] = $user['google_avatar'];
        $conn->query("UPDATE users SET last_login = NOW() WHERE id = " . (int)$user['id']);
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Invalid email or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login | AssetVault</title>
  <link rel="stylesheet" href="style.css">
</head>
<body class="auth-body login-bg">
  <div class="auth-box">
    <img src="asset/logo.png" alt="AssetVault Logo" class="auth-logo" onerror="this.style.display='none'">
    <h1 class="auth-title">ASSETVAULT</h1>
    <p class="auth-sub">Sign in with your SPIST account</p>

    <?php
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        echo '<div class="alert alert-' . e($f['type']) . '">' . e($f['msg']) . '</div>';
        unset($_SESSION['flash']);
    }
    if ($error): ?>
      <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <!-- Google Sign-In -->
    <div id="google-btn-wrap">
      <button class="btn-google" id="googleSignInBtn" onclick="signInWithGoogle()">
        <img src="assets/google-icon.svg" alt="" class="google-icon">
        Sign in with Google (@spist.edu.ph)
      </button>
      <div id="google-error" class="alert alert-error" style="display:none"></div>
    </div>

    <div class="or-divider"><span>or sign in with email</span></div>

    <!-- Local login -->
    <form method="post" autocomplete="off">
      <label>School Email</label>
      <input type="email" name="email" placeholder="yourname@spist.edu.ph"
             value="<?= e($_POST['email'] ?? '') ?>" required>

      <label>Password</label>
      <div class="password-wrap">
        <input type="password" name="password" id="password" placeholder="Your password" required>
        <span class="toggle-eye" onclick="togglePassword()" id="eyeIcon">👁️</span>
      </div>

      <button type="submit" class="btn btn-gold btn-block">Login</button>
    </form>

    <p class="auth-link">No account yet? <a href="signup.php">Sign up</a></p>

    <div class="auth-domain-note">
      🏫 Only <strong>@spist.edu.ph</strong> accounts are accepted
    </div>
  </div>

  <script type="module">
    import { initializeApp }       from "https://www.gstatic.com/firebasejs/10.12.2/firebase-app.js";
    import { getAuth, GoogleAuthProvider, signInWithPopup, signOut }
                                   from "https://www.gstatic.com/firebasejs/10.12.2/firebase-auth.js";

    const firebaseConfig = {
      apiKey:     "<?= FIREBASE_API_KEY ?>",
      authDomain: "<?= FIREBASE_AUTH_DOMAIN ?>",
      projectId:  "<?= FIREBASE_PROJECT_ID ?>"
    };

    const app      = initializeApp(firebaseConfig);
    const auth     = getAuth(app);
    const provider = new GoogleAuthProvider();
    provider.setCustomParameters({ prompt: 'select_account', hd: 'spist.edu.ph' });

    window.signInWithGoogle = async function () {
      const btn    = document.getElementById('googleSignInBtn');
      const errDiv = document.getElementById('google-error');
      btn.disabled = true;
      btn.textContent = 'Signing in…';
      errDiv.style.display = 'none';

      try {
        const result  = await signInWithPopup(auth, provider);
        const user    = result.user;

        // Enforce @spist.edu.ph on client
        if (!user.email.endsWith('@spist.edu.ph')) {
          await signOut(auth);
          errDiv.textContent = 'Only @spist.edu.ph school accounts are allowed.';
          errDiv.style.display = 'block';
          btn.disabled = false;
          btn.innerHTML = '<img src="assets/google-icon.svg" alt="" class="google-icon"> Sign in with Google (@spist.edu.ph)';
          return;
        }

        const idToken = await user.getIdToken();
        const resp    = await fetch('google_auth_callback.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ idToken, uid: user.uid, email: user.email, name: user.displayName, photoURL: user.photoURL })
        });

        const data = await resp.json();
        if (data.ok) {
          window.location = 'dashboard.php';
        } else {
          errDiv.textContent = data.error || 'Authentication failed.';
          errDiv.style.display = 'block';
          await signOut(auth);
          btn.disabled = false;
          btn.innerHTML = '<img src="assets/google-icon.svg" alt="" class="google-icon"> Sign in with Google (@spist.edu.ph)';
        }
      } catch (err) {
        errDiv.textContent = err.message;
        errDiv.style.display = 'block';
        btn.disabled = false;
        btn.innerHTML = '<img src="assets/google-icon.svg" alt="" class="google-icon"> Sign in with Google (@spist.edu.ph)';
      }
    };
  </script>

  <script>
    function togglePassword() {
      const input = document.getElementById('password');
      const eye   = document.getElementById('eyeIcon');
      input.type = input.type === 'password' ? 'text' : 'password';
      eye.textContent = input.type === 'password' ? '👁️' : '🙈';
    }
  </script>
</body>
</html>