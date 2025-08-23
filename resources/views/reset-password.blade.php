<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Reset Password</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  
  <!-- Tailwind CSS CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary: '#EDA130',
            'primary-light': '#F6D092',
            'primary-dark': '#D98A1A',
          },
          animation: {
            'float': 'float 6s ease-in-out infinite',
            'fade-in': 'fadeIn 0.5s ease-out forwards',
          },
          keyframes: {
            float: {
              '0%, 100%': { transform: 'translateY(0)' },
              '50%': { transform: 'translateY(-10px)' },
            },
            fadeIn: {
              '0%': { opacity: '0', transform: 'translateY(10px)' },
              '100%': { opacity: '1', transform: 'translateY(0)' },
            }
          }
        }
      }
    }
  </script>
  
  <!-- Favicon from inline SVG -->
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,
  %3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E
  %3Ccircle cx='12' cy='12' r='10' fill='%23EDA130'/%3E
  %3Cpath d='M7 13l3 3 7-7' fill='none' stroke='white' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'/%3E
  %3C/svg%3E">

  <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
    
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Inter', sans-serif;
      background: linear-gradient(135deg, #f8f4eb 0%, #fef9f0 100%);
      color: #2f2f2f;
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      padding: 24px;
    }
    
    .password-container {
      position: relative;
      width: 100%;
    }
    
    .toggle-password {
      position: absolute;
      right: 12px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      cursor: pointer;
      color: #666;
      transition: color 0.2s;
    }
    
    .toggle-password:hover {
      color: #EDA130;
    }
    
    .password-strength {
      height: 4px;
      margin-top: 4px;
      border-radius: 2px;
      transition: all 0.3s ease;
    }
    
    .strength-weak {
      width: 33.33%;
      background-color: #EF4444;
    }
    
    .strength-medium {
      width: 66.66%;
      background-color: #F59E0B;
    }
    
    .strength-strong {
      width: 100%;
      background-color: #10B981;
    }
    
    .floating-element {
      animation: float 6s ease-in-out infinite;
    }
    
    .card {
      animation: fadeIn 0.8s ease-out forwards;
      opacity: 0;
    }
    
    .success-checkmark {
      display: none;
      animation: fadeIn 0.5s ease-out;
    }
  </style>
</head>
<body class="bg-gray-50">
  <!-- Background decorative elements -->
  <div class="fixed top-0 left-0 w-full h-full -z-10 overflow-hidden">
    <div class="absolute -top-24 -left-24 w-64 h-64 rounded-full bg-primary-light opacity-20 floating-element"></div>
    <div class="absolute -bottom-16 -right-16 w-48 h-48 rounded-full bg-primary-light opacity-30 floating-element" style="animation-delay: 2s;"></div>
    <div class="absolute top-1/4 right-1/4 w-32 h-32 rounded-full bg-primary-light opacity-10 floating-element" style="animation-delay: 4s;"></div>
  </div>

  <div class="card bg-white p-8 md:p-10 rounded-2xl shadow-xl w-full max-w-md border-t-4 border-primary transform transition-all duration-300">
    <!-- Success icon (hidden by default) -->
    <div class="success-checkmark flex justify-center mb-6">
      <svg class="w-16 h-16 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
      </svg>
    </div>
    
    <h1 class="text-2xl font-bold text-center text-gray-800 mb-2">Reset Password</h1>
    <p class="text-gray-600 text-center mb-6">Silakan masukkan password baru Anda di bawah ini.</p>

    <form method="POST" action="{{ url('/submit-reset-password') }}" id="resetForm">
      @csrf
      <input type="hidden" name="token" value="{{ $token }}">
      <input type="hidden" name="email" value="{{ $email }}">

      <div class="mb-5">
        <div class="password-container">
          <input type="password" name="password" id="password" placeholder="Password baru" required
                 class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-200">
          <button type="button" class="toggle-password" id="togglePassword">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
            </svg>
          </button>
        </div>
        <div class="password-strength strength-weak mt-1" id="passwordStrength"></div>
        <p class="text-xs text-gray-500 mt-1" id="passwordHint">Gunakan 8+ karakter dengan kombinasi huruf, angka, dan simbol</p>
      </div>

      <div class="mb-6">
        <div class="password-container">
          <input type="password" name="password_confirmation" id="password_confirmation" placeholder="Konfirmasi password" required
                 class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-200">
          <button type="button" class="toggle-password" id="toggleConfirmPassword">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
            </svg>
          </button>
        </div>
      </div>

      <p id="errorMessage" class="text-red-500 text-sm mb-4 hidden">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        Konfirmasi password tidak cocok.
      </p>

      <button type="submit" id="submitBtn" disabled
              class="w-full bg-primary hover:bg-primary-dark text-white font-medium py-3 px-4 rounded-lg transition-all duration-300 transform hover:scale-[1.02] disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none flex items-center justify-center">
        <span id="buttonText">Ubah Password</span>
        <svg id="loadingSpinner" class="animate-spin -ml-1 mr-2 h-5 w-5 text-white hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
      </button>
    </form>
  </div>

  <script>
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('password_confirmation');
    const errorMessage = document.getElementById('errorMessage');
    const submitBtn = document.getElementById('submitBtn');
    const togglePassword = document.getElementById('togglePassword');
    const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
    const passwordStrength = document.getElementById('passwordStrength');
    const passwordHint = document.getElementById('passwordHint');
    const loadingSpinner = document.getElementById('loadingSpinner');
    const buttonText = document.getElementById('buttonText');
    const successCheckmark = document.querySelector('.success-checkmark');

    // Toggle password visibility
    togglePassword.addEventListener('click', function() {
      const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
      password.setAttribute('type', type);
      
      // Change the icon
      this.querySelector('svg').innerHTML = type === 'password' 
        ? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />'
        : '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />';
    });

    toggleConfirmPassword.addEventListener('click', function() {
      const type = confirmPassword.getAttribute('type') === 'password' ? 'text' : 'password';
      confirmPassword.setAttribute('type', type);
      
      // Change the icon
      this.querySelector('svg').innerHTML = type === 'password' 
        ? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />'
        : '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />';
    });

    // Password strength checker
    function checkPasswordStrength(value) {
      // Reset if empty
      if (!value) {
        passwordStrength.className = 'password-strength';
        passwordStrength.classList.remove('strength-weak', 'strength-medium', 'strength-strong');
        passwordHint.textContent = 'Gunakan 8+ karakter dengan kombinasi huruf, angka, dan simbol';
        passwordHint.className = 'text-xs text-gray-500 mt-1';
        return;
      }
      
      // Length check
      if (value.length < 8) {
        passwordStrength.className = 'password-strength strength-weak';
        passwordHint.textContent = 'Password terlalu pendek (minimal 8 karakter)';
        passwordHint.className = 'text-xs text-red-500 mt-1';
        return;
      }
      
      // Strength calculation
      let strength = 0;
      
      // Contains letters and numbers
      if (/[a-zA-Z]/.test(value) && /[0-9]/.test(value)) {
        strength += 1;
      }
      
      // Contains special characters
      if (/[^a-zA-Z0-9]/.test(value)) {
        strength += 1;
      }
      
      // Contains both uppercase and lowercase
      if (/[a-z]/.test(value) && /[A-Z]/.test(value)) {
        strength += 1;
      }
      
      // Long password bonus
      if (value.length > 10) {
        strength += 1;
      }
      
      // Visual feedback
      if (strength < 2) {
        passwordStrength.className = 'password-strength strength-weak';
        passwordHint.textContent = 'Password lemah - tambahkan angka, huruf kapital, atau simbol';
        passwordHint.className = 'text-xs text-red-500 mt-1';
      } else if (strength < 4) {
        passwordStrength.className = 'password-strength strength-medium';
        passwordHint.textContent = 'Password cukup - bisa lebih kuat dengan karakter khusus';
        passwordHint.className = 'text-xs text-yellow-600 mt-1';
      } else {
        passwordStrength.className = 'password-strength strength-strong';
        passwordHint.textContent = 'Password kuat!';
        passwordHint.className = 'text-xs text-green-600 mt-1';
      }
    }

    function validatePasswords() {
      const passVal = password.value;
      const confirmVal = confirmPassword.value;

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

    password.addEventListener('input', function() {
      validatePasswords();
      checkPasswordStrength(this.value);
    });
    
    confirmPassword.addEventListener('input', validatePasswords);

    // Form submission simulation
    document.getElementById('resetForm').addEventListener('submit', function(e) {
      e.preventDefault();
      
      // Show loading state
      loadingSpinner.classList.remove('hidden');
      buttonText.textContent = 'Memproses...';
      submitBtn.disabled = true;
      
      // Simulate API call
      setTimeout(() => {
        // Hide loading state
        loadingSpinner.classList.add('hidden');
        
        // Show success state (in a real scenario, this would be after successful API response)
        successCheckmark.style.display = 'flex';
        buttonText.textContent = 'Password Berhasil Diubah!';
        submitBtn.classList.remove('bg-primary', 'hover:bg-primary-dark');
        submitBtn.classList.add('bg-green-500');
        
        // Reset form after success
        password.value = '';
        confirmPassword.value = '';
        
        // Redirect after delay (simulation)
        setTimeout(() => {
          alert('Password berhasil diubah! Dalam aplikasi nyata, Anda akan diarahkan ke halaman login.');
        }, 1000);
      }, 1500);
    });
  </script>
</body>
</html>