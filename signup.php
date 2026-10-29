<?php
require 'config.php';
if (!empty($_SESSION['user_id'])) { header('Location: dashboard.php'); exit; }
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email   = strtolower(trim($_POST['email'] ?? ''));
    $pass    = $_POST['password'] ?? '';
    $confirm = $_POST['confirm']  ?? '';
    $name    = trim($_POST['full_name'] ?? '');

    $domain = '@' . ALLOWED_DOMAIN;
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !str_ends_with($email, $domain)) {
        $error = "Please enter a valid school email (example{$domain}).";
    } elseif (strlen($pass) < 8 || !preg_match('/[A-Z]/', $pass) || !preg_match('/[0-9]/', $pass)) {
        $error = 'Password: min 8 chars, 1 uppercase, 1 number.';
    } elseif ($pass !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $stmt = $conn->prepare('SELECT id FROM users WHERE gmail = ?');
        $stmt->bind_param('s', $email); $stmt->execute(); $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $error = 'This email is already registered. Please log in.';
        } else {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $ins  = $conn->prepare('INSERT INTO users (gmail, password, full_name, auth_provider, role) VALUES (?,?,?,"local","staff")');
            $ins->bind_param('sss', $email, $hash, $name);
            $ins->execute();
            flash('Account created! You can now log in.', 'success');
            header('Location: login.php'); exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Sign Up | AssetVault SPIST</title>
  <link rel="manifest" href="manifest.json">
  <meta name="theme-color" content="#0a0d0b">
  <link rel="stylesheet" href="style.css">
</head>
<body class="auth-body login-bg">
  <div class="auth-box">
    <div class="auth-logo-wrap">
      <img src="asset/logo.png" alt="Logo" class="auth-logo" onerror="this.style.display='none'">
    </div>
    <h1 class="auth-title">ASSETVAULT</h1>
    <p class="auth-sub">Create your SPIST account</p>

    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

    <button class="btn-google" id="googleBtn" onclick="signInWithGoogle()">
      <img src="assets/google-icon.svg" class="google-icon" alt="">
      Continue with Google (@<?= ALLOWED_DOMAIN ?>)
    </button>
    <div id="g-err" class="alert alert-error" style="display:none"></div>

    <div class="or-divider"><span>or register with email</span></div>

    <form method="post" autocomplete="off">
      <label>Full Name</label>
      <input type="text" name="full_name" placeholder="Your full name" value="<?= e($_POST['full_name']??'') ?>" maxlength="120">

      <label>School Email *</label>
      <input type="email" name="email" placeholder="you@<?= ALLOWED_DOMAIN ?>"
             value="<?= e($_POST['email']??'') ?>" required>

      <label>Password *</label>
      <div class="password-wrap">
        <input type="password" name="password" id="pw" placeholder="Min 8 chars, 1 uppercase, 1 number" required>
        <span class="toggle-eye" onclick="togglePw('pw','eye1')" id="eye1">👁️</span>
      </div>
      <div class="pw-strength-bar" id="pwBar" style="display:none"><div id="pwFill" class="pw-fill"></div></div>
      <small id="pwHint" class="gray"></small>

      <label>Confirm Password *</label>
      <div class="password-wrap">
        <input type="password" name="confirm" id="pw2" placeholder="Repeat password" required>
        <span class="toggle-eye" onclick="togglePw('pw2','eye2')" id="eye2">👁️</span>
      </div>

      <button type="submit" class="btn btn-gold btn-block" style="margin-top:18px">Create Account</button>
    </form>

    <p class="auth-link">Already registered? <a href="login.php">Login</a></p>
    <div class="auth-domain-note">🏫 Only <strong>@<?= ALLOWED_DOMAIN ?></strong> accounts accepted</div>
  </div>

  <script type="module">
    import { initializeApp } from "https://www.gstatic.com/firebasejs/10.12.2/firebase-app.js";
    import { getAuth, GoogleAuthProvider, signInWithPopup, signOut } from "https://www.gstatic.com/firebasejs/10.12.2/firebase-auth.js";
    const app  = initializeApp({ apiKey:"<?= FIREBASE_API_KEY ?>", authDomain:"<?= FIREBASE_AUTH_DOMAIN ?>", projectId:"<?= FIREBASE_PROJECT_ID ?>" });
    const auth = getAuth(app);
    const prov = new GoogleAuthProvider();
    prov.setCustomParameters({ prompt:'select_account', hd:'<?= ALLOWED_DOMAIN ?>' });
    window.signInWithGoogle = async () => {
      const btn = document.getElementById('googleBtn');
      const err = document.getElementById('g-err');
      btn.disabled = true; btn.textContent = 'Connecting…'; err.style.display='none';
      try {
        const r = await signInWithPopup(auth, prov);
        if (!r.user.email.endsWith('@<?= ALLOWED_DOMAIN ?>')) {
          await signOut(auth);
          err.textContent = 'Only @<?= ALLOWED_DOMAIN ?> accounts are allowed.';
          err.style.display='block'; btn.disabled=false;
          btn.innerHTML='<img src="assets/google-icon.svg" class="google-icon"> Continue with Google (@<?= ALLOWED_DOMAIN ?>)'; return;
        }
        const token = await r.user.getIdToken();
        const res   = await fetch('google_auth_callback.php',{method:'POST',headers:{'Content-Type':'application/json'},
          body:JSON.stringify({idToken:token,uid:r.user.uid,email:r.user.email,name:r.user.displayName,photoURL:r.user.photoURL})});
        const d = await res.json();
        if (d.ok) { window.location='dashboard.php'; }
        else { err.textContent=d.error||'Failed.'; err.style.display='block'; await signOut(auth); btn.disabled=false;
          btn.innerHTML='<img src="assets/google-icon.svg" class="google-icon"> Continue with Google (@<?= ALLOWED_DOMAIN ?>)'; }
      } catch(e) { err.textContent=e.message; err.style.display='block'; btn.disabled=false;
        btn.innerHTML='<img src="assets/google-icon.svg" class="google-icon"> Continue with Google (@<?= ALLOWED_DOMAIN ?>)'; }
    };
  </script>
  <script>
    function togglePw(id,eye){ const i=document.getElementById(id),e=document.getElementById(eye); i.type=i.type==='password'?'text':'password'; e.textContent=i.type==='password'?'👁️':'🙈'; }
    document.getElementById('pw').addEventListener('input',function(){
      const v=this.value,bar=document.getElementById('pwBar'),fill=document.getElementById('pwFill'),hint=document.getElementById('pwHint');
      bar.style.display=v?'block':'none';
      let s=0; if(v.length>=8)s++;if(/[A-Z]/.test(v))s++;if(/[0-9]/.test(v))s++;if(/[^A-Za-z0-9]/.test(v))s++;
      fill.style.width=(s*25)+'%'; fill.style.background=['#b3413a','#e5a320','#d4af37','#2e7d57'][s-1]||'#333';
      hint.textContent=['','Weak','Fair','Good','Strong'][s];
    });
  </script>
</body>
</html>