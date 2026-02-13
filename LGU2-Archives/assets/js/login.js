// Placeholder for login page scripts (kept intentionally minimal)
document.addEventListener('DOMContentLoaded', function(){
  var form = document.getElementById('loginForm');
  var submitBtn = document.getElementById('loginSubmit');
  var submitting = false;
  if (form) {
    form.addEventListener('submit', function(e){
      if (submitting) {
        e.preventDefault();
        return false;
      }
      submitting = true;
      if (submitBtn) submitBtn.disabled = true;
    });
  }
});

    // Password visibility toggle feature
 
  const passwordInput = document.getElementById('password');
  const toggleButton = document.getElementById('togglePassword');
  const eyeOpen = document.getElementById('eyeOpen');
  const eyeClosed = document.getElementById('eyeClosed');

  toggleButton.addEventListener('click', () => {
    const isHidden = passwordInput.type === 'password';

    passwordInput.type = isHidden ? 'text' : 'password';
    eyeOpen.classList.toggle('hidden', isHidden);
    eyeClosed.classList.toggle('hidden', !isHidden);

    toggleButton.setAttribute(
      'aria-label',
      isHidden ? 'Hide password' : 'Show password'
    );
  });

