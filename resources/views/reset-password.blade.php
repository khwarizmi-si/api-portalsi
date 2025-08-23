<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Reset Password</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- Tailwind CSS -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary: '#EDA130',
            'primary-dark': '#d18a25',
            'primary-light': '#f6d6a9',
          }
        }
      }
    }
  </script>

  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <style>
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
    
    * {
      font-family: 'Poppins', sans-serif;
    }
    
    body {
      background: linear-gradient(120deg, #fdf6e3 0%, #faf5f0 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    
    .card {
      transition: all 0.3s ease;
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.1);
      border-top: 6px solid #EDA130;
      overflow: hidden;
    }
    
    .card:hover {
      transform: translateY(-5px);
      box-shadow: 0 35px 60px -12px rgba(0, 0, 0, 0.15);
    }
    
    .password-container {
      position: relative;
    }
    
    .toggle-password {
      position: absolute;
      right: 12px;
      top: 50%;
      transform: translateY(-50%);
      cursor: pointer;
      color: #a0a0a0;
      transition: color 0.2s;
    }
    
    .toggle-password:hover {
      color: #EDA130;
    }
    
    .password-strength {
      height: 5px;
      border-radius: 3px;
      margin-top: 8px;
      background: #f0f0f0;
      overflow: hidden;
    }
    
    .strength-meter {
      height: 100%;
      width: 0;
      border-radius: 3px;
      transition: width 0.3s, background 0.3s;
    }
    
    .input-field {
      transition: all 0.3s;
      border: 2px solid #e5e7eb;
    }
    
    .input-field:focus {
      border-color: #EDA130;
      box-shadow: 0 0 0 3px rgba(237, 161, 48, 0.2);
    }
    
    .success-animation {
      display: none;
    }
    
    .checkmark {
      width: 80px;
      height: 80px;
      border-radius: 50%;
      display: block;
      stroke-width: 5;
      stroke: #fff;
      stroke-miterlimit: 10;
      box-shadow: 0 0 0 #EDA130;
      animation: fill .4s ease-in-out .4s forwards, scale .3s ease-in-out .9s both;
      margin: 0 auto;
    }
    
    .checkmark-circle {
      stroke-dasharray: 166;
      stroke-dashoffset: 166;
      stroke-width: 5;
      stroke-miterlimit: 10;
      stroke: #EDA130;
      fill: none;
      animation: stroke 0.6s cubic-bezier(0.65, 0, 0.45, 1) forwards;
    }
    
    .checkmark-check {
      transform-origin: 50% 50%;
      stroke-dasharray: 48;
      stroke-dashoffset: 48;
      animation: stroke 0.3s cubic-bezier(0.65, 0, 0.45, 1) 0.8s forwards;
    }
    
    @keyframes stroke {
      100% {
        stroke-dashoffset: 0;
      }
    }
    
    @keyframes scale {
      0%, 100% {
        transform: none;
      }
      50% {
        transform: scale3d(1.1, 1.1, 1);
      }
    }
    
    @keyframes fill {
      100% {
        box-shadow: inset 0 0 0 40px #EDA130;
      }
    }
    
    .floating-icon {
      animation: float 3s ease-in-out infinite;
    }
    
    @keyframes float {
      0%, 100% {
        transform: translateY(0);
      }
      50% {
        transform: translateY(-10px);
      }
    }
  </style>
</head>
<body class="p-4">
  <div class="card bg-white rounded-2xl p-8 max-w-md w-full">
    <!-- Success Animation (initially hidden) -->
    <div class="success-animation mb-6">
      <svg class="checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
        <circle class="checkmark-circle" cx="26" cy="26" r="25" fill="none"/>
        <path class="checkmark-check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8"/>
      </svg>
      <p class="text-center text-green-600 font-medium mt-4">Password berhasil direset!</p>
    </div>

    <!-- Header with icon -->
    <div class="text-center mb-6">
      <div class="floating-icon inline-flex items-center justify-center w-16 h-16 rounded-full bg-primary-light text-primary mb-4">
        <i class="fas fa-key text-xl"></i>
      </div>
      <h1 class="text-2xl font-bold text-gray-800">Reset Password</h1>
    </div>

    <p class="text-gray-600 text-center mb-6">
      Silakan masukkan password baru Anda di bawah ini.
    </p>

    <form method="POST" action="{{ url('/submit-reset-password') }}" id="resetForm">
      @csrf
      <input type="hidden" name="token" value="{{ $token }}">
      <input type="hidden" name="email" value="{{ $email }}">

      <!-- New Password Field -->
      <div class="mb-4">
        <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password Baru</label>
        <div class="password-container">
          <input type="password" 
                 name="password" 
                 id="password" 
                 placeholder="Masukkan password baru" 
                 class="w-full px-4 py-3 rounded-lg input-field focus:outline-none focus:ring-2 focus:ring-primary"
                 required
                 oninput="validatePasswords()">
          <span class="toggle-password" onclick="togglePassword('password')">
            <i class="far fa-eye"></i>
          </span>
        </div>
        <div class="password-strength mt-2">
          <div id="strength-meter" class="strength-meter"></div>
        </div>
        <p id="password-strength-text" class="text-xs mt-1"></p>
      </div>

      <!-- Confirm Password Field -->
      <div class="mb-6">
        <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">Konfirmasi Password</label>
        <div class="password-container">
          <input type="password" 
                 name="password_confirmation" 
                 id="password_confirmation" 
                 placeholder="Konfirmasi password baru" 
                 class="w-full px-4 py-3 rounded-lg input-field focus:outline-none focus:ring-2 focus:ring-primary"
                 required
                 oninput="validatePasswords()">
          <span class="toggle-password" onclick="togglePassword('password_confirmation')">
            <i class="far fa-eye"></i>
          </span>
        </div>
        <p id="errorMessage" class="text-red-500 text-sm mt-1 hidden">
          <i class="fas fa-exclamation-circle mr-1"></i> Konfirmasi password tidak cocok.
        </p>
      </div>

      <button type="submit" 
              id="submitBtn" 
              disabled
              class="w-full bg-primary hover:bg-primary-dark text-white font-semibold py-3 px-4 rounded-lg transition-all duration-300 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-opacity-50 disabled:opacity-70 disabled:cursor-not-allowed flex items-center justify-center">
        <span id="button-text">Ubah Password</span>
        <span id="button-loading" class="hidden">
          <i class="fas fa-circle-notch fa-spin mr-2"></i> Memproses...
        </span>
      </button>
    </form>

    <div class="mt-6 text-center">
      <p class="text-sm text-gray-600">Ingat password Anda? 
        <a href="#" class="text-primary font-medium hover:underline">Masuk di sini</a>
      </p>
    </div>
  </div>

  <script>
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('password_confirmation');
    const errorMessage = document.getElementById('errorMessage');
    const submitBtn = document.getElementById('submitBtn');
    const strengthMeter = document.getElementById('strength-meter');
    const strengthText = document.getElementById('password-strength-text');
    const buttonText = document.getElementById('button-text');
    const buttonLoading = document.getElementById('button-loading');
    const successAnimation = document.querySelector('.success-animation');
    const form = document.getElementById('resetForm');

    function togglePassword(fieldId) {
      const field = document.getElementById(fieldId);
      const icon = field.nextElementSibling.querySelector('i');
      
      if (field.type === 'password') {
        field.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
      } else {
        field.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
      }
    }

    function checkPasswordStrength(password) {
      let strength = 0;
      
      // Length check
      if (password.length >= 8) strength += 20;
      
      // Lowercase and uppercase check
      if (password.match(/([a-z].*[A-Z])|([A-Z].*[a-z])/)) strength += 20;
      
      // Digit check
      if (password.match(/([0-9])/)) strength += 20;
      
      // Special character check
      if (password.match(/([!,@,#,$,%,^,&,*,?,_,~])/)) strength += 20;
      
      // More than 2 special characters
      if (password.match(/(.*[!,@,#,$,%,^,&,*,?,_,~].*[!,@,#,$,%,^,&,*,?,_,~])/)) strength += 20;
      
      return strength;
    }

    function updatePasswordStrength() {
      const strength = checkPasswordStrength(password.value);
      
      strengthMeter.style.width = `${strength}%`;
      
      if (strength < 40) {
        strengthMeter.style.background = '#ef4444';
        strengthText.textContent = 'Password lemah';
        strengthText.className = 'text-xs mt-1 text-red-500';
      } else if (strength < 80) {
        strengthMeter.style.background = '#f59e0b';
        strengthText.textContent = 'Password cukup';
        strengthText.className = 'text-xs mt-1 text-yellow-500';
      } else {
        strengthMeter.style.background = '#10b981';
        strengthText.textContent = 'Password kuat';
        strengthText.className = 'text-xs mt-1 text-green-500';
      }
    }

    function validatePasswords() {
      const passVal = password.value;
      const confirmVal = confirmPassword.value;
      
      updatePasswordStrength();

      if (!passVal || !confirmVal) {
        submitBtn.disabled = true;
        errorMessage.classList.add('hidden');
        return;
      }

      if (passVal !== confirmVal) {
        submitBtn.disabled = true;
        errorMessage.classList.remove('hidden');
        confirmPassword.classList.add('border-red-500');
      } else {
        submitBtn.disabled = false;
        errorMessage.classList.add('hidden');
        confirmPassword.classList.remove('border-red-500');
      }
    }

    // Form submission handler
    form.addEventListener('submit', function(e) {
      e.preventDefault();
      
      // Show loading state
      buttonText.classList.add('hidden');
      buttonLoading.classList.remove('hidden');
      submitBtn.disabled = true;
      
      // Simulate API call
      setTimeout(() => {
        // Show success animation
        successAnimation.style.display = 'block';
        form.style.display = 'none';
        document.querySelector('p.text-gray-600').style.display = 'none';
        document.querySelector('.mt-6').style.display = 'none';
        
        // Reset button state
        buttonText.classList.remove('hidden');
        buttonLoading.classList.add('hidden');
      }, 2000);
    });

    // Initialize
    password.addEventListener('input', validatePasswords);
    confirmPassword.addEventListener('input', validatePasswords);
  </script>
</body>
</html>